<div wire:ignore id="dashboardApp">
            {{-- Alerta dinámica AJAX --}}
            <div id="dashboardAjaxAlert" class="alert d-none alert-dismissible fade show mb-3" role="alert">
                <span id="dashboardAjaxAlertText"></span>
                <button type="button" class="btn-close" aria-label="Cerrar" onclick="document.getElementById('dashboardAjaxAlert').classList.add('d-none')"></button>
            </div>

            {{-- Encabezado --}}
            <div class="row mb-3">
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="mb-0">Panel de Control</h4>
                            <span id="dashboardLoadingBadge" class="badge bg-label-primary d-none">
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Actualizando...
                            </span>
                        </div>
                        <small class="text-muted">
                            Resumen ejecutivo, cartera y alertas en tiempo real
                            <span class="ms-2 text-muted">• Actualizado: <strong id="dashboardUpdatedAt">{{ $payload['updatedAt'] ?? now()->format('d/m/Y H:i:s') }}</strong></span>
                        </small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-label-primary" id="btnRefreshDashboard">
                            <i class="ri ri-refresh-line me-1"></i> Actualizar
                        </button>
                        <a href="{{ route('admin.dashboard.export-excel') }}" class="btn btn-label-success" id="btnExportExcel">
                            <i class="mdi mdi-file-excel me-1"></i> Exportar Alertas
                        </a>
                        <a href="{{ route('admin.dashboard.export-pdf') }}" class="btn btn-label-danger" id="btnExportPdf">
                            <i class="ri ri-file-pdf-2-line me-1"></i> Exportar PDF
                        </a>
                    </div>
                </div>
            </div>

            {{-- Barra de Filtros --}}
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <h6 class="mb-0"><i class="ri ri-filter-3-line me-1"></i> Filtros Dinámicos</h6>
                    <div class="d-flex flex-wrap gap-1" id="quickDateButtons">
                        <button type="button" class="btn btn-xs btn-outline-primary quick-range-btn" data-range="today">Hoy</button>
                        <button type="button" class="btn btn-xs btn-outline-primary quick-range-btn" data-range="next7">Próximos 7 días</button>
                        <button type="button" class="btn btn-xs btn-outline-primary quick-range-btn" data-range="month">Este Mes</button>
                        <button type="button" class="btn btn-xs btn-outline-primary quick-range-btn" data-range="year">Este Año</button>
                        <button type="button" class="btn btn-xs btn-label-secondary quick-range-btn" data-range="clear">
                            <i class="ri ri-close-line me-1"></i> Limpiar
                        </button>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="row g-3">
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="filterDateFrom">Desde</label>
                            <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="{{ $payload['filters']['dateFrom'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="filterDateTo">Hasta</label>
                            <input type="date" id="filterDateTo" class="form-control form-control-sm" value="{{ $payload['filters']['dateTo'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="filterMinAmount">Monto mínimo ($)</label>
                            <input type="number" step="0.01" id="filterMinAmount" class="form-control form-control-sm" placeholder="0.00" value="{{ $payload['filters']['minAmount'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="filterMaxAmount">Monto máximo ($)</label>
                            <input type="number" step="0.01" id="filterMaxAmount" class="form-control form-control-sm" placeholder="0.00" value="{{ $payload['filters']['maxAmount'] ?? '' }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="filterAnticipationDays">Anticipación (días)</label>
                            <input type="number" id="filterAnticipationDays" class="form-control form-control-sm" min="1" max="365" value="{{ $payload['filters']['anticipationDays'] ?? 7 }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1" for="filterSearchClient">Buscar cliente / contrato</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="ri ri-search-line"></i></span>
                                <input type="text" id="filterSearchClient" class="form-control" placeholder="Nombre, cédula, #..." value="{{ $payload['filters']['searchClient'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI Cards --}}
            <div class="row g-3 mb-3">
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1" id="kpiClientesTotal">{{ $payload['metrics']['clientes']['total'] ?? 0 }}</h4>
                                    <small class="text-muted">Clientes Totales</small>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-primary">
                                        <i class="ri ri-team-line ri-24px"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-3">
                                <span class="badge bg-label-success">Activos <span id="kpiClientesActivos">{{ $payload['metrics']['clientes']['activos'] ?? 0 }}</span></span>
                                <span class="badge bg-label-secondary">Inactivos <span id="kpiClientesInactivos">{{ $payload['metrics']['clientes']['inactivos'] ?? 0 }}</span></span>
                                <span class="badge bg-label-danger">Con Mora <span id="kpiClientesMora">{{ $payload['metrics']['clientes']['con_mora'] ?? 0 }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1" id="kpiContratosVigentes">{{ $payload['metrics']['contratos']['vigentes'] ?? 0 }}</h4>
                                    <small class="text-muted">Contratos Vigentes</small>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-info">
                                        <i class="ri ri-file-paper-line ri-24px"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-3">
                                <span class="badge bg-label-success">Al día <span id="kpiContratosAlDia">{{ $payload['metrics']['contratos']['al_dia'] ?? 0 }}</span></span>
                                <span class="badge bg-label-danger">En mora <span id="kpiContratosVencidos">{{ $payload['metrics']['contratos']['vencidos'] ?? 0 }}</span></span>
                                <span class="badge bg-label-primary">Completados <span id="kpiContratosCompletados">{{ $payload['metrics']['contratos']['completados'] ?? 0 }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1" id="kpiPagosRecibidos">{{ $payload['metrics']['pagos']['recibidos'] ?? 0 }}</h4>
                                    <small class="text-muted">Pagos Recibidos (Filtro)</small>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-success">
                                        <i class="ri ri-money-dollar-circle-line ri-24px"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-3">
                                <span class="badge bg-label-warning">Pendientes <span id="kpiPagosPendientes">{{ $payload['metrics']['pagos']['pendientes'] ?? 0 }}</span></span>
                                <span class="badge bg-label-danger">Cuotas Vencidas <span id="kpiPagosMorosos">{{ $payload['metrics']['pagos']['morosos'] ?? 0 }}</span></span>
                            </div>
                            <small class="text-danger d-block mt-1 fw-medium">Saldo vencido: $<span id="kpiSaldoMora">{{ number_format($payload['metrics']['pagos']['saldo_mora'] ?? 0, 2) }}</span></small>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1" id="kpiIngresosMensual">${{ number_format($payload['metrics']['ingresos']['mensual'] ?? 0, 2) }}</h4>
                                    <small class="text-muted" id="kpiIngresosLabel">{{ $payload['metrics']['ingresos']['periodo_label'] ?? 'Ingresos Mensuales' }}</small>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-primary">
                                        <i class="ri ri-bar-chart-line ri-24px"></i>
                                    </span>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-3">Anual: <strong id="kpiIngresosAnual">${{ number_format($payload['metrics']['ingresos']['anual'] ?? 0, 2) }}</strong></small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Gráficos --}}
            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h6 class="mb-0">Tendencia de Ingresos</h6>
                                <small class="text-muted" id="trendRangeLabel">{{ $payload['charts']['trend']['rangeLabel'] ?? '' }}</small>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-label-primary">Total: $<span id="trendTotalPeriodo">{{ number_format($payload['charts']['trend']['totalPeriodo'] ?? 0, 2) }}</span></span>
                                <span class="badge bg-label-info">Pagos: <span id="trendTotalPagos">{{ $payload['charts']['trend']['totalPagosPeriodo'] ?? 0 }}</span></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="dashboardIncomeTrend" style="min-height: 290px;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0">Estado de Cartera y Contratos</h6>
                            <small class="text-muted">Distribución actual de contratos</small>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <div id="dashboardPortfolioChart" style="min-height: 215px;"></div>
                            <ul class="list-unstyled mb-0 mt-2 border-top pt-2 small">
                                <li class="d-flex justify-content-between mb-1">
                                    <span class="text-muted"><i class="ri ri-checkbox-circle-fill text-success me-1"></i> Total Cobrado (filtro):</span>
                                    <strong class="text-success" id="carteraCobrado">${{ number_format($payload['charts']['cartera']['total_cobrado'] ?? 0, 2) }}</strong>
                                </li>
                                <li class="d-flex justify-content-between mb-1">
                                    <span class="text-muted"><i class="ri ri-time-fill text-info me-1"></i> Cartera por Vencer:</span>
                                    <strong class="text-info" id="carteraPorVencer">${{ number_format($payload['charts']['cartera']['saldo_por_vencer'] ?? 0, 2) }}</strong>
                                </li>
                                <li class="d-flex justify-content-between">
                                    <span class="text-muted"><i class="ri ri-error-warning-fill text-danger me-1"></i> Saldo en Mora:</span>
                                    <strong class="text-danger" id="carteraMora">${{ number_format($payload['charts']['cartera']['saldo_en_mora'] ?? 0, 2) }}</strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tablas: Cuotas próximas a vencerse & Contratos por vencer --}}
            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h6 class="mb-0">Cuotas próximas a vencerse</h6>
                                <small class="text-muted">Ordenadas por vencimiento próximo y moras recientes</small>
                            </div>
                            <div class="btn-group btn-group-sm" role="group" id="cuotasFilterTabs">
                                <button type="button" class="btn btn-primary cuotas-tab-btn" data-filter="all">
                                    Todas (<span id="cuotasCountAll">{{ $payload['cuotas']['counts']['all'] ?? 0 }}</span>)
                                </button>
                                <button type="button" class="btn btn-outline-primary cuotas-tab-btn" data-filter="upcoming">
                                    Próximas (<span id="cuotasCountUpcoming">{{ $payload['cuotas']['counts']['upcoming'] ?? 0 }}</span>)
                                </button>
                                <button type="button" class="btn btn-outline-danger cuotas-tab-btn" data-filter="overdue">
                                    Vencidas (<span id="cuotasCountOverdue">{{ $payload['cuotas']['counts']['overdue'] ?? 0 }}</span>)
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Cliente / Contrato</th>
                                        <th class="text-center">Cuota</th>
                                        <th>Vencimiento</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="cuotasTableBody"></tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 py-2" id="cuotasPagination"></div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h6 class="mb-0">Contratos por vencer</h6>
                                <small class="text-muted">Contratos vigentes ordenados por fecha fin</small>
                            </div>
                            <div class="btn-group btn-group-sm" role="group" id="contratosFilterTabs">
                                <button type="button" class="btn btn-primary contratos-tab-btn" data-filter="all">
                                    Todos (<span id="contratosCountAll">{{ $payload['contratos']['counts']['all'] ?? 0 }}</span>)
                                </button>
                                <button type="button" class="btn btn-outline-warning contratos-tab-btn" data-filter="anticipation">
                                    En rango (<span id="contratosCountAnticipation">{{ $payload['contratos']['counts']['anticipation'] ?? 0 }}</span>)
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Contrato / Cliente</th>
                                        <th>Progreso</th>
                                        <th>Vence</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-center">Ir</th>
                                    </tr>
                                </thead>
                                <tbody id="contratosTableBody"></tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 py-2" id="contratosPagination"></div>
                    </div>
                </div>
            </div>

            {{-- Tabla: Clientes con pendientes --}}
            <div class="row g-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h6 class="mb-0">Clientes con pendientes</h6>
                                <small class="text-muted">Priorizados por cuotas vencidas y mayor saldo pendiente (<span id="selectedClientsCount">0</span> seleccionados)</small>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div class="input-group input-group-sm" style="width: 260px;">
                                    <span class="input-group-text"><i class="ri ri-search-line"></i></span>
                                    <input type="text" id="searchClientesPendientes" class="form-control" placeholder="Buscar cliente, cédula o teléfono..." value="{{ $payload['filters']['searchClient'] ?? '' }}">
                                    <button type="button" class="btn btn-outline-secondary {{ empty($payload['filters']['searchClient']) ? 'd-none' : '' }}" id="btnClearSearchClientes" title="Limpiar búsqueda">
                                        <i class="ri ri-close-line"></i>
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="btnNotifyWhatsApp">
                                    <i class="ri ri-whatsapp-line me-1"></i> Notificar por WhatsApp
                                </button>
                                <button type="button" class="btn btn-sm btn-label-secondary" id="btnScheduleReminders">
                                    <i class="ri ri-time-line me-1"></i> Programar envíos
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllVisibleClients" title="Seleccionar visibles">
                                        </th>
                                        <th>Cliente</th>
                                        <th>Documento</th>
                                        <th>Teléfono</th>
                                        <th class="text-center">Cuotas Pendientes</th>
                                        <th class="text-end">Saldo Total</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="clientesTableBody"></tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 py-2" id="clientesPagination"></div>
                    </div>
                </div>
            </div>

        {{-- Modal: Estado de Cuenta / Cuotas Pendientes del Cliente --}}
        <div class="modal fade" id="modalEstadoCuentaCliente" tabindex="-1" aria-labelledby="modalEstadoCuentaTitle" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header py-3 px-4 border-bottom">
                        <div>
                            <h5 class="modal-title mb-0" id="modalEstadoCuentaTitle">Estado de Cuenta - Cuotas Pendientes</h5>
                            <small class="text-muted" id="modalClienteSubtitle">Cargando información...</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="modalEstadoCuentaLoading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-muted mt-2 mb-0">Cargando cuotas pendientes del cliente...</p>
                        </div>

                        <div id="modalEstadoCuentaError" class="alert alert-danger d-none mb-0"></div>

                        <div id="modalEstadoCuentaContent" class="d-none">
                            <div class="row g-3 mb-3">
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100 d-flex justify-content-between align-items-center bg-transparent">
                                        <div>
                                            <small class="text-muted d-block mb-1">Total Pagado</small>
                                            <strong class="fs-5 text-success" id="modalResTotalPagado">$0.00</strong>
                                        </div>
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded bg-label-success">
                                                <i class="ri ri-checkbox-circle-line"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100 d-flex justify-content-between align-items-center bg-transparent">
                                        <div>
                                            <small class="text-muted d-block mb-1">Saldo Pendiente</small>
                                            <strong class="fs-5 text-danger" id="modalResSaldoPendiente">$0.00</strong>
                                        </div>
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded bg-label-danger">
                                                <i class="ri ri-money-dollar-circle-line"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100 d-flex justify-content-between align-items-center bg-transparent">
                                        <div>
                                            <small class="text-muted d-block mb-1">Cuotas Vencidas</small>
                                            <strong class="fs-5 text-danger" id="modalResCuotasVencidas">0</strong>
                                        </div>
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded bg-label-warning">
                                                <i class="ri ri-error-warning-line"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100 d-flex justify-content-between align-items-center bg-transparent">
                                        <div>
                                            <small class="text-muted d-block mb-1">Cuotas Pendientes</small>
                                            <strong class="fs-5 text-primary" id="modalResCuotasPendientes">0</strong>
                                        </div>
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded bg-label-primary">
                                                <i class="ri ri-calendar-todo-line"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3" id="modalContratosFilterWrap">
                                <small class="text-muted d-block mb-2 fw-medium">Filtrar por contrato:</small>
                                <div class="d-flex flex-wrap gap-2" id="modalContratosTabs"></div>
                            </div>

                            <div class="table-responsive border rounded" style="max-height: 380px; overflow-y: auto;">
                                <table class="table table-hover table-sm align-middle mb-0">
                                    <thead class="table-light sticky-top" style="z-index: 1;">
                                        <tr>
                                            <th>Contrato</th>
                                            <th class="text-center"># Cuota</th>
                                            <th>Vencimiento</th>
                                            <th>Estado / Días</th>
                                            <th class="text-end">Monto Cuota</th>
                                            <th class="text-end">Abonado</th>
                                            <th class="text-end">Saldo Pendiente</th>
                                            <th class="text-center">Pagar</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalCuotasPendientesBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-3 px-4 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <a href="#" id="modalBtnEstadoCuentaCompleto" class="btn btn-label-info">
                            <i class="ri ri-file-list-3-line me-1"></i> Ver Estado de Cuenta Completo
                        </a>
                        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="ri ri-close-line me-1"></i> Cerrar
                            </button>
                            <a href="#" id="modalBtnRegistrarPago" class="btn btn-primary">
                                <i class="ri ri-money-dollar-circle-line me-1"></i> Registrar Pago
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>

