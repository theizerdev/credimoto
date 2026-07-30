<?php

namespace App\Livewire\Admin\Reportes;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\PlanPago;
use App\Models\Pago;
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
    }

    public function search()
    {
        $filters = [
            'cliente_id' => $this->cliente_id,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'estado' => $this->estado,
            'tipo_contrato' => $this->tipo_contrato
        ];

        app(AuditService::class)->logUserAction('report.account_status.query', $filters, 'Consulta de Estado de Cuenta');

        $cliente = Cliente::find($this->cliente_id);
        if (!$cliente) {
            $this->result = ['contratos' => [], 'pagos' => [], 'cuotas_pagadas' => [], 'cuotas_pendientes' => [], 'resumen' => ['total_pagado' => 0, 'pendiente' => 0, 'proximo_vencimiento' => null, 'estado' => 'pendiente'], 'trend' => ['labels' => [], 'values' => []]];
            return;
        }

        $contratos = Contrato::with(['unidad.moto'])
            ->where('cliente_id', $cliente->id)
            ->when($this->tipo_contrato, fn($q) => $q->where('tipo_contrato', $this->tipo_contrato))
            ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
            ->orderBy('created_at', 'desc')
            ->get();

        $contratoIds = $contratos->pluck('id');
        $planPagos = PlanPago::with('contrato')
            ->whereIn('contrato_id', $contratoIds)
            ->when($this->dateFrom, fn($q) => $q->where('fecha_vencimiento', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha_vencimiento', '<=', $this->dateTo))
            ->orderBy('fecha_vencimiento')
            ->get();

        $pagos = Pago::where('cliente_id', $cliente->id)
            ->when($this->dateFrom, fn($q) => $q->where('fecha', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha', '<=', $this->dateTo))
            ->orderBy('fecha', 'desc')
            ->get();

        $cuotasPagadas = $planPagos->where('estado', 'pagado');
        $detallesPorPlan = \App\Models\PagoDetalle::with('conceptoPago')
            ->whereIn('plan_pago_id', $cuotasPagadas->pluck('id'))
            ->get()
            ->groupBy('plan_pago_id');
        $cuotasPendientes = $planPagos->filter(fn($p) => in_array($p->estado, ['pendiente', 'parcial', 'vencido']));

        $totalPagado = $pagos->where('estado', 'aprobado')->sum('total');
        $pendiente = $planPagos->sum('saldo_pendiente');
        $proximo = $cuotasPendientes->where('estado', 'pendiente')->sortBy('fecha_vencimiento')->first();
        $hayVencidos = $cuotasPendientes->where('estado', 'vencido')->count() > 0;
        $estadoCuenta = $hayVencidos ? 'moroso' : ($pendiente > 0 ? 'pendiente' : 'al_dia');

        $labels = [];
        $values = [];
        foreach ($pagos->groupBy(fn($p) => optional($p->fecha)->format('Y-m')) as $mes => $list) {
            $labels[] = $mes;
            $values[] = $list->sum('total');
        }

        $this->result = [
            'contratos' => $contratos->toArray(),
            'pagos' => $pagos->toArray(),
            'cuotas_pagadas' => $cuotasPagadas->map(function($c) use ($detallesPorPlan) {
                $detalle = optional($detallesPorPlan->get($c->id))->first();
                return [
                    'contrato_id' => $c->contrato_id,
                    'numero' => $c->numero_cuota,
                    'descripcion' => $detalle?->descripcion ?? ($detalle?->conceptoPago?->nombre ?? ("Cuota #{$c->numero_cuota}")),
                    'fecha_pago' => optional($c->fecha_pago_real)->format('d/m/Y'),
                    'monto' => $c->monto_total
                ];
            })->values()->toArray(),
            'cuotas_pendientes' => $cuotasPendientes->map(function($c) {
                return [
                    'contrato_id' => $c->contrato_id,
                    'numero' => $c->numero_cuota,
                    'vencimiento' => optional($c->fecha_vencimiento)->format('d/m/Y'),
                    'saldo' => $c->saldo_pendiente,
                    'estado' => $c->estado
                ];
            })->values()->toArray(),
            'resumen' => [
                'total_pagado' => $totalPagado,
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
        $headers = ['Tipo', 'Detalle', 'Monto', 'Fecha'];
        $rows = collect();
        foreach ($this->result['pagos'] as $p) {
            $rows->push([
                'Pago',
                $p['numero_completo'] ?? '',
                $p['total'] ?? 0,
                !empty($p['fecha']) ? Carbon::parse($p['fecha'])->format('d/m/Y') : ''
            ]);
        }
        foreach ($this->result['cuotas_pagadas'] as $c) {
            $desc = $c['descripcion'] ?? ($c['numero'] === 0 ? 'Cuota Inicial' : ('Cuota #' . $c['numero']));
            $rows->push([
                'Cuota Pagada',
                'Contrato #' . $c['contrato_id'] . ' - ' . $desc,
                $c['monto'] ?? 0,
                $c['fecha_pago'] ?? ''
            ]);
        }
        foreach ($this->result['cuotas_pendientes'] as $c) {
            $descPend = ($c['numero'] === 0 ? 'Cuota Inicial' : ('Cuota #' . $c['numero']));
            $rows->push([
                'Cuota Pendiente',
                'Contrato #' . $c['contrato_id'] . ' - ' . $descPend,
                $c['saldo'] ?? 0,
                $c['vencimiento'] ?? ''
            ]);
        }
        $cliente = \App\Models\Cliente::find($this->cliente_id);
        $filename = 'estado_cuenta_' . ($cliente?->documento ?? 'cliente') . '_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new class($rows, $headers) implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize {
            private $rows;
            private $headers;
            public function __construct($rows, $headers) { $this->rows = $rows; $this->headers = $headers; }
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->headers; }
            public function styles(Worksheet $sheet)
            {
                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
                $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle("A1:D{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                return [];
            }
        }, $filename);
    }
    
    public function exportPdf()
    {
        $cliente = Cliente::find($this->cliente_id);
        $html = view('livewire.admin.reportes.estado-cuenta-pdf', [
            'cliente' => $cliente,
            'result' => $this->result,
            'generatedAt' => now()->format('d/m/Y H:i')
        ])->render();
        
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $filename = 'estado_cuenta_' . ($cliente?->documento ?? 'cliente') . '_' . now()->format('Ymd_His') . '.pdf';
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