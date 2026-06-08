<div>
    @push('styles')
    <link rel="stylesheet" href="{{ asset('/materialize/assets//vendor/libs/bs-stepper/bs-stepper.css') }}" />
    @endpush

    @push('scripts')
    <script src="{{ asset('/materialize/assets//vendor/libs/bs-stepper/bs-stepper.js') }}"></script>
    @endpush
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Contrato #{{ $numero_contrato }}</h5>
                    <a href="{{ route('admin.contratos.show', $contrato->id) }}" class="btn btn-label-secondary">
                        <i class="ri ri-arrow-left-line me-1"></i> Volver
                    </a>
                </div>

                <!-- Wizard Navigation -->
                <div class="card-body">
                    <div class="bs-stepper wizard-numbered mt-2">
                        <div class="bs-stepper-header">
                            <div class="step {{ $step >= 1 ? 'active' : '' }}" data-target="#datos-generales">
                                <button type="button" class="step-trigger">
                                    <span class="bs-stepper-circle"><i class="ri ri-check-line"></i></span>
                                    <span class="bs-stepper-label">
                                        <span class="bs-stepper-number">01</span>
                                        <span class="d-flex flex-column gap-1 ms-2">
                                            <span class="bs-stepper-title">Datos Generales</span>
                                            <span class="bs-stepper-subtitle">Cliente y Unidad</span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                            <div class="line"></div>
                            <div class="step {{ $step >= 2 ? 'active' : '' }}" data-target="#condiciones">
                                <button type="button" class="step-trigger">
                                    <span class="bs-stepper-circle"><i class="ri ri-check-line"></i></span>
                                    <span class="bs-stepper-label">
                                        <span class="bs-stepper-number">02</span>
                                        <span class="d-flex flex-column gap-1 ms-2">
                                            <span class="bs-stepper-title">Condiciones</span>
                                            <span class="bs-stepper-subtitle">Financiamiento</span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                            <div class="line"></div>
                            <div class="step {{ $step >= 3 ? 'active' : '' }}" data-target="#confirmacion">
                                <button type="button" class="step-trigger">
                                    <span class="bs-stepper-circle"><i class="ri ri-check-line"></i></span>
                                    <span class="bs-stepper-label">
                                        <span class="bs-stepper-number">03</span>
                                        <span class="d-flex flex-column gap-1 ms-2">
                                            <span class="bs-stepper-title">Confirmación</span>
                                            <span class="bs-stepper-subtitle">Revisar y Guardar</span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div class="bs-stepper-content">
                            <form wire:submit.prevent="nextStep">
                                <!-- Paso 1: Datos Generales -->
                                @if($step == 1)
                                <div id="datos-generales" class="content active dstepper-block">
                                    <div class="content-header mb-4">
                                        <h6 class="mb-0">Información Inicial</h6>
                                        <small>Seleccione la empresa, cliente y unidad para el contrato.</small>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-floating form-floating-outline">
                                                <select wire:model.live="empresa_id" id="empresa_id" class="form-select @error('empresa_id') is-invalid @enderror">
                                                    <option value="">Seleccione...</option>
                                                    @foreach($empresas as $empresa)
                                                        <option value="{{ $empresa->id }}">{{ $empresa->razon_social }}</option>
                                                    @endforeach
                                                </select>
                                                <label for="empresa_id">Empresa</label>
                                                @error('empresa_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-floating form-floating-outline">
                                                <select wire:model="sucursal_id" id="sucursal_id" class="form-select @error('sucursal_id') is-invalid @enderror"
                                                        {{ empty($empresa_id) ? 'disabled' : '' }}>
                                                    <option value="">Seleccione...</option>
                                                    @foreach($sucursales as $sucursal)
                                                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                                                    @endforeach
                                                </select>
                                                <label for="sucursal_id">Sucursal</label>
                                                @error('sucursal_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-floating form-floating-outline">
                                                <select wire:model="cliente_id" id="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror"
                                                        {{ empty($empresa_id) ? 'disabled' : '' }}>
                                                    <option value="">Seleccione un cliente...</option>
                                                    @if($clientes)
                                                        @foreach($clientes as $cliente)
                                                            <option value="{{ $cliente->id }}">
                                                                {{ $cliente->nombre_completo }} ({{ $cliente->documento }})
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                <label for="cliente_id">Cliente</label>
                                                @error('cliente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="form-text">
                                                ¿Cliente nuevo? <a href="{{ route('admin.clientes.create') }}" target="_blank">Regístralo aquí</a> y recarga la página.
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-floating form-floating-outline">
                                                <select wire:model.live="moto_unidad_id" id="moto_unidad_id" class="form-select @error('moto_unidad_id') is-invalid @enderror"
                                                        {{ empty($empresa_id) ? 'disabled' : '' }}>
                                                    <option value="">Seleccione una unidad...</option>
                                                    @if($unidades_disponibles)
                                                        @foreach($unidades_disponibles as $unidad)
                                                            <option value="{{ $unidad->id }}">
                                                                {{ $unidad->moto->titulo }} - {{ $unidad->color_especifico }} ({{ $unidad->vin }})
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                <label for="moto_unidad_id">Unidad (Moto)</label>
                                                @error('moto_unidad_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-12 d-flex justify-content-between mt-4">
                                            <button class="btn btn-outline-secondary btn-prev" disabled type="button">
                                                <i class="ri ri-arrow-left-line me-1"></i> Anterior
                                            </button>
                                            <button type="submit" class="btn btn-primary btn-next">
                                                Siguiente <i class="ri ri-arrow-right-line ms-1"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Paso 2: Condiciones Financieras -->
                                @if($step == 2)
                                <div id="condiciones" class="content active dstepper-block">
                                    <div class="content-header mb-4">
                                        <h6 class="mb-0">Condiciones del Crédito</h6>
                                        <small>Configure los parámetros financieros para calcular las cuotas.</small>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12 mb-3">
                                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                                <i class="ri ri-alert-line me-2"></i>
                                                <div>
                                                    <strong>Advertencia:</strong> Al modificar las condiciones financieras, se recalculará todo el plan de pagos. Las cuotas existentes serán eliminadas y reemplazadas.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12 mb-2">
                                            <div class="form-floating form-floating-outline">
                                                <input disabled type="text" step="0.01" id="numero_contrato" class="form-control @error('numero_contrato') is-invalid @enderror"
                                                       wire:model="numero_contrato" placeholder="0.00" />
                                                <label for="numero_contrato">Número de Contrato</label>
                                                @error('numero_contrato') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-floating form-floating-outline">
                                                <input type="number" step="0.01" id="precio_venta_final" class="form-control @error('precio_venta_final') is-invalid @enderror"
                                                       wire:model.live.debounce.500ms="precio_venta_final" placeholder="0.00" />
                                                <label for="precio_venta_final">Precio Venta Final ($)</label>
                                                @error('precio_venta_final') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-floating form-floating-outline">
                                                <input type="number" step="0.01" id="cuota_inicial" class="form-control @error('cuota_inicial') is-invalid @enderror"
                                                       wire:model.live.debounce.500ms="cuota_inicial" placeholder="0.00" />
                                                <label for="cuota_inicial">Cuota Inicial ($)</label>
                                                @error('cuota_inicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-floating form-floating-outline">
                                                <input type="text" id="monto_financiado" class="form-control bg-light" value="{{ number_format($monto_financiado, 2) }}" readonly />
                                                <label for="monto_financiado">Monto a Financiar ($)</label>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-floating form-floating-outline">
                                                <input type="number" step="0.01" id="tasa_interes_anual" class="form-control @error('tasa_interes_anual') is-invalid @enderror"
                                                       wire:model.live.debounce.500ms="tasa_interes_anual" placeholder="12" />
                                                <label for="tasa_interes_anual">Tasa Interés Anual (%)</label>
                                                @error('tasa_interes_anual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-floating form-floating-outline">
                                                <input type="number" id="plazo_semanas" class="form-control @error('plazo_semanas') is-invalid @enderror"
                                                       wire:model.live.debounce.500ms="plazo_semanas" placeholder="48" />
                                                <label for="plazo_semanas">Duración (Semanas)</label>
                                                @error('plazo_semanas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            @if($plazo_semanas > 0)
                                                <small class="text-muted mt-1 d-block">
                                                    <i class="ri-information-line"></i> Equivale a {{ round($plazo_semanas / 4, 1) }} meses
                                                </small>
                                            @endif
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-floating form-floating-outline">
                                                <select wire:model.live="frecuencia_pago" id="frecuencia_pago" class="form-select @error('frecuencia_pago') is-invalid @enderror">
                                                    <option value="mensual">Mensual</option>
                                                    <option value="quincenal">Quincenal</option>
                                                    <option value="semanal">Semanal</option>
                                                </select>
                                                <label for="frecuencia_pago">Frecuencia de Pago</label>
                                                @error('frecuencia_pago') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        @if($frecuencia_pago === 'mensual')
                                        <div class="col-md-12">
                                            <div class="form-floating form-floating-outline">
                                                <input type="number" min="1" max="31" id="dia_pago_mensual" class="form-control @error('dia_pago_mensual') is-invalid @enderror"
                                                       wire:model="dia_pago_mensual" placeholder="5" />
                                                <label for="dia_pago_mensual">Día de Pago Mensual</label>
                                                @error('dia_pago_mensual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        @endif

                                        <div class="col-md-12">
                                            <div class="form-floating form-floating-outline">
                                                <input type="date" id="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror"
                                                       wire:model="fecha_inicio" />
                                                <label for="fecha_inicio">Fecha Inicio Contrato</label>
                                                @error('fecha_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="card bg-label-primary shadow-none">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title mb-1 text-primary">Resumen Estimado</h6>
                                                    <div class="row">
                                                        <div class="col-md-4 d-flex justify-content-between">
                                                            <span>Frecuencia:</span>
                                                            <span class="fw-bold">{{ ucfirst($frecuencia_pago) }}</span>
                                                        </div>
                                                        <div class="col-md-4 d-flex justify-content-between">
                                                            <span>Total Cuotas:</span>
                                                            <span class="fw-bold">{{ $total_cuotas_calculadas }}</span>
                                                        </div>
                                                        <div class="col-md-4 d-flex justify-content-between">
                                                            <span>Cuota {{ ucfirst($frecuencia_pago) }} Aprox:</span>
                                                            <span class="fw-bold fs-5">${{ number_format($cuota_estimada, 2) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary btn-prev" wire:click="prevStep">
                                                <i class="ri ri-arrow-left-line me-1"></i> Anterior
                                            </button>
                                            <button type="submit" class="btn btn-primary btn-next">
                                                Calcular y Siguiente <i class="ri ri-arrow-right-line ms-1"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Paso 3: Confirmación -->
                                @if($step == 3)
                                <div id="confirmacion" class="content active dstepper-block">
                                    <div class="content-header mb-4">
                                        <h6 class="mb-0">Revisión y Confirmación</h6>
                                        <small>Verifique el plan de pagos antes de guardar los cambios.</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-12 mb-4">
                                            <h6 class="mb-2 text-body">Plan de Pagos Proyectado</h6>

                                            <div class="table-responsive border rounded">
                                                <table class="table table-sm table-striped">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Fecha</th>
                                                            <th class="text-end">Capital</th>
                                                            <th class="text-end">Interés</th>
                                                            <th class="text-end">Total Cuota</th>
                                                            <th class="text-end">Pagado</th>
                                                            <th class="text-end">Saldo Restante</th>
                                                            <th>Estado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($plan_proyectado as $cuota)
                                                        <tr>
                                                            <td>{{ $cuota['numero'] == 0 ? 'Inicial' : $cuota['numero'] }}</td>
                                                            <td>{{ \Carbon\Carbon::parse($cuota['fecha'])->format('d/m/Y') }}</td>
                                                            <td class="text-end">${{ number_format($cuota['monto_capital'], 2) }}</td>
                                                            <td class="text-end">${{ number_format($cuota['monto_interes'], 2) }}</td>
                                                            <td class="text-end fw-bold">${{ number_format($cuota['total'], 2) }}</td>
                                                            <td class="text-end text-success">${{ number_format($cuota['monto_pagado_aplicado'] ?? 0, 2) }}</td>
                                                            <td class="text-end text-muted">${{ number_format($cuota['saldo'], 2) }}</td>
                                                            <td class="text-capitalize">{{ $cuota['estado'] ?? 'pendiente' }}</td>
                                                        </tr>
                                                        @if(!empty($cuota['pagos_aplicados']))
                                                        <tr>
                                                            <td colspan="8" class="small text-muted">
                                                                Pagos aplicados:
                                                                @foreach($cuota['pagos_aplicados'] as $p)
                                                                    <span class="badge bg-light text-dark">{{ optional(\Carbon\Carbon::parse($p['fecha']))->format('d/m/Y') ?? '-' }}: ${{ number_format($p['monto'], 2) }}</span>
                                                                @endforeach
                                                            </td>
                                                        </tr>
                                                        @endif
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="col-12 mb-4">
                                            <div class="form-floating form-floating-outline">
                                                <textarea class="form-control" wire:model="observaciones" rows="3"
                                                          placeholder="Notas adicionales sobre el contrato..."></textarea>
                                                <label for="observaciones">Observaciones</label>
                                                @error('observaciones') <span class="text-danger">{{ $message }}</span> @enderror
                                            </div>
                                        </div>

                                        <div class="col-12 d-flex justify-content-between mt-4">
                                            <button type="button" class="btn btn-outline-secondary btn-prev" wire:click="prevStep">
                                                <i class="ri ri-arrow-left-line me-1"></i> Anterior
                                            </button>
                                            <button type="button" class="btn btn-success btn-submit" wire:click="save"
                                                    wire:confirm="¿Estás seguro de actualizar este contrato? El plan de pagos será recalculado y reemplazado.">
                                                <i class="ri ri-check-double-line me-1"></i> Guardar Cambios
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
