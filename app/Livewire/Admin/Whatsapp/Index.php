<?php

namespace App\Livewire\Admin\Whatsapp;

use Livewire\Component;
use App\Traits\HasDynamicLayout;
use App\Services\WhatsAppService;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;

class Index extends Component
{
    use HasDynamicLayout;

    public $status = 'disconnected';
    public $user = null;
    public $qrCode = null;
    public $connectionError = null;
    public $error = null;
    public $success = null;

    // Configuración de la API
    public $companyId = null;
    public $empresaNombre = null;
    public $whatsapp_active = true;
    public $whatsapp_api_url = '';
    public $whatsapp_instance = '';
    public $whatsapp_rate_limit = 100;
    public $whatsappApiKey = null;

    // Mensaje de prueba
    public $testPhone = '';
    public $testMessage = '¡Hola! Este es un mensaje de prueba desde el panel de WhatsApp.';
    public $isSendingTest = false;
    public $isConnecting = false;
    public $isDisconnecting = false;

    public function mount()
    {
        if (!Auth::user()->can('access whatsapp')) {
            abort(403, 'No tienes permiso para acceder a WhatsApp.');
        }

        $this->initializeWhatsApp();
        $this->checkStatus();
    }

    public function initializeWhatsApp()
    {
        $empresa = auth()->user()->empresa ?? Empresa::find(auth()->user()->empresa_id) ?? null;
        
        if ($empresa) {
            $this->companyId = $empresa->id;
            $this->whatsappApiKey = $empresa->whatsapp_api_key ?? Empresa::generateApiKey();
            $this->empresaNombre = $empresa->razon_social;
            $this->whatsapp_active = (bool) ($empresa->whatsapp_active ?? true);
            $this->whatsapp_api_url = $empresa->whatsapp_api_url ?? config('whatsapp.api_url', 'http://82.165.213.124:8092');
            $this->whatsapp_instance = $empresa->whatsapp_instance ?? ('empresa_' . $empresa->id);
            $this->whatsapp_rate_limit = $empresa->whatsapp_rate_limit ?? 100;
        } else {
            $this->connectionError = 'Usuario sin empresa asignada.';
        }
    }

    public function checkStatus()
    {
        if (!$this->whatsappApiKey) {
            $this->status = 'error';
            $this->connectionError = 'No se ha configurado la API Key de WhatsApp para esta empresa.';
            return;
        }

        try {
            $service = WhatsAppService::forCredentials([
                'api_url' => $this->whatsapp_api_url,
                'api_key' => $this->whatsappApiKey,
                'empresa_id' => $this->companyId,
                'instance' => $this->whatsapp_instance,
            ]);

            $statusData = $service->getStatus();

            if ($statusData === ['_error' => 'service_unavailable']) {
                $this->status = 'service_unavailable';
                $this->connectionError = 'No se puede conectar al servicio de WhatsApp. Verifique la URL de la API.';
                return;
            }

            if ($statusData && isset($statusData['isConnected'])) {
                $this->status = $statusData['isConnected'] ? 'connected' : ($statusData['status'] === 'qr' ? 'qr_ready' : 'disconnected');
                $this->user = $statusData['user'] ?? null;
                $this->qrCode = $statusData['qrCode'] ?? null;

                if ($this->user && isset($this->user['id'])) {
                    $this->user['formatted_id'] = explode(':', $this->user['id'])[0];
                }
                $this->connectionError = null;
            } else {
                $this->status = 'disconnected';
                $this->user = null;
                $this->qrCode = null;
            }
        } catch (\Exception $e) {
            $this->status = 'error';
            $this->connectionError = 'Error al verificar estado: ' . $e->getMessage();
        }
    }

    public function guardarConfiguracion()
    {
        $this->validate([
            'whatsapp_api_url' => 'required|url',
            'whatsapp_instance' => 'required|string|max:100',
            'whatsapp_rate_limit' => 'required|integer|min:1|max:1000',
            'whatsappApiKey' => 'required|string',
        ]);

        try {
            $empresa = Empresa::find($this->companyId);
            if ($empresa) {
                $updateData = [
                    'whatsapp_active' => $this->whatsapp_active,
                    'whatsapp_instance' => $this->whatsapp_instance,
                    'whatsapp_rate_limit' => $this->whatsapp_rate_limit,
                    'whatsapp_api_key' => $this->whatsappApiKey,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('empresas', 'whatsapp_api_url')) {
                    $updateData['whatsapp_api_url'] = rtrim($this->whatsapp_api_url, '/');
                }

                $empresa->update($updateData);
            }

            $this->success = 'Configuración guardada exitosamente.';
            $this->checkStatus();
        } catch (\Exception $e) {
            $this->error = 'Error al guardar configuración: ' . $e->getMessage();
        }
    }

