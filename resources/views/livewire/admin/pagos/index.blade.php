<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary">Gestión de Pagos</h4>
                    <p class="mb-0">Control y registro de pagos de clientes</p>
                </div>
                @can('create pagos')
                <a href="{{ route('admin.pagos.create') }}" class="btn btn-primary">
                    <i class="ri ri-add-line me-1"></i> Nuevo Pago
                </a>
                @endcan
            </div>
        </div>
    </div>

    <!-- Cards estadísticas comparativas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card card-border-shadow-primary h-100" aria-label="Ingresos del mes">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Ingresos aprobados (mes)</h6>
                            <h4 class="mb-0">${{ number_format($comparatives['amount']['current'] ?? 0, 2) }}</h4>
                        </div>
                        @php 
                          $amountDelta = $comparatives['amount']['delta'] ?? 0;
                          $amountDeltaPercent = $comparatives['amount']['deltaPercent'];
                          $up = $amountDelta > 0;
                        @endphp
                        <span class="badge bg-{{ $up ? 'label-success' : 'label-danger' }}">
                            <i class="ri ri-arrow-{{ $up ? 'up' : 'down' }}-line"></i>
                            {{ $amountDeltaPercent !== null ? number_format($amountDeltaPercent, 1) . '%' : 'N/A' }}
                        </span>
                    </div>
                    <small class="text-muted">Vs mes pasado: ${{ number_format($comparatives['amount']['previous'] ?? 0, 2) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-border-shadow-info h-100" aria-label="Pagos aprobados del mes">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Pagos aprobados (mes)</h6>
                            <h4 class="mb-0">{{ number_format($comparatives['count']['current'] ?? 0) }}</h4>
                        </div>
                        @php 
                          $countDelta = $comparatives['count']['delta'] ?? 0;
                          $countDeltaPercent = $comparatives['count']['deltaPercent'];
                          $upc = $countDelta > 0;
                        @endphp
                        <span class="badge bg-{{ $upc ? 'label-success' : 'label-danger' }}">
                            <i class="ri ri-arrow-{{ $upc ? 'up' : 'down' }}-line"></i>
                            {{ $countDeltaPercent !== null ? number_format($countDeltaPercent, 1) . '%' : 'N/A' }}
                        </span>
                    </div>
                    <small class="text-muted">Vs mes pasado: {{ number_format($comparatives['count']['previous'] ?? 0) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-border-shadow-success h-100" aria-label="Total aprobados">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Aprobados</h6>
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="mb-0">{{ number_format($stats['aprobados'] ?? 0) }}</h4>
                        <i class="ri ri-checkbox-circle-line text-success" aria-hidden="true"></i>
                    </div>
                    <small class="text-muted">Pagos con estado aprobado</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-border-shadow-warning h-100" aria-label="Total pendientes">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Pendientes</h6>
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="mb-0">{{ number_format($stats['pendientes'] ?? 0) }}</h4>
                        <i class="ri ri-time-line text-warning" aria-hidden="true"></i>
                    </div>
                    <small class="text-muted">Pagos en estado pendiente</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista y filtros -->
    <div class="card">
        <!-- Filtros -->
        <div class="card-header border-bottom">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" placeholder="Cliente, CI, Referencia..."
                           wire:model.live.debounce.300ms="search">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" wire:model.live="status">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobado">Aprobado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Mostrar</label>
                    <select class="form-select" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="button" class="btn btn-label-secondary" wire:click="clearFilters">
                        <i class="ri ri-eraser-line"></i>
                    </button>
                    <button type="button" class="btn btn-label-success w-100" wire:click="export">
                        <i class="mdi mdi-file-excel me-1"></i> Exportar
                    </button>
                </div>
            </div>
        </div>

        <div class="card-datatable table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th wire:click="sort('numero')" style="cursor: pointer;">
                            Documento
                            @if($sortBy === 'numero')
                                <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </th>
                        <th>Cliente</th>
                        <th wire:click="sort('total')" style="cursor: pointer;">
                            Monto
                            @if($sortBy === 'total')
                                <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </th>
                        <th wire:click="sort('fecha')" style="cursor: pointer;">
                            Fecha
                            @if($sortBy === 'fecha')
                                <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </th>
                        <th wire:click="sort('estado')" style="cursor: pointer;">
                            Estado
                            @if($sortBy === 'estado')
                                <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                            @endif
                        </th>
                        <th>Método</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pagos as $pago)
                    <tr>
                        <td class="fw-bold">{{ $pago->numero ?? $pago->id }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-medium">{{ $pago->cliente->nombre_completo ?? 'Cliente Eliminado' }}</span>
                                <small class="text-muted">{{ $pago->cliente->documento ?? '' }}</small>
                            </div>
                        </td>
                        <td>${{ number_format($pago->total, 2) }}</td>
                        <td>{{ $pago->fecha->format('d/m/Y') }}</td>
                        <td>
                            @php
                                $badges = [
                                    'pendiente' => 'bg-label-warning',
                                    'aprobado' => 'bg-label-success',
                                    'cancelado' => 'bg-label-danger'
                                ];
                            @endphp
                            <span class="badge {{ $badges[$pago->estado] ?? 'bg-label-primary' }}">
                                {{ ucfirst($pago->estado) }}
                            </span>
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $pago->metodo_pago ?? '')) }}</td>
                        <td>
                            <div class="dropdown">
                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                    <i class="ri ri-more-2-line"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="{{ route('admin.pagos.show', $pago->id) }}">
                                        <i class="ri ri-eye-line me-1"></i> Ver Detalles
                                    </a>
                                    
                                    <button class="dropdown-item" wire:click="printReceipt({{ $pago->id }})">
                                        <i class="ri ri-printer-line me-1"></i> Imprimir Recibo
                                    </button>
                                    
                                    <a class="dropdown-item" href="{{ route('admin.pagos.ticket-thermal', $pago->id) }}" target="_blank">
                                        <i class="ri ri-printer-line me-1"></i> Imprimir Ticket Térmico
                                    </a>
                                    
                                    <button class="dropdown-item" wire:click="openTicketPreview({{ $pago->id }})">
                                        <i class="ri ri-eye-line me-1"></i> Vista previa Ticket Térmico
                                    </button>

                                    @can('edit pagos')
                                    <button class="dropdown-item" wire:click="toggleStatus({{ $pago->id }})">
                                        <i class="ri ri-checkbox-circle-line me-1"></i> 
                                        {{ $pago->estado === 'aprobado' ? 'Marcar Pendiente' : 'Aprobar Pago' }}
                                    </button>
                                    <button class="dropdown-item" wire:click="enviarReciboWhatsApp({{ $pago }})">
                                        <i class="ri ri-whatsapp-line me-1"></i> 
                                        Enviar Recibo por WhatsApp
                                    </button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="text-center">
                                <i class="ri ri-money-dollar-circle-line fs-1 text-muted"></i>
                                <p class="mt-2">No se encontraron pagos registrados.</p>
                                @can('create pagos')
                                <a href="{{ route('admin.pagos.create') }}" class="btn btn-sm btn-primary">
                                    Registrar Pago
                                </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $pagos->links('livewire.pagination') }}
        </div>
    </div>
    


    <!-- Gráficos comparativos -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Comparativa de ingresos (Mes anterior vs actual)</h6>
                </div>
                <div class="card-body">
                    <div id="paymentsComparativeChart" style="min-height: 260px;" aria-label="Comparativa de ingresos"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Distribución por estado</h6>
                </div>
                <div class="card-body">
                    <div id="paymentsStatusDonut" style="min-height: 260px;" aria-label="Distribución por estado de pagos"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Previsualización de Recibo -->
    @if($showPreview)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vista Previa del Recibo</h5>
                    <button type="button" class="btn-close" wire:click="closePreview"></button>
                </div>
                <div class="modal-body p-0" style="height: 500px;">
                    <iframe src="{{ route('admin.pagos.print', $previewPagoId) }}" 
                            width="100%" height="100%" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closePreview">Cerrar</button>
                    <a href="{{ route('admin.pagos.print', $previewPagoId) }}" target="_blank" class="btn btn-primary">
                        <i class="ri ri-download-line me-1"></i> Descargar PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    @if($showTicketPreview)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vista Previa Ticket Térmico</h5>
                    <button type="button" class="btn-close" wire:click="$set('showTicketPreview', false)"></button>
                </div>
                <div class="modal-body p-0" style="height: 500px;">
                    <iframe src="{{ route('admin.pagos.ticket-thermal', $previewPagoId) }}" 
                            width="100%" height="100%" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <small class="text-muted">Seleccione la impresora Roccia RC-5801 en el diálogo de impresión</small>
                    </div>
                    <button type="button" class="btn btn-secondary" wire:click="$set('showTicketPreview', false)">
                        Cerrar
                    </button>
                    <a href="{{ route('admin.pagos.ticket-thermal', $previewPagoId) }}" target="_blank" class="btn btn-primary">
                        <i class="ri ri-printer-line me-1"></i> Imprimir Ticket
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="{{ asset('materialize/assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<script>
function renderPaymentsCharts() {
  // Serie mensual (línea/área)
  const monthlyLabels = @json(collect($monthlySeries)->pluck('label'));
  const monthlyValues = @json(collect($monthlySeries)->pluck('value'));
  const monthlyOptions = {
    chart: { type: 'area', height: 260, toolbar: { show: false } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    xaxis: { categories: monthlyLabels },
    yaxis: { labels: { formatter: (val) => '$' + Number(val).toFixed(0) } },
    colors: ['#7367F0'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 0.3, opacityFrom: 0.6, opacityTo: 0.1 } },
    tooltip: { y: { formatter: (val) => '$' + Number(val).toFixed(2) } }
  };
  const chartMonthly = new ApexCharts(document.querySelector('#paymentsMonthlyChart'), monthlyOptions);
  chartMonthly.render();

  // Comparativa ingresos (barra)
  const amountCurrent = {{ $comparatives['amount']['current'] ?? 0 }};
  const amountPrevious = {{ $comparatives['amount']['previous'] ?? 0 }};
  const comparativeOptions = {
    chart: { type: 'bar', height: 260, toolbar: { show: false } },
    series: [{ name: 'Ingresos', data: [amountPrevious, amountCurrent] }],
    plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    xaxis: { categories: ['Mes anterior', 'Mes actual'] },
    yaxis: { labels: { formatter: (val) => '$' + Number(val).toFixed(0) } },
    colors: ['#00CFE8'],
    tooltip: { y: { formatter: (val) => '$' + Number(val).toFixed(2) } }
  };
  const chartComparative = new ApexCharts(document.querySelector('#paymentsComparativeChart'), comparativeOptions);
  chartComparative.render();

  // Donut por estado
  const total = {{ $stats['total'] ?? 0 }};
  const approved = {{ $stats['aprobados'] ?? 0 }};
  const pending = {{ $stats['pendientes'] ?? 0 }};
  const others = Math.max(0, total - approved - pending);
  const chartDonut = new ApexCharts(document.querySelector('#paymentsStatusDonut'), {
    chart: { type: 'donut', height: 260 },
    series: [approved, pending, others],
    labels: ['Aprobados', 'Pendientes', 'Otros'],
    colors: ['#28C76F', '#FF9F43', '#EA5455'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val) => val.toFixed(1) + '%' },
    tooltip: { y: { formatter: (val) => Number(val).toFixed(0) } }
  });
  chartDonut.render();
}
document.addEventListener('DOMContentLoaded', renderPaymentsCharts);
document.addEventListener('livewire:init', () => {
  Livewire.hook('message.processed', (message, component) => {
    const ids = ['#paymentsMonthlyChart', '#paymentsComparativeChart', '#paymentsStatusDonut'];
    ids.forEach(id => {
      const el = document.querySelector(id);
      if (el) el.innerHTML = '';
    });
    renderPaymentsCharts();
  });
});
</script>
@endpush
