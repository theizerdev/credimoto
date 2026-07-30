<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private $baseUrl;


    private $apiKey;


    private $companyId;

    private $instanceName;

    private $timeout;

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Constructor del servicio WhatsApp
     *
     * @param  Empresa|int|null  $empresa  - Empresa, ID de empresa, o null para usar la del usuario actual
     *
     * @param  Empresa|int|null  $empresa  - Empresa, ID de empresa, o null para usar la del usuario actual
     */
    public function __construct($empresa = null)
    {
        $this->baseUrl = config('whatsapp.api_url', 'http://82.165.213.124:8092');
        $this->baseUrl = config('whatsapp.api_url', 'http://82.165.213.124:8092');
        $this->timeout = config('whatsapp.timeout', 30);

        if (is_array($empresa)) {
            $this->resolveCredentials($empresa);
        } else {
            $this->resolveCompany($empresa);
        }
    }

    public static function forCredentials(array $credentials): self
    {
        return new self($credentials);
    }

    /**
     * Resuelve las credenciales provistas directamente
     */
    private function resolveCredentials(array $credentials): void
    {
        if (! empty($credentials['api_url'])) {
            $this->baseUrl = rtrim($credentials['api_url'], '/');
        }

        $this->timeout = $credentials['timeout'] ?? $this->timeout;
        $this->companyId = $credentials['empresa_id'] ?? $credentials['company_id'] ?? 1;
        $this->apiKey = $credentials['api_key'] ?? $credentials['apiKey'] ?? null;
        $this->instanceName = $credentials['instance'] ?? $credentials['whatsapp_instance'] ?? null;

        if ($this->companyId) {
            $empresaModel = Empresa::find($this->companyId);
            if ($empresaModel) {
                if (! $this->apiKey) {
                    $this->apiKey = $empresaModel->whatsapp_api_key;
                }
                if (! $this->instanceName && ! empty($empresaModel->whatsapp_instance)) {
                    $this->instanceName = $empresaModel->whatsapp_instance;
                }
                if (! empty($empresaModel->whatsapp_api_url)) {
                    $this->baseUrl = rtrim($empresaModel->whatsapp_api_url, '/');
                }
            }

            // Consultar MessagingConnection si aun no hay instancia
            if (! $this->instanceName) {
                $connection = MessagingConnection::forEmpresa($this->companyId)
                    ->whereHas('provider', fn($q) => $q->where('slug', 'whatsapp_lite'))
                    ->first();
                if ($connection && ! empty($connection->credentials['instance'])) {
                    $this->instanceName = $connection->credentials['instance'];
                }
            }
        }

        if (! $this->apiKey) {
            $this->apiKey = config('whatsapp.api_key', 'test-api-key-vargas-centro');
        }

        if (! $this->instanceName) {
            $this->instanceName = 'empresa_'.$this->companyId;
        }
    }

    /**
     * Resuelve la empresa y configura la API key e instancia
     */
    private function resolveCompany($empresa = null): void
    {
        $empresaModel = null;

        $empresaModel = null;

        if ($empresa instanceof Empresa) {
            $empresaModel = $empresa;
        } elseif (is_numeric($empresa)) {
            $empresaModel = Empresa::find($empresa);
        } elseif (auth()->check() && auth()->user()->empresa_id) {
            $empresaModel = Empresa::find(auth()->user()->empresa_id);
        }

        if (! $empresaModel) {
            $empresaModel = Empresa::find(1);
        }

        if ($empresaModel) {
            $this->companyId = $empresaModel->id;
            $this->apiKey = $empresaModel->whatsapp_api_key ?? config('whatsapp.api_key', 'test-api-key-vargas-centro');
            if (! empty($empresaModel->whatsapp_api_url)) {
                $this->baseUrl = rtrim($empresaModel->whatsapp_api_url, '/');
            }
            $this->instanceName = ! empty($empresaModel->whatsapp_instance)
                ? $empresaModel->whatsapp_instance
                : 'empresa_'.$empresaModel->id;

            return;
        }

        $this->companyId = 1;
        $this->apiKey = config('whatsapp.api_key', 'test-api-key-vargas-centro');
        $this->instanceName = 'empresa_1';
    }

    /**
     * Obtiene los headers necesarios para la API
     */
    private function getHeaders(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'X-Company-Id' => (string) $this->companyId,
            'Content-Type' => 'application/json',
        ];
    }

    public static function forCompany($empresa): self
    {
        return new self($empresa);
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    public function getInstanceName(): string
    {
        return $this->instanceName;
    }

    /**
     * Obtener el estado de la conexión WhatsApp
     * Usa timeout reducido (10s) como Conexion.php
     */
    public function getStatus()
    {
        try {
            $url = "{$this->baseUrl}/api/instance/{$this->instanceName}/status";
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders())
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['status'] ?? 'close';
                $isConnected = ($status === 'open');

                return [
                    'instanceName' => $data['instanceName'] ?? $this->instanceName,
                    'status' => $status,
                    'isConnected' => $isConnected,
                    'connectionState' => $isConnected ? 'connected' : ($status === 'qr' ? 'qr_ready' : $status),
                    'qrCode' => $data['qrDataUrl'] ?? null,
                    'token' => $data['token'] ?? null,
                    'user' => [
                        'id' => $data['userJid'] ?? null,
                    ],
                    'raw' => $data,
                ];
            }

            // Si la instancia aún no existe en el manager de Node (404), la declaramos como desconectada
            if ($response->status() === 404) {
                return [
                    'instanceName' => $this->instanceName,
                    'status' => 'close',
                    'isConnected' => false,
                    'connectionState' => 'disconnected',
                    'qrCode' => null,
                    'user' => null,
                ];
            }

            Log::warning('WhatsApp Status HTTP Error', [
                'company_id' => $this->companyId,
                'instance' => $this->instanceName,
                'status' => $response->status(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('WhatsApp Service Unavailable: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'url' => $this->baseUrl,
                'instance' => $this->instanceName,
            ]);

            return ['_error' => 'service_unavailable'];
        } catch (\Exception $e) {
            Log::error('WhatsApp Status Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'instance' => $this->instanceName,
            ]);


            return null;
        }
    }

    /**
     * Obtener código QR para conectar WhatsApp
     */
    public function getQRCode()
    {
        $status = $this->getStatus();
        if ($status && isset($status['qrCode'])) {
            return ['qrCode' => $status['qrCode']];
        }

        return null;
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendMessage(string $to, string $message, bool $isWelcome = false)
    public function sendMessage(string $to, string $message, bool $isWelcome = false)
    {
        try {
            $url = "{$this->baseUrl}/api/message/send-text/{$this->instanceName}";
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post($url, [
                    'to' => $to,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp mensaje enviado', [
                    'company_id' => $this->companyId,
                    'instance' => $this->instanceName,
                    'to' => $to,
                ]);

                return $response->json();
            } else {
                Log::error('WhatsApp Send Message Failed', [
                    'company_id' => $this->companyId,
                    'instance' => $this->instanceName,
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Message Error: '.$e->getMessage(), [
            Log::error('WhatsApp Send Message Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'instance' => $this->instanceName,
                'to' => $to,
            ]);

            return null;
        }
    }

    /**
     * Enviar documento o imagen vía URL
     */
    public function sendMedia(string $to, string $mediaUrl, string $caption = '')
    {
        try {
            $url = "{$this->baseUrl}/api/message/send-media/{$this->instanceName}";
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post($url, [
                    'to' => $to,
                    'url' => $mediaUrl,
                    'caption' => $caption,
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Media Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'instance' => $this->instanceName,
                'to' => $to,
            ]);


            return null;
        }
    }

    public function sendDocument(string $to, string $filePath, string $caption = '')
    {
        if (file_exists($filePath)) {
            $mime = mime_content_type($filePath) ?: 'application/pdf';
            $base64 = base64_encode(file_get_contents($filePath));
            $filePath = "data:{$mime};base64,{$base64}";
        }
        return $this->sendMedia($to, $filePath, $caption);
    }

    public function sendImage(string $to, string $filePath, string $caption = '')
    {
        if (file_exists($filePath)) {
            $mime = mime_content_type($filePath) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($filePath));
            $filePath = "data:{$mime};base64,{$base64}";
        }
        return $this->sendMedia($to, $filePath, $caption);
    }

    /**
     * Conectar / Crear instancia en el servidor de WhatsApp
     */
    public function connect()
    {
        try {
            $url = "{$this->baseUrl}/api/instance/create";
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post($url, [
                    'name' => $this->instanceName,
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Connect Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'instance' => $this->instanceName,
            ]);


            return null;
        }
    }

    /**
     * Desconectar / Eliminar instancia
     */
    public function disconnect()
    {
        try {
            $url = "{$this->baseUrl}/api/instance/{$this->instanceName}";
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->delete($url);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Disconnect Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'instance' => $this->instanceName,
            ]);


            return null;
        }
    }

    public function reconnect()
    {
        return $this->connect();
    }

    public function removeSession()
    {
        return $this->disconnect();
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->companyId);
        return ! empty($this->apiKey) && ! empty($this->companyId);
    }
}