    public function connect()
    {
        if (!$this->whatsappApiKey) {
            $this->error = 'No se ha configurado la API Key.';
            return;
        }

        $this->isConnecting = true;
        $this->error = null;
        $this->success = null;

        try {
            $service = WhatsAppService::forCredentials([
                'api_url' => $this->whatsapp_api_url,
                'api_key' => $this->whatsappApiKey,
                'empresa_id' => $this->companyId,
                'instance' => $this->whatsapp_instance,
            ]);

            $service->connect();
            $this->success = 'Iniciando conexión WhatsApp. Espere el código QR...';
            $this->checkStatus();
        } catch (\Exception $e) {
            $this->error = 'Error al conectar: ' . $e->getMessage();
        }

        $this->isConnecting = false;
    }

    public function disconnect()
    {
        $this->isDisconnecting = true;
        $this->error = null;

        try {
            $service = WhatsAppService::forCredentials([
                'api_url' => $this->whatsapp_api_url,
                'api_key' => $this->whatsappApiKey,
                'empresa_id' => $this->companyId,
                'instance' => $this->whatsapp_instance,
            ]);

            $service->disconnect();
            $this->status = 'disconnected';
            $this->user = null;
            $this->qrCode = null;
            $this->success = 'Instancia desconectada correctamente.';
        } catch (\Exception $e) {
            $this->error = 'Error al desconectar: ' . $e->getMessage();
        }

        $this->isDisconnecting = false;
    }

    public function enviarMensajePrueba()
    {
        $this->validate([
            'testPhone' => 'required|string|min:8',
            'testMessage' => 'required|string',
        ]);

        $this->isSendingTest = true;
        $this->error = null;
        $this->success = null;

        try {
            $service = WhatsAppService::forCredentials([
                'api_url' => $this->whatsapp_api_url,
                'api_key' => $this->whatsappApiKey,
                'empresa_id' => $this->companyId,
                'instance' => $this->whatsapp_instance,
            ]);

            $result = $service->sendMessage($this->testPhone, $this->testMessage);

            if ($result) {
                $this->success = 'Mensaje de prueba enviado exitosamente a ' . $this->testPhone;
            } else {
                $this->error = 'No se pudo enviar el mensaje de prueba. Asegúrese de que el estado sea Conectado.';
            }
        } catch (\Exception $e) {
            $this->error = 'Error al enviar mensaje de prueba: ' . $e->getMessage();
        }

        $this->isSendingTest = false;
    }

    public function generateToken()
    {
        $this->whatsappApiKey = Empresa::generateApiKey();
        $this->success = 'Nuevo token generado. Recuerda guardar la configuración.';
        $this->error = null;
    }

    public function syncCompany()
    {
        $this->checkStatus();
        $this->success = 'Sincronización completada con el motor de WhatsApp.';
        $this->error = null;
    }

    public function refresh()
    {
        $this->checkStatus();
    }

    public function clearMessages()
    {
        $this->error = null;
        $this->success = null;
        $this->connectionError = null;
    }

    public function getStatusColorProperty()
    {
        return match ($this->status) {
            'connected' => 'success',
            'connecting', 'qr_ready' => 'warning',
            'service_unavailable' => 'secondary',
            'error' => 'danger',
            default => 'danger'
        };
    }

    public function getStatusIconProperty()
    {
        return match ($this->status) {
            'connected' => 'ri ri-checkbox-circle-fill',
            'connecting' => 'ri ri-loader-4-line',
            'qr_ready' => 'ri ri-qr-code-line',
            'service_unavailable' => 'ri ri-wifi-off-line',
            default => 'ri ri-close-circle-fill'
        };
    }

    public function getStatusTextProperty()
    {
        return match ($this->status) {
            'connected' => 'Conectado',
            'connecting' => 'Conectando...',
            'qr_ready' => 'Escanear QR',
            'service_unavailable' => 'Servicio No Disponible',
            default => 'Desconectado'
        };
    }

    public function render()
    {
        return $this->renderWithLayout('livewire.admin.whatsapp.index', [
            'statusColor' => $this->statusColor,
            'statusIcon' => $this->statusIcon,
            'statusText' => $this->statusText
        ], [
            'title' => 'WhatsApp - Integración y Diagnóstico',
            'description' => 'Panel de control para vinculación de WhatsApp API',
        ]);
    }
}