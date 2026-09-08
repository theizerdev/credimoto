<?php

namespace App\Livewire\Admin\Contratos;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use App\Models\Contrato;
use App\Models\Cliente;
use App\Models\MotoUnidad;
use App\Models\PlanPago;
use App\Models\Empresa;
use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Edit extends Component
{
    use HasDynamicLayout;

    // Pasos del Wizard
    public $step = 1;

    // Contrato actual
    public $contrato;
    public $contratoId;

    // Datos del Contrato
    public $empresa_id = '';
    public $sucursal_id = '';
    public $cliente_id = '';
    public $moto_unidad_id = '';
    public $vendedor_id = '';
    public $numero_contrato = '';

    // Condiciones Financieras
    public $precio_venta_final = 0;
    public $cuota_inicial = 0;
    public $monto_financiado = 0;
    public $tasa_interes_anual = 0;
    public $plazo_semanas = 48;
    public $plazo_meses = 12;
    public $dia_pago_mensual = 5;
    public $frecuencia_pago = 'mensual';
    public $fecha_inicio;
    public $observaciones = '';

    // Proyección de Cuotas
    public $plan_proyectado = [];
    public $cuota_estimada = 0;
    public $total_cuotas_calculadas = 0;

    // Listas para Selects
    public $empresas;
    public $sucursales = [];
    public $clientes;
    public $unidades_disponibles = [];

    protected $rules = [
        1 => [
            'empresa_id' => 'required',
            'sucursal_id' => 'required',
            'cliente_id' => 'required',
            'moto_unidad_id' => 'required',
        ],
        2 => [
            'precio_venta_final' => 'required|numeric|min:1',
            'cuota_inicial' => 'required|numeric|min:0',
            'tasa_interes_anual' => 'required|numeric|min:0',
            'plazo_semanas' => 'required|integer|min:1|max:240',
            'frecuencia_pago' => 'required|in:semanal,quincenal,mensual',
            'dia_pago_mensual' => 'required_if:frecuencia_pago,mensual|nullable|integer|min:1|max:31',
            'fecha_inicio' => 'required|date',
        ]
    ];

    /**
     * Genera un número de contrato único con dígitos aleatorios
     *
     * @return string
     */
    private function generateUniqueContractNumber(): string
    {
        do {
            // Generar 6 dígitos aleatorios
            $randomDigits = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $numeroContrato =  $randomDigits;

            // Verificar que no exista en la base de datos (excluyendo el contrato actual)
            $existe = Contrato::where('numero_contrato', $numeroContrato)
                ->where('id', '!=', $this->contratoId)
                ->exists();
        } while ($existe);

        return $numeroContrato;
    }

    public function mount(Contrato $contrato)
    {
        $this->contrato = $contrato;
        $this->contratoId = $contrato->id;

        // Cargar recursos iniciales PRIMERO
        $this->empresas = Empresa::forUser()->where('status', true)->get();

        // Cargar datos del contrato DESPUÉS de cargar empresas
        $this->empresa_id = $contrato->empresa_id;
        $this->sucursal_id = $contrato->sucursal_id;
        $this->cliente_id = $contrato->cliente_id;
        $this->moto_unidad_id = $contrato->moto_unidad_id;
        $this->vendedor_id = $contrato->vendedor_id;
        $this->numero_contrato = $contrato->numero_contrato;
        $this->precio_venta_final = $contrato->precio_venta_final;
        $this->cuota_inicial = $contrato->cuota_inicial;
        $this->monto_financiado = $contrato->monto_financiado;
        $this->tasa_interes_anual = $contrato->tasa_interes_anual;
        $this->plazo_semanas = $contrato->plazo_semanas;
        $this->plazo_meses = $contrato->plazo_meses;
        $this->dia_pago_mensual = $contrato->dia_pago_mensual;
        $this->frecuencia_pago = $contrato->frecuencia_pago;
        $this->fecha_inicio = $contrato->fecha_inicio->format('Y-m-d');
        $this->observaciones = $contrato->observaciones;

        // Cargar sucursales, clientes y unidades SIN resetear los valores actuales
        $this->sucursales = Sucursal::where('empresa_id', $this->empresa_id)->get();
        $this->clientes = Cliente::where('empresa_id', $this->empresa_id)->where('activo', true)->get();

        // Solo unidades disponibles o la unidad actual del contrato
        $this->unidades_disponibles = MotoUnidad::with('moto')
            ->where('empresa_id', $this->empresa_id)
            ->where(function($query) {
                $query->where('estado', 'disponible')
                    ->orWhere('id', $this->moto_unidad_id);
            })
            ->get();

        // Calcular totales iniciales
        $this->calculateTotals();
    }

    public function updatedEmpresaId($value)
    {
        $this->sucursales = Sucursal::where('empresa_id', $value)->get();
        $this->loadResources($value);
    }

    public function loadResources($empresaId)
    {
        $this->reset(['cliente_id', 'moto_unidad_id']);

        $this->clientes = Cliente::where('empresa_id', $empresaId)->where('activo', true)->get();
        // Solo unidades disponibles o la unidad actual del contrato
        $this->unidades_disponibles = MotoUnidad::with('moto')
            ->where('empresa_id', $empresaId)
            ->where(function($query) {
                $query->where('estado', 'disponible')
                    ->orWhere('id', $this->moto_unidad_id);
            })
            ->get();
    }

    public function updatedMotoUnidadId($value)
    {
        $unidad = MotoUnidad::find($value);
        if ($unidad) {
            $this->precio_venta_final = $unidad->precio_venta;
            $this->calculateTotals();
        }
    }

    public function updatedPrecioVentaFinal() { $this->calculateTotals(); }
    public function updatedCuotaInicial() { $this->calculateTotals(); }
    public function updatedTasaInteresAnual() { $this->calculateTotals(); }
    public function updatedPlazoSemanas() {
        $this->plazo_meses = round((int) $this->plazo_semanas / 4, 1);
        $this->calculateTotals();
    }
    public function updatedFrecuenciaPago() { $this->calculateTotals(); }

    public function getNumCuotas(): int
    {
        $semanas = (int) $this->plazo_semanas;
        return match ($this->frecuencia_pago) {
            'semanal' => $semanas,
            'quincenal' => (int) ceil($semanas / 2),
            default => (int) ceil($semanas / 4),
        };
    }

    public function getPeriodsPerYear(): int
    {
        return match ($this->frecuencia_pago) {
            'semanal' => 52,
            'quincenal' => 24,
            default => 12,
        };
    }

    public function calculateTotals()
    {
        $precio = (float) $this->precio_venta_final;
        $inicial = (float) $this->cuota_inicial;
        
        $this->monto_financiado = max(0, $precio - $inicial);
        $numCuotas = $this->getNumCuotas();
        $this->total_cuotas_calculadas = $numCuotas;
        
        if ($this->monto_financiado > 0 && $numCuotas > 0) {
            // Lógica de cálculo de cuota unificada para todas las frecuencias
            $periodsPerYear = $this->getPeriodsPerYear();
            $interes_total = ($this->monto_financiado * ($this->tasa_interes_anual / 100)) / $periodsPerYear * $numCuotas;
            $total_a_pagar = $this->monto_financiado + $interes_total;
            $this->cuota_estimada = $total_a_pagar / $numCuotas;
        } else {
            $this->cuota_estimada = 0;
        }
    }

    /**
     * Obtiene el próximo día hábil (Lunes a Sábado) a partir de una fecha dada
     * Si la fecha es Domingo, se mueve al Lunes siguiente
     *
     * @param Carbon $fecha
     * @return Carbon
     */
    private function getNextBusinessDay(Carbon $fecha): Carbon
    {
        // Si es domingo (dayOfWeek = 0), mover al lunes siguiente
        if ($fecha->dayOfWeek === 0) {
            return $fecha->copy()->addDay();
        }

        // Si es sábado (dayOfWeek = 6) o cualquier otro día hábil, mantener la fecha
        return $fecha->copy();
    }

    /**
     * Cuenta los días hábiles (Lunes a Sábado) entre dos fechas
     *
     * @param Carbon $fechaInicio
     * @param Carbon $fechaFin
     * @return int
     */
    private function countBusinessDays(Carbon $fechaInicio, Carbon $fechaFin): int
    {
        $dias = 0;
        $fechaActual = $fechaInicio->copy();

        while ($fechaActual <= $fechaFin) {
            // Contar solo Lunes (1) a Sábado (6)
            if ($fechaActual->dayOfWeek >= 1 && $fechaActual->dayOfWeek <= 6) {
                $dias++;
            }
            $fechaActual->addDay();
        }

        return $dias;
    }

    /**
     * Agrega días hábiles (Lunes a Sábado) a una fecha
     *
     * @param Carbon $fecha
     * @param int $dias
     * @return Carbon
     */
    private function addBusinessDays(Carbon $fecha, int $dias): Carbon
    {
        $fechaResultado = $fecha->copy();
        $diasAgregados = 0;

        while ($diasAgregados < $dias) {
            $fechaResultado->addDay();
            // Solo contar Lunes a Sábado
            if ($fechaResultado->dayOfWeek >= 1 && $fechaResultado->dayOfWeek <= 6) {
                $diasAgregados++;
            }
        }

        return $fechaResultado;
    }

    public function generateProjection()
    {
        $this->validate($this->rules[2]);
        $this->calculateTotals();

        $plan = [];
        $fecha_pago = Carbon::parse($this->fecha_inicio);
        $numCuotas = $this->getNumCuotas();
        $periodsPerYear = $this->getPeriodsPerYear();

        // Define la fecha de la primera cuota según la frecuencia de pago
        if ($this->frecuencia_pago === 'semanal') {
            // Si hay cuota inicial, la primera cuota se desplaza 7 días.
            if ($this->cuota_inicial > 0) {
                $fecha_pago->addDays(7);
            }
        } elseif ($this->frecuencia_pago === 'quincenal') {
            // Si hay cuota inicial, la primera cuota se desplaza a la siguiente quincena (15 días).
            if ($this->cuota_inicial > 0) {
                $fecha_pago->addDays(15);
            }
        } elseif ($this->frecuencia_pago === 'mensual') {
            // Si hay cuota inicial, la primera cuota es el mes siguiente.
            if ($this->cuota_inicial > 0) {
                $fecha_pago->addMonth();
            }
            // Si la fecha de inicio es posterior al día de pago, y no hay cuota inicial,
            // la primera cuota debe ser en el mes siguiente.
            elseif (Carbon::parse($this->fecha_inicio)->day > $this->dia_pago_mensual) {
                $fecha_pago->addMonth();
            }
            
            // Se ajusta al día de pago del mes correspondiente.
            try {
                $fecha_pago->day = $this->dia_pago_mensual;
            } catch (\Exception $e) {
                $fecha_pago->day = $fecha_pago->daysInMonth;
            }
        }

        // Al final, se ajusta la fecha calculada para que sea un día hábil.
        $fecha_pago = $this->getNextBusinessDay($fecha_pago);



        $saldo = $this->monto_financiado;
        $capital_por_cuota = $this->monto_financiado / $numCuotas;

        // Se calcula el interés por cuota de forma unificada para todas las frecuencias.
        // Si la tasa de interés es 0, el resultado será 0.
        $interes_por_cuota = ($this->monto_financiado * ($this->tasa_interes_anual / 100)) / $periodsPerYear;

        if ($this->cuota_inicial > 0) {
            $plan[] = [
                'numero' => 0,
                'tipo' => 'inicial',
                'fecha' => $this->fecha_inicio,
                'monto_capital' => $this->cuota_inicial,
                'monto_interes' => 0,
                'total' => $this->cuota_inicial,
                'saldo' => $this->monto_financiado
            ];
        }

        $tipoCuota = match ($this->frecuencia_pago) {
            'semanal' => 'semanal',
            'quincenal' => 'quincenal',
            default => 'mensual',
        };

        for ($i = 1; $i <= $numCuotas; $i++) {
            $total_cuota = $capital_por_cuota + $interes_por_cuota;
            $saldo -= $capital_por_cuota;
            $plan[] = [
                'numero' => $i,
                'tipo' => $tipoCuota,
                'fecha' => $fecha_pago->format('Y-m-d'),
                'monto_capital' => round($capital_por_cuota, 2),
                'monto_interes' => round($interes_por_cuota, 2),
                'total' => round($total_cuota, 2),
                'saldo' => max(0, round($saldo, 2))
            ];

            if ($this->frecuencia_pago === 'semanal') {
                // Para semanal: sumar 7 días y buscar el próximo día hábil
                $fecha_pago->addDays(7);
                $fecha_pago = $this->getNextBusinessDay($fecha_pago);
            } elseif ($this->frecuencia_pago === 'quincenal') {
                // Para quincenal: sumar 15 días naturales y asegurar día hábil
                $fecha_pago->addDays(15);
                $fecha_pago = $this->getNextBusinessDay($fecha_pago);
            } else {
                $fecha_pago->addMonth();
                if ($fecha_pago->day != $this->dia_pago_mensual) {
                    try {
                        $fecha_pago->day = $this->dia_pago_mensual;
                    } catch (\Exception $e) {
                        $fecha_pago->day = $fecha_pago->daysInMonth;
                    }
                }
            }
        }

        // Mapear pagos existentes (PagoDetalle) al plan proyectado para la vista de confirmación
        $pagosDetalles = \App\Models\PagoDetalle::with('pago')
            ->where(function($q) {
                $q->whereHas('planPago', function($query) {
                    $query->where('contrato_id', $this->contrato->id);
                })
                ->orWhere('cliente_id', $this->contrato->cliente_id);
            })
            ->whereHas('pago', function($query) {
                $query->where('estado', '!=', 'cancelado');
            })
            ->get()
            ->sortBy(fn($d) => optional($d->pago)->fecha?->getTimestamp() ?? 0)
            ->values();

        if ($pagosDetalles->isNotEmpty()) {
            $detalleIndex = 0;

            foreach ($plan as $idx => &$p) {
                $p['monto_pagado_aplicado'] = 0;
                $p['pagos_aplicados'] = [];
                $p['estado'] = 'pendiente';

                // No aplicar saldo acumulado a la cuota inicial (numero 0)
                if ($p['numero'] == 0) {
                    continue;
                }

                $restoCuota = (float) $p['total'];

                while ($restoCuota > 0 && isset($pagosDetalles[$detalleIndex])) {
                    $detalle = $pagosDetalles[$detalleIndex];
                    $montoDetalle = (float) $detalle->subtotal;

                    if ($montoDetalle <= 0) {
                        $detalleIndex++;
                        continue;
                    }

                    $tomar = min($montoDetalle, $restoCuota);

                    $p['pagos_aplicados'][] = [
                        'pago_id' => $detalle->pago_id,
                        'detalle_id' => $detalle->id,
                        'monto' => round($tomar, 2),
                        'fecha' => optional($detalle->pago)->fecha?->toDateString()
                    ];

                    $p['monto_pagado_aplicado'] += $tomar;
                    $restoCuota -= $tomar;

                    // Reducir subtotal temporalmente en la colección para consumir parcialmente
                    $pagosDetalles[$detalleIndex]->subtotal = max(0, $montoDetalle - $tomar);
                    if ($pagosDetalles[$detalleIndex]->subtotal <= 0) {
                        $detalleIndex++;
                    }
                }

                // Ajustar saldo y estado visual
                $p['monto_pagado_aplicado'] = round($p['monto_pagado_aplicado'], 2);
                $p['saldo'] = max(0, round($p['total'] - $p['monto_pagado_aplicado'], 2));
                if ($p['monto_pagado_aplicado'] >= $p['total']) {
                    $p['estado'] = 'pagado';
                } elseif ($p['monto_pagado_aplicado'] > 0) {
                    $p['estado'] = 'parcial';
                }
            }
            unset($p);
        } else {
            // Inicializar campos cuando no hay pagos
            foreach ($plan as $idx => &$p) {
                $p['monto_pagado_aplicado'] = 0;
                $p['pagos_aplicados'] = [];
                $p['estado'] = 'pendiente';
            }
            unset($p);
        }

        $this->plan_proyectado = $plan;
        $this->step = 3;
    }

    public function nextStep()
    {
        $this->validate($this->rules[$this->step]);
        if ($this->step == 2) {
            $this->generateProjection();
        } else {
            $this->step++;
        }
    }

    public function prevStep()
    {
        $this->step--;
    }

    public function save()
    {
        DB::transaction(function () {
            // 1. Actualizar Contrato
            $this->contrato->update([
                'numero_contrato' => $this->numero_contrato,
                'empresa_id' => $this->empresa_id,
                'sucursal_id' => $this->sucursal_id,
                'cliente_id' => $this->cliente_id,
                'moto_unidad_id' => $this->moto_unidad_id,
                'vendedor_id' => $this->vendedor_id,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin_estimada' => end($this->plan_proyectado)['fecha'],
                'precio_venta_final' => $this->precio_venta_final,
                'cuota_inicial' => $this->cuota_inicial,
                'monto_financiado' => $this->monto_financiado,
                'tasa_interes_anual' => $this->tasa_interes_anual,
                'plazo_semanas' => $this->plazo_semanas,
                'plazo_meses' => $this->plazo_meses,
                'dia_pago_mensual' => $this->dia_pago_mensual,
                'frecuencia_pago' => $this->frecuencia_pago,
                'saldo_pendiente' => $this->monto_financiado,
                'cuotas_totales' => $this->getNumCuotas(),
                'observaciones' => $this->observaciones
            ]);

            // 2. Obtener los detalles de pagos (para reasignarlos después)
            $pagosDetalles = \App\Models\PagoDetalle::with('pago')
                ->where(function($q) {
                    $q->whereHas('planPago', function($query) {
                        $query->where('contrato_id', $this->contrato->id);
                    })
                    ->orWhere('cliente_id', $this->contrato->cliente_id);
                })
                ->whereHas('pago', function($query) {
                    $query->where('estado', '!=', 'cancelado');
                })
                ->get()
                ->sortBy(fn($d) => optional($d->pago)->fecha?->getTimestamp() ?? 0)
                ->values();

            // Monto total pagado
            $montoTotalPagado = $pagosDetalles->sum('subtotal');

            // Guardar una copia en memoria del iterador para reasignar montos
            $detalleIndex = 0;

            // 3. Eliminar el plan de pagos anterior (los PagoDetalle quedan con plan_pago_id = null)
            $this->contrato->planPagos()->delete();

            // 4. Crear nuevo Plan de Pagos y aplicar pagos acumulativamente
            $montoPendienteAplicar = $montoTotalPagado; // Este es el monto total que se ha pagado y que hay que distribuir

            foreach ($this->plan_proyectado as $index => $cuota) {
                $planPago = PlanPago::create([
                    'contrato_id' => $this->contrato->id,
                    'empresa_id' => $this->empresa_id,
                    'numero_cuota' => $cuota['numero'],
                    'tipo_cuota' => $cuota['tipo'],
                    'fecha_vencimiento' => $cuota['fecha'],
                    'monto_capital' => $cuota['monto_capital'],
                    'monto_interes' => $cuota['monto_interes'],
                    'monto_total' => $cuota['total'],
                    'saldo_pendiente' => $cuota['total'], // Inicialmente debe todo el monto de la cuota
                    'estado' => 'pendiente'
                ]);

                // Aplicar el pago acumulativo a esta cuota en orden
                if ($montoPendienteAplicar > 0 && $cuota['numero'] > 0) { // No aplicar a la cuota inicial (número 0)
                    // Calcular cuánto se puede aplicar a esta cuota
                    $montoAplicable = min($montoPendienteAplicar, $cuota['total']);

                    // Actualizar el saldo pendiente de la cuota
                    $nuevoSaldoPendiente = max(0, $cuota['total'] - $montoAplicable);

                    $planPago->update([
                        'saldo_pendiente' => $nuevoSaldoPendiente
                    ]);

                    // Actualizar el estado de la cuota según el pago aplicado
                    if ($montoAplicable >= $cuota['total']) {
                        $planPago->update(['estado' => 'pagado']);
                        $montoPendienteAplicar -= $cuota['total'];
                    } elseif ($montoAplicable > 0) {
                        $planPago->update(['estado' => 'parcial']);
                        $montoPendienteAplicar -= $montoAplicable;
                    }

                    // Reasignar PagoDetalle existentes a este nuevo PlanPago hasta cubrir $montoAplicable
                    $restoPorAsignar = $montoAplicable;
                    while ($restoPorAsignar > 0 && isset($pagosDetalles[$detalleIndex])) {
                        $detalle = $pagosDetalles[$detalleIndex];
                        $montoDetalle = (float) $detalle->subtotal;

                        if ($montoDetalle <= 0) {
                            $detalleIndex++;
                            continue;
                        }

                        if ($montoDetalle <= $restoPorAsignar + 0.0001) {
                            // Asignar todo el detalle a la cuota
                            $detalle->plan_pago_id = $planPago->id;
                            $detalle->save();
                            $restoPorAsignar -= $montoDetalle;
                            $detalleIndex++;
                        } else {
                            // Dividir el detalle: crear uno nuevo con la parte asignada y reducir el original
                            $asignado = $restoPorAsignar;

                            \App\Models\PagoDetalle::create([
                                'pago_id' => $detalle->pago_id,
                                'cliente_id' => optional($detalle->pago)->cliente_id ?? $this->contrato->cliente_id,
                                'concepto_pago_id' => $detalle->concepto_pago_id,
                                'plan_pago_id' => $planPago->id,
                                'descripcion' => $detalle->descripcion,
                                'cantidad' => 1,
                                'precio_unitario' => $asignado
                            ]);

                            // Reducir el subtotal del detalle original (ajustando cantidad/precio para mantener consistencia)
                            $nuevoSubtotalOriginal = $montoDetalle - $asignado;
                            $detalle->cantidad = 1;
                            $detalle->precio_unitario = $nuevoSubtotalOriginal;
                            $detalle->save();

                            $restoPorAsignar = 0;
                        }
                    }
                }
            }

            // 4. Actualizar estado de la unidad si cambió
            if ($this->contrato->wasChanged('moto_unidad_id')) {
                // Liberar la unidad anterior
                $unidadAnterior = MotoUnidad::find($this->contrato->getOriginal('moto_unidad_id'));
                if ($unidadAnterior) {
                    $unidadAnterior->estado = 'disponible';
                    $unidadAnterior->save();
                }

                // Reservar la nueva unidad
                $unidadNueva = MotoUnidad::find($this->moto_unidad_id);
                if ($unidadNueva) {
                    $unidadNueva->estado = 'reservado';
                    $unidadNueva->save();
                }
            }
        });

        session()->flash('message', 'Contrato actualizado exitosamente.');
        return redirect()->route('admin.contratos.show', $this->contrato->id);
    }

    public function render()
    {
        return view('livewire.admin.contratos.edit')->layout($this->getLayout());
    }
}