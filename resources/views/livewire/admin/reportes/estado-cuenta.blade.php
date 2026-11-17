<div>
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri ri-check-line me-2"></i>{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">Estado de Cuenta por Cliente</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" wire:model.live="cliente_id">
                        <option value="">Seleccione...</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }} {{ $c->apellido }} ({{ $c->documento }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" wire:model.lazy="dateFrom">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" wire:model.lazy="dateTo">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" wire:model.live="estado">
                        <option value="">Todos</option>
                        <option value="al_dia">Al Día</option>
                        <option value="moroso">Moroso</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="col-md-12 d-flex gap-2 flex-wrap align-items-center">
                    <button class="btn btn-primary" wire:click="search">
                        <i class="ri ri-search-line me-1"></i> Consultar
                    </button>
                    <button class="btn btn-label-success" wire:click="exportExcel">
                        <i class="mdi mdi-file-excel me-1"></i> Exportar Excel
                        @if($selected_contrato_id)
                            @php $cSel = collect($result['contratos'])->firstWhere('id', $selected_contrato_id); @endphp
                            <span class="badge bg-success text-white ms-1">Contrato #{{ $cSel['numero_contrato'] ?? $selected_contrato_id }}</span>
                        @endif
                    </button>
                    <button class="btn btn-label-danger" wire:click="exportPdf">
                        <i class="ri ri-file-pdf-2-line me-1"></i> Exportar PDF
                        @if($selected_contrato_id)
                            @php $cSel = collect($result['contratos'])->firstWhere('id', $selected_contrato_id); @endphp
                            <span class="badge bg-danger text-white ms-1">Contrato #{{ $cSel['numero_contrato'] ?? $selected_contrato_id }}</span>
                        @endif
                    </button>
                    <button class="btn btn-label-info" wire:click="sendReminders">
                        <i class="ri ri-notification-line me-1"></i> Enviar Recordatorios
                    </button>

                    @if($selected_contrato_id)
                        @php $cSel = collect($result['contratos'])->firstWhere('id', $selected_contrato_id); @endphp
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" wire:click="selectContrato({{ $selected_contrato_id }})">
                            <i class="ri ri-close-circle-line me-1"></i> Quitar filtro de Contrato (Ver Todos)
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">${{ number_format($result['resumen']['total_pagado'] ?? 0, 2) }}</h5>
                            <small class="text-muted">Total Pagado</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ri ri-bank-card-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">${{ number_format($result['resumen']['pendiente'] ?? 0, 2) }}</h5>
                            <small class="text-muted">Saldo Pendiente</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ri ri-time-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">{{ $result['resumen']['proximo_vencimiento'] ?? 'N/A' }}</h5>
                            <small class="text-muted">Próximo Vencimiento</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="ri ri-calendar-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    @php
                        $estado = $result['resumen']['estado'] ?? 'pendiente';
                        $labelClass = $estado === 'moroso' ? 'danger' : ($estado === 'pendiente' ? 'warning' : 'success');
                        $texto = $estado === 'moroso' ? 'Moroso' : ($estado === 'pendiente' ? 'Pendiente' : 'Al Día');
                    @endphp
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><span class="badge bg-label-{{ $labelClass }}">{{ $texto }}</span></h5>
                            <small class="text-muted">Estado del Cliente</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-{{ $labelClass }}">
                                <i class="ri ri-information-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0 d-inline-block">Contratos</h6>
                @if($selected_contrato_id)
                    @php $cSel = collect($result['contratos'])->firstWhere('id', $selected_contrato_id); @endphp
                    <span class="badge bg-primary ms-2">
                        <i class="ri ri-filter-line me-1"></i> Mostrando solo Contrato #{{ $cSel['numero_contrato'] ?? $selected_contrato_id }}
                    </span>
                @else
                    <span class="text-muted small ms-2">(Haga clic en un contrato para filtrar el estado de cuenta y exportarlo individualmente)</span>
                @endif
            </div>
            @if($selected_contrato_id)
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="selectContrato({{ $selected_contrato_id }})">
                    <i class="ri ri-refresh-line me-1"></i> Ver todos los contratos
                </button>
            @endif
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>N° Contrato</th>
                            <th>Unidad</th>
                            <th>Inicio</th>
                            <th>Estado</th>
                            <th class="text-center" style="width: 140px;">Selección</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($result['contratos'] as $c)
                            @php $isSelected = ($selected_contrato_id == $c['id']); @endphp
                            <tr wire:click="selectContrato({{ $c['id'] }})"
                                style="cursor: pointer;"
                                class="{{ $isSelected ? 'table-primary border-primary fw-medium' : '' }}">
                                <td>
                                    @if($isSelected)
                                        <i class="ri ri-radio-button-fill text-primary me-1 fs-5 align-middle"></i>
                                    @else
                                        <i class="ri ri-checkbox-blank-circle-line text-muted me-1 fs-5 align-middle"></i>
                                    @endif
                                    <strong>{{ $c['numero_contrato'] ?? $c['id'] }}</strong>
                                </td>
                                <td>{{ $c['unidad']['moto']['marca'] ?? '' }} {{ $c['unidad']['moto']['modelo'] ?? '' }} ({{ $c['unidad']['moto']['anio'] ?? '' }})</td>
                                <td>{{ \Carbon\Carbon::parse($c['created_at'])->format('d/m/Y') }}</td>
                                <td><span class="badge bg-label-{{ ($c['estado'] ?? '') === 'mora' ? 'danger' : 'primary' }}">{{ ucfirst($c['estado'] ?? 'borrador') }}</span></td>
                                <td class="text-center" onclick="event.stopPropagation();">
                                    @if($isSelected)
                                        <button type="button" class="btn btn-xs btn-primary px-3 shadow-sm" wire:click="selectContrato({{ $c['id'] }})">
                                            <i class="ri ri-check-line me-1"></i> Seleccionado
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-primary px-3" wire:click="selectContrato({{ $c['id'] }})">
                                            Filtrar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center">Sin contratos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Cuotas Pagadas</h6>
                    @if($selected_contrato_id)
                        @php $cSel = collect($result['contratos'])->firstWhere('id', $selected_contrato_id); @endphp
                        <span class="badge bg-label-primary">Contrato #{{ $cSel['numero_contrato'] ?? $selected_contrato_id }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Contrato</th>
                                    <th>Descripción</th>
                                    <th>Fecha Pago</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($result['cuotas_pagadas'] as $c)
                                <tr>
                                    <td>#{{ $c['numero_contrato'] ?? $c['contrato_id'] }}</td>
                                    <td>{{ $c['descripcion'] }}</td>
                                    <td>{{ $c['fecha_pago'] }}</td>
                                    <td class="text-end">${{ number_format($c['monto'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted text-center">Sin cuotas pagadas</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Cuotas Pendientes</h6>
                    @if($selected_contrato_id)
                        @php $cSel = collect($result['contratos'])->firstWhere('id', $selected_contrato_id); @endphp
                        <span class="badge bg-label-primary">Contrato #{{ $cSel['numero_contrato'] ?? $selected_contrato_id }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Contrato</th>
                                    <th># Cuota</th>
                                    <th>Vencimiento</th>
                                    <th>Estado</th>
                                    <th class="text-end">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($result['cuotas_pendientes'] as $c)
                                <tr>
                                    <td>#{{ $c['numero_contrato'] ?? $c['contrato_id'] }}</td>
                                    <td>{{ $c['numero'] }}</td>
                                    <td>{{ $c['vencimiento'] }}</td>
                                    <td><span class="badge bg-label-{{ $c['estado'] === 'vencido' ? 'danger' : ($c['estado'] === 'parcial' ? 'warning' : 'secondary') }}">{{ ucfirst($c['estado']) }}</span></td>
                                    <td class="text-end">${{ number_format($c['saldo'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-muted text-center">Sin cuotas pendientes</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">Tendencia de Pagos</h6>
        </div>
        <div class="card-body">
            <div id="paymentsTrendChart" style="min-height: 260px;"></div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('materialize/assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('trendUpdated', (data) => {
            const labels = data.labels || [];
            const values = data.values || [];
            const el = document.querySelector('#paymentsTrendChart');
            if (el) el.innerHTML = '';
            const chart = new ApexCharts(document.querySelector('#paymentsTrendChart'), {
                chart: { type: 'line', height: 260, toolbar: { show: false } },
                series: [{ name: 'Pagos', data: values }],
                xaxis: { categories: labels },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                colors: ['#7367F0'],
                tooltip: { y: { formatter: val => '$' + Number(val).toFixed(2) } }
            });
            chart.render();
        });
    });
    </script>
    @endpush
</div>
