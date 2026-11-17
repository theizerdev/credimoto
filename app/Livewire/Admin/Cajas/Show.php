<?php

namespace App\Livewire\Admin\Cajas;

use App\Models\Caja;
use App\Models\ExchangeRate;
use App\Models\Empresa;
use App\Traits\HasDynamicLayout;
use App\Traits\HasRegionalFormatting;
use App\Traits\HasDualCurrency;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Show extends Component
{
    use HasDynamicLayout, HasRegionalFormatting, HasDualCurrency;


    public Caja $caja;
    public $observaciones_cierre = '';
    public $showCerrarModal = false;
    public $showRecalcularModal = false;

    protected $rules = [
        'observaciones_cierre' => 'nullable|string|max:500',
    ];

    public function mount(Caja $caja)
    {
        $this->caja = $caja->load(['usuario', 'sucursal', 'pagos.detalles.conceptoPago', 'pagos.cliente']);
    }

    public function abrirModalCerrar()
    {
        if ($this->caja->estado === 'cerrada') {
            session()->flash('error', 'La caja ya está cerrada.');
            return;
        }

        $this->caja->calcularTotales();
        $this->showCerrarModal = true;
    }

    public function cerrarCaja()
    {
        $this->validate();

        if ($this->caja->cerrar($this->observaciones_cierre)) {
            $this->showCerrarModal = false;
            session()->flash('message', 'Caja cerrada exitosamente.');
            $this->caja->refresh();
            
            // Enviar notificación WhatsApp con el reporte Excel
            $this->enviarNotificacionCierreCaja();
        }
 else {
            session()->flash('error', 'No se pudo cerrar la caja.');
        }
    }

    public function recalcularMontos()
    {
        if ($this->caja->estado !== 'cerrada') {
            session()->flash('error', 'Solo se pueden recalcular montos de cajas cerradas.');
            return;
        }

        $this->showRecalcularModal = true;
    }

    public function confirmarRecalcular()
    {
        if ($this->caja->estado !== 'cerrada') {
            session()->flash('error', 'Solo se pueden recalcular montos de cajas cerradas.');
            $this->showRecalcularModal = false;
            return;
        }

        try {
            // Recalcular los totales
            $this->caja->calcularTotales();
            
            session()->flash('message', 'Montos recalculados exitosamente. El monto de cierre se ha actualizado.');
            $this->caja->refresh();
            $this->showRecalcularModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error al recalcular los montos: ' . $e->getMessage());
            $this->showRecalcularModal = false;
        }
    }

    public function getResumenPorMetodoProperty()
    {
        return $this->caja->pagos()
            ->where('estado', 'aprobado')
            ->selectRaw('metodo_pago, COUNT(*) as cantidad, SUM(total) as total')
            ->groupBy('metodo_pago')
            ->get();
    }

    public function getResumenPorConceptoProperty()
    {
        return $this->caja->pagos()
            ->where('estado', 'aprobado')
            ->with(['detalles.conceptoPago'])
            ->get()
            ->flatMap(function ($pago) {
                return $pago->detalles->map(function ($detalle) {
                    return [
                        'concepto' => $detalle->conceptoPago->nombre ?? 'Sin concepto',
                        'cantidad' => $detalle->cantidad,
                        'precio' => $detalle->precio_unitario,
                        'subtotal' => $detalle->subtotal,
                    ];
                });
            })
            ->groupBy('concepto')
            ->map(function ($items, $concepto) {
                return [
                    'concepto' => $concepto,
                    'cantidad' => $items->sum('cantidad'),
                    'total' => $items->sum('subtotal'),
                ];
            });
    }

    public function exportarExcel()
    {
        return redirect()->route('admin.cajas.export', $this->caja->id);
    }

    public function render()
    {
        return view('livewire.admin.cajas.show')->layout($this->getLayout());
    }

    /**
     * Enviar notificación WhatsApp con el reporte de cierre de caja
     */
    private function enviarNotificacionCierreCaja()
    {
        try {
            // Obtener la empresa para obtener el teléfono registrado
            $empresa = Empresa::find($this->caja->empresa_id);
            
            if (!$empresa || !$empresa->telefono) {
                \Log::warning('No se puede enviar notificación WhatsApp: empresa sin teléfono registrado', [
                    'caja_id' => $this->caja->id,
                    'empresa_id' => $this->caja->empresa_id
                ]);
                return;
            }

            // Generar el archivo Excel temporalmente
            $excelPath = $this->generarExcelTemporal();
            
            if (!$excelPath) {
                \Log::error('Error al generar el archivo Excel para la notificación', [
                    'caja_id' => $this->caja->id
                ]);
                return;
            }

            // Enviar mensaje con archivo adjunto (el mensaje se incluye en el caption)
            $resultado = $this->enviarWhatsAppConArchivo($empresa->telefono, '', $excelPath);

            if ($resultado) {
                session()->flash('success', 'Notificación WhatsApp enviada exitosamente');
            } else {
                session()->flash('warning', 'Caja cerrada, pero no se pudo enviar la notificación WhatsApp');
            }

            // Limpiar archivo temporal
            if (file_exists($excelPath)) {
                Storage::delete($excelPath);
            }

        } catch (\Exception $e) {
            \Log::error('Error al enviar notificación WhatsApp de cierre de caja: ' . $e->getMessage(), [
                'caja_id' => $this->caja->id,
                'empresa_id' => $this->caja->empresa_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            session()->flash('warning', 'Caja cerrada, pero ocurrió un error al enviar la notificación WhatsApp');
        }
    }

    /**
     * Generar archivo Excel temporal del reporte de caja
     */
    private function generarExcelTemporal()
    {
        try {
            $this->caja->load(['usuario', 'sucursal', 'pagos.detalles.conceptoPago', 'pagos.matricula.student']);
            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Configurar encabezado
            $sheet->setCellValue('A1', 'REPORTE DETALLADO DE CAJA');
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Información de la caja
            $row = 3;
            $sheet->setCellValue('A' . $row, 'Fecha:');
            $sheet->setCellValue('B' . $row, $this->caja->fecha->format('d/m/Y'));
            $sheet->setCellValue('D' . $row, 'Usuario:');
            $sheet->setCellValue('E' . $row, $this->caja->usuario->name);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Sucursal:');
            $sheet->setCellValue('B' . $row, $this->caja->sucursal->nombre);
            $sheet->setCellValue('D' . $row, 'Estado:');
            $sheet->setCellValue('E' . $row, ucfirst($this->caja->estado));
            
            // Tasa de cambio
            $tasaCambio = ExchangeRate::whereDate('created_at', $this->caja->fecha)->first();
            if ($tasaCambio) {
                $row++;
                $sheet->setCellValue('A' . $row, 'Tasa de Cambio:');
                $sheet->setCellValue('B' . $row, number_format($tasaCambio->usd_rate, 4) . ' Bs/$');
            }
            
            // Resumen financiero
            $row += 2;
            $sheet->setCellValue('A' . $row, 'RESUMEN FINANCIERO');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $datos = [
                ['Concepto', 'Monto USD', 'Monto Bs'],
                ['Monto Inicial', number_format($this->caja->monto_inicial, 2), $tasaCambio ? number_format($this->caja->monto_inicial * $tasaCambio->usd_rate, 2) : '-'],
                ['Total Efectivo', number_format($this->caja->total_efectivo, 2), $tasaCambio ? number_format($this->caja->total_efectivo * $tasaCambio->usd_rate, 2) : '-'],
                ['Total Transferencias', number_format($this->caja->total_transferencias, 2), $tasaCambio ? number_format($this->caja->total_transferencias * $tasaCambio->usd_rate, 2) : '-'],
                ['Total Ingresos', number_format($this->caja->total_ingresos, 2), $tasaCambio ? number_format($this->caja->total_ingresos * $tasaCambio->usd_rate, 2) : '-'],
                ['Monto Final', number_format($this->caja->monto_final, 2), $tasaCambio ? number_format($this->caja->monto_final * $tasaCambio->usd_rate, 2) : '-']
            ];
            
            foreach ($datos as $fila) {
                $sheet->setCellValue('A' . $row, $fila[0]);
                $sheet->setCellValue('B' . $row, $fila[1]);
                $sheet->setCellValue('C' . $row, $fila[2]);
                $row++;
            }
            
            // Detalle de pagos
            $row += 2;
            $sheet->setCellValue('A' . $row, 'DETALLE DE PAGOS');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $encabezados = ['Documento', 'Estudiante', 'Método', 'Monto USD', 'Monto Bs', 'Referencia', 'Hora'];
            foreach ($encabezados as $col => $encabezado) {
                $sheet->setCellValue([$col + 1, $row], $encabezado);
            }
            $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
            
            $row++;
            foreach ($this->caja->pagos()->where('estado', 'aprobado')->get() as $pago) {
                if ($pago->es_pago_mixto && $pago->detalles_pago_mixto) {
                    foreach ($pago->detalles_pago_mixto as $detalle) {
                        $montoBolivares = in_array($detalle['metodo'], ['transferencia', 'pago_movil', 'efectivo_bolivares']) && $pago->tasa_cambio 
                            ? number_format($detalle['monto'] * $pago->tasa_cambio, 2) 
                            : '-';
                        
                        $sheet->setCellValue('A' . $row, $pago->numero_completo);
                        $sheet->setCellValue('B' . $row, $pago->matricula->student->nombres . ' ' . $pago->matricula->student->apellidos);
                        $sheet->setCellValue('C' . $row, ucfirst(str_replace('_', ' ', $detalle['metodo'])));
                        $sheet->setCellValue('D' . $row, number_format($detalle['monto'], 2));
                        $sheet->setCellValue('E' . $row, $montoBolivares);
                        $sheet->setCellValue('F' . $row, $detalle['referencia'] ?: '-');
                        $sheet->setCellValue('G' . $row, $pago->created_at->format('H:i'));
                        $row++;
                    }
                } else {
                    $montoBolivares = $pago->total_bolivares ? number_format($pago->total_bolivares, 2) : '-';
                    
                    $sheet->setCellValue('A' . $row, $pago->numero_completo);
                    $sheet->setCellValue('B' . $row, $pago->matricula->student->nombres . ' ' . $pago->matricula->student->apellidos);
                    $sheet->setCellValue('C' . $row, $pago->metodo_pago);
                    $sheet->setCellValue('D' . $row, number_format($pago->total, 2));
                    $sheet->setCellValue('E' . $row, $montoBolivares);
                    $sheet->setCellValue('F' . $row, $pago->referencia ?: '-');
                    $sheet->setCellValue('G' . $row, $pago->created_at->format('H:i'));
                    $row++;
                }
            }
            
            // Aplicar estilos
            $sheet->getStyle('A1:G' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(20);
            $sheet->getColumnDimension('D')->setWidth(12);
            $sheet->getColumnDimension('E')->setWidth(12);
            $sheet->getColumnDimension('F')->setWidth(15);
            $sheet->getColumnDimension('G')->setWidth(10);
            
            // Guardar en archivo temporal
            $filename = 'caja_' . $this->caja->fecha->format('Y-m-d') . '_' . $this->caja->numero_corte . '_' . time() . '.xlsx';
            $tempPath = storage_path('app/temp/' . $filename);
            
            // Crear directorio si no existe
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);
            
            return $tempPath;
            
        } catch (\Exception $e) {
            \Log::error('Error al generar Excel temporal: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Enviar mensaje WhatsApp con archivo adjunto
     */
    private function enviarWhatsAppConArchivo($telefono, $mensaje, $rutaArchivo)
    {
        try {
            // Obtener token JWT
            $jwtToken = config('whatsapp.api_key', 'test-api-key-vargas-centro');
            
            // Formatear número de teléfono
            $telefonoFormateado = $this->formatPhoneNumber($telefono);
            
            // Verificar que el archivo existe
            if (!file_exists($rutaArchivo)) {
                \Log::error('Archivo no encontrado para enviar por WhatsApp: ' . $rutaArchivo);
                return false;
            }
            
            // Preparar mensaje completo con el caption
            $caption = "✅ *CIERRE DE CAJA EXITOSO* ✅\n\n";
            $caption .= "📊 *Resumen del cierre:*\n";
            $caption .= "• Fecha: " . $this->caja->fecha->format('d/m/Y') . "\n";
            $caption .= "• Sucursal: " . $this->caja->sucursal->nombre . "\n";
            $caption .= "• Usuario: " . $this->caja->usuario->name . "\n";
            $caption .= "• Monto Inicial: $" . number_format($this->caja->monto_inicial, 2) . "\n";
            $caption .= "• Total Ingresos: $" . number_format($this->caja->total_ingresos, 2) . "\n";
            $caption .= "• Monto Final: $" . number_format($this->caja->monto_final, 2) . "\n\n";
            $caption .= "📎 Reporte detallado adjunto.";
            
            // Enviar solo el documento con el mensaje como caption
            $nombreArchivo = basename($rutaArchivo);
            $responseDoc = Http::withHeaders([
                'X-API-Key' => $jwtToken
            ])->attach(
                'document', file_get_contents($rutaArchivo), $nombreArchivo
            )->timeout(60)->post(config('whatsapp.api_url', 'http://localhost:3001') . '/api/whatsapp/send-document', [
                'to' => $telefonoFormateado,
                'caption' => $caption
            ]);

            if ($responseDoc->successful()) {
                \Log::info('Documento Excel enviado exitosamente por WhatsApp', [
                    'caja_id' => $this->caja->id,
                    'phone' => $telefono,
                    'filename' => $nombreArchivo
                ]);
                return true;
            } else {
                \Log::error('Error al enviar documento WhatsApp: ' . $responseDoc->body(), [
                    'caja_id' => $this->caja->id,
                    'phone' => $telefono,
                    'status' => $responseDoc->status(),
                    'response' => $responseDoc->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            \Log::error('Error al enviar WhatsApp con archivo: ' . $e->getMessage(), [
                'caja_id' => $this->caja->id,
                'phone' => $telefono,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Formatear número de teléfono para WhatsApp
     */
    private function formatPhoneNumber($phone)
    {
        // Obtener la empresa y su país
        $empresa = auth()->user()->empresa;
        if (!$empresa) {
            $empresa = \DB::table('empresas')->first();
        }
        
        // Obtener código de país
        $codigoPais = '58'; // Default Venezuela
        if ($empresa && $empresa->pais_id) {
            $pais = \DB::table('pais')->where('id', $empresa->pais_id)->first();
            if ($pais && $pais->codigo_telefonico) {
                $codigoPais = ltrim($pais->codigo_telefonico, '+');
            }
        }
        
        // Limpiar número
        $cleaned = preg_replace('/\D/', '', $phone);
        
        // Quitar el 0 inicial si existe
        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }
        
        // Agregar código de país si no lo tiene
        if (strlen($cleaned) === 10 && !str_starts_with($cleaned, $codigoPais)) {
            $cleaned = $codigoPais . $cleaned;
        }
        
        // Agregar sufijo de WhatsApp
        return $cleaned . '@s.whatsapp.net';
    }
}