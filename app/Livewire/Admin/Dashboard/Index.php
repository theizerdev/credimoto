<?php

namespace App\Livewire\Admin\Dashboard;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\PlanPago;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
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
    public $selectedClients = [];
    public $metrics = [];
    public $monthlyTrend = ['labels' => [], 'values' => []];
    public $minAmount = '';
    public $maxAmount = '';

    protected $queryString = [
        'anticipationDays' => ['except' => 7],
        'searchClient' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'minAmount' => ['except' => ''],
        'maxAmount' => ['except' => '']
    ];

    public function mount()
    {
        if (!Auth::check()) abort(403);
        $this->loadData();
    }

    public function updatedSearchClient()
    {
        $this->resetPage();
    }

    public function refresh()
    {
        $empresaId = Auth::user()->empresa_id;
        $sucursalId = Auth::user()->sucursal_id;
        Cache::forget("dashboard.metrics.{$empresaId}.{$sucursalId}");
        $this->loadData();
    }

    public function loadData()
    {
        $empresaId = Auth::user()->empresa_id;
        $sucursalId = Auth::user()->sucursal_id;
        $cacheKey = "dashboard.metrics.{$empresaId}.{$sucursalId}";
        $this->metrics = Cache::remember($cacheKey, 60, function () use ($empresaId, $sucursalId) {
            $clientesTotal = Cliente::count();
            $clientesActivos = Cliente::where('activo', true)->count();
            $clientesInactivos = Cliente::where('activo', false)->count();
            $contratosVigentes = Contrato::activos()->count();
            $contratosVencidos = Contrato::where('cuotas_vencidas', '>', 0)->count();
            $pagosRecibidos = Pago::where('estado', 'aprobado')->count();
            $pagosPendientes = Pago::where('estado', 'pendiente')->count();
            $pagosMorosos = PlanPago::where('estado', 'vencido')->count();
            return [
                'clientes' => [
                    'total' => $clientesTotal,
                    'activos' => $clientesActivos,
                    'inactivos' => $clientesInactivos
                ],
                'contratos' => [
                    'vigentes' => $contratosVigentes,
                    'vencidos' => $contratosVencidos
                ],
                'pagos' => [
                    'recibidos' => $pagosRecibidos,
                    'pendientes' => $pagosPendientes,
                    'morosos' => $pagosMorosos
                ],
                'ingresos' => [
                    'mensual' => Pago::where('estado', 'aprobado')->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
                    'anual' => Pago::where('estado', 'aprobado')->whereBetween('fecha', [now()->startOfYear(), now()->endOfYear()])->sum('total')
                ]
            ];
        });

        $labels = [];
        $values = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->copy()->subMonths($i);
            $labels[] = $m->format('M');
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();
            $values[] = Pago::where('estado', 'aprobado')->whereBetween('fecha', [$start, $end])->sum('total');
        }
        $this->monthlyTrend = ['labels' => $labels, 'values' => $values];
        $this->dispatch('trendUpdated', labels: $labels, values: $values);
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

    public function notifyWhatsApp()
    {
        $empresaId = Auth::user()->empresa_id;
        $service = WhatsAppService::forCompany($empresaId);
        foreach ($this->selectedClients as $clienteId) {
            $cliente = Cliente::find($clienteId);
            if (!$cliente) continue;
            $phone = $this->formatPhoneNumber($cliente->telefono);
          
            if (!$phone) continue;
            $pendientes = PlanPago::whereHas('contrato', function ($q) use ($cliente) {
                $q->where('cliente_id', $cliente->id);
            })->whereIn('estado', ['pendiente', 'vencido'])->orderBy('fecha_vencimiento')->limit(3)->get();
            $msg = "Recordatorio de cuotas pendientes\n\n";
            $msg .= "Cliente: {$cliente->nombre} {$cliente->apellido}\n";
            foreach ($pendientes as $p) {
                $msg .= "Cuota #{$p->numero_cuota} vence el " . optional($p->fecha_vencimiento)->format('d/m/Y') . " • Saldo: $" . number_format($p->saldo_pendiente, 2) . "\n";
            }
            $msg .= "\nPara más detalles, comuníquese con administración.";
            $service->sendMessage($phone, $msg,true);
        }
        app(AuditService::class)->logUserAction('dashboard.notify.whatsapp', [
            'clients' => $this->selectedClients,
            'anticipationDays' => $this->anticipationDays
        ], 'Notificaciones WhatsApp enviadas desde Dashboard');
        session()->flash('message', 'Notificaciones enviadas por WhatsApp.');
    }

    public function scheduleReminders()
    {
        $scheduledAt = now()->addDay()->setTime(8, 0);
        foreach ($this->selectedClients as $clienteId) {
            \App\Models\WhatsAppScheduledMessage::create([
                'recipient_phone' => optional(Cliente::find($clienteId))->telefono,
                'recipient_name' => optional(Cliente::find($clienteId))->nombre,
                'message_content' => 'Recordatorio: tiene cuotas pendientes próximamente.',
                'variables' => [],
                'scheduled_at' => $scheduledAt,
                'created_by' => Auth::id()
            ]);
        }
        app(AuditService::class)->logUserAction('dashboard.reminders.schedule', [
            'clients' => $this->selectedClients,
            'scheduled_at' => $scheduledAt->toDateTimeString()
        ], 'Recordatorios programados desde Dashboard');
        session()->flash('message', 'Recordatorios programados correctamente.');
    }

    public function exportExcel()
    {
        $headers = ['Tipo', 'Cliente', 'Detalle', 'Fecha', 'Monto/Saldo'];
        $rows = collect();
        $limitDate = now()->addDays($this->anticipationDays);
        $cuotasIter = PlanPago::with(['contrato.cliente' => function($query) {
                $query->withTrashed(); // Incluir clientes eliminados también
            }])
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('fecha_vencimiento', '<=', $limitDate)
            ->when($this->dateFrom, fn($q) => $q->where('fecha_vencimiento', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha_vencimiento', '<=', $this->dateTo))
            ->when($this->minAmount, fn($q) => $q->where('saldo_pendiente', '>=', (float)$this->minAmount))
            ->when($this->maxAmount, fn($q) => $q->where('saldo_pendiente', '<=', (float)$this->maxAmount))
            ->orderBy('fecha_vencimiento')
            ->limit(100)
            ->get();
        foreach ($cuotasIter as $c) {
            $rows->push([
                'Cuota por vencer',
                optional($c->contrato->cliente)->nombre . ' ' . optional($c->contrato->cliente)->apellido,
                'Cuota #' . $c->numero_cuota,
                optional($c->fecha_vencimiento)->format('d/m/Y'),
                $c->saldo_pendiente
            ]);
        }
        $contratosIter = Contrato::with('cliente')->whereIn('estado', ['activo', 'mora'])
            ->where('fecha_fin_estimada', '<=', $limitDate)
            ->when($this->dateFrom, fn($q) => $q->where('fecha_fin_estimada', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha_fin_estimada', '<=', $this->dateTo))
            ->orderBy('fecha_fin_estimada')
            ->limit(100)
            ->get();
        foreach ($contratosIter as $ct) {
            $rows->push([
                'Contrato por vencer',
                $ct->cliente->nombre . ' ' . $ct->cliente->apellido,
                $ct->numero_contrato,
                optional($ct->fecha_fin_estimada)->format('d/m/Y'),
                $ct->saldo_pendiente
            ]);
        }
        $clientesIter = Cliente::whereHas('contratos', function ($q) {
                $q->whereHas('planPagos', function ($qp) {
                    $qp->whereIn('estado', ['pendiente', 'vencido']);
                });
            })
            ->when($this->searchClient, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nombre', 'like', '%' . $this->searchClient . '%')
                        ->orWhere('apellido', 'like', '%' . $this->searchClient . '%')
                        ->orWhere('documento', 'like', '%' . $this->searchClient . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
        foreach ($clientesIter as $cl) {
            $rows->push([
                'Cliente con pendientes',
                $cl->nombre . ' ' . $cl->apellido,
                $cl->documento,
                now()->format('d/m/Y'),
                ''
            ]);
        }
        $filename = 'dashboard_alertas_' . now()->format('Y-m-d_His') . '.xlsx';
        app(AuditService::class)->logUserAction('dashboard.export.excel', [
            'rows' => $rows->count()
        ], 'Exportación Excel desde Dashboard');
        return Excel::download(new class($rows, $headers) implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize {
            private $rows;
            private $headers;
            public function __construct($rows, $headers) { $this->rows = $rows; $this->headers = $headers; }
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->headers; }
            public function styles(Worksheet $sheet)
            {
                $sheet->getStyle('A1:E1')->getFont()->setBold(true);
                $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle("A1:E{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                return [];
            }
        }, $filename);
    }

    public function exportPdf()
    {
        $clienteCount = Cliente::count();
        $html = view('livewire.admin.dashboard.pdf', [
            'metrics' => $this->metrics,
            'clienteCount' => $clienteCount,
            'generatedAt' => now()->format('d/m/Y H:i')
        ])->render();
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $filename = 'dashboard_resumen_' . now()->format('Ymd_His') . '.pdf';
        app(AuditService::class)->logUserAction('dashboard.export.pdf', [
            'filename' => $filename
        ], 'Exportación PDF desde Dashboard');
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function render()
    {
        $limitDate = now()->addDays($this->anticipationDays);
        $cuotas = PlanPago::with(['contrato' => function($query) {
                $query->withTrashed(); // Incluir contratos eliminados también
                $query->with(['cliente' => function($clienteQuery) {
                    $clienteQuery->withTrashed(); // Incluir clientes eliminados también
                }]);
            }])
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('fecha_vencimiento', '<=', $limitDate)
            ->when($this->dateFrom, fn($q) => $q->where('fecha_vencimiento', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha_vencimiento', '<=', $this->dateTo))
            ->when($this->minAmount, fn($q) => $q->where('saldo_pendiente', '>=', (float)$this->minAmount))
            ->when($this->maxAmount, fn($q) => $q->where('saldo_pendiente', '<=', (float)$this->maxAmount))
            ->orderBy('fecha_vencimiento')
            ->paginate(10, ['*'], 'cuotasPage');
        $contratos = Contrato::with('cliente')->whereIn('estado', ['activo', 'mora'])
            ->where('fecha_fin_estimada', '<=', $limitDate)
            ->when($this->dateFrom, fn($q) => $q->where('fecha_fin_estimada', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('fecha_fin_estimada', '<=', $this->dateTo))
            ->orderBy('fecha_fin_estimada')
            ->paginate(10, ['*'], 'contratosPage');
        $clientes = Cliente::whereHas('contratos', function ($q) {
                $q->whereHas('planPagos', function ($qp) {
                    $qp->whereIn('estado', ['pendiente', 'vencido']);
                });
            })
            ->when($this->searchClient, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nombre', 'like', '%' . $this->searchClient . '%')
                        ->orWhere('apellido', 'like', '%' . $this->searchClient . '%')
                        ->orWhere('documento', 'like', '%' . $this->searchClient . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'clientesPage');
        return view('livewire.admin.dashboard.index', compact('cuotas', 'contratos', 'clientes'))->layout($this->getLayout(), [
            'title' => 'Dashboard'
        ]);
    }
}