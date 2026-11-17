<?php

namespace App\Livewire\Admin\Reportes;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\PlanPago;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\Audit\AuditService;
use App\Services\Export\ExportImportService;
use App\Services\WhatsAppService;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;
use Dompdf\Dompdf;

class EstadoCuenta extends Component
{
    use HasDynamicLayout;

    public $cliente_id = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $estado = ''; // al_dia, moroso, cancelado
    public $tipo_contrato = ''; // financiado, contado, etc (placeholder)
    public $selected_contrato_id = null;
    public $result = [
        'contratos' => [],
        'pagos' => [],
        'cuotas_pagadas' => [],
        'cuotas_pendientes' => [],
        'resumen' => [
            'total_pagado' => 0,
            'pendiente' => 0,
            'proximo_vencimiento' => null,
            'estado' => 'pendiente'
        ],
        'trend' => [
            'labels' => [],
            'values' => []
        ]
    ];

    public function mount()
    {
        $user = Auth::user();
        if (!$user->can('access reports') && !$user->can('access pagos') && !$user->can('access contratos')) {
            abort(403);
        }

        $clienteId = request()->query('cliente_id');
        if ($clienteId && Cliente::where('id', $clienteId)->exists()) {
            $this->cliente_id = (int) $clienteId;
            $this->search();
        }
    }

    public function updatedClienteId()
    {
        $this->selected_contrato_id = null;
        $this->search();
    }

    public function selectContrato($contratoId)
    {
        if ($this->selected_contrato_id == $contratoId) {
            $this->selected_contrato_id = null;
        } else {
            $this->selected_contrato_id = (int) $contratoId;
        }
        $this->search();
    }

