<div>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Contrato #{{ $contrato->numero_contrato }}
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
                    <span class="badge {{ $badges[$contrato->estado] ?? 'bg-label-primary' }} ms-2">
                        {{ ucfirst($contrato->estado) }}
                    </span>
                </h5>
                <div>
                    <a href="{{ route('admin.contratos.index') }}" class="btn btn-label-secondary me-2">
                        <i class="ri ri-arrow-left-line me-1"></i> Volver
                    </a>
                    @if($contrato->estado === 'borrador')
                        <button class="btn btn-success" wire:click="changeStatus('activo')"
                                wire:confirm="¿Activar contrato? Esto confirmará la venta.">
                            <i class="ri ri-check-line me-1"></i> Activar
                        </button>
                    @endif
                    @if(!in_array($contrato->estado, ['cancelado', 'completado']))
                        <button class="btn btn-label-danger ms-2" wire:click="changeStatus('cancelado')"
                                wire:confirm="¿Cancelar contrato? La unidad será liberada.">
                            <i class="ri ri-close-circle-line me-1"></i> Cancelar
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Info Cliente y Moto -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">Información del Cliente</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <div>
                            <span class="fw-bold">Nombre:</span> {{ $contrato->cliente->nombre_completo }}
                        </div>
                        <div>
                            <span class="fw-bold">Documento:</span> {{ $contrato->cliente->tipo_documento }} {{ $contrato->cliente->documento }}
                        </div>
                        <div>
                            <span class="fw-bold">Teléfono:</span> {{ $contrato->cliente->telefono }}
                        </div>
                        <div>
                            <span class="fw-bold">Email:</span> {{ $contrato->cliente->email ?? 'N/A' }}
                        </div>
                        <div>
                            <span class="fw-bold">Dirección:</span> {{ $contrato->cliente->direccion ?? 'N/A' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">Detalle de la Unidad</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <div>
                            <span class="fw-bold">Modelo:</span> {{ $contrato->unidad->moto->titulo }}
                        </div>
                        <div>
                            <span class="fw-bold">Color:</span> {{ $contrato->unidad->color_especifico }}
                        </div>
                        <div>
                            <span class="fw-bold">VIN / Chasis:</span> {{ $contrato->unidad->vin }}
                        </div>
                        <div>
                            <span class="fw-bold">Motor:</span> {{ $contrato->unidad->numero_motor }}
                        </div>
                        <div>
                            <span class="fw-bold">Placa:</span> 
                            <span class="badge bg-label-primary">{{ $contrato->unidad->placa ?? 'Por Asignar' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumen Financiero -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Resumen Financiero</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="d-flex flex-column">
                                <span class="text-muted mb-1">Precio Venta</span>
                                <h4 class="mb-0 text-primary">${{ number_format($contrato->precio_venta_final, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="d-flex flex-column">
                                <span class="text-muted mb-1">Cuota Inicial</span>
                                <h4 class="mb-0 text-success">${{ number_format($contrato->cuota_inicial, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="d-flex flex-column">
                                <span class="text-muted mb-1">Monto Financiado</span>
                                <h4 class="mb-0 text-info">${{ number_format($contrato->monto_financiado, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="d-flex flex-column">
                                <span class="text-muted mb-1">Saldo Pendiente</span>
                                <h4 class="mb-0 text-danger">${{ number_format($contrato->saldo_pendiente, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <span class="fw-bold">Plazo:</span> {{ $contrato->plazo_semanas }} semanas ({{ round($contrato->plazo_semanas / 4, 1) }} meses)
                        </div>
                        <div class="col-md-4">
                            <span class="fw-bold">Tasa Anual:</span> {{ $contrato->tasa_interes_anual }}%
                        </div>
                        <div class="col-md-4">
                            <span class="fw-bold">Progreso:</span> {{ $contrato->cuotas_pagadas }} / {{ $contrato->cuotas_totales }} cuotas
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plan de Pagos -->
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Plan de Pagos</h6>
                    @if($contrato->saldo_pendiente > 0 || in_array($contrato->estado, ['activo', 'mora']))
                        <a href="{{ route('admin.contratos.pagar', $contrato->id) }}" class="btn btn-sm btn-primary">
                            <i class="ri ri-money-dollar-circle-line me-1"></i> Registrar Pago
                        </a>
                    @endif
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vencimiento</th>
                                <th>Tipo</th>
                                <th class="text-end">Monto Cuota</th>
                                <th class="text-end">Pagado</th>
                                <th class="text-end">Saldo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contrato->planPagos as $cuota)
                            <tr>
                                <td>{{ $cuota->numero_cuota == 0 ? 'Inicial' : $cuota->numero_cuota }}</td>
                                <td>
                                    {{ $cuota->fecha_vencimiento->format('d/m/Y') }}
                                    @if($cuota->estado == 'pendiente' && $cuota->fecha_vencimiento < now())
                                        <span class="badge bg-label-danger ms-1">Vencido</span>
                                    @endif
                                </td>
                                <td>{{ ucfirst($cuota->tipo_cuota) }}</td>
                                <td class="text-end fw-bold">${{ number_format($cuota->monto_total, 2) }}</td>
                                <td class="text-end text-success">${{ number_format($cuota->monto_pagado, 2) }}</td>
                                <td class="text-end text-danger">${{ number_format($cuota->saldo_pendiente, 2) }}</td>
                                <td>
                                    @php
                                        $statusClass = match($cuota->estado) {
                                            'pagado' => 'bg-label-success',
                                            'parcial' => 'bg-label-info',
                                            'pendiente' => 'bg-label-secondary',
                                            'vencido' => 'bg-label-danger',
                                            default => 'bg-label-primary'
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }}">
                                        {{ ucfirst($cuota->estado) }}
                                    </span>
                                </td>
                                <td>
                                    @if($cuota->saldo_pendiente > 0)
                                        <a href="{{ route('admin.contratos.pagar', $contrato->id) }}" class="btn btn-sm btn-icon btn-label-primary" title="Pagar Cuota">
                                            <i class="ri ri-hand-coin-line"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
