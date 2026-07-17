<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private $baseUrl;

    private $apiKey;

    private $companyId;

    private $timeout;

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Constructor del servicio WhatsApp
     *
     * @param  Empresa|int|null  $empresa  - Empresa, ID de empresa, o null para usar la del usuario actual
     */
    public function __construct($empresa = null)
    {
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
            $this->baseUrl = $credentials['api_url'];
        }

        $this->timeout = $credentials['timeout'] ?? $this->timeout;
        $this->companyId = $credentials['empresa_id'] ?? $credentials['company_id'] ?? 1;
        $this->apiKey = $credentials['api_key'] ?? $credentials['apiKey'] ?? null;

        if (! $this->apiKey && $this->companyId) {
            $empresaModel = Empresa::find($this->companyId);
            if ($empresaModel) {
                $this->apiKey = $empresaModel->whatsapp_api_key;
            }
        }

        if (! $this->apiKey) {
            $this->apiKey = config('whatsapp.api_key', 'test-api-key-vargas-centro');
        }
    }

    /**
     * Resuelve la empresa y configura la API key
     */
    private function resolveCompany($empresa = null): void
    {
        $empresaModel = null;

        if ($empresa instanceof Empresa) {
            $empresaModel = $empresa;
        } elseif (is_numeric($empresa)) {
            $empresaModel = Empresa::find($empresa);
        } elseif (auth()->check() && auth()->user()->empresa_id) {
            $empresaModel = Empresa::find(auth()->user()->empresa_id);
        }

        // Si no logramos resolver un modelo de empresa por parámetro o por usuario autenticado, usamos la empresa 1 por defecto (tienda principal)
        if (! $empresaModel) {
            $empresaModel = Empresa::find(1);
        }

        if ($empresaModel) {
            $this->companyId = $empresaModel->id;
            $this->apiKey = $empresaModel->whatsapp_api_key ?? config('whatsapp.api_key', 'test-api-key-vargas-centro');
            if (! empty($empresaModel->whatsapp_api_url)) {
                $this->baseUrl = rtrim($empresaModel->whatsapp_api_url, '/');
            }

            return;
        }

        // Fallback total si la base de datos no tiene empresas
        $this->companyId = 1;
        $this->apiKey = config('whatsapp.api_key', 'test-api-key-vargas-centro');
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

    /**
     * Crea una instancia del servicio para una empresa específica
     */
    public static function forCompany($empresa): self
    {
        return new self($empresa);
    }

    /**
     * Obtener el ID de la empresa configurada
     */
    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    /**
     * Obtener el estado de la conexión WhatsApp
     * Usa timeout reducido (10s) como Conexion.php
     */
    public function getStatus()
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/whatsapp/status");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('WhatsApp Status HTTP Error', [
                'company_id' => $this->companyId,
                'status' => $response->status(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::error('WhatsApp Service Unavailable: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'url' => $this->baseUrl,
            ]);

            return ['_error' => 'service_unavailable'];
        } catch (\Exception $e) {
            Log::error('WhatsApp Status Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Obtener código QR para conectar WhatsApp
     */
    public function getQRCode()
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/whatsapp/qr");

            return $response->successful() ? $response->json() : null;
        } catch (ConnectionException $e) {
            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp QR Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendMessage(string $to, string $message, bool $isWelcome = false)
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/api/whatsapp/send", [
                    'to' => $to,
                    'message' => $message,
                    'type' => 'text',
                    'isWelcome' => $isWelcome,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp mensaje enviado', [
                    'company_id' => $this->companyId,
                    'to' => $to,
                    'message_id' => $response->json('messageId'),
                ]);

                return $response->json();
            } else {
                Log::error('WhatsApp Send Message Failed', [
                    'company_id' => $this->companyId,
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Message Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'to' => $to,
            ]);

            return null;
        }
    }

    /**
     * Enviar documento (PDF, Excel, Word, etc.)
     */
    public function sendDocument(string $to, string $filePath, string $caption = '')
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-Company-Id' => (string) $this->companyId,
                ])
                ->attach('document', file_get_contents($filePath), basename($filePath))
                ->post("{$this->baseUrl}/api/whatsapp/send-document", [
                    'to' => $to,
                    'caption' => $caption,
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Document Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'to' => $to,
            ]);

            return null;
        }
    }

    public function sendImage(string $to, string $filePath, string $caption = '')
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-Company-Id' => (string) $this->companyId,
                ])
                ->attach('image', file_get_contents($filePath), basename($filePath))
                ->post("{$this->baseUrl}/api/whatsapp/send-image", [
                    'to' => $to,
                    'caption' => $caption,
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Image Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'to' => $to,
            ]);

            return null;
        }
    }

    /**
     * Obtener historial de mensajes
     */
    public function getMessages(array $filters = [])
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/whatsapp/messages", $filters);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Get Messages Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Conectar WhatsApp
     */
    public function connect()
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/api/whatsapp/connect");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Connect Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Desconectar WhatsApp
     */
    public function disconnect()
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->delete("{$this->baseUrl}/api/whatsapp/disconnect");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Disconnect Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Reconectar WhatsApp
     */
    public function reconnect()
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/api/whatsapp/force-reconnect");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Reconnect Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Eliminar sesión (logout completo)
     */
    public function removeSession()
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->delete("{$this->baseUrl}/api/whatsapp/session");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Remove Session Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Obtener estadísticas de la empresa computadas desde los mensajes.
     * El API no tiene un endpoint /stats por empresa, así que las calculamos
     * consultando /messages con diferentes filtros de status.
     */
    public function getStats()
    {
        try {
            // All statuses defined in the Message model (ENUM)
            $statuses = ['pending', 'sent', 'delivered', 'read', 'failed', 'received'];
            $stats = ['total' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0, 'pending' => 0, 'today' => 0];

            // Fetch count for each status from the API
            foreach ($statuses as $status) {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/api/whatsapp/messages", [
                        'status' => $status,
                        'limit' => 1,
                        'page' => 1,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $count = $data['total'] ?? 0;
                    // Map to display categories: read→delivered, received→sent
                    match ($status) {
                        'sent' => $stats['sent'] = $count,
                        'delivered' => $stats['delivered'] = $count,
                        'failed' => $stats['failed'] = $count,
                        'pending' => $stats['pending'] = $count,
                        'read' => $stats['delivered'] += $count,     // read = delivered (read)
                        'received' => $stats['sent'] += $count,      // received = sent (received by server)
                        default => null,
                    };
                }
            }

            $stats['total'] = $stats['sent'] + $stats['delivered'] + $stats['failed'] + $stats['pending'];

            // Fetch recent messages (last 100) to compute today's count accurately
            $recent = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/whatsapp/messages", [
                    'limit' => 100,
                    'page' => 1,
                ]);

            $messages = [];
            if ($recent->successful()) {
                $recentData = $recent->json();
                $messages = $recentData['messages'] ?? [];

                // Count today's messages (any status: sent, delivered, read)
                $today = now()->format('Y-m-d');
                $stats['today'] = collect($messages)
                    ->filter(function ($m) use ($today) {
                        $createdAt = $m['createdAt'] ?? $m['created_at'] ?? '';
                        $status = $m['status'] ?? '';

                        return in_array($status, ['sent', 'delivered', 'read', 'received'])
                            && str_starts_with($createdAt, $today);
                    })
                    ->count();
            }

            return [
                'stats' => $stats,
                'messages' => array_slice($messages, 0, 15), // return only last 15 for display
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Stats Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
            ]);

            return null;
        }
    }

    /**
     * Obtener estadísticas del manager (todas las empresas)
     */
    public function getManagerStats()
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/whatsapp/manager/stats");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Manager Stats Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Enviar mensaje interactivo con botones (WhatsApp Business API)
     */
    public function sendInteractiveMessage(string $to, array $interactiveMessage)
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/api/whatsapp/send-interactive", [
                    'to' => $to,
                    'interactive' => $interactiveMessage,
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp mensaje interactivo enviado', [
                    'company_id' => $this->companyId,
                    'to' => $to,
                    'message_id' => $response->json('messageId'),
                ]);
            }

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Interactive Message Error: '.$e->getMessage(), [
                'company_id' => $this->companyId,
                'to' => $to,
            ]);

            return null;
        }
    }

    /**
     * Verifica si el servicio tiene configuración válida
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->companyId);
    }
}
