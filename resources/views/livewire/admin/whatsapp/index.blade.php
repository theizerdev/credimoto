<div @if($status === 'connecting' || $status === 'qr_ready') wire:poll.5s="checkStatus" @endif>
    @section('title', 'WhatsApp - Módulo de API')

    {{-- Breadcrumb --}}
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Integraciones /</span> Módulo de API de WhatsApp
    </h4>

    <p class="text-muted mb-4">Gestiona la sesión de WhatsApp de tu empresa, tokens de API, servidores de conexión y alertas automáticas.</p>

    {{-- System Alerts --}}
    @if($error || $connectionError)
        <div class="alert alert-danger alert-dismissible waves-effect waves-light" role="alert">
            {{ $error ?? $connectionError }}
            <button type="button" class="btn-close" wire:click="clearMessages" aria-label="Close"></button>
        </div>
    @endif

    @if($success)
        <div class="alert alert-success alert-dismissible waves-effect waves-light" role="alert">
            {{ $success }}
            <button type="button" class="btn-close" wire:click="clearMessages" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        {{-- Left Column: Configuración de API --}}
        <div class="col-md-5 col-12 mb-4">
            <div class="card h-100">
                <div class="card-header border-bottom mb-3">
                    <h5 class="card-title mb-0">Configuración de API</h5>
                    <small class="text-muted">Configura los detalles de conexión para {{ $empresaNombre ?? 'tu empresa' }}.</small>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="guardarConfiguracion">
                        
                        <div class="d-flex justify-content-between align-items-center border rounded p-3 mb-4">
                            <div>
                                <h6 class="mb-0 fw-medium">Habilitar integración de WhatsApp</h6>
                                <small class="text-muted">Habilitar el envío automático de plantillas.</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="whatsapp_active" wire:model="whatsapp_active">
                            </div>
                        </div>

                        <div class="form-floating form-floating-outline mb-4">
                            <input type="text" class="form-control" id="whatsapp_api_url" wire:model="whatsapp_api_url" placeholder="http://166.1.85.56:3000">
                            <label for="whatsapp_api_url">IP de conexión / URL de API</label>
                            <div class="form-text">Node service base URL.</div>
                        </div>

                        <div class="form-floating form-floating-outline mb-4">
                            <input type="text" class="form-control" id="whatsapp_instance" wire:model="whatsapp_instance" placeholder="driscolls">
                            <label for="whatsapp_instance">WhatsApp Instance Name</label>
                            <div class="form-text">Name of the session/instance in the WhatsApp API engine.</div>
                        </div>

                        <div class="form-floating form-floating-outline mb-4">
                            <input type="number" class="form-control" id="whatsapp_rate_limit" wire:model="whatsapp_rate_limit" placeholder="100">
                            <label for="whatsapp_rate_limit">Límite de mensajes (msg/min)</label>
                            <div class="form-text">Máximo de mensajes enviados por minuto.</div>
                        </div>

                        <div class="mb-4">
                            <div class="input-group input-group-merge">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" id="whatsappApiKey" class="form-control" wire:model="whatsappApiKey">
                                    <label for="whatsappApiKey">Token de API de la empresa</label>
                                </div>
                                <span class="input-group-text cursor-pointer" onclick="navigator.clipboard.writeText('{{ $whatsappApiKey }}'); alert('Token copiado al portapapeles');" title="Copiar Token">
                                    <i class="mdi mdi-content-copy"></i>
                                </span>
                            </div>
                            <div class="form-text mt-1">This credentials token authorizes this company to communicate with the node server.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3 waves-effect waves-light" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="guardarConfiguracion">
                                Guardar Configuración
                            </span>
                            <span wire:loading wire:target="guardarConfiguracion">
                                <span class="spinner-border spinner-border-sm me-1"></span> Guardando...
                            </span>
                        </button>

                        <div class="row g-3">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100 waves-effect" wire:click="generateToken">
                                    <i class="mdi mdi-key-outline me-1"></i> Generate Token
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100 waves-effect" wire:click="syncCompany">
                                    <i class="mdi mdi-server me-1"></i> Sync Company
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right Column: Estado de conexión --}}
        <div class="col-md-7 col-12 mb-4">
            <div class="card h-100">
                <div class="card-header border-bottom mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Estado de conexión</h5>
                        <small class="text-muted">Link and monitor WhatsApp server state.</small>
                    </div>
                    <div>
                        @if($status === 'connected')
                            <span class="badge rounded-pill bg-label-success">Conectado</span>
                        @elseif($status === 'qr_ready')
                            <span class="badge rounded-pill bg-label-warning">Escanear QR</span>
                        @elseif($status === 'connecting')
                            <span class="badge rounded-pill bg-label-info">Conectando...</span>
                        @else
                            <span class="badge rounded-pill bg-label-danger">Desconectado</span>
                        @endif
                    </div>
                </div>
                
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    @if($status === 'connected')
                        <div class="mb-4 mt-2">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-success"><i class="mdi mdi-check fs-2"></i></span>
                            </div>
                            <h4 class="mb-1">Vinculado exitosamente</h4>
                            <p class="text-success fw-medium">Sesión activa en el motor Baileys</p>
                        </div>
                        
                        <div class="card bg-lighter border shadow-none mx-auto mb-4 text-start" style="max-width: 450px;">
                            <div class="card-body p-3">
                                <div class="row border-bottom pb-3 mb-3">
                                    <div class="col-6">
                                        <small class="text-uppercase text-muted d-block mb-1">Cuenta vinculada</small>
                                        <div class="d-flex align-items-center">
                                            <i class="mdi mdi-whatsapp fs-5 me-2 text-muted"></i>
                                            <span class="fw-medium">Cuenta de WhatsApp</span>
                                        </div>
                                    </div>
                                    <div class="col-6 border-start pl-3">
                                        <small class="text-uppercase text-muted d-block mb-1">JID del teléfono</small>
                                        <span class="fw-bold">{{ $user['formatted_id'] ?? ($user['id'] ?? 'Desconocido') }}</span>
                                    </div>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted d-block mb-1">Última sincronización / conexión</small>
                                    <span class="fw-medium">N/D</span>
                                </div>
                            </div>
                        </div>
                    @elseif($status === 'qr_ready')
                        <div class="mb-4 mt-2">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-dark"><i class="mdi mdi-qrcode fs-2"></i></span>
                            </div>
                            <h4 class="mb-1">Vincular Dispositivo</h4>
                            <p class="text-muted">Escanea el código QR con tu aplicación de WhatsApp</p>
                        </div>
                        
                        @if($qrCode)
                            <div class="mx-auto border p-2 rounded mb-4" style="width: fit-content;">
                                <img src="{{ $qrCode }}" alt="WhatsApp QR Code" style="max-width: 200px;">
                            </div>
                        @else
                            <div class="spinner-border text-primary my-4" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        @endif
                    @elseif($status === 'connecting')
                        <div class="mb-4 mt-2">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-info">
                                    <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                                </span>
                            </div>
                            <h4 class="mb-1">Iniciando Sesión...</h4>
                            <p class="text-muted">Conectando con el motor de WhatsApp</p>
                        </div>
                    @else
                        <div class="mb-4 mt-2">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-danger"><i class="mdi mdi-close fs-2"></i></span>
                            </div>
                            <h4 class="mb-1">Sin conexión</h4>
                            <p class="text-muted">La sesión de WhatsApp no está activa</p>
                        </div>
                    @endif

                    <div class="d-flex justify-content-center gap-3">
                        @if($status === 'connected' || $status === 'qr_ready')
                            <button type="button" class="btn btn-outline-secondary waves-effect" wire:click="refresh" wire:loading.attr="disabled">
                                <i class="mdi mdi-refresh me-1"></i> Restablecer sesión
                            </button>
                            <button type="button" class="btn btn-danger waves-effect waves-light" wire:click="disconnect" wire:loading.attr="disabled">
                                <i class="mdi mdi-power me-1"></i> Disconnect
                            </button>
                        @else
                            <button type="button" class="btn btn-primary waves-effect waves-light" wire:click="connect" wire:loading.attr="disabled">
                                <i class="mdi mdi-power-plug me-1"></i> Conectar
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