@push('scripts')
<script>
            (function() {
                const DASHBOARD_ENDPOINTS = {
                    data: @json(route('admin.dashboard.data')),
                    notify: @json(route('admin.dashboard.notify-whatsapp')),
                    schedule: @json(route('admin.dashboard.schedule-reminders')),
                    exportExcel: @json(route('admin.dashboard.export-excel')),
                    exportPdf: @json(route('admin.dashboard.export-pdf')),
                    clientePendientesTemplate: @json(route('admin.dashboard.cliente-pendientes', ['cliente' => '__CLIENT_ID__']))
                };
                const CSRF_TOKEN = @json(csrf_token());
                const INITIAL_PAYLOAD = @json($payload);

                const state = {
                    dateFrom: INITIAL_PAYLOAD?.filters?.dateFrom || '',
                    dateTo: INITIAL_PAYLOAD?.filters?.dateTo || '',
                    minAmount: INITIAL_PAYLOAD?.filters?.minAmount ?? '',
                    maxAmount: INITIAL_PAYLOAD?.filters?.maxAmount ?? '',
                    anticipationDays: INITIAL_PAYLOAD?.filters?.anticipationDays || 7,
                    searchClient: INITIAL_PAYLOAD?.filters?.searchClient || '',
                    cuotasFilter: INITIAL_PAYLOAD?.filters?.cuotasFilter || 'all',
                    contratosFilter: INITIAL_PAYLOAD?.filters?.contratosFilter || 'all',
                    cuotasPage: 1,
                    contratosPage: 1,
                    clientesPage: 1
                };

                const selectedClients = new Set();
                let activeAbortController = null;
                let searchDebounceTimer = null;
                let pollIntervalId = null;

                function esc(value) {
                    if (value === null || value === undefined) return '';
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function fmtMoney(val) {
                    return Number(val || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                function buildQueryParams(includePages = true) {
                    const params = new URLSearchParams();
                    if (state.dateFrom) params.set('dateFrom', state.dateFrom);
                    if (state.dateTo) params.set('dateTo', state.dateTo);
                    if (state.minAmount !== '' && state.minAmount !== null) params.set('minAmount', state.minAmount);
                    if (state.maxAmount !== '' && state.maxAmount !== null) params.set('maxAmount', state.maxAmount);
                    if (state.anticipationDays) params.set('anticipationDays', String(state.anticipationDays));
                    if (state.searchClient) params.set('searchClient', state.searchClient);
                    params.set('cuotasFilter', state.cuotasFilter);
                    params.set('contratosFilter', state.contratosFilter);
                    if (includePages) {
                        params.set('cuotasPage', String(state.cuotasPage));
                        params.set('contratosPage', String(state.contratosPage));
                        params.set('clientesPage', String(state.clientesPage));
                    }
                    return params;
                }

                function updateExportLinks() {
                    const query = buildQueryParams(false).toString();
                    const suffix = query ? ('?' + query) : '';
                    const excelBtn = document.getElementById('btnExportExcel');
                    const pdfBtn = document.getElementById('btnExportPdf');
                    if (excelBtn) excelBtn.href = DASHBOARD_ENDPOINTS.exportExcel + suffix;
                    if (pdfBtn) pdfBtn.href = DASHBOARD_ENDPOINTS.exportPdf + suffix;
                }

                function showAlert(message, type = 'success') {
                    const box = document.getElementById('dashboardAjaxAlert');
                    const text = document.getElementById('dashboardAjaxAlertText');
                    if (!box || !text) return;
                    box.className = `alert alert-${type} alert-dismissible fade show mb-3`;
                    text.textContent = message;
                    box.classList.remove('d-none');
                    setTimeout(() => {
                        box.classList.add('d-none');
                    }, 6000);
                }

                function setLoading(isLoading) {
                    const badge = document.getElementById('dashboardLoadingBadge');
                    if (badge) {
                        badge.classList.toggle('d-none', !isLoading);
                    }
                }

                function renderKpis(metrics) {
                    if (!metrics) return;
                    const set = (id, val) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = val;
                    };
                    set('kpiClientesTotal', metrics.clientes?.total ?? 0);
                    set('kpiClientesActivos', metrics.clientes?.activos ?? 0);
                    set('kpiClientesInactivos', metrics.clientes?.inactivos ?? 0);
                    set('kpiClientesMora', metrics.clientes?.con_mora ?? 0);

                    set('kpiContratosVigentes', metrics.contratos?.vigentes ?? 0);
                    set('kpiContratosAlDia', metrics.contratos?.al_dia ?? 0);
                    set('kpiContratosVencidos', metrics.contratos?.vencidos ?? 0);
                    set('kpiContratosCompletados', metrics.contratos?.completados ?? 0);

                    set('kpiPagosRecibidos', metrics.pagos?.recibidos ?? 0);
                    set('kpiPagosPendientes', metrics.pagos?.pendientes ?? 0);
                    set('kpiPagosMorosos', metrics.pagos?.morosos ?? 0);
                    set('kpiSaldoMora', fmtMoney(metrics.pagos?.saldo_mora ?? 0));

                    set('kpiIngresosMensual', '$' + fmtMoney(metrics.ingresos?.mensual ?? 0));
                    set('kpiIngresosAnual', '$' + fmtMoney(metrics.ingresos?.anual ?? 0));
                    set('kpiIngresosLabel', metrics.ingresos?.periodo_label || 'Ingresos Mensuales');
                }

                let pendingChartsData = null;

                function renderCharts(charts) {
                    if (!charts) return;
                    const setText = (id, txt) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = txt;
                    };

                    const trend = charts.trend || {};
                    setText('trendRangeLabel', trend.rangeLabel || '');
                    setText('trendTotalPeriodo', fmtMoney(trend.totalPeriodo || 0));
                    setText('trendTotalPagos', trend.totalPagosPeriodo || 0);

                    const cartera = charts.cartera || {};
                    setText('carteraCobrado', '$' + fmtMoney(cartera.total_cobrado || 0));
                    setText('carteraPorVencer', '$' + fmtMoney(cartera.saldo_por_vencer || 0));
                    setText('carteraMora', '$' + fmtMoney(cartera.saldo_en_mora || 0));

                    if (typeof ApexCharts === 'undefined') {
                        pendingChartsData = charts;
                        window.addEventListener('load', () => {
                            if (pendingChartsData) {
                                const data = pendingChartsData;
                                pendingChartsData = null;
                                renderCharts(data);
                            }
                        }, {
                            once: true
                        });
                        return;
                    }

                    window.__dashboardCharts = window.__dashboardCharts || {
                        trend: null,
                        portfolio: null
                    };

                    function resetChartContainer(key, el) {
                        if (window.__dashboardCharts[key]) {
                            try {
                                window.__dashboardCharts[key].destroy();
                            } catch (_) {}
                            window.__dashboardCharts[key] = null;
                        }
                        if (el) {
                            el.innerHTML = '';
                        }
                    }

                    const trendEl = document.getElementById('dashboardIncomeTrend');
                    if (trendEl) {
                        try {
                            resetChartContainer('trend', trendEl);

                            const labels = Array.isArray(trend.labels) ? trend.labels : [];
                            const values = Array.isArray(trend.values) ? trend.values.map(v => Number(v || 0)) : [];
                            const counts = Array.isArray(trend.counts) ? trend.counts.map(c => Number(c || 0)) : [];

                            if (labels.length === 0) {
                                trendEl.innerHTML = '<div class="text-center text-muted py-5">Sin ingresos en el rango seleccionado</div>';
                            } else {
                                window.__dashboardCharts.trend = new ApexCharts(trendEl, {
                                    chart: {
                                        type: 'area',
                                        height: 290,
                                        toolbar: { show: false },
                                        animations: { enabled: true, speed: 300 }
                                    },
                                    series: [{
                                        name: 'Ingresos',
                                        data: values
                                    }],
                                    xaxis: {
                                        categories: labels
                                    },
                                    dataLabels: { enabled: false },
                                    markers: { size: 4, hover: { size: 6 } },
                                    stroke: { curve: 'smooth', width: 3 },
                                    yaxis: {
                                        labels: {
                                            formatter: val => '$' + Number(val || 0).toFixed(0)
                                        }
                                    },
                                    colors: ['#7367F0'],
                                    fill: {
                                        type: 'gradient',
                                        gradient: {
                                            shadeIntensity: 0.3,
                                            opacityFrom: 0.55,
                                            opacityTo: 0.08
                                        }
                                    },
                                    tooltip: {
                                        y: {
                                            formatter: (val, opts) => {
                                                const idx = opts?.dataPointIndex ?? 0;
                                                const cnt = counts[idx] ?? 0;
                                                return '$' + fmtMoney(val) + ` (${cnt} pagos)`;
                                            }
                                        }
                                    }
                                });
                                window.__dashboardCharts.trend.render();
                            }
                        } catch (chartErr) {
                            console.warn('Error rendering trend chart:', chartErr);
                        }
                    }

                    const portEl = document.getElementById('dashboardPortfolioChart');
                    if (portEl) {
                        try {
                            resetChartContainer('portfolio', portEl);

                            const portSeries = Array.isArray(cartera.contratos_counts)
                                ? cartera.contratos_counts.map(v => Number(v || 0))
                                : [0, 0, 0];
                            const portLabels = Array.isArray(cartera.contratos_labels)
                                ? cartera.contratos_labels
                                : ['Al Día', 'En Mora', 'Completados'];
                            const totalPort = portSeries.reduce((a, b) => a + b, 0);

                            if (totalPort === 0) {
                                portEl.innerHTML = '<div class="text-center text-muted py-5">Sin contratos para graficar</div>';
                            } else {
                                window.__dashboardCharts.portfolio = new ApexCharts(portEl, {
                                    chart: {
                                        type: 'donut',
                                        height: 225,
                                        animations: { enabled: true, speed: 300 }
                                    },
                                    series: portSeries,
                                    labels: portLabels,
                                    colors: ['#28C76F', '#EA5455', '#7367F0'],
                                    legend: {
                                        position: 'bottom',
                                        fontSize: '12px'
                                    },
                                    dataLabels: {
                                        enabled: true,
                                        formatter: val => Math.round(val) + '%'
                                    },
                                    plotOptions: {
                                        pie: {
                                            donut: {
                                                size: '65%',
                                                labels: {
                                                    show: true,
                                                    total: {
                                                        show: true,
                                                        label: 'Contratos',
                                                        formatter: w => (w?.globals?.seriesTotals || []).reduce((a, b) => a + b, 0)
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                                window.__dashboardCharts.portfolio.render();
                            }
                        } catch (portErr) {
                            console.warn('Error rendering portfolio chart:', portErr);
                        }
                    }
                }

                function renderPagination(containerId, meta, tableKey) {
                    const container = document.getElementById(containerId);
                    if (!container) return;
                    const total = meta?.total || 0;
                    const current = meta?.current_page || 1;
                    const last = meta?.last_page || 1;
                    const from = meta?.from || 0;
                    const to = meta?.to || 0;

                    const infoHtml = `<small class="text-muted">Mostrando ${from} a ${to} de ${total}</small>`;
                    if (last <= 1) {
                        container.innerHTML = infoHtml;
                        return;
                    }

                    const start = Math.max(1, current - 2);
                    const end = Math.min(last, current + 2);
                    let pagesHtml = '';

                    pagesHtml += `<li class="page-item ${current <= 1 ? 'disabled' : ''}">
                <button type="button" class="page-link ajax-page-btn" data-table="${tableKey}" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>&laquo;</button>
            </li>`;

                    for (let p = start; p <= end; p++) {
                        pagesHtml += `<li class="page-item ${p === current ? 'active' : ''}">
                    <button type="button" class="page-link ajax-page-btn" data-table="${tableKey}" data-page="${p}">${p}</button>
                </li>`;
                    }

                    pagesHtml += `<li class="page-item ${current >= last ? 'disabled' : ''}">
                <button type="button" class="page-link ajax-page-btn" data-table="${tableKey}" data-page="${current + 1}" ${current >= last ? 'disabled' : ''}>&raquo;</button>
            </li>`;

                    container.innerHTML = `${infoHtml}<nav><ul class="pagination pagination-sm mb-0">${pagesHtml}</ul></nav>`;
                }

                function renderCuotasTable(cuotasData) {
                    const tbody = document.getElementById('cuotasTableBody');
                    if (!tbody) return;

                    const counts = cuotasData?.counts || {};
                    const setCount = (id, v) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = v ?? 0;
                    };
                    setCount('cuotasCountAll', counts.all);
                    setCount('cuotasCountUpcoming', counts.upcoming);
                    setCount('cuotasCountOverdue', counts.overdue);

                    document.querySelectorAll('.cuotas-tab-btn').forEach(btn => {
                        const f = btn.dataset.filter;
                        const active = f === state.cuotasFilter;
                        if (f === 'overdue') {
                            btn.className = `btn ${active ? 'btn-danger' : 'btn-outline-danger'} cuotas-tab-btn`;
                        } else {
                            btn.className = `btn ${active ? 'btn-primary' : 'btn-outline-primary'} cuotas-tab-btn`;
                        }
                    });

                    const rows = cuotasData?.data || [];
                    if (rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Sin cuotas en el rango seleccionado</td></tr>';
                    } else {
                        tbody.innerHTML = rows.map(c => `
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-medium text-truncate" style="max-width: 190px;" title="${esc(c.cliente_nombre)}">${esc(c.cliente_nombre)}</span>
                                <small class="text-muted">
                                    <a href="${esc(c.url_contrato)}" class="text-decoration-none">#${esc(c.numero_contrato)}</a>
                                    ${c.cliente_documento ? ' • ' + esc(c.cliente_documento) : ''}
                                </small>
                            </div>
                        </td>
                        <td class="text-center"><span class="badge bg-label-secondary">#${esc(c.numero_cuota)}</span></td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="small fw-medium">${esc(c.fecha_vencimiento_fmt)}</span>
                                <span class="badge bg-label-${esc(c.badge_color)} align-self-start" style="font-size: 0.7rem;">${esc(c.dias_texto)}</span>
                            </div>
                        </td>
                        <td class="text-end fw-bold">$${esc(c.saldo_pendiente_fmt)}</td>
                        <td class="text-center">
                            <a href="${esc(c.url_pagar)}" class="btn btn-xs btn-icon btn-label-primary" title="Registrar pago de cuota">
                                <i class="ri ri-hand-coin-line"></i>
                            </a>
                        </td>
                    </tr>
                `).join('');
                    }

                    renderPagination('cuotasPagination', cuotasData?.meta, 'cuotas');
                }

                function renderContratosTable(contratosData) {
                    const tbody = document.getElementById('contratosTableBody');
                    if (!tbody) return;

                    const counts = contratosData?.counts || {};
                    const setCount = (id, v) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = v ?? 0;
                    };
                    setCount('contratosCountAll', counts.all);
                    setCount('contratosCountAnticipation', counts.anticipation);

                    document.querySelectorAll('.contratos-tab-btn').forEach(btn => {
                        const f = btn.dataset.filter;
                        const active = f === state.contratosFilter;
                        if (f === 'anticipation') {
                            btn.className = `btn ${active ? 'btn-warning' : 'btn-outline-warning'} contratos-tab-btn`;
                        } else {
                            btn.className = `btn ${active ? 'btn-primary' : 'btn-outline-primary'} contratos-tab-btn`;
                        }
                    });

                    const rows = contratosData?.data || [];
                    if (rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Sin contratos por vencer en este filtro</td></tr>';
                    } else {
                        tbody.innerHTML = rows.map(ct => `
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <a href="${esc(ct.url_contrato)}" class="fw-bold text-decoration-none">#${esc(ct.numero_contrato)}</a>
                                <small class="text-muted text-truncate" style="max-width: 155px;" title="${esc(ct.cliente_nombre)}">${esc(ct.cliente_nombre)}</small>
                            </div>
                        </td>
                        <td style="min-width: 85px;">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>${esc(ct.progreso_texto)}</span>
                                <span class="text-muted">${esc(ct.progreso_pct)}%</span>
                            </div>
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar ${ct.estado === 'mora' ? 'bg-warning' : 'bg-success'}" style="width: ${esc(ct.progreso_pct)}%"></div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="small fw-medium">${esc(ct.fecha_fin_fmt)}</span>
                                <span class="badge bg-label-${esc(ct.badge_color)} align-self-start" style="font-size: 0.68rem;">${esc(ct.dias_texto)}</span>
                            </div>
                        </td>
                        <td class="text-end fw-bold text-danger">$${esc(ct.saldo_pendiente_fmt)}</td>
                        <td class="text-center">
                            <a href="${esc(ct.url_contrato)}" class="btn btn-xs btn-icon btn-label-info" title="Ver contrato">
                                <i class="ri ri-eye-line"></i>
                            </a>
                        </td>
                    </tr>
                `).join('');
                    }

                    renderPagination('contratosPagination', contratosData?.meta, 'contratos');
                }

                function updateSelectedCount() {
                    const countEl = document.getElementById('selectedClientsCount');
                    if (countEl) countEl.textContent = String(selectedClients.size);

                    const visibleChecks = Array.from(document.querySelectorAll('.client-select-checkbox'));
                    const selectAll = document.getElementById('selectAllVisibleClients');
                    if (selectAll) {
                        selectAll.checked = visibleChecks.length > 0 && visibleChecks.every(cb => cb.checked);
                    }
                }

                function renderClientesTable(clientesData) {
                    const tbody = document.getElementById('clientesTableBody');
                    if (!tbody) return;

                    const rows = clientesData?.data || [];
                    if (rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin clientes pendientes para los filtros actuales</td></tr>';
                    } else {
                        tbody.innerHTML = rows.map(cl => {
                            const checked = selectedClients.has(Number(cl.id)) ? 'checked' : '';
                            return `
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input client-select-checkbox" value="${esc(cl.id)}" ${checked}>
                            </td>
                            <td>
                                <a href="${esc(cl.url_cliente)}" class="fw-medium text-decoration-none">${esc(cl.nombre_completo)}</a>
                            </td>
                            <td>${esc(cl.documento)}</td>
                            <td>${cl.telefono ? esc(cl.telefono) : '<span class="text-muted">N/A</span>'}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-link p-0 text-decoration-none btn-open-estado-cuenta" data-cliente-id="${esc(cl.id)}" title="Ver detalle de cuotas pendientes">
                                    <div class="d-inline-flex gap-1">
                                        ${cl.cuotas_vencidas_count > 0
                                            ? `<span class="badge bg-label-danger">${esc(cl.cuotas_vencidas_count)} vencida(s)</span>`
                                            : '<span class="badge bg-label-success">Al día</span>'}
                                        <span class="badge bg-label-secondary">${esc(cl.cuotas_pendientes_count)} pend.</span>
                                    </div>
                                </button>
                            </td>
                            <td class="text-end fw-bold text-danger">$${esc(cl.saldo_total_fmt)}</td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="${esc(cl.url_cliente)}" class="btn btn-xs btn-icon btn-label-info" title="Ver Cliente">
                                        <i class="ri ri-user-line"></i>
                                    </a>
                                    <a href="${esc(cl.url_pagar)}" class="btn btn-xs btn-icon btn-label-primary" title="Registrar Pago">
                                        <i class="ri ri-money-dollar-circle-line"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs btn-icon btn-label-secondary btn-open-estado-cuenta" data-cliente-id="${esc(cl.id)}" title="Estado de Cuenta (Cuotas Pendientes)">
                                        <i class="ri ri-file-list-3-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                        }).join('');
                    }

                    updateSelectedCount();
                    renderPagination('clientesPagination', clientesData?.meta, 'clientes');
                }

                function applyPayload(payload) {
                    if (!payload) return;
                    renderKpis(payload.metrics);
                    renderCuotasTable(payload.cuotas);
                    renderContratosTable(payload.contratos);
                    renderClientesTable(payload.clientes);
                    const upd = document.getElementById('dashboardUpdatedAt');
                    if (upd && payload.updatedAt) upd.textContent = payload.updatedAt;
                    updateExportLinks();
                    renderCharts(payload.charts);
                }

                async function fetchDashboardData(showIndicator = true) {
                    if (activeAbortController) {
                        activeAbortController.abort();
                    }
                    activeAbortController = new AbortController();
                    if (showIndicator) setLoading(true);

                    try {
                        const url = DASHBOARD_ENDPOINTS.data + '?' + buildQueryParams(true).toString();
                        const res = await fetch(url, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            signal: activeAbortController.signal
                        });
                        if (!res.ok) throw new Error('Error al cargar los datos del dashboard.');
                        const payload = await res.json();
                        applyPayload(payload);
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            showAlert(err.message || 'No se pudo actualizar el dashboard.', 'danger');
                        }
                    } finally {
                        if (showIndicator) setLoading(false);
                    }
                }

                async function sendClientBatchAction(endpoint, buttonEl) {
                    const ids = Array.from(selectedClients);
                    if (ids.length === 0) {
                        showAlert('Seleccione al menos un cliente de la lista para continuar.', 'warning');
                        return;
                    }

                    const originalHtml = buttonEl.innerHTML;
                    buttonEl.disabled = true;
                    buttonEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

                    try {
                        const res = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': CSRF_TOKEN,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                clients: ids
                            })
                        });
                        const json = await res.json();
                        showAlert(json.message || 'Operación completada.', json.success ? 'success' : 'warning');
                    } catch (err) {
                        showAlert('Ocurrió un error al procesar la solicitud.', 'danger');
                    } finally {
                        buttonEl.disabled = false;
                        buttonEl.innerHTML = originalHtml;
                    }
                }

                let modalDataCache = null;
                let modalSelectedContratoId = null;

                function renderModalCuotas() {
                    const tbody = document.getElementById('modalCuotasPendientesBody');
                    const tabsEl = document.getElementById('modalContratosTabs');
                    const btnPagar = document.getElementById('modalBtnRegistrarPago');
                    if (!tbody || !modalDataCache) return;

                    const contratos = modalDataCache.contratos || [];
                    const allCuotas = modalDataCache.cuotas || [];

                    if (tabsEl) {
                        const allActive = modalSelectedContratoId === null;
                        let html = `<button type="button" class="btn btn-sm ${allActive ? 'btn-primary' : 'btn-outline-primary'} modal-contrato-pill" data-contrato-id="">
                            Todos (${allCuotas.length})
                        </button>`;
                        contratos.forEach(ct => {
                            const active = Number(modalSelectedContratoId) === Number(ct.id);
                            html += `<button type="button" class="btn btn-sm ${active ? 'btn-primary' : 'btn-outline-secondary'} modal-contrato-pill" data-contrato-id="${esc(ct.id)}">
                                #${esc(ct.numero_contrato)} — ${esc(ct.moto)} (${esc(ct.progreso_texto)}) • $${esc(ct.saldo_pendiente_fmt)}
                            </button>`;
                        });
                        tabsEl.innerHTML = html;
                    }

                    const filtered = modalSelectedContratoId === null
                        ? allCuotas
                        : allCuotas.filter(c => Number(c.contrato_id) === Number(modalSelectedContratoId));

                    if (btnPagar) {
                        const activeContrato = modalSelectedContratoId !== null
                            ? contratos.find(ct => Number(ct.id) === Number(modalSelectedContratoId))
                            : null;
                        btnPagar.href = activeContrato?.url_pagar || modalDataCache.cliente?.url_pagar || '#';
                    }

                    if (filtered.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Este cliente no tiene cuotas pendientes en este filtro.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = filtered.map(c => `
                        <tr>
                            <td><span class="fw-medium">#${esc(c.numero_contrato)}</span></td>
                            <td class="text-center"><span class="badge bg-label-secondary">#${esc(c.numero_cuota)}</span></td>
                            <td>${esc(c.fecha_vencimiento_fmt)}</td>
                            <td>
                                <span class="badge bg-label-${esc(c.badge_color)}">${esc(c.dias_texto)}</span>
                            </td>
                            <td class="text-end">$${esc(c.monto_total_fmt)}</td>
                            <td class="text-end text-success">$${esc(c.monto_pagado_fmt)}</td>
                            <td class="text-end fw-bold text-danger">$${esc(c.saldo_pendiente_fmt)}</td>
                            <td class="text-center">
                                <a href="${esc(c.url_pagar)}" class="btn btn-xs btn-icon btn-label-primary" title="Pagar cuota">
                                    <i class="ri ri-hand-coin-line"></i>
                                </a>
                            </td>
                        </tr>
                    `).join('');
                }

                async function openClientePendientesModal(clienteId) {
                    const modalEl = document.getElementById('modalEstadoCuentaCliente');
                    const loadingEl = document.getElementById('modalEstadoCuentaLoading');
                    const contentEl = document.getElementById('modalEstadoCuentaContent');
                    const errorEl = document.getElementById('modalEstadoCuentaError');
                    const titleEl = document.getElementById('modalEstadoCuentaTitle');
                    const subEl = document.getElementById('modalClienteSubtitle');
                    if (!modalEl) return;

                    modalDataCache = null;
                    modalSelectedContratoId = null;
                    if (titleEl) titleEl.textContent = 'Estado de Cuenta - Cuotas Pendientes';
                    if (subEl) subEl.textContent = 'Cargando información...';
                    loadingEl?.classList.remove('d-none');
                    contentEl?.classList.add('d-none');
                    errorEl?.classList.add('d-none');

                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }

                    try {
                        const url = DASHBOARD_ENDPOINTS.clientePendientesTemplate.replace('__CLIENT_ID__', String(clienteId));
                        const res = await fetch(url, {
                            method: 'GET',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || 'No se pudo cargar el estado de cuenta del cliente.');
                        }

                        modalDataCache = data;
                        if (titleEl) titleEl.textContent = `Estado de Cuenta — ${data.cliente?.nombre_completo || 'Cliente'}`;
                        if (subEl) {
                            const parts = [];
                            if (data.cliente?.documento) parts.push(`Doc: ${data.cliente.documento}`);
                            if (data.cliente?.telefono) parts.push(`Tel: ${data.cliente.telefono}`);
                            subEl.textContent = parts.join(' • ') || 'Detalle de cuotas pendientes';
                        }

                        const setTxt = (id, v) => {
                            const el = document.getElementById(id);
                            if (el) el.textContent = v;
                        };
                        setTxt('modalResTotalPagado', '$' + (data.resumen?.total_pagado_fmt || '0.00'));
                        setTxt('modalResSaldoPendiente', '$' + (data.resumen?.saldo_pendiente_fmt || '0.00'));
                        setTxt('modalResCuotasVencidas', data.resumen?.cuotas_vencidas_count ?? 0);
                        setTxt('modalResCuotasPendientes', data.resumen?.cuotas_pendientes_count ?? 0);

                        const btnCompleto = document.getElementById('modalBtnEstadoCuentaCompleto');
                        if (btnCompleto) btnCompleto.href = data.cliente?.url_estado_cuenta || '#';

                        renderModalCuotas();
                        loadingEl?.classList.add('d-none');
                        contentEl?.classList.remove('d-none');
                    } catch (err) {
                        loadingEl?.classList.add('d-none');
                        if (errorEl) {
                            errorEl.textContent = err.message || 'Error al cargar los datos del cliente.';
                            errorEl.classList.remove('d-none');
                        }
                    }
                }

                function formatIsoDate(dateObj) {
                    const y = dateObj.getFullYear();
                    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                    const d = String(dateObj.getDate()).padStart(2, '0');
                    return `${y}-${m}-${d}`;
                }

                function bindDashboardEvents() {
                    const app = document.getElementById('dashboardApp');
                    if (!app || app.dataset.bound === '1') return;
                    app.dataset.bound = '1';

                    const dateFromEl = document.getElementById('filterDateFrom');
                    const dateToEl = document.getElementById('filterDateTo');
                    const minAmountEl = document.getElementById('filterMinAmount');
                    const maxAmountEl = document.getElementById('filterMaxAmount');
                    const anticipationEl = document.getElementById('filterAnticipationDays');
                    const searchEl = document.getElementById('filterSearchClient');
                    const searchClientesEl = document.getElementById('searchClientesPendientes');
                    const btnClearClientesEl = document.getElementById('btnClearSearchClientes');

                    const syncSearchInputs = (rawValue, sourceEl = null) => {
                        if (searchEl && searchEl !== sourceEl) searchEl.value = rawValue;
                        if (searchClientesEl && searchClientesEl !== sourceEl) searchClientesEl.value = rawValue;
                        if (btnClearClientesEl) {
                            btnClearClientesEl.classList.toggle('d-none', rawValue.trim() === '');
                        }
                    };

                    const resetPagesAndFetch = () => {
                        state.cuotasPage = 1;
                        state.contratosPage = 1;
                        state.clientesPage = 1;
                        fetchDashboardData(true);
                    };

                    dateFromEl?.addEventListener('change', () => {
                        state.dateFrom = dateFromEl.value;
                        resetPagesAndFetch();
                    });
                    dateToEl?.addEventListener('change', () => {
                        state.dateTo = dateToEl.value;
                        resetPagesAndFetch();
                    });
                    minAmountEl?.addEventListener('change', () => {
                        state.minAmount = minAmountEl.value;
                        resetPagesAndFetch();
                    });
                    maxAmountEl?.addEventListener('change', () => {
                        state.maxAmount = maxAmountEl.value;
                        resetPagesAndFetch();
                    });
                    anticipationEl?.addEventListener('change', () => {
                        const v = Math.max(1, Math.min(365, parseInt(anticipationEl.value || '7', 10)));
                        anticipationEl.value = String(v);
                        state.anticipationDays = v;
                        resetPagesAndFetch();
                    });
                    searchEl?.addEventListener('input', () => {
                        syncSearchInputs(searchEl.value, searchEl);
                        clearTimeout(searchDebounceTimer);
                        searchDebounceTimer = setTimeout(() => {
                            state.searchClient = searchEl.value.trim();
                            resetPagesAndFetch();
                        }, 200);
                    });
                    searchClientesEl?.addEventListener('input', () => {
                        syncSearchInputs(searchClientesEl.value, searchClientesEl);
                        clearTimeout(searchDebounceTimer);
                        searchDebounceTimer = setTimeout(() => {
                            state.searchClient = searchClientesEl.value.trim();
                            resetPagesAndFetch();
                        }, 200);
                    });
                    btnClearClientesEl?.addEventListener('click', () => {
                        syncSearchInputs('');
                        state.searchClient = '';
                        resetPagesAndFetch();
                        searchClientesEl?.focus();
                    });

                    document.getElementById('btnRefreshDashboard')?.addEventListener('click', () => fetchDashboardData(true));

                    document.querySelectorAll('.quick-range-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const now = new Date();
                            const range = btn.dataset.range;
                            if (range === 'today') {
                                const todayStr = formatIsoDate(now);
                                state.dateFrom = todayStr;
                                state.dateTo = todayStr;
                            } else if (range === 'next7') {
                                const next = new Date(now);
                                next.setDate(now.getDate() + 7);
                                state.dateFrom = formatIsoDate(now);
                                state.dateTo = formatIsoDate(next);
                                state.anticipationDays = 7;
                                if (anticipationEl) anticipationEl.value = '7';
                            } else if (range === 'month') {
                                const first = new Date(now.getFullYear(), now.getMonth(), 1);
                                const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                                state.dateFrom = formatIsoDate(first);
                                state.dateTo = formatIsoDate(last);
                            } else if (range === 'year') {
                                const first = new Date(now.getFullYear(), 0, 1);
                                const last = new Date(now.getFullYear(), 11, 31);
                                state.dateFrom = formatIsoDate(first);
                                state.dateTo = formatIsoDate(last);
                            } else if (range === 'clear') {
                                state.dateFrom = '';
                                state.dateTo = '';
                                state.minAmount = '';
                                state.maxAmount = '';
                                state.searchClient = '';
                                state.anticipationDays = 7;
                                state.cuotasFilter = 'all';
                                state.contratosFilter = 'all';
                                if (minAmountEl) minAmountEl.value = '';
                                if (maxAmountEl) maxAmountEl.value = '';
                                syncSearchInputs('');
                                if (anticipationEl) anticipationEl.value = '7';
                            }
                            if (dateFromEl) dateFromEl.value = state.dateFrom;
                            if (dateToEl) dateToEl.value = state.dateTo;
                            resetPagesAndFetch();
                        });
                    });

                    app.addEventListener('click', (e) => {
                        const pageBtn = e.target.closest('.ajax-page-btn');
                        if (pageBtn && !pageBtn.disabled) {
                            const table = pageBtn.dataset.table;
                            const page = parseInt(pageBtn.dataset.page || '1', 10);
                            if (table === 'cuotas') state.cuotasPage = page;
                            if (table === 'contratos') state.contratosPage = page;
                            if (table === 'clientes') state.clientesPage = page;
                            fetchDashboardData(true);
                            return;
                        }

                        const cuotaTab = e.target.closest('.cuotas-tab-btn');
                        if (cuotaTab) {
                            state.cuotasFilter = cuotaTab.dataset.filter || 'all';
                            state.cuotasPage = 1;
                            fetchDashboardData(true);
                            return;
                        }

                        const contratoTab = e.target.closest('.contratos-tab-btn');
                        if (contratoTab) {
                            state.contratosFilter = contratoTab.dataset.filter || 'all';
                            state.contratosPage = 1;
                            fetchDashboardData(true);
                            return;
                        }

                        const openModalBtn = e.target.closest('.btn-open-estado-cuenta');
                        if (openModalBtn) {
                            const cid = openModalBtn.dataset.clienteId;
                            if (cid) openClientePendientesModal(cid);
                            return;
                        }

                        const modalPill = e.target.closest('.modal-contrato-pill');
                        if (modalPill) {
                            const rawId = modalPill.dataset.contratoId;
                            modalSelectedContratoId = rawId ? Number(rawId) : null;
                            renderModalCuotas();
                        }
                    });

                    app.addEventListener('change', (e) => {
                        if (e.target.id === 'selectAllVisibleClients') {
                            const checked = e.target.checked;
                            document.querySelectorAll('.client-select-checkbox').forEach(cb => {
                                cb.checked = checked;
                                const id = Number(cb.value);
                                if (checked) selectedClients.add(id);
                                else selectedClients.delete(id);
                            });
                            updateSelectedCount();
                            return;
                        }

                        if (e.target.classList.contains('client-select-checkbox')) {
                            const id = Number(e.target.value);
                            if (e.target.checked) selectedClients.add(id);
                            else selectedClients.delete(id);
                            updateSelectedCount();
                        }
                    });

                    document.getElementById('btnNotifyWhatsApp')?.addEventListener('click', function() {
                        sendClientBatchAction(DASHBOARD_ENDPOINTS.notify, this);
                    });
                    document.getElementById('btnScheduleReminders')?.addEventListener('click', function() {
                        sendClientBatchAction(DASHBOARD_ENDPOINTS.schedule, this);
                    });

                    applyPayload(INITIAL_PAYLOAD);

                    if (!pollIntervalId) {
                        pollIntervalId = setInterval(() => {
                            if (document.visibilityState === 'visible') {
                                fetchDashboardData(false);
                            }
                        }, 60000);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bindDashboardEvents);
                } else {
                    bindDashboardEvents();
                }
                document.addEventListener('livewire:navigated', bindDashboardEvents);
            })();
</script>
@endpush
