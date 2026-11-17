<?php

namespace App\Livewire\Admin\Nomina;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use App\Models\Empleado;
use App\Models\CalendarioNomina;
use App\Models\Nomina;
use App\Models\NominaItem;
use App\Services\Audit\AuditService;
use App\Services\WhatsAppService;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Procesar extends Component
{
    use HasDynamicLayout;

    public $dateFrom;
    public $dateTo;
    public $frecuencia = 'mensual';
    public $horas_extra = 0;
    public $extra_rate = 1.5;
    public $extras = [];
    public $bonos = [];
    public $comisiones = [];
    public $selectedEmpleadoIds = [];
    public $empleados = [];
    public $nominaId = null;
    public $result = [];
    public $receipts = [];

    public function mount()
    {
        if (!auth()->user()->can('access nomina')) {
            abort(403);
        }
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->empleados = Empleado::where('activo', true)->orderBy('nombre')->limit(100)->get();
        foreach ($this->empleados as $emp) {
            $this->extras[$emp->id] = (float)$emp->horas_extra_base;
            $this->bonos[$emp->id] = (float)$emp->bono_fijo;
            $this->comisiones[$emp->id] = (float)$emp->comision_fija;
        }
    }

    private function calcularISR(float $base): float
    {
        if ($base <= 500) return 0;
        if ($base <= 1000) return ($base - 500) * 0.10;
        return (500 * 0.10) + (($base - 1000) * 0.20);
    }

    public function precalcular()
    {
        $empleados = Empleado::whereIn('id', $this->selectedEmpleadoIds)->get();
        $items = [];
        $totalPercepciones = 0;
        $totalDeducciones = 0;
        foreach ($empleados as $e) {
            $monto = $e->salario_base;
            $items[] = [
                'empleado_id' => $e->id,
                'empleado' => $e->nombre . ' ' . ($e->apellido ?? ''),
                'concepto_nombre' => 'Sueldo Base',
                'tipo' => 'percepcion',
                'cantidad' => 1,
                'monto_unitario' => $monto,
                'subtotal' => $monto
            ];
            $totalPercepciones += $monto;

            // Horas extra (MVP: global horas para todos)
            $horasEmp = (float)($this->extras[$e->id] ?? $this->horas_extra);
            if ($horasEmp > 0) {
                $tarifaHora = ($e->salario_base / 30) / 8;
                $extraSubtotal = $tarifaHora * $horasEmp * $this->extra_rate;
                $items[] = [
                    'empleado_id' => $e->id,
                    'empleado' => $e->nombre . ' ' . ($e->apellido ?? ''),
                    'concepto_nombre' => 'Horas Extra',
                    'tipo' => 'percepcion',
                    'cantidad' => $horasEmp,
                    'monto_unitario' => round($tarifaHora * $this->extra_rate, 2),
                    'subtotal' => round($extraSubtotal, 2)
                ];
                $totalPercepciones += $extraSubtotal;
            }

            // Bonos
            $bonoEmp = (float)($this->bonos[$e->id] ?? 0);
            if ($bonoEmp > 0) {
                $items[] = [
                    'empleado_id' => $e->id,
                    'empleado' => $e->nombre . ' ' . ($e->apellido ?? ''),
                    'concepto_nombre' => 'Bono',
                    'tipo' => 'percepcion',
                    'cantidad' => 1,
                    'monto_unitario' => round($bonoEmp, 2),
                    'subtotal' => round($bonoEmp, 2)
                ];
                $totalPercepciones += $bonoEmp;
            }

            // Comisiones
            $comEmp = (float)($this->comisiones[$e->id] ?? 0);
            if ($comEmp > 0) {
                $items[] = [
                    'empleado_id' => $e->id,
                    'empleado' => $e->nombre . ' ' . ($e->apellido ?? ''),
                    'concepto_nombre' => 'Comisión',
                    'tipo' => 'percepcion',
                    'cantidad' => 1,
                    'monto_unitario' => round($comEmp, 2),
                    'subtotal' => round($comEmp, 2)
                ];
                $totalPercepciones += $comEmp;
            }

            // ISR (tabla simplificada)
            $isr = $this->calcularISR($e->salario_base);
            if ($isr > 0) {
                $items[] = [
                    'empleado_id' => $e->id,
                    'empleado' => $e->nombre . ' ' . ($e->apellido ?? ''),
                    'concepto_nombre' => 'ISR',
                    'tipo' => 'deduccion',
                    'cantidad' => 1,
                    'monto_unitario' => round($isr, 2),
                    'subtotal' => round($isr, 2)
                ];
                $totalDeducciones += $isr;
            }
        }
        $this->result = [
            'items' => $items,
            'total' => round($totalPercepciones - $totalDeducciones, 2)
        ];
        app(AuditService::class)->logUserAction('nomina.precalcular', [
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'count' => count($items)
        ], 'Precalculo de nómina');
    }

    public function aprobar()
    {
        if (empty($this->result['items'])) {
            $this->precalcular();
        }
        $cal = CalendarioNomina::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nombre' => 'Periodo ' . now()->format('Y-m'),
            'frecuencia' => $this->frecuencia,
            'periodo_inicio' => $this->dateFrom,
            'periodo_fin' => $this->dateTo,
            'estado' => 'aprobada'
        ]);
        $nomina = Nomina::create([
            'empresa_id' => auth()->user()->empresa_id,
            'sucursal_id' => auth()->user()->sucursal_id,
            'calendario_id' => $cal->id,
            'periodo_inicio' => $this->dateFrom,
            'periodo_fin' => $this->dateTo,
            'estado' => 'aprobada',
            'total' => $this->result['total'] ?? 0
        ]);
        foreach ($this->result['items'] as $it) {
            NominaItem::create([
                'nomina_id' => $nomina->id,
                'empleado_id' => $it['empleado_id'],
                'concepto_nombre' => $it['concepto_nombre'],
                'tipo' => $it['tipo'],
                'cantidad' => $it['cantidad'],
                'monto_unitario' => $it['monto_unitario'],
                'subtotal' => $it['subtotal']
            ]);
        }
        $this->nominaId = $nomina->id;
        app(AuditService::class)->logUserAction('nomina.aprobar', [
            'nomina_id' => $nomina->id,
            'total' => $nomina->total
        ], 'Nómina aprobada');
        session()->flash('message', 'Nómina aprobada y guardada.');
    }

    public function exportExcel()
    {
        $rows = collect();
        foreach ($this->result['items'] ?? [] as $it) {
            $rows->push([
                $it['empleado'],
                $it['concepto_nombre'],
                $it['tipo'],
                $it['cantidad'],
                $it['monto_unitario'],
                $it['subtotal']
            ]);
        }
        return Excel::download(new class($rows) implements FromCollection, WithHeadings, ShouldAutoSize {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function collection(): \Illuminate\Support\Enumerable { return $this->rows; }
            public function headings(): array { return ['Empleado', 'Concepto', 'Tipo', 'Cantidad', 'Unitario', 'Subtotal']; }
        }, 'nomina_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function enviarWhatsApp()
    {
        $service = WhatsAppService::forCompany(auth()->user()->empresa_id);
        foreach ($this->result['items'] ?? [] as $it) {
            $emp = Empleado::find($it['empleado_id']);
            if (!$emp || empty($emp->telefono)) continue;
            $msg = "Comprobante de Nómina\n\n";
            $msg .= "Empleado: {$emp->nombre} {$emp->apellido}\n";
            $msg .= "Periodo: " . \Carbon\Carbon::parse($this->dateFrom)->format('d/m/Y') . " - " . \Carbon\Carbon::parse($this->dateTo)->format('d/m/Y') . "\n";
            $msg .= "{$it['concepto_nombre']}: $" . number_format($it['subtotal'], 2) . "\n";
            $service->send($emp->telefono, $msg);
        }
        app(AuditService::class)->logUserAction('nomina.whatsapp.send', [
            'count' => count($this->result['items'] ?? [])
        ], 'WhatsApp nómina enviada');
        session()->flash('message', 'Mensajes de WhatsApp enviados.');
    }
    
    public function generarRecibosPdf()
    {
        if (empty($this->result['items'])) {
            $this->precalcular();
        }
        $grouped = [];
        foreach ($this->result['items'] as $it) {
            $grouped[$it['empleado_id']][] = $it;
        }
        $this->receipts = [];
        foreach ($grouped as $empId => $items) {
            $emp = Empleado::find($empId);
            if (!$emp) continue;
            $payload = "EMP:{$emp->id}|PERIODO:" . \Carbon\Carbon::parse($this->dateFrom)->format('Ymd') . "-" . \Carbon\Carbon::parse($this->dateTo)->format('Ymd');
            $percepciones = 0; $deducciones = 0;
            foreach ($items as $it) {
                if ($it['tipo'] === 'percepcion') $percepciones += $it['subtotal'];
                else $deducciones += $it['subtotal'];
            }
            $neto = $percepciones - $deducciones;
            $signature = hash('sha256', $payload . "|NETO:{$neto}|" . config('app.key'));
            $qrBase64 = null;
            try {
                if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                    $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(120)->generate($payload . '|SIG:' . $signature);
                    $qrBase64 = base64_encode($qrPng);
                } else {
                    $qrUrl = 'https://chart.googleapis.com/chart?chs=120x120&cht=qr&chl=' . urlencode($payload . '|SIG:' . $signature);
                    $qrBase64 = @base64_encode(@file_get_contents($qrUrl));
                }
            } catch (\Exception $e) {
                $qrBase64 = null;
            }
            $html = view('livewire.admin.nomina.recibo', [
                'empleado' => $emp,
                'items' => $items,
                'from' => $this->dateFrom,
                'to' => $this->dateTo,
                'signature' => $signature,
                'qrBase64' => $qrBase64
            ])->render();
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();
            $pdf = $dompdf->output();
            $path = 'temp/nomina/recibo_' . $emp->id . '_' . now()->format('Ymd_His') . '.pdf';
            Storage::put($path, $pdf);
            $this->receipts[$empId] = storage_path('app/' . $path);
        }
        app(AuditService::class)->logUserAction('nomina.receipts.generate', [
            'count' => count($this->receipts)
        ], 'Recibos PDF generados');
        session()->flash('message', 'Recibos PDF generados.');
    }
    
    public function enviarWhatsAppRecibos()
    {
        if (empty($this->receipts)) {
            $this->generarRecibosPdf();
        }
        $service = WhatsAppService::forCompany(auth()->user()->empresa_id);
        $sent = 0;
        foreach ($this->receipts as $empId => $filePath) {
            $emp = Empleado::find($empId);
            if (!$emp || empty($emp->telefono)) continue;
            $caption = "Recibo de Nómina\n{$emp->nombre} {$emp->apellido}\nPeriodo: " .
                \Carbon\Carbon::parse($this->dateFrom)->format('d/m/Y') . " - " .
                \Carbon\Carbon::parse($this->dateTo)->format('d/m/Y');
            $resp = $service->sendDocument($service->formatPhone($emp->telefono), $filePath, $caption);
            if ($resp) $sent++;
            // Limpieza opcional
            try { @unlink($filePath); } catch (\Exception $e) {}
        }
        app(AuditService::class)->logUserAction('nomina.whatsapp.send_docs', [
            'count' => $sent
        ], 'Recibos PDF enviados por WhatsApp');
        session()->flash('message', "Se enviaron {$sent} recibos por WhatsApp.");
    }

    public function render()
    {
        return view('livewire.admin.nomina.procesar', [
            'empleados' => $this->empleados
        ])->layout($this->getLayout(), [
            'title' => 'Procesar Nómina'
        ]);
    }
}
