<?php

namespace App\Livewire\Admin\Pagos;
use App\Traits\HasDynamicLayout;
use App\Traits\HasRegionalFormatting;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Pago;
use App\Models\ExchangeRate;
use App\Traits\Exportable;
use Codedge\Fpdf\Fpdf\Fpdf;
use App\Services\ThermalPrinterService;

class Index extends Component
{
    use WithPagination, Exportable, HasDynamicLayout, HasRegionalFormatting;

    public $showPreview = false;
    public $previewPagoId;
    public $showTicketPreview = false;
    public $printers = [];
    public $selectedPrinter = null;

    public $search = '';
    public $status = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $perPage = 10;
    public $monthsRange = 6;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 10]
    ];

    public function getStatsProperty()
    {
        // Base query según rol, sin dependencias de relaciones inexistentes
        $baseQuery = Pago::query();
        if (auth()->check()) {
            if (!auth()->user()->hasRole('Super Administrador')) {
                if (auth()->user()->empresa_id) {
                    $baseQuery->where('pagos.empresa_id', auth()->user()->empresa_id);
                }
                if (auth()->user()->sucursal_id) {
                    $baseQuery->where('pagos.sucursal_id', auth()->user()->sucursal_id);
                }
            }
            if (auth()->user()->cliente_id) {
                $baseQuery->where('cliente_id', auth()->user()->cliente_id);
            }
        }

        return [
            'total' => (clone $baseQuery)->count(),
            'aprobados' => (clone $baseQuery)->where('estado', 'aprobado')->count(),
            'pendientes' => (clone $baseQuery)->where('estado', 'pendiente')->count(),
            'ingresos_totales' => (clone $baseQuery)->where('estado', 'aprobado')->sum('total') ?: 0
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortBy = $field;
        $this->resetPage();
    }

    public function delete(Pago $pago)
    {
        // Verificar permiso para eliminar pagos
        if (!auth()->user()->can('delete pagos')) {
            session()->flash('error', 'No tienes permiso para eliminar pagos.');
            return;
        }

        try {
            $pago->delete();
            session()->flash('message', 'Pago eliminado correctamente.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error al eliminar el pago: ' . $e->getMessage());
        }

        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->status = '';
        $this->sortBy = 'created_at';
        $this->sortDirection = 'desc';
        $this->perPage = 10;
        $this->resetPage();
    }

    public function toggleStatus($pagoId)
    {
        if (!auth()->user()->can('edit pagos')) {
            session()->flash('error', 'No tienes permiso para editar pagos.');
            return;
        }

        $pago = Pago::find($pagoId);
        if ($pago) {
            $pago->estado = $pago->estado === 'aprobado' ? 'pendiente' : 'aprobado';
            $pago->save();
        }
    }

    public function getExportQuery()
    {
        return $this->getQuery();
    }

    public function getExportHeaders()
    {
        return [
            'Documento', 'Cliente', 'DNI', 'Total', 'Fecha', 'Estado', 'Método Pago'
        ];
    }

    public function formatExportRow($pago)
    {
        $clienteName = $pago->cliente ? $pago->cliente->nombre_completo : 'N/A';
        $clienteDocumento = $pago->cliente ? $pago->cliente->documento : 'N/A';

        return [
            $pago->numero ?? $pago->id,
            $clienteName,
            $clienteDocumento,
            number_format($pago->total, 2),
            $pago->fecha->format('d/m/Y'),
            ucfirst($pago->estado),
            $pago->metodo_pago ?? ''
        ];
    }

    private function getQuery()
    {
        $query = Pago::with(['cliente', 'detalles', 'user']);

        if (auth()->check() && auth()->user()->cliente_id) {
            $query->where('cliente_id', auth()->user()->cliente_id);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->whereHas('cliente', function ($subQuery) {
                    $subQuery->where('nombre', 'like', '%' . $this->search . '%')
                        ->orWhere('apellido', 'like', '%' . $this->search . '%')
                        ->orWhere('documento', 'like', '%' . $this->search . '%');
                })
                ->orWhere('referencia', 'like', '%' . $this->search . '%')
                ->orWhere('numero', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->status !== '') {
            $query->where('estado', $this->status);
        }

        return $query->orderBy($this->sortBy, $this->sortDirection);
    }

    public function render()
    {
        $pagos = $this->getQuery()->paginate($this->perPage);
        $comparatives = $this->getMonthlyComparatives();
        $monthly = $this->getMonthlyTotals($this->monthsRange);

        return view('livewire.admin.pagos.index', [
            'pagos' => $pagos,
            'stats' => $this->stats,
            'comparatives' => $comparatives,
            'monthlySeries' => $monthly
        ])
            ->layout($this->getLayout());
    }
    
    public function thermalPrint($pagoId)
    {
        $pago = Pago::with(['empresa','sucursal'])->findOrFail($pagoId);
        if (!ThermalPrinterService::isAvailable()) {
            session()->flash('error', 'Impresora no disponible. Verifique conexión.');
            return;
        }
        $result = ThermalPrinterService::printPayment($pago);
        if ($result['success']) {
            session()->flash('message', 'Ticket enviado a la impresora.');
        } else {
            session()->flash('error', 'Error de impresión: ' . $result['message']);
        }
    }

    public function thermalPrintSelected($pagoId)
    {
        $pago = Pago::with(['empresa','sucursal'])->findOrFail($pagoId);
        $printer = $this->selectedPrinter ?: config('printing.default_printer');
        if (!ThermalPrinterService::isAvailable($printer)) {
            session()->flash('error', 'Impresora no disponible. Verifique conexión.');
            return;
        }
        $result = ThermalPrinterService::printPayment($pago, ['printer' => $printer]);
        if ($result['success']) {
            session()->flash('message', 'Ticket enviado a la impresora.');
        } else {
            session()->flash('error', 'Error de impresión: ' . $result['message']);
        }
    }

    public function openTicketPreview($pagoId)
    {
        $this->previewPagoId = $pagoId;
        $this->showTicketPreview = true;
        $this->printers = ThermalPrinterService::listPrinters();
        $this->selectedPrinter = $this->printers[0] ?? config('printing.default_printer');
    }

    public function ticketView(Pago $pago)
    {
        $pago->load(['empresa', 'sucursal', 'cliente', 'detalles.planPago.contrato', 'user']);

        $merchant = [
            'name' => $pago->empresa->razon_social ?? 'Comercio',
            'rif' => $pago->empresa->documento ?? '',
            'phone' => $pago->empresa->telefono ?? '',
            'branch' => $pago->sucursal->nombre ?? '',
            'address' => $pago->sucursal->direccion ?? '',
            'branch_phone' => $pago->sucursal->telefono ?? '',
        ];

        $cliente = $pago->cliente;
        $customer = [
            'name' => $cliente->nombre_completo ?? 'N/A',
            'document' => ($cliente->tipo_documento ?? 'CI') . ': ' . ($cliente->documento ?? 'N/A'),
            'phone' => $cliente->telefono ?? '',
        ];

        $tasaCambio = $pago->tasa_cambio;
        if (!$tasaCambio) {
            $rate = ExchangeRate::whereDate('date', $pago->fecha ?? $pago->created_at)->first();
            $tasaCambio = $rate->usd_rate ?? null;
        }

        $transaction = $pago->numero_completo ?? ($pago->serie . '-' . str_pad($pago->numero ?? 0, 8, '0', STR_PAD_LEFT));

        $payment = [
            'transaction' => $transaction,
            'type' => ucfirst($pago->tipo_pago ?? 'recibo'),
            'date' => optional($pago->fecha)->format('d/m/Y'),
            'time' => optional($pago->created_at)->format('H:i'),
            'method' => ucfirst(str_replace('_', ' ', $pago->metodo_pago ?? 'N/A')),
            'reference' => $pago->referencia ?? '',
            'is_mixed' => $pago->es_pago_mixto,
            'mixed_details' => $pago->detalles_pago_mixto ?? [],
            'cashier' => $pago->user->name ?? '',
            'status' => ucfirst($pago->estado ?? ''),
        ];

        $details = $pago->detalles->map(function ($d) {
            return [
                'description' => $d->descripcion,
                'qty' => $d->cantidad,
                'price' => $d->precio_unitario,
                'subtotal' => $d->subtotal,
            ];
        })->toArray();

        $totals = [
            'subtotal_usd' => round($pago->subtotal, 2),
            'discount_usd' => round($pago->descuento, 2),
            'total_usd' => round($pago->total, 2),
            'exchange_rate' => $tasaCambio ? round($tasaCambio, 4) : null,
            'subtotal_bs' => $tasaCambio ? round($pago->subtotal * $tasaCambio, 2) : null,
            'discount_bs' => $tasaCambio ? round($pago->descuento * $tasaCambio, 2) : null,
            'total_bs' => $tasaCambio ? round($pago->total * $tasaCambio, 2) : ($pago->total_bolivares ? round($pago->total_bolivares, 2) : null),
        ];

        // Next installment: try via pago_detalles → plan_pago → contrato, fallback via cliente → contratos
        $nextInstallment = null;
        $contrato = $pago->detalles->first(fn($d) => $d->plan_pago_id)?->planPago?->contrato;
        if (!$contrato && $cliente) {
            $contrato = \App\Models\Contrato::where('cliente_id', $cliente->id)
                ->whereIn('estado', ['activo', 'mora'])
                ->latest()
                ->first();
        }
        if ($contrato) {
            $next = \App\Models\PlanPago::where('contrato_id', $contrato->id)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('numero_cuota')
                ->first();
            if ($next) {
                $nextInstallment = [
                    'number' => $next->numero_cuota,
                    'date' => optional($next->fecha_vencimiento)->format('d/m/Y'),
                    'amount_usd' => round($next->monto_total, 2),
                    'amount_bs' => $tasaCambio ? round($next->monto_total * $tasaCambio, 2) : null,
                    'pending' => round($next->saldo_pendiente, 2),
                ];
            }
        }

        // QR code
        $qrBase64 = null;
        try {
            $qrData = url('/verificar-pago/' . $pago->id . '?tx=' . urlencode($transaction));
            if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(150)->generate($qrData);
                $qrBase64 = base64_encode($qrPng);
            } else {
                $qrBase64 = @base64_encode(@file_get_contents('https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($qrData)));
            }
        } catch (\Exception $e) {
            $qrBase64 = null;
        }

        return response()->view('livewire.admin.pagos.ticket', compact(
            'merchant', 'customer', 'payment', 'details', 'totals', 'nextInstallment', 'qrBase64'
        ));
    }

    /**
     * Ticket alternativo optimizado para impresoras térmicas 58mm
     */
    public function ticketThermalView(Pago $pago)
    {
        // Reutiliza la misma preparación de datos que ticketView
        $pago->load(['empresa', 'sucursal', 'cliente', 'detalles.planPago.contrato', 'user']);

        $merchant = [
            'name' => $pago->empresa->razon_social ?? 'Comercio',
            'rif' => $pago->empresa->documento ?? '',
            'phone' => $pago->empresa->telefono ?? '',
            'branch' => $pago->sucursal->nombre ?? '',
            'address' => $pago->sucursal->direccion ?? '',
            'branch_phone' => $pago->sucursal->telefono ?? '',
        ];

        $cliente = $pago->cliente;
        $customer = [
            'name' => $cliente->nombre_completo ?? 'N/A',
            'document' => ($cliente->tipo_documento ?? 'CI') . ': ' . ($cliente->documento ?? 'N/A'),
            'phone' => $cliente->telefono ?? '',
        ];

        $tasaCambio = $pago->tasa_cambio;
        if (!$tasaCambio) {
            $rate = ExchangeRate::whereDate('date', $pago->fecha ?? $pago->created_at)->first();
            $tasaCambio = $rate->usd_rate ?? null;
        }

        $transaction = $pago->numero_completo ?? ($pago->serie . '-' . str_pad($pago->numero ?? 0, 8, '0', STR_PAD_LEFT));

        $payment = [
            'transaction' => $transaction,
            'type' => ucfirst($pago->tipo_pago ?? 'recibo'),
            'date' => optional($pago->fecha)->format('d/m/Y'),
            'time' => optional($pago->created_at)->format('H:i'),
            'method' => ucfirst(str_replace('_', ' ', $pago->metodo_pago ?? 'N/A')),
            'reference' => $pago->referencia ?? '',
            'is_mixed' => $pago->es_pago_mixto,
            'mixed_details' => $pago->detalles_pago_mixto ?? [],
            'cashier' => $pago->user->name ?? '',
            'status' => ucfirst($pago->estado ?? ''),
        ];

        $details = $pago->detalles->map(function ($d) {
            return [
                'description' => $d->descripcion,
                'qty' => $d->cantidad,
                'price' => $d->precio_unitario,
                'subtotal' => $d->subtotal,
            ];
        })->toArray();

        $totals = [
            'subtotal_usd' => round($pago->subtotal, 2),
            'discount_usd' => round($pago->descuento, 2),
            'total_usd' => round($pago->total, 2),
            'exchange_rate' => $tasaCambio ? round($tasaCambio, 4) : null,
            'subtotal_bs' => $tasaCambio ? round($pago->subtotal * $tasaCambio, 2) : null,
            'discount_bs' => $tasaCambio ? round($pago->descuento * $tasaCambio, 2) : null,
            'total_bs' => $tasaCambio ? round($pago->total * $tasaCambio, 2) : ($pago->total_bolivares ? round($pago->total_bolivares, 2) : null),
        ];

        $nextInstallment = null;
        $contrato = $pago->detalles->first(fn($d) => $d->plan_pago_id)?->planPago?->contrato;
        if (!$contrato && $cliente) {
            $contrato = \App\Models\Contrato::where('cliente_id', $cliente->id)
                ->whereIn('estado', ['activo', 'mora'])
                ->latest()
                ->first();
        }
        if ($contrato) {
            $next = \App\Models\PlanPago::where('contrato_id', $contrato->id)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('numero_cuota')
                ->first();
            if ($next) {
                $nextInstallment = [
                    'number' => $next->numero_cuota,
                    'date' => optional($next->fecha_vencimiento)->format('d/m/Y'),
                    'amount_usd' => round($next->monto_total, 2),
                    'amount_bs' => $tasaCambio ? round($next->monto_total * $tasaCambio, 2) : null,
                    'pending' => round($next->saldo_pendiente, 2),
                ];
            }
        }

        $qrBase64 = null;
        try {
            $qrData = url('/verificar-pago/' . $pago->id . '?tx=' . urlencode($transaction));
            if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
                $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(150)->generate($qrData);
                $qrBase64 = base64_encode($qrPng);
            } else {
                $qrBase64 = @base64_encode(@file_get_contents('https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($qrData)));
            }
        } catch (\Exception $e) {
            $qrBase64 = null;
        }

        $contractNumber = $contrato->numero_contrato ?? null;

        return response()->view('livewire.admin.pagos.ticket-thermal', compact(
            'merchant', 'customer', 'payment', 'details', 'totals', 'nextInstallment', 'qrBase64', 'contractNumber'
        ));
    }

    /**
     * Totales del mes actual vs mes anterior: ingresos y cantidad de pagos aprobados
     */
    private function getMonthlyComparatives(): array
    {
        $now = now();
        $startCurrent = $now->copy()->startOfMonth();
        $endCurrent = $now->copy()->endOfMonth();
        $startPrev = $now->copy()->subMonth()->startOfMonth();
        $endPrev = $now->copy()->subMonth()->endOfMonth();

        $baseQuery = Pago::query();
        if (auth()->check() && !auth()->user()->hasRole('Super Administrador')) {
            $baseQuery = Pago::withoutGlobalScope('multitenancy')
                ->where(function($query) {
                    if (auth()->user()->empresa_id) {
                        $query->where('pagos.empresa_id', auth()->user()->empresa_id);
                    }
                    if (auth()->user()->sucursal_id) {
                        $query->where('pagos.sucursal_id', auth()->user()->sucursal_id);
                    }
                });
        }

        $currentApproved = (clone $baseQuery)
            ->where('estado', 'aprobado')
            ->whereBetween('fecha', [$startCurrent, $endCurrent]);
        $prevApproved = (clone $baseQuery)
            ->where('estado', 'aprobado')
            ->whereBetween('fecha', [$startPrev, $endPrev]);

        $currentAmount = (clone $currentApproved)->sum('total') ?: 0;
        $prevAmount = (clone $prevApproved)->sum('total') ?: 0;
        $currentCount = (clone $currentApproved)->count();
        $prevCount = (clone $prevApproved)->count();

        return [
            'amount' => [
                'current' => $currentAmount,
                'previous' => $prevAmount,
                'delta' => $currentAmount - $prevAmount,
                'deltaPercent' => $prevAmount > 0 ? (($currentAmount - $prevAmount) / $prevAmount) * 100 : null
            ],
            'count' => [
                'current' => $currentCount,
                'previous' => $prevCount,
                'delta' => $currentCount - $prevCount,
                'deltaPercent' => $prevCount > 0 ? (($currentCount - $prevCount) / $prevCount) * 100 : null
            ]
        ];
    }

    /**
     * Serie de ingresos por mes (últimos 6 meses)
     */
    private function getMonthlyTotalsLastSix(): array
    {
        return $this->getMonthlyTotals(6);
    }

    public function getMonthlyTotals(int $months): array
    {
        $months = max(1, min(12, $months));
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->copy()->subMonths($i);
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();
            $query = Pago::query();
            if (auth()->check() && !auth()->user()->hasRole('Super Administrador')) {
                $query = Pago::withoutGlobalScope('multitenancy')
                    ->where(function($q) {
                        if (auth()->user()->empresa_id) {
                            $q->where('pagos.empresa_id', auth()->user()->empresa_id);
                        }
                        if (auth()->user()->sucursal_id) {
                            $q->where('pagos.sucursal_id', auth()->user()->sucursal_id);
                        }
                    });
            }
            $total = $query->where('estado', 'aprobado')->whereBetween('fecha', [$start, $end])->sum('total') ?: 0;
            $series[] = [
                'label' => $month->format('M'),
                'value' => $total
            ];
        }
        return $series;
    }

    public function setMonthsRange($months)
    {
        $this->monthsRange = (int)$months;
    }

    public function printReceipt(Pago $pago)
    {
        $this->previewPagoId = $pago->id;
        $this->showPreview = true;
    }

    public function downloadReceipt(Pago $pago)
    {
        $pdf = new Fpdf('P', 'mm', 'Letter');
        $pdf->AddPage();

        // Configurar fuentes
        $pdf->SetFont('Arial', 'B', 16);

        // Mitad de la página (para el recibo original y copia)
        $pageHeight = 279.4; // Altura de carta en mm
        $halfPage = $pageHeight / 2;

        // Generar recibo original en la mitad superior
        $this->generateReceiptContent($pdf, $pago, 'ORIGINAL', 5);

        // Generar copia en la mitad inferior
        $this->generateReceiptContent($pdf, $pago, 'COPIA', $halfPage + 7);

        // Mostrar PDF en el navegador en lugar de descargarlo
        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recibo_pago_' . $pago->numero_completo . '.pdf"'
        ]);
    }

    public function generateReceiptContent(Fpdf $pdf, Pago $pago, $tipo, $yPosition)
    {
        // Establecer posición Y inicial
        $pdf->SetY($yPosition);

        // Encabezado con tipo de recibo
        $pdf->SetFont('Arial', 'B', 16);
        //$pdf->Cell(0, 8, 'RECIBO DE PAGO - ' . $tipo, 0, 1, 'C');

        // Línea divisoria
        //$pdf->Line(10, $pdf->GetY() + 2, 200, $pdf->GetY() + 2);
        $pdf->Ln(22);

        // Obtener tasa de cambio
        $exchangeRate = ExchangeRate::whereDate('created_at', $pago->created_at)->first();
        //dd($exchangeRate);

        // Información del pago (alineada a la izquierda)
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, 'Nro. Recibo:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        // Extraer solo el número después del guión
        $numeroRecibo = explode('-', $pago->numero_completo);
        $numeroMostrar = isset($numeroRecibo[1]) ? $numeroRecibo[1] : $pago->numero_completo;
        $pdf->Cell(0, 5, $numeroMostrar, 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, 'Fecha de pago:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, $pago->fecha->format('d/m/Y'), 0, 1, 'L');

    


        // Información del cliente
        $cliente = $pago->cliente;
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, 'Cliente:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, utf8_decode($cliente->nombre_completo ?? 'N/A'), 0, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, 'Documento:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, utf8_decode($cliente->documento ?? 'N/A'), 0, 1, 'L');
       
        


        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, 'Fecha de emision:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 5, $pago->created_at->format('d/m/Y'), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 5, utf8_decode('Método de pago:'), 0, 0, 'L');
        
        // Para pagos mixtos, mostrar el método con los detalles en la misma línea
         if (strtolower($pago->metodo_pago ?? '') === 'pago mixto' && !empty($pago->detalles_pago_mixto)) {
            $pdf->SetFont('Arial', 'B', 8);
            $detalles = [];
            foreach ($pago->detalles_pago_mixto as $detalleMixto) {
                $metodo = ucfirst(str_replace('_', ' ', $detalleMixto['metodo'] ?? ''));
                $monto = $detalleMixto['monto'] ?? 0;
                $referencia = $detalleMixto['referencia'] ?? '';
                $detalles[] = "$metodo: " . number_format($monto, 2, ',', '.') . ($referencia ? " - Ref: $referencia" : "");
            }
            $pdf->Cell(0, 5, strtoupper($pago->metodo_pago) . ' (' . implode(' ', $detalles) . ')', 0, 1, 'L');
        } else {
            // Para métodos de pago normales, mostrar referencia en la misma línea si existe
            $metodoPagoTexto = strtoupper($pago->metodo_pago ?? 'N/A');
            if (in_array($pago->metodo_pago, ['transferencia', 'pago movil', 'punto de venta']) && !empty($pago->referencia)) {
                $metodoPagoTexto .= ' - Ref: ' . $pago->referencia;
            }
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(0, 5, $metodoPagoTexto, 0, 1, 'L');
        }

        

        // Detalles del pago
        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(145, 5, 'Concepto', 1, 0, 'C');
        $pdf->Cell(25, 5, 'Cantidad', 1, 0, 'C');
        $pdf->Cell(25, 5, 'Monto', 1, 1, 'C');

        $pdf->SetFont('Arial', '', 7);
        foreach ($pago->detalles as $detalle) {
            $pdf->Cell(145, 5, substr($detalle->descripcion, 0, 50), 1, 0);
            $pdf->Cell(25, 5, number_format($detalle->cantidad, 2, ',', '.'), 1, 0, 'R');

            // Convertir monto a bolívares si hay tasa de cambio
            $monto = $detalle->precio_unitario * $detalle->cantidad;
            $pdf->Cell(25, 5, '$' . number_format($monto, 2, ',', '.'), 1, 1, 'R');
            $pdf->Ln();
        }

        // Totales
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(170, 5, 'Subtotal:', 1, 0, 'R');
        if ($exchangeRate) {
            $subtotalBs = $pago->subtotal * $exchangeRate->usd_rate;
            $pdf->Cell(25, 5, 'Bs. ' . number_format($subtotalBs, 2, ',', '.'), 1, 1, 'R');
        } else {
            $pdf->Cell(25, 5, '$' . number_format($pago->subtotal, 2, ',', '.'), 1, 1, 'R');
        }

        if ($pago->descuento > 0) {
            $pdf->Cell(170, 5, 'Descuento:', 1, 0, 'R');
            if ($exchangeRate) {
                $descuentoBs = $pago->descuento * $exchangeRate->usd_rate;
                $pdf->Cell(25, 5, 'Bs. ' . number_format($descuentoBs, 2, ',', '.'), 1, 1, 'R');
            } else {
                $pdf->Cell(25, 5, '$' . number_format($pago->descuento, 2, ',', '.'), 1, 1, 'R');
            }
        }

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(170, 5, 'Total:', 1, 0, 'R');
        if ($exchangeRate) {
            $totalBs = $pago->total * $exchangeRate->usd_rate;
            $pdf->Cell(25, 5, 'Bs. ' . number_format($totalBs, 2, ',', '.'), 1, 1, 'R');
        } else {
            $pdf->Cell(25, 5, '$' . number_format($pago->total, 2, ',', '.'), 1, 1, 'R');
        }

        // Firma
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(90, 5, '', 0, 0); // Espacio en blanco
        $pdf->Cell(80, 5, '__________________________', 0, 1, 'C');
        $pdf->Cell(90, 5, '', 0, 0); // Espacio en blanco
        $pdf->Cell(80, 5, 'Firma y Sello', 0, 1, 'C');
    }


     public function enviarReciboWhatsApp(Pago $pago)
    {
        $cliente = $pago->cliente;
        
        if (!$cliente || !$cliente->telefono) {
            return false;
        }

        $numero = $this->formatPhoneNumber($cliente->telefono);
        $mensaje = $this->generarMensajeWhatsApp($pago);

        // Usar WhatsAppService en lugar de Http directo para mayor robustez y compatibilidad
        try {
            $whatsappService = new \App\Services\WhatsAppService(auth()->user()->empresa_id);
            $response = $whatsappService->sendMessage($numero, $mensaje, true);

            if ($response && ($response['success'] ?? false)) {
                return true;
            }

            \Log::error('Error WhatsApp Service Response: ' . json_encode($response));
            return false;
        } catch (\Exception $e) {
             \Log::error('Error enviando WhatsApp (Exception): ' . $e->getMessage());
             return false;
        }
    }


    private function generarMensajeWhatsApp($pago)
    {
        $cliente = $pago->cliente;
        $totalFormateado = '$' . number_format($pago->total, 2, ',', '.');

        $mensaje = "💳 *Pago Recibido - Inversiones Danger 3000 C.A*\n\n";
        $mensaje .= "Estimado/a *{$cliente->nombre_completo}*,\n\n";
        $mensaje .= "Hemos recibido su pago correctamente.\n\n";

        $mensaje .= "📄 *Detalles del Pago:*\n";
        $mensaje .= "• Número de Recibo: *{$pago->numero_completo}*\n";
        $mensaje .= "• Fecha: {$pago->fecha->format('d/m/Y')}\n";
        $mensaje .= "• Método: " . ucfirst(str_replace('_', ' ', $pago->metodo_pago)) . "\n";
        if ($pago->referencia) {
            $mensaje .= "• Referencia: {$pago->referencia}\n";
        }

        $mensaje .= "\n📋 *Conceptos Pagados:*\n";
        foreach ($pago->detalles as $detalle) {
            $montoDetalle = '$' . number_format($detalle->precio_unitario * $detalle->cantidad, 2, ',', '.');
            $mensaje .= "• {$detalle->descripcion}: {$montoDetalle}\n";
        }

        $mensaje .= "\n💰 *Total Pagado: {$totalFormateado}*\n\n";
        $mensaje .= "Gracias por su pago puntual.\n\n";
        $mensaje .= "*Inversiones Danger 3000 C.A - Tu aliado en dos ruedas*";

        return $mensaje;
    }

    
    private function formatPhoneNumber($number)
    {
        $empresa = \DB::table('empresas')->where('id', 1)->first();
        $pais = $empresa ? \DB::table('pais')->where('id', $empresa->pais_id)->first() : null;
        $codigoPais = $pais ? $pais->codigo_telefonico : '58';

        $cleaned = preg_replace('/[^0-9]/', '', $number);

        if (strlen($cleaned) > 10 && str_starts_with($cleaned, $codigoPais)) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        return $codigoPais . $cleaned;
    }

    public function closePreview()
    {
        $this->showPreview = false;
        $this->previewPagoId = null;
    }
}
