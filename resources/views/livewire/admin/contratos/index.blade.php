<div>
    <div class="row">
        <!-- Estadísticas -->
        <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">{{ $totalContratos }}</h4>
                            <p class="mb-0">Total Contratos</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ri ri-file-list-3-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">{{ $contratosActivos }}</h4>
                            <p class="mb-0">Activos / Mora</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ri ri-checkbox-circle-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">{{ $contratosMora }}</h4>
                            <p class="mb-0">En Mora</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="ri ri-alarm-warning-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-sm-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">{{ $contratosEliminados }}</h4>
                            <p class="mb-0">Contratos Eliminados</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-danger">
                                <i class="ri ri-delete-bin-2-line ri-24px"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <ul class="nav nav-pills" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ !$showDeleted ? 'active' : '' }}" 
                                    id="contratos-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#contratos" 
                                    type="button" 
                                    role="tab" 
                                    wire:click="setShowDeleted(false)">
                                Contratos Activos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $showDeleted ? 'active' : '' }}" 
                                    id="eliminados-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#eliminados" 
                                    type="button" 
                                    role="tab" 
                                    wire:click="setShowDeleted(true)">
                                Contratos Eliminados
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1">
                                {{ $showDeleted ? 'Contratos Eliminados' : 'Gestión de Contratos' }}
                            </h5>
                            <p class="mb-0">
                                {{ $showDeleted ? 'Lista de contratos eliminados que pueden ser restaurados' : 'Administra los créditos y contratos de venta' }}
                            </p>
                        </div>
                        @can('create contratos')
                        <div>
                            @unless($showDeleted)
                            <a href="{{ route('admin.contratos.create') }}" class="btn btn-primary">
                                <i class="ri ri-add-line"></i> Nuevo Contrato
                            </a>
                            @endunless
                        </div>
                        @endcan
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card-header border-bottom">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" placeholder="Contrato, cliente, placa..."
                                   wire:model.live.debounce.300ms="search">
                        </div>

                        @unless($showDeleted)
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" wire:model.live="estado">
                                <option value="">Todos los estados</option>
                                <option value="borrador">Borrador</option>
                                <option value="activo">Activo</option>
                                <option value="mora">En Mora</option>
                                <option value="completado">Completado</option>
                                <option value="cancelado">Cancelado</option>
                                <option value="reposicion">Reposición</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Empresa</label>
                            <select class="form-select" wire:model.live="empresa_id">
                                <option value="">Todas las empresas</option>
                                @foreach($empresas as $empresa)
                                    <option value="{{ $empresa->id }}">{{ $empresa->razon_social }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endunless

                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="button" class="btn btn-label-secondary" wire:click="clearFilters">
                                <i class="ri ri-eraser-line"></i> Limpiar
                            </button>
                            <button type="button" class="btn btn-label-success" wire:click="export">
                                <i class="mdi mdi-file-excel"></i> Exportar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-datatable table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th wire:click="sort('numero_contrato')" style="cursor: pointer;">
                                    Contrato
                                    @if(!$showDeleted && $sortBy === 'numero_contrato')
                                        <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </th>
                                <th>Cliente</th>
                                <th>Moto / Placa</th>
                                @unless($showDeleted)
                                <th wire:click="sort('monto_financiado')" style="cursor: pointer;">
                                    Financiamiento
                                    @if($sortBy === 'monto_financiado')
                                        <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </th>
                                <th wire:click="sort('saldo_pendiente')" style="cursor: pointer;">
                                    Saldo
                                    @if($sortBy === 'saldo_pendiente')
                                        <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </th>
                                <th wire:click="sort('estado')" style="cursor: pointer;">
                                    Estado
                                    @if($sortBy === 'estado')
                                        <i class="ri ri-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-line"></i>
                                    @endif
                                </th>
                                @endunless
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contratos as $contrato)
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">#{{ $contrato->numero_contrato }}</span>
                                    <br>
                                    <small class="text-muted">{{ $contrato->fecha_inicio->format('d/m/Y') }}</small>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold">{{ $contrato->cliente->nombre_completo ?? 'N/A' }}</span>
                                        <small class="text-muted">{{ $contrato->cliente->documento ?? 'N/A' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-truncate" style="max-width: 150px;" title="{{ $contrato->unidad->moto->titulo ?? 'N/A' }}">
                                            {{ $contrato->unidad->moto->titulo ?? 'N/A' }}
                                        </span>
                                        <small class="text-muted">{{ $contrato->unidad->placa ?? 'S/P' }}</small>
                                    </div>
                                </td>
                                @unless($showDeleted)
                                <td>
                                    ${{ number_format($contrato->monto_financiado, 2) }}
                                    <br>
                                    <small class="text-muted">{{ $contrato->plazo_semanas }} sem. ({{ round($contrato->plazo_semanas / 4, 1) }} meses)</small>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger">${{ number_format($contrato->saldo_pendiente, 2) }}</span>
                                    <br>
                                    <small class="text-muted">
                                        {{ $contrato->cuotas_pagadas }}/{{ $contrato->cuotas_totales }} cuotas
                                    </small>
                                </td>
                                <td>
                                    @php
                                        $badges = [
                                            'borrador' => 'bg-label-secondary',
                                            'activo' => 'bg-label-success',
                                            'completado' => 'bg-label-info',
                                            'cancelado' => 'bg-label-dark',
                                            'mora' => 'bg-label-warning',
                                            'reposicion' => 'bg-label-danger'
                                        ];
                                    @endphp
                                    
                                    <!-- Toggle switch for active/inactive status -->
                                    @if(in_array($contrato->estado, ['activo', 'cancelado']))
                                        <div class="d-flex align-items-center">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="statusToggle{{ $contrato->id }}"
                                                       wire:loading.attr="disabled"
                                                       @if($contrato->estado === 'activo') checked @endif
                                                       wire:click="toggleContractStatus({{ $contrato->id }})"
                                                       wire:confirm="¿Está seguro de que desea cambiar el estado de este contrato?">
                                            </div>
                                            <span class="badge ms-2 {{ $badges[$contrato->estado] ?? 'bg-label-primary' }}">
                                                {{ ucfirst($contrato->estado) }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="badge {{ $badges[$contrato->estado] ?? 'bg-label-primary' }}">
                                            {{ ucfirst($contrato->estado) }}
                                        </span>
                                    @endif
                                </td>
                                @endunless
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                            <i class="ri ri-more-2-line"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.contratos.show', $contrato->id) }}">
                                                <i class="ri ri-eye-line me-1"></i> Ver Detalle
                                            </a>
                                      
                                            @can('edit contratos')
                                            <a class="dropdown-item" href="{{ route('admin.contratos.edit', $contrato->id) }}">
                                                <i class="ri ri-pencil-line me-1"></i> Editar
                                            </a>
                                            @endcan
                                           
                                             @if($contrato->deleted_at != null)

                                              
                                            <button type="button" class="dropdown-item text-success"
                                                    wire:click="restore({{ $contrato->id }})"
                                                    wire:confirm="¿Estás seguro de que deseas restaurar este contrato?">
                                                <i class="ri ri-refresh-line me-1"></i> Restaurar
                                            </button>
                                            
                                            @else
                                            <button type="button" class="dropdown-item text-danger"
                                                    wire:click="delete({{ $contrato->id }})"
                                                    wire:confirm="¿Estás seguro de que deseas eliminar este contrato?">
                                                <i class="ri ri-eraser-line me-1"></i> Eliminar
                                            </button>
                                            
                                            @endif
                                            
                                             
                                           
                                            
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $showDeleted ? 5 : 8 }}" class="text-center">
                                    @if($showDeleted)
                                        No se encontraron contratos eliminados que coincidan con los filtros
                                    @else
                                        No se encontraron contratos que coincidan con los filtros
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="card-footer">
                   {{ $contratos->links('livewire.pagination') }}
                </div>
            </div>
        </div>
    </div>
</div>