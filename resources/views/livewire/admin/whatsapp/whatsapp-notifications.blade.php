<div>
    @section('title', 'Notificaciones WhatsApp')

    @push('styles')
    <style>
        .wa-config-hero { background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color:#fff; border-radius:.75rem; padding:1.4rem 1.6rem; }
        .wa-config-hero h2 { color:#fff; margin:0; }
        .wa-config-hero p { opacity:.9; margin:0; }
        .wa-stat-card { border:1px solid rgba(0,0,0,.06); border-radius:.65rem; padding:.9rem 1rem; display:flex; align-items:center; gap:.85rem; height:100%; background:#fff; }
        .wa-stat-card .stat-icon { width:44px; height:44px; border-radius:11px; flex:0 0 44px; display:flex; align-items:center; justify-content:center; font-size:1.15rem; }
        .wa-stat-card .stat-value { font-size:1.35rem; font-weight:600; line-height:1; }
        .wa-stat-card .stat-label { font-size:.72rem; color:var(--bs-secondary-color); text-transform:uppercase; letter-spacing:.4px; font-weight:600; }
        .wa-module-card { border:1px solid rgba(0,0,0,.07); border-radius:.65rem; background:#fff; height:100%; }
        .wa-action-row { border-top:1px solid rgba(0,0,0,.06); padding:.85rem 1rem; }
        .wa-action-row:first-child { border-top:0; }
        .wa-recipient-switch { min-width:140px; }
        .nav-pills .nav-link { cursor:pointer; }
    </style>
    @endpush

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.index') }}" class="text-decoration-none">WhatsApp</a></li>
            <li class="breadcrumb-item active" aria-current="page">Notificaciones</li>
        </ol>
    </nav>

    <div class="wa-config-hero mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="fw-semibold"><i class="ri ri-notification-3-line me-2"></i>Notificaciones WhatsApp</h2>
            <p class="mt-1 mb-0">Control por modulo, accion y destinatario para los envios automaticos.</p>
        </div>
        <button type="button" class="btn btn-light btn-sm" wire:click="save" wire:loading.attr="disabled" @disabled(!$canManage)>
            <span wire:loading.remove wire:target="save"><i class="ri ri-save-line me-1"></i>Guardar</span>
            <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span>Guardando...</span>
        </button>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri ri-checkbox-circle-line me-2"></i>{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(!$empresaId)
        <div class="alert alert-warning">
            <i class="ri ri-alert-line me-2"></i>No hay una empresa disponible para configurar notificaciones.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="wa-stat-card">
                <div class="stat-icon bg-label-primary"><i class="ri ri-list-check-3"></i></div>
                <div>
                    <div class="stat-label">Controles</div>
                    <div class="stat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="wa-stat-card">
                <div class="stat-icon bg-label-success"><i class="ri ri-toggle-line"></i></div>
                <div>
                    <div class="stat-label">Activos</div>
                    <div class="stat-value">{{ $stats['enabled'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="wa-stat-card">
                <div class="stat-icon bg-label-info"><i class="ri ri-plug-line"></i></div>
                <div>
                    <div class="stat-label">Conectados</div>
                    <div class="stat-value">{{ $stats['connected'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="wa-stat-card">
                <div class="stat-icon bg-label-warning"><i class="ri ri-time-line"></i></div>
                <div>
                    <div class="stat-label">Pendientes</div>
                    <div class="stat-value">{{ $stats['pending'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <ul class="nav nav-pills flex-column flex-sm-row gap-2" role="tablist">
                    @foreach($sectors as $sectorKey => $sector)
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link {{ $activeSector === $sectorKey ? 'active' : '' }}" wire:click="setActiveSector('{{ $sectorKey }}')">
                                <i class="ri {{ $sector['icon'] }} me-1"></i>{{ $sector['label'] }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                @if($activeSector && $canManage)
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm" wire:click="activateSector('{{ $activeSector }}')">
                            <i class="ri ri-check-double-line me-1"></i>Activar sector
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="deactivateSector('{{ $activeSector }}')">
                            <i class="ri ri-close-circle-line me-1"></i>Desactivar sector
                        </button>
                    </div>
                @endif
            </div>

            @if($activeSector && isset($sectors[$activeSector]))
                <div class="row g-3">
                    @foreach($sectors[$activeSector]['modules'] as $moduleKey => $module)
                        @php($moduleStats = $this->moduleStats($moduleKey, $module))
                        <div class="col-xl-6">
                            <div class="wa-module-card">
                                <div class="p-3 d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <h6 class="mb-1">{{ $module['label'] }}</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge bg-label-primary">{{ $moduleStats['enabled'] }}/{{ $moduleStats['total'] }} activos</span>
                                            <span class="badge bg-label-info">{{ $moduleStats['connected'] }} conectados</span>
                                        </div>
                                    </div>
                                    @if($canManage)
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="ri ri-more-2-line"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button type="button" class="dropdown-item" wire:click="activateModule('{{ $activeSector }}', '{{ $moduleKey }}')">
                                                    <i class="ri ri-check-line me-1 text-success"></i>Activar modulo
                                                </button>
                                                <button type="button" class="dropdown-item" wire:click="deactivateModule('{{ $activeSector }}', '{{ $moduleKey }}')">
                                                    <i class="ri ri-close-line me-1 text-secondary"></i>Desactivar modulo
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    @foreach($module['actions'] as $actionKey => $action)
                                        <div class="wa-action-row">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                                <div>
                                                    <div class="fw-semibold">{{ $action['label'] }}</div>
                                                    @if($action['connected'])
                                                        <span class="badge bg-label-success"><i class="ri ri-plug-line me-1"></i>Conectado</span>
                                                    @else
                                                        <span class="badge bg-label-warning"><i class="ri ri-time-line me-1"></i>Pendiente de conexion</span>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-wrap gap-3">
                                                    @foreach($action['recipients'] as $recipientKey)
                                                        <div class="form-check form-switch wa-recipient-switch mb-0">
                                                            <input class="form-check-input"
                                                                   type="checkbox"
                                                                   id="wa_{{ $moduleKey }}_{{ $actionKey }}_{{ $recipientKey }}"
                                                                   wire:model.live="settings.{{ $moduleKey }}.{{ $actionKey }}.{{ $recipientKey }}"
                                                                   @disabled(!$canManage)>
                                                            <label class="form-check-label" for="wa_{{ $moduleKey }}_{{ $actionKey }}_{{ $recipientKey }}">
                                                                {{ $recipientLabels[$recipientKey] ?? ucfirst($recipientKey) }}
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