    public function search()
    {
        $filters = [
            'cliente_id' => $this->cliente_id,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'estado' => $this->estado,
            'tipo_contrato' => $this->tipo_contrato,
            'selected_contrato_id' => $this->selected_contrato_id
        ];

        app(AuditService::class)->logUserAction('report.account_status.query', $filters, 'Consulta de Estado de Cuenta');

        $cliente = Cliente::find($this->cliente_id);
        if (!$cliente) {
            $this->selected_contrato_id = null;
            $this->result = ['contratos' => [], 'pagos' => [], 'cuotas_pagadas' => [], 'cuotas_pendientes' => [], 'resumen' => ['total_pagado' => 0, 'pendiente' => 0, 'proximo_vencimiento' => null, 'estado' => 'pendiente'], 'trend' => ['labels' => [], 'values' => []]];
            return;
        }

        $contratos = Contrato::with(['unidad.moto'])
            ->where('cliente_id', $cliente->id)
            ->when($this->tipo_contrato, fn($q) => $q->where('tipo_contrato', $this->tipo_contrato))
            ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($this->selected_contrato_id && !$contratos->contains('id', $this->selected_contrato_id)) {
            $this->selected_contrato_id = null;
        }

        $activeContratoIds = $this->selected_contrato_id
            ? collect([$this->selected_contrato_id])
            : $contratos->pluck('id');

        $planPagos = PlanPago::with('contrato')
            ->whereIn('contrato_id', $activeContratoIds)
            ->when($this->dateFrom, fn($q) => $q->where('fecha_vencimiento', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha_vencimiento', '<=', $this->dateTo))
            ->orderBy('fecha_vencimiento')
            ->get();

        $pagosQuery = Pago::where('cliente_id', $cliente->id)
            ->when($this->dateFrom, fn($q) => $q->where('fecha', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha', '<=', $this->dateTo));

        if ($this->selected_contrato_id) {
            $pagosQuery->whereHas('detalles.planPago', function($q) {
                $q->where('contrato_id', $this->selected_contrato_id);
            });
        }

        $pagos = $pagosQuery->orderBy('fecha', 'desc')->get();

        $cuotasPagadas = $planPagos->where('estado', 'pagado');
        $detallesPorPlan = PagoDetalle::with(['conceptoPago', 'pago'])
            ->whereIn('plan_pago_id', $cuotasPagadas->pluck('id'))
            ->get()
            ->groupBy('plan_pago_id');
        $cuotasPendientes = $planPagos->filter(fn($p) => in_array($p->estado, ['pendiente', 'parcial', 'vencido']));

        $fallbackGeneralRate = (float)(ExchangeRate::orderBy('date', 'desc')->value('usd_rate') ?? 1);

        $cuotasPagadasMapped = $cuotasPagadas->map(function($c) use ($detallesPorPlan, $fallbackGeneralRate) {
            $detalles = $detallesPorPlan->get($c->id) ?? collect();
            $detalle = $detalles->first();
            $pago = $detalle?->pago;

            // Determine fecha de pago
            $fechaPago = $c->fecha_pago_real 
                ? Carbon::parse($c->fecha_pago_real) 
                : ($pago?->fecha ? Carbon::parse($pago->fecha) : ($c->fecha_vencimiento ? Carbon::parse($c->fecha_vencimiento) : null));

            $montoUsd = (float) $c->monto_total;
            $montoBs = 0;
            $tasa = 0;

            if ($detalles->isNotEmpty()) {
                foreach ($detalles as $dt) {
                    $dtPago = $dt->pago;
                    $dtTasa = (float)($dtPago?->tasa_cambio ?: 0);
                    if ($dtTasa <= 0 && $dtPago?->fecha) {
                        $dtTasa = (float)(ExchangeRate::whereDate('date', $dtPago->fecha)->value('usd_rate')
                            ?: ExchangeRate::whereDate('date', '<=', $dtPago->fecha)->orderBy('date', 'desc')->value('usd_rate')
                            ?: 0);
                    }
                    if ($dtTasa <= 0 && $c->fecha_pago_real) {
                        $dtTasa = (float)(ExchangeRate::whereDate('date', $c->fecha_pago_real)->value('usd_rate')
                            ?: ExchangeRate::whereDate('date', '<=', $c->fecha_pago_real)->orderBy('date', 'desc')->value('usd_rate')
                            ?: 0);
                    }
                    if ($dtTasa <= 0) {
                        $dtTasa = $fallbackGeneralRate;
                    }
                    $montoBs += round(((float)$dt->subtotal) * $dtTasa, 2);
                    $tasa = $dtTasa;
                }
            } else {
                $tasaDate = $fechaPago ?: now();
                $tasa = (float)(ExchangeRate::whereDate('date', $tasaDate)->value('usd_rate')
                    ?: ExchangeRate::whereDate('date', '<=', $tasaDate)->orderBy('date', 'desc')->value('usd_rate')
                    ?: $fallbackGeneralRate);
                $montoBs = round($montoUsd * $tasa, 2);
            }

            return [
                'id' => $c->id,
                'contrato_id' => $c->contrato_id,
                'numero_contrato' => $c->contrato?->numero_contrato ?? $c->contrato_id,
                'numero' => $c->numero_cuota,
                'descripcion' => $detalle?->descripcion ?? ($detalle?->conceptoPago?->nombre ?? ($c->numero_cuota === 0 ? 'Cuota Inicial' : "Cuota #{$c->numero_cuota}")),
                'fecha_pago' => $fechaPago ? $fechaPago->format('d/m/Y') : '',
                'tasa_cambio' => $tasa,
                'monto' => $montoUsd,
                'monto_bs' => $montoBs,
            ];
        })->values();

        $totalCuotasPagadasUsd = (float) $cuotasPagadasMapped->sum('monto');
        $totalCuotasPagadasBs = (float) $cuotasPagadasMapped->sum('monto_bs');

        if ($this->selected_contrato_id) {
            $totalPagado = (float) PagoDetalle::whereHas('pago', fn($q) => $q->where('estado', 'aprobado'))
                ->whereHas('planPago', fn($q) => $q->where('contrato_id', $this->selected_contrato_id))
                ->sum('subtotal');
            $totalPagadoBs = $totalCuotasPagadasBs;
        } else {
            $totalPagado = (float) $pagos->where('estado', 'aprobado')->sum('total');
            $totalPagadoBs = (float) $pagos->where('estado', 'aprobado')->sum('total_bolivares');
            if ($totalPagadoBs <= 0) {
                $totalPagadoBs = $totalCuotasPagadasBs;
            }
        }

        $pendiente = $planPagos->sum('saldo_pendiente');
        $proximo = $cuotasPendientes->where('estado', 'pendiente')->sortBy('fecha_vencimiento')->first();
        $hayVencidos = $cuotasPendientes->where('estado', 'vencido')->count() > 0;
        $estadoCuenta = $hayVencidos ? 'moroso' : ($pendiente > 0 ? 'pendiente' : 'al_dia');

        $labels = [];
        $values = [];
        if ($this->selected_contrato_id) {
            foreach ($pagos->groupBy(fn($p) => optional($p->fecha)->format('Y-m')) as $mes => $list) {
                $labels[] = $mes;
                $pagoIds = $list->pluck('id');
                $montoMes = PagoDetalle::whereIn('pago_id', $pagoIds)
                    ->whereHas('pago', fn($q) => $q->where('estado', 'aprobado'))
                    ->whereHas('planPago', fn($q) => $q->where('contrato_id', $this->selected_contrato_id))
                    ->sum('subtotal');
                $values[] = (float) $montoMes;
            }
        } else {
            foreach ($pagos->groupBy(fn($p) => optional($p->fecha)->format('Y-m')) as $mes => $list) {
                $labels[] = $mes;
                $values[] = (float) $list->where('estado', 'aprobado')->sum('total');
            }
        }

        $this->result = [
            'contratos' => $contratos->toArray(),
            'pagos' => $pagos->toArray(),
            'cuotas_pagadas' => $cuotasPagadasMapped->toArray(),
            'cuotas_pendientes' => $cuotasPendientes->map(function($c) {
                return [
                    'contrato_id' => $c->contrato_id,
                    'numero_contrato' => $c->contrato?->numero_contrato ?? $c->contrato_id,
                    'numero' => $c->numero_cuota,
                    'vencimiento' => optional($c->fecha_vencimiento)->format('d/m/Y'),
                    'saldo' => $c->saldo_pendiente,
                    'estado' => $c->estado
                ];
            })->values()->toArray(),
            'resumen' => [
                'total_pagado' => $totalPagado,
                'total_pagado_bs' => $totalPagadoBs,
                'total_cuotas_pagadas_usd' => $totalCuotasPagadasUsd,
                'total_cuotas_pagadas_bs' => $totalCuotasPagadasBs,
                'pendiente' => $pendiente,
                'proximo_vencimiento' => $proximo ? $proximo->fecha_vencimiento->format('d/m/Y') : null,
                'estado' => $estadoCuenta
            ],
            'trend' => [
                'labels' => $labels,
                'values' => $values
            ]
        ];
        
        $this->dispatch('trendUpdated', labels: $labels, values: $values);
    }

    public function exportExcel()
    {
        $contrato = $this->selected_contrato_id ? Contrato::find($this->selected_contrato_id) : null;
        $headers = ['Tipo', 'Contrato', 'Detalle', 'Fecha', 'Tasa (Bs.)', 'Monto ($)', 'Monto (Bs.)'];
        $rows = collect();
        foreach ($this->result['pagos'] as $p) {
            $detallePago = $p['numero_completo'] ?? '';
            if ($contrato) {
                $detallePago .= ' (Contrato #' . ($contrato->numero_contrato ?? $contrato->id) . ')';
            }
            $rows->push([
                'Pago',
                $contrato ? ($contrato->numero_contrato ?? $contrato->id) : '',
                $detallePago,
                !empty($p['fecha']) ? Carbon::parse($p['fecha'])->format('d/m/Y') : '',
                !empty($p['tasa_cambio']) ? (float)$p['tasa_cambio'] : '',
                (float)($p['total'] ?? 0),
                (float)($p['total_bolivares'] ?? 0)
            ]);
        }
        $totCuotasUsd = 0;
        $totCuotasBs = 0;
        foreach ($this->result['cuotas_pagadas'] as $c) {
            $desc = $c['descripcion'] ?? ($c['numero'] === 0 ? 'Cuota Inicial' : ('Cuota #' . $c['numero']));
            $numContrato = $c['numero_contrato'] ?? $c['contrato_id'];
            $mUsd = (float)($c['monto'] ?? 0);
            $mBs = (float)($c['monto_bs'] ?? 0);
            $totCuotasUsd += $mUsd;
            $totCuotasBs += $mBs;
            $rows->push([
                'Cuota Pagada',
                '#' . $numContrato,
                $desc,
                $c['fecha_pago'] ?? '',
                !empty($c['tasa_cambio']) ? (float)$c['tasa_cambio'] : '',
                $mUsd,
                $mBs
            ]);
        }
        foreach ($this->result['cuotas_pendientes'] as $c) {
            $descPend = ($c['numero'] === 0 ? 'Cuota Inicial' : ('Cuota #' . $c['numero']));
            $numContrato = $c['numero_contrato'] ?? $c['contrato_id'];
            $rows->push([
                'Cuota Pendiente',
                '#' . $numContrato,
                $descPend,
                $c['vencimiento'] ?? '',
                '',
                (float)($c['saldo'] ?? 0),
                ''
            ]);
        }

        // Fila de sumatorias totales
        $rows->push([
            'TOTAL CUOTAS PAGADAS',
            '',
            '',
            '',
            '',
            $totCuotasUsd,
            $totCuotasBs
        ]);

        $cliente = Cliente::find($this->cliente_id);
        $suffix = $contrato ? '_contrato_' . ($contrato->numero_contrato ?? $contrato->id) : '';
        $filename = 'estado_cuenta_' . ($cliente?->documento ?? 'cliente') . $suffix . '_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new class($rows, $headers) implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize {
            private $rows;
            private $headers;
            public function __construct($rows, $headers) { $this->rows = $rows; $this->headers = $headers; }
            public function collection(): \Illuminate\Support\Enumerable { return $this->rows; }
            public function headings(): array { return $this->headers; }
            public function styles(Worksheet $sheet): ?array
            {
                $sheet->getStyle('A1:G1')->getFont()->setBold(true);
                $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle("A1:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$lastRow}:G{$lastRow}")->getFont()->setBold(true);
                return [];
            }
        }, $filename);
    }
    
    public function exportPdf()
    {
        $cliente = Cliente::find($this->cliente_id);
        $contratoSeleccionado = $this->selected_contrato_id ? Contrato::with('unidad.moto')->find($this->selected_contrato_id) : null;
        $html = view('livewire.admin.reportes.estado-cuenta-pdf', [
            'cliente' => $cliente,
            'contratoSeleccionado' => $contratoSeleccionado,
            'result' => $this->result,
            'generatedAt' => now()->format('d/m/Y H:i')
        ])->render();
        
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $suffix = $contratoSeleccionado ? '_contrato_' . ($contratoSeleccionado->numero_contrato ?? $contratoSeleccionado->id) : '';
        $filename = 'estado_cuenta_' . ($cliente?->documento ?? 'cliente') . $suffix . '_' . now()->format('Ymd_His') . '.pdf';
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf'
        ]);
    }

