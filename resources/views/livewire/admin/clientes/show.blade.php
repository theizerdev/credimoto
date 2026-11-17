<div>
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex gap-4">
                            <div class="avatar avatar-xl">
                                <div class="avatar-initial rounded bg-label-primary fs-3">
                                    {{ substr($cliente->nombre, 0, 1) }}{{ substr($cliente->apellido, 0, 1) }}
                                </div>
                            </div>
                            <div>
                                <h4 class="mb-1">{{ $cliente->nombre_completo }}</h4>
                                <div class="d-flex gap-3 text-muted mb-2">
                                    <div class="d-flex align-items-center gap-1">
                                        <i class="ri ri-id-card-line"></i>
                                        {{ $cliente->documento }}
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <i class="ri ri-phone-line"></i>
                                        {{ $cliente->telefono ?? 'N/A' }}
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <i class="ri ri-mail-line"></i>
                                        {{ $cliente->email ?? 'N/A' }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-label-{{ $cliente->activo ? 'success' : 'danger' }}">
                                        {{ $cliente->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                    <span class="badge bg-label-primary">
                                        {{ $this->stats['contratos_activos'] }} Contratos Activos
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="ri ri-download-line me-1"></i> Exportar
                                </button>
                                <ul class="dropdown-menu">
                                    <li><button class="dropdown-item" wire:click="exportar('pdf')"><i class="ri ri-file-pdf-line me-2"></i>PDF</button></li>
                                    <li><button class="dropdown-item" wire:click="exportar('excel')"><i class="ri ri-file-excel-line me-2"></i>Excel</button></li>
                                </ul>
                            </div>
                            <button type="button" class="btn btn-outline-warning" wire:click="enviarRecordatorio" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="enviarRecordatorio">
                                    <i class="ri ri-notification-badge-line me-1"></i> Recordar Pago
                                </span>
                                <span wire:loading wire:target="enviarRecordatorio">
                                    <i class="ri ri-loader-4-line ri-spin me-1"></i> Enviando...
                                </span>
                            </button>
                            <a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-outline-primary">
                                <i class="ri ri-edit-line me-1"></i> Editar
                            </a>
                            <a href="{{ route('admin.pagos.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-primary">
                                <i class="ri ri-money-dollar-circle-line me-1"></i> Registrar Pago
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <h5 class="mb-1">${{ number_format($this->stats['total_pagado'], 2) }}</h5>
                            <small class="text-muted">Total Pagado</small>
                        </div>
                        <span class="badge bg-label-success rounded p-2">
                            <i class="ri ri-money-dollar-circle-line fs-4"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <h5 class="mb-1">${{ number_format($this->stats['deuda_total'], 2) }}</h5>
                            <small class="text-muted">Deuda Pendiente</small>
                        </div>
                        <span class="badge bg-label-danger rounded p-2">
                            <i class="ri ri-hand-coin-line fs-4"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <h5 class="mb-1">{{ $this->contratos->total() }}</h5>
                            <small class="text-muted">Total Contratos</small>
                        </div>
                        <span class="badge bg-label-info rounded p-2">
                            <i class="ri ri-file-list-3-line fs-4"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="nav-align-top mb-4">
                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $activeTab === 'general' ? 'active' : '' }}" 
                                wire:click="$set('activeTab', 'general')">
                            <i class="ri ri-user-line me-1"></i> General
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $activeTab === 'contratos' ? 'active' : '' }}" 
                                wire:click="$set('activeTab', 'contratos')">
                            <i class="ri ri-file-text-line me-1"></i> Contratos
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $activeTab === 'pagos' ? 'active' : '' }}" 
                                wire:click="$set('activeTab', 'pagos')">
                            <i class="ri ri-history-line me-1"></i> Historial de Pagos
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $activeTab === 'pendientes' ? 'active' : '' }}" 
                                wire:click="$set('activeTab', 'pendientes')">
                            <i class="ri ri-alarm-warning-line me-1"></i> Obligaciones Pendientes
                        </button>
                    </li>
                </ul>

                <div class="tab-content shadow-none p-0 bg-transparent">
                    
                    <!-- General Tab -->
                    <div class="tab-pane fade {{ $activeTab === 'general' ? 'show active' : '' }}">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Información Personal</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Nombre Completo:</span>
                                                <span>{{ $cliente->nombre_completo }}</span>
                                            </li>
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Documento:</span>
                                                <span>{{ $cliente->documento }}</span>
                                            </li>
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Fecha Nacimiento:</span>
                                                <span>{{ $cliente->fecha_nacimiento ? $cliente->fecha_nacimiento->format('d/m/Y') : 'N/A' }}</span>
                                            </li>
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Estado Civil:</span>
                                                <span>{{ ucfirst($cliente->estado_civil ?? 'N/A') }}</span>
                                            </li>
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Ocupación:</span>
                                                <span>{{ $cliente->ocupacion ?? 'N/A' }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Información de Contacto</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Teléfono:</span>
                                                <span>{{ $cliente->telefono ?? 'N/A' }}</span>
                                            </li>
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Email:</span>
                                                <span>{{ $cliente->email ?? 'N/A' }}</span>
                                            </li>
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Dirección:</span>
                                                <span>{{ $cliente->direccion ?? 'N/A' }}</span>
                                            </li>
                                            <li class="mb-3">
                                                <span class="fw-medium text-heading me-2">Ciudad/Estado:</span>
                                                <span>{{ $cliente->ciudad ?? '' }} {{ $cliente->estado ? ', ' . $cliente->estado : '' }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contratos Tab -->
                    <div class="tab-pane fade {{ $activeTab === 'contratos' ? 'show active' : '' }}">
                        <div class="card">
                            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Contratos</h5>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control form-control-sm" placeholder="Buscar contrato..." wire:model.live.debounce.300ms="searchContratos">
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>N° Contrato</th>
                                            <th>Unidad</th>
                                            <th>Fecha Inicio</th>
                                            <th>Precio</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($this->contratos as $contrato)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.contratos.show', $contrato) }}" class="fw-medium">
                                                    #{{ $contrato->id }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <span class="avatar-initial rounded bg-label-secondary">
                                                            <i class="ri ri-motorbike-line"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span class="fw-medium d-block">{{ $contrato->unidad->moto->modelo ?? 'Moto' }}</span>
                                                        <small class="text-muted">{{ $contrato->unidad->color ?? '' }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $contrato->fecha_inicio ? $contrato->fecha_inicio->format('d/m/Y') : 'N/A' }}</td>
                                            <td>${{ number_format($contrato->precio_total, 2) }}</td>
                                            <td>
                                                @php
                                                    $statusClass = match($contrato->estado) {
                                                        'activo' => 'success',
                                                        'completado', 'finalizado' => 'primary',
                                                        'cancelado' => 'danger',
                                                        'mora' => 'warning',
                                                        default => 'secondary'
                                                    };
                                                @endphp
                                                <span class="badge bg-label-{{ $statusClass }}">
                                                    {{ ucfirst($contrato->estado) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.contratos.show', $contrato) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill">
                                                    <i class="ri ri-eye-line"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4">No se encontraron contratos</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer">
                                {{ $this->contratos->links() }}
                            </div>
                        </div>
                    </div>

                    <!-- Pagos Tab -->
                    <div class="tab-pane fade {{ $activeTab === 'pagos' ? 'show active' : '' }}">
                        <div class="card">
                            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Historial de Pagos</h5>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control form-control-sm" placeholder="Buscar pago..." wire:model.live.debounce.300ms="searchPagos">
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Referencia</th>
                                            <th>Fecha</th>
                                            <th>Método</th>
                                            <th>Conceptos</th>
                                            <th class="text-end">Monto</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($this->pagos as $pago)
                                        <tr>
                                            <td>
                                                <span class="fw-medium">{{ $pago->numero_completo }}</span>
                                                @if($pago->referencia)
                                                    <br><small class="text-muted">Ref: {{ $pago->referencia }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $pago->fecha->format('d/m/Y') }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $pago->metodo_pago)) }}</td>
                                            <td>
                                                <small class="text-truncate d-block" style="max-width: 200px;">
                                                    {{ $pago->detalles->pluck('descripcion')->implode(', ') }}
                                                </small>
                                            </td>
                                            <td class="text-end fw-bold text-success">
                                                ${{ number_format($pago->total, 2) }}
                                            </td>
                                            <td>
                                                <span class="badge bg-label-{{ $pago->estado === 'aprobado' ? 'success' : 'warning' }}">
                                                    {{ ucfirst($pago->estado) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.pagos.show', $pago) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill">
                                                    <i class="ri ri-eye-line"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No se encontraron pagos</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer">
                                {{ $this->pagos->links() }}
                            </div>
                        </div>
                    </div>

                    <!-- Obligaciones Pendientes Tab -->
                    <div class="tab-pane fade {{ $activeTab === 'pendientes' ? 'show active' : '' }}">
                        <div class="card">
                            <div class="card-header border-bottom">
                                <h5 class="card-title mb-0">Obligaciones Pendientes</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Vencimiento</th>
                                            <th>Contrato</th>
                                            <th>Descripción</th>
                                            <th class="text-end">Monto Total</th>
                                            <th class="text-end">Pagado</th>
                                            <th class="text-end">Saldo Pendiente</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($this->obligacionesPendientes as $obligacion)
                                        <tr class="{{ $obligacion->fecha_vencimiento < now() ? 'table-danger' : '' }}">
                                            <td>
                                                <span class="fw-medium">{{ $obligacion->fecha_vencimiento->format('d/m/Y') }}</span>
                                                @if($obligacion->fecha_vencimiento < now())
                                                    <br><small class="text-danger fw-bold">Vencido hace {{ $obligacion->fecha_vencimiento->diffInDays(now()) }} días</small>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.contratos.show', $obligacion->contrato_id) }}">
                                                    #{{ $obligacion->contrato_id }}
                                                </a>
                                            </td>
                                            <td>Cuota #{{ $obligacion->numero_cuota }}</td>
                                            <td class="text-end">${{ number_format($obligacion->monto_total, 2) }}</td>
                                            <td class="text-end text-success">${{ number_format($obligacion->monto_pagado, 2) }}</td>
                                            <td class="text-end fw-bold text-danger">${{ number_format($obligacion->saldo_pendiente, 2) }}</td>
                                            <td>
                                                <span class="badge bg-label-{{ $obligacion->estado === 'parcial' ? 'warning' : 'danger' }}">
                                                    {{ ucfirst($obligacion->estado) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.pagos.create', ['cliente_id' => $cliente->id, 'contrato_id' => $obligacion->contrato_id]) }}" 
                                                   class="btn btn-sm btn-primary">
                                                    Pagar
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="text-center">
                                                    <i class="ri ri-checkbox-circle-line fs-1 text-success mb-2"></i>
                                                    <p class="mb-0">¡Excelente! El cliente no tiene obligaciones pendientes.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
