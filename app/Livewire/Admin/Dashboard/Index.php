<?php

namespace App\Livewire\Admin\Dashboard;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\PlanPago;
use App\Models\WhatsAppScheduledMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\WhatsAppService;
use App\Services\Audit\AuditService;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Index extends Component
{
    use HasDynamicLayout, WithPagination;

    public $dateFrom = '';
    public $dateTo = '';
    public $anticipationDays = 7;
    public $searchClient = '';
    public $minAmount = '';
    public $maxAmount = '';
    public $cuotasFilter = 'all';
    public $contratosFilter = 'all';
    public $selectedClients = [];

    public $metrics = [];
    public $monthlyTrend = ['labels' => [], 'values' => [], 'counts' => []];
    public $charts = [];
    public $initialPayload = [];

    protected $queryString = [
        'anticipationDays' => ['except' => 7],
        'searchClient' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'minAmount' => ['except' => ''],
        'maxAmount' => ['except' => ''],
        'cuotasFilter' => ['except' => 'all'],
        'contratosFilter' => ['except' => 'all'],
    ];

    public function mount()
    {
        if (!Auth::check()) {
            abort(403);
        }
        $this->loadData();
    }

    public function updatedSearchClient()
    {
        $this->resetPage();
    }

    public function refresh()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $filters = $this->extractFilters();
        $payload = $this->buildDashboardPayload($filters, true);

        $this->metrics = $payload['metrics'];
        $this->charts = $payload['charts'];
        $this->monthlyTrend = $payload['charts']['trend'];
        $this->initialPayload = $payload;
    }

    /**
     * Endpoint AJAX principal del Dashboard.
     */
    public function ajaxData(Request $request)
    {
        if (!Auth::check()) {
            abort(403);
        }

        $filters = $this->extractFilters($request);
        $payload = $this->buildDashboardPayload($filters, true);

        return response()->json($payload);
    }

    /**
     * Devuelve el resumen de estado de cuenta y cuotas pendientes de un cliente para el modal AJAX.
     */
    public function ajaxClientePendientes(Request $request, $clienteId)
    {
        if (!Auth::check()) {
            abort(403);
        }

        $cliente = Cliente::find($clienteId);
        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado.'
            ], 404);
        }

        $today = now()->startOfDay();
        $todayStr = $today->toDateString();

        $contratos = Contrato::with(['unidad.moto'])
            ->where('cliente_id', $cliente->id)
            ->where(function ($q) {
                $q->whereIn('estado', ['activo', 'mora'])
                  ->orWhere('saldo_pendiente', '>', 0);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $cuotas = PlanPago::with('contrato')
            ->whereIn('contrato_id', $contratos->pluck('id'))
            ->where('saldo_pendiente', '>', 0)
            ->where('estado', '!=', 'pagado')
            ->orderBy('fecha_vencimiento', 'asc')
            ->orderBy('numero_cuota', 'asc')
            ->get();

        $totalPagado = (float) Pago::where('cliente_id', $cliente->id)
            ->where('estado', 'aprobado')
            ->sum('total');

        $saldoPendiente = (float) $cuotas->sum('saldo_pendiente');
        $cuotasVencidasCount = $cuotas->filter(
            fn($c) => $c->fecha_vencimiento && Carbon::parse($c->fecha_vencimiento)->toDateString() < $todayStr
        )->count();

        return response()->json([
            'success' => true,
            'cliente' => [
                'id' => $cliente->id,
                'nombre_completo' => trim("{$cliente->nombre} {$cliente->apellido}"),
                'documento' => $cliente->documento ?? '',
                'telefono' => $cliente->telefono ?? '',
                'email' => $cliente->email ?? '',
                'url_cliente' => route('admin.clientes.show', $cliente->id),
                'url_pagar' => route('admin.pagos.create', ['cliente_id' => $cliente->id]),
                'url_estado_cuenta' => route('admin.reportes.estado-cuenta', ['cliente_id' => $cliente->id]),
            ],
            'resumen' => [
                'total_pagado_fmt' => number_format($totalPagado, 2),
                'saldo_pendiente_fmt' => number_format($saldoPendiente, 2),
                'cuotas_vencidas_count' => $cuotasVencidasCount,
                'cuotas_pendientes_count' => $cuotas->count(),
            ],
            'contratos' => $contratos->map(function ($ct) {
                return [
                    'id' => $ct->id,
                    'numero_contrato' => $ct->numero_contrato,
                    'moto' => $ct->unidad?->moto?->titulo ?? 'Moto',
                    'placa' => $ct->unidad?->placa ?? 'S/P',
                    'estado' => $ct->estado,
                    'progreso_texto' => "{$ct->cuotas_pagadas}/{$ct->cuotas_totales}",
                    'saldo_pendiente_fmt' => number_format((float) $ct->saldo_pendiente, 2),
                    'url_contrato' => route('admin.contratos.show', $ct->id),
                    'url_pagar' => route('admin.contratos.pagar', $ct->id),
                ];
            })->values()->toArray(),
            'cuotas' => $cuotas->map(function ($c) use ($today) {
                $vencimiento = $c->fecha_vencimiento ? Carbon::parse($c->fecha_vencimiento) : $today->copy();
                $timing = $this->formatDaysText($vencimiento, $today);

                return [
                    'id' => $c->id,
                    'contrato_id' => $c->contrato_id,
                    'numero_contrato' => $c->contrato?->numero_contrato ?? (string) $c->contrato_id,
                    'numero_cuota' => $c->numero_cuota,
                    'tipo_cuota' => ucfirst((string) ($c->tipo_cuota ?? 'regular')),
                    'fecha_vencimiento_fmt' => $vencimiento->format('d/m/Y'),
                    'dias_texto' => $timing['dias_texto'],
                    'badge_color' => $timing['badge_color'],
                    'estado' => $c->estado,
                    'monto_total_fmt' => number_format((float) $c->monto_total, 2),
                    'monto_pagado_fmt' => number_format((float) $c->monto_pagado, 2),
                    'saldo_pendiente_fmt' => number_format((float) $c->saldo_pendiente, 2),
                    'url_pagar' => route('admin.contratos.pagar', $c->contrato_id),
                ];
            })->values()->toArray(),
        ]);
    }

    /**
     * Envía recordatorios inmediatos por WhatsApp vía AJAX.
     */
    public function ajaxNotifyWhatsApp(Request $request)
    {
        if (!Auth::check()) {
            abort(403);
        }

        $clientIds = $this->normalizeClientIds($request->input('clients', $this->selectedClients));
        if (empty($clientIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Seleccione al menos un cliente para notificar.'
            ], 422);
        }

        $empresaId = Auth::user()->empresa_id;
        $service = WhatsAppService::forCompany($empresaId);
        $clientes = Cliente::whereIn('id', $clientIds)->get();

        $sent = 0;
        $failed = 0;

        foreach ($clientes as $cliente) {
            $phone = $this->formatPhoneNumber($cliente->telefono);
            if (!$phone) {
                $failed++;
                continue;
            }

            $pendientes = PlanPago::whereHas('contrato', function ($q) use ($cliente) {
                    $q->where('cliente_id', $cliente->id)
                      ->whereIn('estado', ['activo', 'mora']);
                })
                ->where('saldo_pendiente', '>', 0)
                ->where('estado', '!=', 'pagado')
                ->orderBy('fecha_vencimiento')
                ->limit(3)
                ->get();

            $msg = "Recordatorio de cuotas pendientes\n\n";
            $msg .= "Cliente: " . trim("{$cliente->nombre} {$cliente->apellido}") . "\n";
            foreach ($pendientes as $p) {
                $msg .= "Cuota #{$p->numero_cuota} vence el " . optional($p->fecha_vencimiento)->format('d/m/Y')
                      . " • Saldo: $" . number_format((float) $p->saldo_pendiente, 2) . "\n";
            }
            $msg .= "\nPara más detalles, comuníquese con administración.";

            try {
                $result = $service->sendMessage($phone, $msg, true);
                if (is_array($result) && array_key_exists('success', $result) && !$result['success']) {
                    $failed++;
                } else {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('Error enviando WhatsApp desde Dashboard AJAX', [
                    'cliente_id' => $cliente->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        app(AuditService::class)->logUserAction('dashboard.notify.whatsapp', [
            'clients' => $clientIds,
            'sent' => $sent,
            'failed' => $failed,
        ], 'Notificaciones WhatsApp enviadas desde Dashboard');

        $ok = $sent > 0;
        $message = $ok
            ? "Notificaciones enviadas: {$sent}" . ($failed > 0 ? " ({$failed} no enviadas)" : '.')
            : 'No se pudo enviar ninguna notificación. Verifique la conexión de WhatsApp y los teléfonos de los clientes.';

        return response()->json([
            'success' => $ok,
            'sent' => $sent,
            'failed' => $failed,
            'message' => $message,
        ], $ok ? 200 : 422);
    }

    /**
     * Programa recordatorios de WhatsApp para el día siguiente a las 08:00 vía AJAX.
     */
    public function ajaxScheduleReminders(Request $request)
    {
        if (!Auth::check()) {
            abort(403);
        }

        $clientIds = $this->normalizeClientIds($request->input('clients', $this->selectedClients));
        if (empty($clientIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Seleccione al menos un cliente para programar envíos.'
            ], 422);
        }

        $scheduledAt = now()->addDay()->setTime(8, 0);
        $clientes = Cliente::whereIn('id', $clientIds)->get();
        $scheduled = 0;

        foreach ($clientes as $cliente) {
            if (empty($cliente->telefono)) {
                continue;
            }

            WhatsAppScheduledMessage::create([
                'recipient_phone' => $cliente->telefono,
                'recipient_name' => trim("{$cliente->nombre} {$cliente->apellido}"),
                'message_content' => 'Recordatorio: tiene cuotas pendientes próximamente. Comuníquese con administración para más detalles.',
                'variables' => [],
                'scheduled_at' => $scheduledAt,
                'created_by' => Auth::id(),
            ]);
            $scheduled++;
        }

        app(AuditService::class)->logUserAction('dashboard.reminders.schedule', [
            'clients' => $clientIds,
            'scheduled' => $scheduled,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
        ], 'Recordatorios programados desde Dashboard');

        return response()->json([
            'success' => $scheduled > 0,
            'scheduled' => $scheduled,
            'message' => $scheduled > 0
                ? "Se programaron {$scheduled} recordatorio(s) para el {$scheduledAt->format('d/m/Y H:i')}."
                : 'Los clientes seleccionados no tienen número telefónico registrado.',
        ], $scheduled > 0 ? 200 : 422);
    }

    public function notifyWhatsApp()
    {
        $response = $this->ajaxNotifyWhatsApp(request()->merge(['clients' => $this->selectedClients]));
        $data = $response->getData(true);
        session()->flash($data['success'] ? 'message' : 'error', $data['message']);
    }

    public function scheduleReminders()
    {
        $response = $this->ajaxScheduleReminders(request()->merge(['clients' => $this->selectedClients]));
        $data = $response->getData(true);
        session()->flash($data['success'] ? 'message' : 'error', $data['message']);
    }

    public function exportExcel()
    {
        if (!Auth::check()) {
            abort(403);
        }

        $filters = $this->extractFilters(request());
        $today = now()->startOfDay();
        $headers = ['Tipo', 'Cliente', 'Contrato / Documento', 'Fecha Vencimiento', 'Estado / Días', 'Monto / Saldo'];
        $rows = collect();

        $cuotasIter = $this->buildCuotasBaseQuery($filters, false)
            ->orderBy('fecha_vencimiento')
            ->limit(250)
            ->get();

        foreach ($cuotasIter as $c) {
            $cliente = optional($c->contrato)->cliente;
            $timing = $this->formatDaysText(Carbon::parse($c->fecha_vencimiento), $today);
            $rows->push([
                'Cuota por vencer',
                $cliente ? trim("{$cliente->nombre} {$cliente->apellido}") : 'N/A',
                'Contrato #' . (optional($c->contrato)->numero_contrato ?? $c->contrato_id) . " - Cuota #{$c->numero_cuota}",
                optional($c->fecha_vencimiento)->format('d/m/Y'),
                $timing['dias_texto'],
                (float) $c->saldo_pendiente,
            ]);
        }

        $contratosIter = $this->buildContratosBaseQuery($filters, false)
            ->orderBy('fecha_fin_estimada')
            ->limit(250)
            ->get();

        foreach ($contratosIter as $ct) {
            $cliente = $ct->cliente;
            $timing = $ct->fecha_fin_estimada
                ? $this->formatDaysText(Carbon::parse($ct->fecha_fin_estimada), $today, 'Finaliza en', 'Vencido hace')
                : ['dias_texto' => ucfirst($ct->estado)];

            $rows->push([
                'Contrato por vencer',
                $cliente ? trim("{$cliente->nombre} {$cliente->apellido}") : 'N/A',
                "#{$ct->numero_contrato} ({$ct->cuotas_pagadas}/{$ct->cuotas_totales} cuotas)",
                optional($ct->fecha_fin_estimada)->format('d/m/Y'),
                $timing['dias_texto'],
                (float) $ct->saldo_pendiente,
            ]);
        }

        $clientesIter = $this->buildClientesQuery($filters, $today->toDateString())
            ->limit(250)
            ->get();

        foreach ($clientesIter as $cl) {
            $rows->push([
                'Cliente con pendientes',
                trim("{$cl->nombre} {$cl->apellido}"),
                $cl->documento,
                now()->format('d/m/Y'),
                "{$cl->cuotas_vencidas_count} vencidas / {$cl->cuotas_pendientes_count} pend.",
                (float) $cl->saldo_total_pendiente,
            ]);
        }

        $filename = 'dashboard_alertas_' . now()->format('Y-m-d_His') . '.xlsx';
        app(AuditService::class)->logUserAction('dashboard.export.excel', [
            'rows' => $rows->count(),
            'filters' => $filters,
        ], 'Exportación Excel desde Dashboard');

        return Excel::download(new class($rows, $headers) implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize {
            private $rows;
            private $headers;

            public function __construct($rows, $headers)
            {
                $this->rows = $rows;
                $this->headers = $headers;
            }

            public function collection(): \Illuminate\Support\Enumerable
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headers;
            }

            public function styles(Worksheet $sheet): ?array
            {
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle("A1:F{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                return [];
            }
        }, $filename);
    }

    public function exportPdf()
    {
        if (!Auth::check()) {
            abort(403);
        }

        $filters = $this->extractFilters(request());
        $metrics = $this->computeMetrics($filters);
        $charts = $this->computeCharts($filters, $metrics);
        $cuotas = $this->buildCuotasData($filters, 15)['data'];
        $contratos = $this->buildContratosData($filters, 15)['data'];

        $html = view('livewire.admin.dashboard.pdf', [
            'metrics' => $metrics,
            'charts' => $charts,
            'filters' => $filters,
            'cuotas' => $cuotas,
            'contratos' => $contratos,
            'clienteCount' => $metrics['clientes']['total'] ?? Cliente::count(),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $filename = 'dashboard_resumen_' . now()->format('Ymd_His') . '.pdf';

        app(AuditService::class)->logUserAction('dashboard.export.pdf', [
            'filename' => $filename,
            'filters' => $filters,
        ], 'Exportación PDF desde Dashboard');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function render()
    {
        $filters = $this->extractFilters();
        $payload = !empty($this->initialPayload) ? $this->initialPayload : $this->buildDashboardPayload($filters, true);

        return view('livewire.admin.dashboard.index', [
            'payload' => $payload,
        ])->layout($this->getLayout(), [
            'title' => 'Dashboard',
        ]);
    }

    /**
     * Extrae y normaliza los filtros desde Request o propiedades del componente.
     */
    protected function extractFilters(?Request $request = null): array
    {
        $source = $request ?? request();

        $dateFrom = $this->sanitizeDate($source->input('dateFrom', $this->dateFrom));
        $dateTo = $this->sanitizeDate($source->input('dateTo', $this->dateTo));

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $rawMin = $source->input('minAmount', $this->minAmount);
        $rawMax = $source->input('maxAmount', $this->maxAmount);
        $minAmount = ($rawMin !== '' && $rawMin !== null && is_numeric($rawMin)) ? (float) $rawMin : null;
        $maxAmount = ($rawMax !== '' && $rawMax !== null && is_numeric($rawMax)) ? (float) $rawMax : null;

        if ($minAmount !== null && $maxAmount !== null && $minAmount > $maxAmount) {
            [$minAmount, $maxAmount] = [$maxAmount, $minAmount];
        }

        $anticipation = (int) $source->input('anticipationDays', $this->anticipationDays);
        $anticipationDays = max(1, min(365, $anticipation > 0 ? $anticipation : 7));

        $cuotasFilter = (string) $source->input('cuotasFilter', $this->cuotasFilter);
        if (!in_array($cuotasFilter, ['all', 'overdue', 'upcoming'], true)) {
            $cuotasFilter = 'all';
        }

        $contratosFilter = (string) $source->input('contratosFilter', $this->contratosFilter);
        if (!in_array($contratosFilter, ['all', 'anticipation'], true)) {
            $contratosFilter = 'all';
        }

        return [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'minAmount' => $minAmount,
            'maxAmount' => $maxAmount,
            'anticipationDays' => $anticipationDays,
            'searchClient' => trim((string) $source->input('searchClient', $this->searchClient)),
            'cuotasFilter' => $cuotasFilter,
            'contratosFilter' => $contratosFilter,
            'cuotasPage' => max(1, (int) $source->input('cuotasPage', 1)),
            'contratosPage' => max(1, (int) $source->input('contratosPage', 1)),
            'clientesPage' => max(1, (int) $source->input('clientesPage', 1)),
        ];
    }

    /**
     * Construye todo el payload JSON del dashboard.
     */
    protected function buildDashboardPayload(array $filters, bool $withPagination = true): array
    {
        $metrics = $this->computeMetrics($filters);
        $charts = $this->computeCharts($filters, $metrics);
        $perPage = $withPagination ? 8 : 100;

        return [
            'filters' => $filters,
            'metrics' => $metrics,
            'charts' => $charts,
            'cuotas' => $this->buildCuotasData($filters, $perPage),
            'contratos' => $this->buildContratosData($filters, $perPage),
            'clientes' => $this->buildClientesData($filters, $perPage),
            'updatedAt' => now()->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * Calcula las métricas principales adaptadas a los filtros activos.
     */
    protected function computeMetrics(array $filters): array
    {
        $todayStr = now()->toDateString();
        $hasDateFilter = $filters['dateFrom'] !== '' || $filters['dateTo'] !== '';

        $clientesTotal = Cliente::count();
        $clientesActivos = Cliente::where('activo', true)->count();
        $clientesInactivos = Cliente::where('activo', false)->count();
        $clientesConMora = Cliente::whereHas('contratos', function ($q) use ($todayStr) {
            $q->whereIn('estado', ['activo', 'mora'])
              ->whereHas('planPagos', function ($sub) use ($todayStr) {
                  $sub->where('saldo_pendiente', '>', 0)
                      ->where('estado', '!=', 'pagado')
                      ->whereDate('fecha_vencimiento', '<', $todayStr);
              });
        })->count();

        $contratosVigentes = Contrato::activos()->count();
        $contratosVencidos = Contrato::activos()
            ->where(function ($q) use ($todayStr) {
                $q->where('cuotas_vencidas', '>', 0)
                  ->orWhere('estado', 'mora')
                  ->orWhereHas('planPagos', function ($sub) use ($todayStr) {
                      $sub->where('saldo_pendiente', '>', 0)
                          ->where('estado', '!=', 'pagado')
                          ->whereDate('fecha_vencimiento', '<', $todayStr);
                  });
            })->count();
        $contratosAlDia = max(0, $contratosVigentes - $contratosVencidos);
        $contratosCompletados = Contrato::whereIn('estado', ['completado', 'finalizado'])->count();

        $pagosAprobadosQuery = Pago::where('estado', 'aprobado')
            ->when($filters['dateFrom'] !== '', fn($q) => $q->whereDate('fecha', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn($q) => $q->whereDate('fecha', '<=', $filters['dateTo']))
            ->when($filters['minAmount'] !== null, fn($q) => $q->where('total', '>=', $filters['minAmount']))
            ->when($filters['maxAmount'] !== null, fn($q) => $q->where('total', '<=', $filters['maxAmount']));

        $pagosRecibidos = (clone $pagosAprobadosQuery)->count();

        $pagosPendientes = Pago::where('estado', 'pendiente')
            ->when($filters['dateFrom'] !== '', fn($q) => $q->whereDate('fecha', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn($q) => $q->whereDate('fecha', '<=', $filters['dateTo']))
            ->when($filters['minAmount'] !== null, fn($q) => $q->where('total', '>=', $filters['minAmount']))
            ->when($filters['maxAmount'] !== null, fn($q) => $q->where('total', '<=', $filters['maxAmount']))
            ->count();

        $moraBase = PlanPago::whereHas('contrato', fn($q) => $q->whereIn('estado', ['activo', 'mora']))
            ->where('saldo_pendiente', '>', 0)
            ->where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '<', $todayStr);

        $pagosMorosos = (clone $moraBase)->count();
        $saldoMora = (float) (clone $moraBase)->sum('saldo_pendiente');

        if ($hasDateFilter) {
            $ingresoPeriodo = (float) (clone $pagosAprobadosQuery)->sum('total');
            $periodoLabel = 'Ingresos del Período';
        } else {
            $ingresoPeriodo = (float) Pago::where('estado', 'aprobado')
                ->whereBetween('fecha', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->when($filters['minAmount'] !== null, fn($q) => $q->where('total', '>=', $filters['minAmount']))
                ->when($filters['maxAmount'] !== null, fn($q) => $q->where('total', '<=', $filters['maxAmount']))
                ->sum('total');
            $periodoLabel = 'Ingresos Mensuales';
        }

        $ingresoAnual = (float) Pago::where('estado', 'aprobado')
            ->whereBetween('fecha', [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()])
            ->when($filters['minAmount'] !== null, fn($q) => $q->where('total', '>=', $filters['minAmount']))
            ->when($filters['maxAmount'] !== null, fn($q) => $q->where('total', '<=', $filters['maxAmount']))
            ->sum('total');

        return [
            'clientes' => [
                'total' => $clientesTotal,
                'activos' => $clientesActivos,
                'inactivos' => $clientesInactivos,
                'con_mora' => $clientesConMora,
            ],
            'contratos' => [
                'vigentes' => $contratosVigentes,
                'vencidos' => $contratosVencidos,
                'al_dia' => $contratosAlDia,
                'completados' => $contratosCompletados,
            ],
            'pagos' => [
                'recibidos' => $pagosRecibidos,
                'pendientes' => $pagosPendientes,
                'morosos' => $pagosMorosos,
                'saldo_mora' => round($saldoMora, 2),
            ],
            'ingresos' => [
                'mensual' => round($ingresoPeriodo, 2),
                'anual' => round($ingresoAnual, 2),
                'periodo_label' => $periodoLabel,
            ],
        ];
    }

    /**
     * Calcula los datos de ambos gráficos (Tendencia de Ingresos y Estado de Cartera).
     */
    protected function computeCharts(array $filters, array $metrics): array
    {
        $todayStr = now()->toDateString();
        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $basePagoQuery = Pago::where('estado', 'aprobado')
            ->when($filters['minAmount'] !== null, fn($q) => $q->where('total', '>=', $filters['minAmount']))
            ->when($filters['maxAmount'] !== null, fn($q) => $q->where('total', '<=', $filters['maxAmount']));

        if ($filters['dateFrom'] !== '' && $filters['dateTo'] !== '') {
            $from = Carbon::parse($filters['dateFrom'])->startOfDay();
            $to = Carbon::parse($filters['dateTo'])->endOfDay();
            $granularity = $from->diffInDays($to) <= 45 ? 'day' : 'month';
        } elseif ($filters['dateFrom'] !== '') {
            $from = Carbon::parse($filters['dateFrom'])->startOfDay();
            $to = $from->gt(now()) ? $from->copy()->endOfMonth() : now()->endOfDay();
            $granularity = $from->diffInDays($to) <= 45 ? 'day' : 'month';
        } elseif ($filters['dateTo'] !== '') {
            $to = Carbon::parse($filters['dateTo'])->endOfDay();
            $from = $to->copy()->subMonths(11)->startOfMonth();
            $granularity = 'month';
        } else {
            $from = now()->copy()->subMonths(11)->startOfMonth();
            $to = now()->copy()->endOfMonth();
            $granularity = 'month';
        }

        $labels = [];
        $values = [];
        $counts = [];

        if ($granularity === 'day') {
            $grouped = (clone $basePagoQuery)
                ->whereDate('fecha', '>=', $from->toDateString())
                ->whereDate('fecha', '<=', $to->toDateString())
                ->selectRaw('DATE(fecha) as bucket, SUM(total) as total_monto, COUNT(*) as total_pagos')
                ->groupBy('bucket')
                ->get()
                ->keyBy('bucket');

            $cursor = $from->copy()->startOfDay();
            $endDay = $to->copy()->startOfDay();
            while ($cursor->lte($endDay)) {
                $key = $cursor->toDateString();
                $labels[] = $cursor->format('d/m');
                $values[] = round((float) ($grouped[$key]->total_monto ?? 0), 2);
                $counts[] = (int) ($grouped[$key]->total_pagos ?? 0);
                $cursor->addDay();
            }
        } else {
            $startMonth = $from->copy()->startOfMonth();
            $endMonth = $to->copy()->endOfMonth();

            $grouped = (clone $basePagoQuery)
                ->whereDate('fecha', '>=', $startMonth->toDateString())
                ->whereDate('fecha', '<=', $endMonth->toDateString())
                ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as bucket, SUM(total) as total_monto, COUNT(*) as total_pagos")
                ->groupBy('bucket')
                ->get()
                ->keyBy('bucket');

            $cursor = $startMonth->copy();
            $limitMonth = $to->copy()->startOfMonth();
            while ($cursor->lte($limitMonth)) {
                $key = $cursor->format('Y-m');
                $labels[] = $monthNames[(int) $cursor->format('n') - 1] . ' ' . $cursor->format('y');
                $values[] = round((float) ($grouped[$key]->total_monto ?? 0), 2);
                $counts[] = (int) ($grouped[$key]->total_pagos ?? 0);
                $cursor->addMonth();
            }
        }

        $totalCobrado = (float) (clone $basePagoQuery)
            ->when($filters['dateFrom'] !== '', fn($q) => $q->whereDate('fecha', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn($q) => $q->whereDate('fecha', '<=', $filters['dateTo']))
            ->sum('total');

        $saldoEnMora = (float) ($metrics['pagos']['saldo_mora'] ?? 0);
        $saldoPorVencer = (float) PlanPago::whereHas('contrato', fn($q) => $q->whereIn('estado', ['activo', 'mora']))
            ->where('saldo_pendiente', '>', 0)
            ->where('estado', '!=', 'pagado')
            ->whereDate('fecha_vencimiento', '>=', $todayStr)
            ->sum('saldo_pendiente');

        return [
            'trend' => [
                'labels' => $labels,
                'values' => $values,
                'counts' => $counts,
                'granularity' => $granularity,
                'totalPeriodo' => round(array_sum($values), 2),
                'totalPagosPeriodo' => array_sum($counts),
                'rangeLabel' => $from->format('d/m/Y') . ' - ' . $to->format('d/m/Y'),
            ],
            'cartera' => [
                'contratos_labels' => ['Al Día', 'En Mora', 'Completados'],
                'contratos_counts' => [
                    (int) ($metrics['contratos']['al_dia'] ?? 0),
                    (int) ($metrics['contratos']['vencidos'] ?? 0),
                    (int) ($metrics['contratos']['completados'] ?? 0),
                ],
                'montos_labels' => ['Cobrado', 'Por Vencer', 'En Mora'],
                'montos' => [
                    round($totalCobrado, 2),
                    round($saldoPorVencer, 2),
                    round($saldoEnMora, 2),
                ],
                'total_cobrado' => round($totalCobrado, 2),
                'saldo_por_vencer' => round($saldoPorVencer, 2),
                'saldo_en_mora' => round($saldoEnMora, 2),
            ],
        ];
    }

    /**
     * Consulta paginada de cuotas próximas a vencer y vencidas.
     */
    protected function buildCuotasData(array $filters, int $perPage = 8): array
    {
        $today = now()->startOfDay();
        $todayStr = $today->toDateString();

        $baseQuery = $this->buildCuotasBaseQuery($filters, false);
        $countAll = (clone $baseQuery)->count();
        $countUpcoming = (clone $baseQuery)->whereDate('fecha_vencimiento', '>=', $todayStr)->count();
        $countOverdue = (clone $baseQuery)->whereDate('fecha_vencimiento', '<', $todayStr)->count();

        $query = $this->buildCuotasBaseQuery($filters, true);

        if ($filters['cuotasFilter'] === 'upcoming') {
            $query->orderBy('fecha_vencimiento', 'asc')->orderBy('numero_cuota', 'asc');
        } elseif ($filters['cuotasFilter'] === 'overdue') {
            $query->orderBy('fecha_vencimiento', 'desc')->orderBy('numero_cuota', 'desc');
        } else {
            $query->orderByRaw('CASE WHEN DATE(fecha_vencimiento) >= ? THEN 0 ELSE 1 END ASC', [$todayStr])
                  ->orderByRaw('CASE WHEN DATE(fecha_vencimiento) >= ? THEN fecha_vencimiento END ASC', [$todayStr])
                  ->orderBy('fecha_vencimiento', 'desc')
                  ->orderBy('numero_cuota', 'asc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'cuotasPage', $filters['cuotasPage']);

        $items = $paginator->getCollection()->map(function ($cuota) use ($today) {
            $cliente = optional($cuota->contrato)->cliente;
            $vencimiento = $cuota->fecha_vencimiento ? Carbon::parse($cuota->fecha_vencimiento) : $today->copy();
            $timing = $this->formatDaysText($vencimiento, $today);

            return [
                'id' => $cuota->id,
                'numero_cuota' => $cuota->numero_cuota,
                'contrato_id' => $cuota->contrato_id,
                'numero_contrato' => optional($cuota->contrato)->numero_contrato ?? (string) $cuota->contrato_id,
                'cliente_id' => $cliente?->id,
                'cliente_nombre' => $cliente ? trim("{$cliente->nombre} {$cliente->apellido}") : 'Sin cliente',
                'cliente_documento' => $cliente?->documento ?? '',
                'fecha_vencimiento_fmt' => $vencimiento->format('d/m/Y'),
                'dias_texto' => $timing['dias_texto'],
                'badge_color' => $timing['badge_color'],
                'saldo_pendiente' => round((float) $cuota->saldo_pendiente, 2),
                'saldo_pendiente_fmt' => number_format((float) $cuota->saldo_pendiente, 2),
                'url_contrato' => route('admin.contratos.show', $cuota->contrato_id),
                'url_pagar' => route('admin.contratos.pagar', $cuota->contrato_id),
            ];
        })->values()->toArray();

        $formatted = $this->formatPaginator($paginator, $items);
        $formatted['counts'] = [
            'all' => $countAll,
            'upcoming' => $countUpcoming,
            'overdue' => $countOverdue,
        ];

        return $formatted;
    }

    /**
     * Consulta paginada de contratos próximos a vencer o en mora.
     */
    protected function buildContratosData(array $filters, int $perPage = 8): array
    {
        $today = now()->startOfDay();

        $baseAll = $this->buildContratosBaseQuery($filters, false, 'all');
        $baseAnticipation = $this->buildContratosBaseQuery($filters, false, 'anticipation');
        $countAll = (clone $baseAll)->count();
        $countAnticipation = (clone $baseAnticipation)->count();

        $query = $this->buildContratosBaseQuery($filters, true)
            ->orderBy('fecha_fin_estimada', 'asc')
            ->orderBy('id', 'asc');

        $paginator = $query->paginate($perPage, ['*'], 'contratosPage', $filters['contratosPage']);

        $items = $paginator->getCollection()->map(function ($ct) use ($today) {
            $cliente = $ct->cliente;
            $fin = $ct->fecha_fin_estimada ? Carbon::parse($ct->fecha_fin_estimada) : null;
            $timing = $fin
                ? $this->formatDaysText($fin, $today, 'En', 'Vencido hace')
                : ['dias_texto' => 'Sin fecha', 'badge_color' => 'secondary'];

            $totales = max(1, (int) $ct->cuotas_totales);
            $pagadas = (int) $ct->cuotas_pagadas;
            $pct = min(100, max(0, (int) round(($pagadas / $totales) * 100)));

            return [
                'id' => $ct->id,
                'numero_contrato' => $ct->numero_contrato,
                'cliente_id' => $cliente?->id,
                'cliente_nombre' => $cliente ? trim("{$cliente->nombre} {$cliente->apellido}") : 'Sin cliente',
                'cliente_documento' => $cliente?->documento ?? '',
                'fecha_fin_fmt' => $fin ? $fin->format('d/m/Y') : 'N/A',
                'cuotas_pagadas' => $pagadas,
                'cuotas_totales' => (int) $ct->cuotas_totales,
                'progreso_texto' => "{$pagadas}/{$ct->cuotas_totales}",
                'progreso_pct' => $pct,
                'saldo_pendiente' => round((float) $ct->saldo_pendiente, 2),
                'saldo_pendiente_fmt' => number_format((float) $ct->saldo_pendiente, 2),
                'estado' => $ct->estado,
                'cuotas_vencidas' => (int) $ct->cuotas_vencidas,
                'dias_texto' => $timing['dias_texto'],
                'badge_color' => $timing['badge_color'],
                'url_contrato' => route('admin.contratos.show', $ct->id),
                'url_pagar' => route('admin.contratos.pagar', $ct->id),
            ];
        })->values()->toArray();

        $formatted = $this->formatPaginator($paginator, $items);
        $formatted['counts'] = [
            'all' => $countAll,
            'anticipation' => $countAnticipation,
        ];

        return $formatted;
    }

    /**
     * Consulta paginada de clientes con cuotas/saldos pendientes.
     */
    protected function buildClientesData(array $filters, int $perPage = 8): array
    {
        $todayStr = now()->toDateString();
        $query = $this->buildClientesQuery($filters, $todayStr);
        $paginator = $query->paginate($perPage, ['*'], 'clientesPage', $filters['clientesPage']);

        $items = $paginator->getCollection()->map(function ($cl) {
            return [
                'id' => $cl->id,
                'nombre_completo' => trim("{$cl->nombre} {$cl->apellido}"),
                'documento' => $cl->documento,
                'telefono' => $cl->telefono ?? '',
                'cuotas_vencidas_count' => (int) ($cl->cuotas_vencidas_count ?? 0),
                'cuotas_pendientes_count' => (int) ($cl->cuotas_pendientes_count ?? 0),
                'saldo_total' => round((float) ($cl->saldo_total_pendiente ?? 0), 2),
                'saldo_total_fmt' => number_format((float) ($cl->saldo_total_pendiente ?? 0), 2),
                'url_cliente' => route('admin.clientes.show', $cl->id),
                'url_estado_cuenta' => route('admin.reportes.estado-cuenta', ['cliente_id' => $cl->id]),
                'url_pagar' => route('admin.pagos.create', ['cliente_id' => $cl->id]),
            ];
        })->values()->toArray();

        return $this->formatPaginator($paginator, $items);
    }

    protected function buildCuotasBaseQuery(array $filters, bool $applyTabFilter = true)
    {
        $todayStr = now()->toDateString();
        $limitStr = now()->addDays($filters['anticipationDays'])->toDateString();
        $hasDateFilter = $filters['dateFrom'] !== '' || $filters['dateTo'] !== '';

        $query = PlanPago::with(['contrato' => function ($q) {
                $q->with(['cliente' => fn($cq) => $cq->withTrashed()]);
            }])
            ->whereHas('contrato', function ($q) use ($filters) {
                $q->whereIn('estado', ['activo', 'mora']);
                if ($filters['searchClient'] !== '') {
                    $term = $filters['searchClient'];
                    $q->where(function ($sub) use ($term) {
                        $sub->where('numero_contrato', 'like', "%{$term}%")
                            ->orWhereHas('cliente', fn($cq) => $this->applyClientFilter($cq, $term));
                    });
                }
            })
            ->where('saldo_pendiente', '>', 0)
            ->where('estado', '!=', 'pagado')
            ->when($filters['minAmount'] !== null, fn($q) => $q->where('saldo_pendiente', '>=', $filters['minAmount']))
            ->when($filters['maxAmount'] !== null, fn($q) => $q->where('saldo_pendiente', '<=', $filters['maxAmount']));

        if ($hasDateFilter) {
            $query->when($filters['dateFrom'] !== '', fn($q) => $q->whereDate('fecha_vencimiento', '>=', $filters['dateFrom']))
                  ->when($filters['dateTo'] !== '', fn($q) => $q->whereDate('fecha_vencimiento', '<=', $filters['dateTo']));
        } else {
            $query->whereDate('fecha_vencimiento', '<=', $limitStr);
        }

        if ($applyTabFilter) {
            if ($filters['cuotasFilter'] === 'overdue') {
                $query->whereDate('fecha_vencimiento', '<', $todayStr);
            } elseif ($filters['cuotasFilter'] === 'upcoming') {
                $query->whereDate('fecha_vencimiento', '>=', $todayStr);
            }
        }

        return $query;
    }

    protected function buildContratosBaseQuery(array $filters, bool $applyMode = true, ?string $forceMode = null)
    {
        $mode = $forceMode ?? $filters['contratosFilter'];
        $hasDateFilter = $filters['dateFrom'] !== '' || $filters['dateTo'] !== '';
        $limitStr = now()->addDays($filters['anticipationDays'])->toDateString();

        $query = Contrato::with(['cliente' => fn($q) => $q->withTrashed()])
            ->whereIn('estado', ['activo', 'mora'])
            ->when($filters['minAmount'] !== null, fn($q) => $q->where('saldo_pendiente', '>=', $filters['minAmount']))
            ->when($filters['maxAmount'] !== null, fn($q) => $q->where('saldo_pendiente', '<=', $filters['maxAmount']))
            ->when($filters['searchClient'] !== '', function ($q) use ($filters) {
                $term = $filters['searchClient'];
                $q->where(function ($sub) use ($term) {
                    $sub->where('numero_contrato', 'like', "%{$term}%")
                        ->orWhereHas('cliente', fn($cq) => $this->applyClientFilter($cq, $term));
                });
            });

        if ($hasDateFilter) {
            $query->when($filters['dateFrom'] !== '', fn($q) => $q->whereDate('fecha_fin_estimada', '>=', $filters['dateFrom']))
                  ->when($filters['dateTo'] !== '', fn($q) => $q->whereDate('fecha_fin_estimada', '<=', $filters['dateTo']));
        } elseif ($applyMode && $mode === 'anticipation') {
            $query->whereDate('fecha_fin_estimada', '<=', $limitStr);
        } elseif (!$applyMode && $mode === 'anticipation') {
            $query->whereDate('fecha_fin_estimada', '<=', $limitStr);
        }

        return $query;
    }

    protected function buildClientesQuery(array $filters, string $todayStr)
    {
        $planSub = PlanPago::query()
            ->join('contratos', 'plan_pagos.contrato_id', '=', 'contratos.id')
            ->whereNull('contratos.deleted_at')
            ->whereIn('contratos.estado', ['activo', 'mora'])
            ->where('plan_pagos.saldo_pendiente', '>', 0)
            ->where('plan_pagos.estado', '!=', 'pagado')
            ->when($filters['dateFrom'] !== '', fn($q) => $q->whereDate('plan_pagos.fecha_vencimiento', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] !== '', fn($q) => $q->whereDate('plan_pagos.fecha_vencimiento', '<=', $filters['dateTo']))
            ->when($filters['minAmount'] !== null, fn($q) => $q->where('plan_pagos.saldo_pendiente', '>=', $filters['minAmount']))
            ->when($filters['maxAmount'] !== null, fn($q) => $q->where('plan_pagos.saldo_pendiente', '<=', $filters['maxAmount']))
            ->selectRaw(
                'contratos.cliente_id as agg_cliente_id, ' .
                'SUM(plan_pagos.saldo_pendiente) as saldo_total_pendiente, ' .
                'COUNT(plan_pagos.id) as cuotas_pendientes_count, ' .
                'SUM(CASE WHEN DATE(plan_pagos.fecha_vencimiento) < ? THEN 1 ELSE 0 END) as cuotas_vencidas_count',
                [$todayStr]
            )
            ->groupBy('contratos.cliente_id');

        return Cliente::query()
            ->joinSub($planSub, 'resumen', function ($join) {
                $join->on('clientes.id', '=', 'resumen.agg_cliente_id');
            })
            ->select(
                'clientes.*',
                'resumen.saldo_total_pendiente',
                'resumen.cuotas_pendientes_count',
                'resumen.cuotas_vencidas_count'
            )
            ->when($filters['searchClient'] !== '', function ($q) use ($filters) {
                $term = $filters['searchClient'];
                $q->where(function ($sub) use ($term) {
                    $this->applyClientFilter($sub, $term);
                    $sub->orWhereHas('contratos', fn($cq) => $cq->where('numero_contrato', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('resumen.cuotas_vencidas_count')
            ->orderByDesc('resumen.saldo_total_pendiente')
            ->orderBy('clientes.nombre');
    }

    protected function applyClientFilter($query, string $term): void
    {
        $query->where(function ($sub) use ($term) {
            $sub->where('nombre', 'like', "%{$term}%")
                ->orWhere('apellido', 'like', "%{$term}%")
                ->orWhere('documento', 'like', "%{$term}%")
                ->orWhere('telefono', 'like', "%{$term}%")
                ->orWhereRaw("CONCAT(TRIM(COALESCE(nombre, '')), ' ', TRIM(COALESCE(apellido, ''))) LIKE ?", ["%{$term}%"]);
        });
    }

    protected function formatDaysText(Carbon $targetDate, Carbon $today, string $futurePrefix = 'En', string $pastPrefix = 'Vencida hace'): array
    {
        $diff = (int) $today->copy()->startOfDay()->diffInDays($targetDate->copy()->startOfDay(), false);

        if ($diff < 0) {
            $abs = abs($diff);
            return [
                'dias_texto' => "{$pastPrefix} {$abs} " . ($abs === 1 ? 'día' : 'días'),
                'badge_color' => 'danger',
            ];
        }

        if ($diff === 0) {
            return [
                'dias_texto' => 'Vence hoy',
                'badge_color' => 'warning',
            ];
        }

        return [
            'dias_texto' => "{$futurePrefix} {$diff} " . ($diff === 1 ? 'día' : 'días'),
            'badge_color' => $diff <= 3 ? 'warning' : 'info',
        ];
    }

    protected function formatPaginator(LengthAwarePaginator $paginator, array $items): array
    {
        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ];
    }

    protected function sanitizeDate($value): string
    {
        $str = trim((string) $value);
        if ($str === '') {
            return '';
        }
        try {
            return Carbon::parse($str)->toDateString();
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function normalizeClientIds($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), fn($id) => $id > 0)));
    }

    private function formatPhoneNumber($number): ?string
    {
        $cleaned = preg_replace('/[^0-9]/', '', (string) $number);
        if ($cleaned === '') {
            return null;
        }

        $empresaId = Auth::user()->empresa_id ?? 1;
        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        $pais = $empresa ? DB::table('pais')->where('id', $empresa->pais_id)->first() : null;
        $codigoPais = $pais && !empty($pais->codigo_telefonico)
            ? preg_replace('/[^0-9]/', '', (string) $pais->codigo_telefonico)
            : '58';

        if (strlen($cleaned) > 10 && str_starts_with($cleaned, $codigoPais)) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        return $codigoPais . $cleaned;
    }
}
