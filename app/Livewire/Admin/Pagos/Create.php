<?php

namespace App\Livewire\Admin\Pagos;

use App\Traits\HasDynamicLayout;
use App\Traits\HasRegionalFormatting;
use Livewire\Component;
use App\Models\Pago;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ConceptoPago;
use App\Models\PlanPago;
use App\Models\Serie;
use App\Models\Caja;
use App\Models\ExchangeRate;
use App\Services\PagoService;
use App\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Create extends Component
{
    use HasDynamicLayout, HasRegionalFormatting;

    public $cliente_id;
    public $contrato_id;

    public $tipo_pago = 'recibo';
    public $fecha;
    public $metodo_pago = 'efectivo';
    public $referencia;
    public $descuento = 0;
    public $observaciones;
    public $detalles = [];
    public $tasa_cambio;
    public $mostrar_bolivares = false;

    // Propiedades para pago mixto
    public $es_pago_mixto = false;
    public $metodos_pago_mixto = [];
    public $totalPagoMixto = 0;

    // Propiedades adicionales
    public $fecha_pago;
    public $monto;

    // Colecciones
    public $clientes = [];
    public $contratos = [];
    public $conceptos = [];
    public $cuotasPendientes = [];

    public $serie_actual;
    public $numero_documento;
    public $caja_abierta;

    // Búsqueda
    public $busqueda_cliente = '';
    public $clientes_filtrados = [];

    // Propiedades para mejoras
    public $monto_recibido = 0;
    public $pagos_anteriores = [];
    public $plantillas_pago = [];
    public $whatsappStatus = 'disconnected';

    // Propiedad para adelantos de pago
    public $adelanto_monto = [];

    protected $listeners = [
        'referencia-mixto-actualizada' => 'procesarReferenciaMixto',
        'whatsapp-status-updated' => 'actualizarEstadoWhatsApp'
    ];

    protected $rules = [
        'cliente_id' => 'required|exists:clientes,id',
        'tipo_pago' => 'required',
        'fecha' => 'required|date',
        'metodo_pago' => 'required',
        'detalles' => 'required|array|min:1',
        'detalles.*.concepto_pago_id' => 'required|exists:conceptos_pago,id',
        'detalles.*.descripcion' => 'required|string',
        'detalles.*.cantidad' => 'required|numeric|min:0.01',
        'detalles.*.precio_unitario' => 'required|numeric|min:0',
    ];

    public function mount()
    {
        $this->fecha = now()->format('Y-m-d');
        $this->fecha_pago = now()->format('Y-m-d');

        $this->cargarTasaCambio();
        $this->verificarCajaAbierta();
        $this->cargarDatos();
        $this->agregarDetalle();
        $this->inicializarPagoMixto();
        $this->cargarPlantillasPago();
        $this->checkWhatsAppStatus();
    }

    public function inicializarPagoMixto()
    {
        $this->metodos_pago_mixto = [
            ['metodo' => 'efectivo', 'monto' => 0, 'referencia' => ''],
            ['metodo' => 'transferencia', 'monto' => 0, 'referencia' => '']
        ];
    }

    public function cargarTasaCambio()
    {
        $fecha = $this->fecha_pago ?: $this->fecha;
        if ($fecha) {
            $service = new ExchangeRateService();
            $rate = $service->fetchAndStoreRates() ? ExchangeRate::getTodayRate()->usd_rate : null;
            // Simplified for now, assuming service stores it.
            // Better: use ExchangeRate model directly if service is complex
            $tasa = ExchangeRate::whereDate('date', $fecha)->first();
            if ($tasa) {
                $this->tasa_cambio = $tasa->usd_rate;
                $this->mostrar_bolivares = true;
                return;
            }
        }

        $tasaHoy = ExchangeRate::getTodayRate();
        if ($tasaHoy) {
            $this->tasa_cambio = $tasaHoy->usd_rate;
            $this->mostrar_bolivares = true;
        }
    }

    public function verificarCajaAbierta()
    {
        $this->caja_abierta = Caja::obtenerCajaAbierta(
            auth()->user()->empresa_id,
            auth()->user()->sucursal_id
        );
    }

    public function cargarDatos()
    {
        $this->conceptos = ConceptoPago::where('activo', true)->get();
        $this->cargarSerieActual();
    }

    public function updatedBusquedaCliente($value)
    {
        if (strlen($value) >= 2) {
            $query = Cliente::where(function($q) use ($value) {
                    $q->where('nombre', 'like', '%' . $value . '%')
                      ->orWhere('apellido', 'like', '%' . $value . '%')
                      ->orWhere('documento', 'like', '%' . $value . '%');
                });

            if (auth()->check() && !auth()->user()->hasRole('Super Administrador')) {
                $query->where('empresa_id', auth()->user()->empresa_id);
            }

            $this->clientes_filtrados = $query->limit(10)->get();
        } else {
            $this->clientes_filtrados = [];
        }
    }

    public function seleccionarCliente($clienteId)
    {
        $this->cliente_id = $clienteId;
        $this->busqueda_cliente = '';
        $this->clientes_filtrados = [];

        $this->cargarContratos($clienteId);
        $this->cargarPagosAnteriores($clienteId);
    }

    public function cargarContratos($clienteId)
    {
        $this->contratos = Contrato::where('cliente_id', $clienteId)
            ->whereIn('estado', ['activo', 'mora'])
            ->get();

        if ($this->contratos->count() === 1) {
            $this->contrato_id = $this->contratos->first()->id;
            $this->updatedContratoId($this->contrato_id);
        } else {
            $this->contrato_id = null;
            $this->cuotasPendientes = [];
        }
    }

    public function updatedContratoId($value)
    {
        if ($value) {
            $this->cuotasPendientes = PlanPago::where('contrato_id', $value)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->orderBy('fecha_vencimiento')
                ->get();
        } else {
            $this->cuotasPendientes = [];
        }
    }

    public function cargarPagosAnteriores($clienteId)
    {
        $this->pagos_anteriores = Pago::where('cliente_id', $clienteId)
            ->where('estado', 'aprobado')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function cargarPlantillasPago()
    {
        $this->plantillas_pago = [
            'mensualidad' => ['nombre' => 'Mensualidad', 'conceptos' => ['Mensualidad']],
            'inicial' => ['nombre' => 'Inicial', 'conceptos' => ['Cuota Inicial']],
            'mora' => ['nombre' => 'Mora', 'conceptos' => ['Recargo por Mora']]
        ];
    }

    public function aplicarPlantilla($tipo)
    {
        $this->detalles = [];
        $plantilla = $this->plantillas_pago[$tipo] ?? null;

        if ($plantilla) {
            foreach ($plantilla['conceptos'] as $nombreConcepto) {
                $concepto = ConceptoPago::where('nombre', 'like', '%' . $nombreConcepto . '%')
                    ->where('activo', true)
                    ->first();

                if ($concepto) {
                    $this->detalles[] = [
                        'concepto_pago_id' => $concepto->id,
                        'plan_pago_id' => null,
                        'descripcion' => $concepto->nombre,
                        'cantidad' => 1,
                        'precio_unitario' => $concepto->precio_sugerido ?? 0
                    ];
                }
            }
        }

        if (empty($this->detalles)) {
            $this->agregarDetalle();
        }
    }

    public function updatedDetalles($value, $key)
    {
        if (str_contains($key, 'precio_unitario') || str_contains($key, 'cantidad')) {
            $this->validateOnly("detalles.{$key}");
        }
    }

    public function getSubtotalProperty()
    {
        return collect($this->detalles)->sum(fn($d) => ($d['cantidad'] ?? 0) * ($d['precio_unitario'] ?? 0));
    }

    public function getTotalProperty()
    {
        return $this->subtotal - $this->descuento;
    }

    public function getTotalBolivaresProperty()
    {
        if ($this->tasa_cambio) {
            return $this->total * $this->tasa_cambio;
        }
        return 0;
    }

    public function getCambioProperty()
    {
        return max(0, $this->monto_recibido - $this->total);
    }

    // Method updatedMatriculaId removed as logic is now in updatedContratoId

    public function updatedTipoPago($value)
    {
        $this->cargarSerieActual();
    }

    public function cargarSerieActual()
    {
        $query = Serie::where('tipo_documento', $this->tipo_pago)
                     ->where('activo', true)
                     ->where('empresa_id', auth()->user()->empresa_id);


        $this->serie_actual = $query->first();

        if ($this->serie_actual) {
            $this->numero_documento = $this->serie_actual->correlativo_actual;
        } else {
            // Fallback
             $this->serie_actual = new Serie([
                'id' => 1,
                'serie' => 'TMP',
                'correlativo_actual' => 1
            ]);
            $this->numero_documento = 1;
        }
    }

    public function agregarDetalle()
    {
        $conceptoDefault = ConceptoPago::where('activo', true)->first();

        $this->detalles[] = [
            'concepto_pago_id' => $conceptoDefault?->id ?? '',
            'plan_pago_id' => null,
            'descripcion' => '',
            'cantidad' => 1,
            'precio_unitario' => 0.01
        ];
    }

    public function eliminarDetalle($index)
    {
        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);
    }

    public function seleccionarCuota($planPagoId)
    {
        $planPago = PlanPago::find($planPagoId);

        if ($planPago) {
            // Buscar conceptos apropiados
            $conceptoMensualidad = ConceptoPago::where('nombre', 'like', '%Mensualidad%')->first();

            // Si no existe, usar el primero disponible o crear uno en memoria
            if (!$conceptoMensualidad) {
                $conceptoMensualidad = ConceptoPago::first();
            }

            // Agregar cuota principal con el saldo pendiente completo
            $this->detalles[] = [
                'concepto_pago_id' => $conceptoMensualidad?->id,
                'plan_pago_id' => $planPago->id,
                'descripcion' => "Cuota #{$planPago->numero_cuota} - Vence: {$planPago->fecha_vencimiento->format('d/m/Y')}",
                'cantidad' => 1,
                'precio_unitario' => $planPago->saldo_pendiente
            ];

            // Si tiene mora calculada y no pagada, agregarla como detalle separado
            if ($planPago->mora_calculada > $planPago->mora_pagada) {
                $conceptoRecargo = ConceptoPago::where('nombre', 'like', '%Mora%')->first();
                $montoMora = $planPago->mora_calculada - $planPago->mora_pagada;

                if ($montoMora > 0) {
                     $this->detalles[] = [
                        'concepto_pago_id' => $conceptoRecargo?->id ?? $conceptoMensualidad?->id,
                        'plan_pago_id' => null,
                        'descripcion' => "Mora - Cuota #{$planPago->numero_cuota}",
                        'cantidad' => 1,
                        'precio_unitario' => $montoMora
                    ];
                }
            }
        }
    }

    /**
     * Registrar un adelanto de pago para una cuota específica
     * Permite al usuario ingresar un monto personalizado menor al saldo pendiente
     */
    public function registrarAdelanto($planPagoId, $montoAdelanto = null)
    {
        $planPago = PlanPago::find($planPagoId);

        if ($planPago) {
            // Validar que el monto sea válido
            $monto = $montoAdelanto ?? ($planPago->saldo_pendiente / 2); // Por defecto 50% si no se especifica

            if ($monto <= 0) {
                session()->flash('error', 'El monto del adelanto debe ser mayor a cero.');
                return;
            }

            if ($monto > $planPago->saldo_pendiente) {
                session()->flash('error', 'El adelanto no puede ser mayor al saldo pendiente ($' . number_format($planPago->saldo_pendiente, 2) . ').');
                return;
            }

            // Buscar concepto apropiado
            $conceptoMensualidad = ConceptoPago::where('nombre', 'like', '%Mensualidad%')->first();
            if (!$conceptoMensualidad) {
                $conceptoMensualidad = ConceptoPago::first();
            }

            // Agregar el adelanto como detalle
            $this->detalles[] = [
                'concepto_pago_id' => $conceptoMensualidad?->id,
                'plan_pago_id' => $planPago->id,
                'descripcion' => "Adelanto Cuota #{$planPago->numero_cuota} - Vence: {$planPago->fecha_vencimiento->format('d/m/Y')}",
                'cantidad' => 1,
                'precio_unitario' => round($monto, 2)
            ];
        }
    }

    public function agregarAbono($scheduleId, $monto = null)
    {
        $schedule = PaymentSchedule::find($scheduleId);
        if ($schedule) {
            $conceptoMensualidad = ConceptoPago::where('nombre', 'Mensualidad')->first();
            $montoAbono = $monto ?? $schedule->saldo_pendiente;

            $this->detalles[] = [
                'concepto_pago_id' => $conceptoMensualidad?->id,
                'payment_schedule_id' => $schedule->id,
                'descripcion' => "Abono Cuota #{$schedule->numero_cuota}",
                'cantidad' => 1,
                'precio_unitario' => $montoAbono
            ];
        }
    }

    public function calcularSubtotal($index)
    {
        $detalle = $this->detalles[$index];
        return ($detalle['cantidad'] ?? 0) * ($detalle['precio_unitario'] ?? 0);
    }

    public function updatedMetodoPago($value)
    {
        $this->es_pago_mixto = ($value === 'pago mixto');
        if (!$this->es_pago_mixto) {
            $this->inicializarPagoMixto();
        }
    }

    public function agregarMetodoPago()
    {
        $this->metodos_pago_mixto[] = ['metodo' => 'efectivo_dolares', 'monto' => 0, 'referencia' => ''];
    }

    public function eliminarMetodoPago($index)
    {
        if (count($this->metodos_pago_mixto) > 1) {
            unset($this->metodos_pago_mixto[$index]);
            $this->metodos_pago_mixto = array_values($this->metodos_pago_mixto);
        }
    }

    public function getTotalPagoMixtoProperty()
    {
        return collect($this->metodos_pago_mixto)->sum('monto');
    }

    public function enviarReciboWhatsApp($pago)
    {
        $cliente = $pago->cliente;
        if (!$cliente || !$cliente->telefono) {
            return false;
        }

        $numero = $this->formatPhoneNumber($cliente->telefono);
       
        $mensaje = $this->generarMensajeWhatsApp($pago);

        // Usar WhatsAppService en lugar de Http directo para mayor robustez y compatibilidad
        try {
            $whatsappService = new \App\Services\WhatsAppService(auth()->user()->empresa_id);
            $response = $whatsappService->sendMessage($numero, $mensaje, true);

            if ($response && ($response['success'] ?? false)) {
                return true;
            }

            \Log::error('Error WhatsApp Service Response: ' . json_encode($response));
            return false;
        } catch (\Exception $e) {
             \Log::error('Error enviando WhatsApp (Exception): ' . $e->getMessage());
             return false;
        }
    }

    private function generarMensajeWhatsApp($pago)
    {
        $cliente = $pago->cliente;
        $totalFormateado = '$' . number_format($pago->total, 2, ',', '.');

        $mensaje = "💳 *Pago Recibido - Inversiones Danger 3000 C.A*\n\n";
        $mensaje .= "Estimado/a *{$cliente->nombre_completo}*,\n\n";
        $mensaje .= "Hemos recibido su pago correctamente.\n\n";

        $mensaje .= "📄 *Detalles del Pago:*\n";
        $mensaje .= "• Número de Recibo: *{$pago->numero_completo}*\n";
        $mensaje .= "• Fecha: {$pago->fecha->format('d/m/Y')}\n";
        $mensaje .= "• Método: " . ucfirst(str_replace('_', ' ', $pago->metodo_pago)) . "\n";
        if ($pago->referencia) {
            $mensaje .= "• Referencia: {$pago->referencia}\n";
        }

        $mensaje .= "\n📋 *Conceptos Pagados:*\n";
        foreach ($pago->detalles as $detalle) {
            $montoDetalle = '$' . number_format($detalle->precio_unitario * $detalle->cantidad, 2, ',', '.');
            $mensaje .= "• {$detalle->descripcion}: {$montoDetalle}\n";
        }

        $mensaje .= "\n💰 *Total Pagado: {$totalFormateado}*\n\n";
        $mensaje .= "Gracias por su pago puntual.\n\n";
        $mensaje .= "*Inversiones Danger 3000 C.A - Tu aliado en dos ruedas*";

        return $mensaje;
    }

     private function formatPhoneNumber($number)
    {
        $empresa = \DB::table('empresas')->where('id', 1)->first();
        $pais = $empresa ? \DB::table('pais')->where('id', $empresa->pais_id)->first() : null;
        $codigoPais = $pais ? $pais->codigo_telefonico : '58';

        $cleaned = preg_replace('/[^0-9]/', '', $number);

        if (strlen($cleaned) > 10 && str_starts_with($cleaned, $codigoPais)) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        return $codigoPais . $cleaned;
    }


    public function checkWhatsAppStatus()
    {
        try {
            $apiUrl = config('whatsapp.api_url', 'http://localhost:3001');
            $apiKey = config('whatsapp.api_key', 'test-api-key-vargas-centro');

            // Verificar si el servicio está disponible
            $healthResponse = \Http::timeout(3)->get($apiUrl . '/health');
            if (!$healthResponse->successful()) {
                $this->whatsappStatus = 'disconnected';
                return;
                }

                // Obtener estado de conexión
                $response = \Http::withHeaders(['X-API-Key' => $apiKey])
                ->timeout(5)
                ->get($apiUrl . '/api/whatsapp/status');

                if ($response->successful()) {
                    $data = $response->json();
                    $this->whatsappStatus = $data['connectionState'] ?? 'disconnected';
            } else {
                $this->whatsappStatus = 'disconnected';
            }
        } catch (\Exception $e) {
            $this->whatsappStatus = 'disconnected';
        }
    }

    public function guardar()
    {
        // Validación especial para pago mixto
        if ($this->es_pago_mixto && abs($this->totalPagoMixto - $this->total) > 0.01) {
             session()->flash('error', 'El total configurado en el pago mixto debe coincidir con el total a pagar.');
             return;
        }

        $this->validate();

        if (!$this->caja_abierta) {
            $this->dispatch('swal:error', [
                'title' => 'Error',
                'text' => 'No hay una caja abierta para registrar pagos.'
            ]);
            return;
        }

       try {
        DB::transaction(function () {

            // Recalcular totales antes de enviar
            $subtotal = $this->subtotal; // Usar el getter corregido
            $total = $this->total;       // Usar el getter corregido
            $totalBolivares = $this->total_bolivares;

            $pagoService = new PagoService();
            $pago = $pagoService->crearPago([
                'tipo_pago' => $this->tipo_pago,
                'fecha' => $this->fecha_pago ?: $this->fecha, // Usar fecha correcta
                'cliente_id' => $this->cliente_id,
                'serie_id' => $this->serie_actual?->id,
                'metodo_pago' => $this->metodo_pago,
                'referencia' => $this->referencia,
                'descuento' => $this->descuento,
                'subtotal' => $subtotal, // Enviar subtotal calculado
                'total' => $total,       // Enviar total calculado
                'total_bolivares' => $totalBolivares,
                'observaciones' => $this->observaciones,
                'tasa_cambio' => $this->tasa_cambio,
                'es_pago_mixto' => $this->es_pago_mixto,
                'detalles_pago_mixto' => $this->es_pago_mixto ? $this->metodos_pago_mixto : null,
                'empresa_id' => auth()->user()->empresa_id,
                'sucursal_id' => auth()->user()->sucursal_id,
                'caja_id' => $this->caja_abierta->id,
                'estado' => 'aprobado',
                'detalles' => $this->detalles
            ]);

            // Enviar notificación por WhatsApp y esperar respuesta
            $whatsappSent = $this->enviarReciboWhatsApp($pago);

            $mensaje = 'Pago registrado exitosamente: ' . $pago->numero_completo;
            if ($whatsappSent) {
                $mensaje .= ' - Notificación WhatsApp enviada.';
            }

            session()->flash('message', $mensaje);
            return redirect()->route('admin.pagos.index');
        });
        } catch (\Throwable $th) {
            session()->flash('error', 'Error al crear el pago: ' . $th->getMessage());
        }
    }

    /**
     * Método updated general para detectar cambios en metodos_pago_mixto
     */
    public function updatedMetodosPagoMixto($value, $key)
    {
        if (str_contains($key, 'referencia')) {
            $this->validarReferenciaMixto($value, $key);
        }
        $this->calculateTotalPagoMixto();
    }

    /**
     * Validar referencia en tiempo real
     */
    private function validarReferenciaMixto($value, $key)
    {
        $parts = explode('.', $key);
        $index = $parts[0] ?? null;

        if ($index !== null && isset($this->metodos_pago_mixto[$index])) {
            $valorLimpio = trim($value);
            $valorLimpio = preg_replace('/[^a-zA-Z0-9\-]/', '', $valorLimpio);

            if (!empty($valorLimpio)) {
                $existeReferenciaDirecta = \App\Models\Pago::where('referencia', $valorLimpio)
                    ->where('estado', 'aprobado')
                    ->exists();

                $existeEnDetalles = \App\Models\Pago::where('estado', 'aprobado')
                    ->whereJsonContains('detalles_pago_mixto', [['referencia' => $valorLimpio]])
                    ->exists();

                if ($existeReferenciaDirecta || $existeEnDetalles) {
                    $this->addError('metodos_pago_mixto.' . $index . '.referencia', 'Referencia ya utilizada.');
                    $this->metodos_pago_mixto[$index]['referencia'] = '';
                } else {
                    $this->metodos_pago_mixto[$index]['referencia'] = $valorLimpio;
                    $this->resetErrorBag('metodos_pago_mixto.' . $index . '.referencia');
                }
            }
        }
    }

    public function calculateTotalPagoMixto()
    {
        $this->totalPagoMixto = collect($this->metodos_pago_mixto)->sum('monto');
    }

    public function procesarReferenciaMixto($data)
    {
        // Listener method if needed
    }

    public function actualizarEstadoWhatsApp($status)
    {
        $this->whatsappStatus = $status;
    }

    public function render()
    {
        $tipos = [
            'recibo' => 'Recibo',
            'factura' => 'Factura',
            'boleta' => 'Boleta',
            'nota_credito' => 'Nota de Crédito'
        ];

        return view('livewire.admin.pagos.create', compact('tipos'))->layout($this->getLayout());
    }
}