    public function sendReminders()
    {
        $cliente = Cliente::find($this->cliente_id);
        $pendientes = collect($this->result['cuotas_pendientes']);

        if ($pendientes->isEmpty()) {
            session()->flash('message', 'No hay cuotas pendientes para enviar recordatorios.');
            return;
        }

        // Encontrar la última cuota vencida
        $ultimaCuotaVencida = $pendientes->whereIn('estado', 'pendiente')
       
        ->first();
    //dd($ultimaCuotaVencida);
        if (!$ultimaCuotaVencida) {
            session()->flash('message', 'No hay cuotas vencidas para enviar recordatorio.');
            return;
        }

        try {
            if (!empty($cliente->telefono)) {
                $whatsappService = app(WhatsAppService::class);
                $formattedPhone = $this->formatPhoneNumber($cliente->telefono);

                $message = "Hola {$cliente->nombre} {$cliente->apellido},\n\n"
                         . "Le recordamos que tiene una cuota vencida.\n\n"
                         . "Cuota #: {$ultimaCuotaVencida['numero']}\n"
                         . "Fecha de Vencimiento: {$ultimaCuotaVencida['vencimiento']}\n"
                         . "Saldo Pendiente: $" . number_format($ultimaCuotaVencida['saldo'], 2) . "\n\n"
                         . "Por favor, realice su pago a la brevedad posible.";

                $whatsappResult = $whatsappService->sendMessage(
                    $formattedPhone,
                    $message,
                    true
                );

                if ($whatsappResult && isset($whatsappResult['success']) && $whatsappResult['success']) {
                    \Log::info('WhatsApp reminder sent successfully', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $formattedPhone,
                    ]);
                    session()->flash('message', 'Recordatorio enviado correctamente por WhatsApp.');
                } else {
                    \Log::error('Failed to send WhatsApp reminder', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $formattedPhone,
                        'response' => $whatsappResult
                    ]);
                    session()->flash('error', 'No se pudo enviar el recordatorio por WhatsApp.');
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error sending WhatsApp reminder: ' . $e->getMessage());
            session()->flash('error', 'Ocurrió un error al enviar el recordatorio.');
        }

        app(AuditService::class)->logUserAction('report.account_status.reminders', ['count' => 1], 'Se envió recordatorio de cuota vencida');
    }
    private function formatPhoneNumber($number)
    {
        $empresa = \DB::table('empresas')->where('id', 1)->first();
        $pais = $empresa ? \DB::table('pais')->where('id', $empresa->pais_id)->first() : null;
        $codigoPais =  '58';

        $cleaned = preg_replace('/[^0-9]/', '', $number);

        if (strlen($cleaned) > 10 && str_starts_with($cleaned, $codigoPais)) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        return $codigoPais . $cleaned;
    }


    public function render()
    {
        // Filtrar solo clientes con contratos activos
        $clientes = Cliente::whereHas('contratos', function($query) {
            $query->whereIn('estado', ['activo', 'mora']);
        })->orderBy('nombre')->get();
        
        return view('livewire.admin.reportes.estado-cuenta', [
            'clientes' => $clientes
        ])->layout($this->getLayout(), [
            'title' => 'Reporte: Estado de Cuenta'
        ]);
    }
}