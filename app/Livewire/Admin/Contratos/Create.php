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
use App\Models\InventoryMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Create extends Component
{
    use HasDynamicLayout;

    // Pasos del Wizard
    public $step = 1;

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
            
            // Verificar que no exista en la base de datos
            $existe = Contrato::where('numero_contrato', $numeroContrato)->exists();
        } while ($existe);
        
        return $numeroContrato;
    }

    public function mount()
    {
        $this->empresas = Empresa::forUser()->where('status', true)->get();
        $this->fecha_inicio = date('Y-m-d');
        $this->vendedor_id = auth()->id();
        $this->numero_contrato = $this->generateUniqueContractNumber();
      
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
        // Solo unidades disponibles
        $this->unidades_disponibles = MotoUnidad::with('moto')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'disponible')
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
            // Calcular el valor semanal primero como referencia
            $numCuotasSemanal = $this->plazo_semanas; // Número de cuotas si fuera semanal
            $capitalPorCuotaSemanal = $this->monto_financiado / $numCuotasSemanal;
            $interesPorCuotaSemanal = ($this->monto_financiado * ($this->tasa_interes_anual / 100)) / 52; // 52 semanas por año
            $cuotaSemanal = $capitalPorCuotaSemanal + $interesPorCuotaSemanal;
            
            if ($this->frecuencia_pago === 'quincenal') {
                // Para quincenal, la cuota debe ser exactamente el doble de la cuota semanal
                $this->cuota_estimada = $cuotaSemanal * 2;
            } else {
                // Para otros casos, usar la lógica original adaptada
                $periodsPerYear = $this->getPeriodsPerYear();
                $interes_total = ($this->monto_financiado * ($this->tasa_interes_anual / 100)) / $periodsPerYear * $numCuotas;
                $total_a_pagar = $this->monto_financiado + $interes_total;
                $this->cuota_estimada = $total_a_pagar / $numCuotas;
            }
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
        $fecha = Carbon::parse($this->fecha_inicio);
        $numCuotas = $this->getNumCuotas();
        $periodsPerYear = $this->getPeriodsPerYear();

        // Regla nueva: para cuotas semanales, la cuota #1 debe caer el día del registro/edición
        // (es decir, fecha_inicio actual). Para mantener consistencia, el primer vencimiento semanal
        // ya no se calcula sumando 7 días.
        if ($this->frecuencia_pago === 'semanal') {
            $fecha_pago = $this->getNextBusinessDay($fecha->copy());
        } else {
            $fecha_pago = $fecha->copy();

            // Calcular la fecha de la primera cuota según la frecuencia
            if ($this->frecuencia_pago === 'mensual') {
                $fecha_pago->addMonth();
                try {
                    $fecha_pago->day = $this->dia_pago_mensual;
                } catch (\Exception $e) {
                    $fecha_pago->day = $fecha_pago->daysInMonth;
                }
            } elseif ($this->frecuencia_pago === 'quincenal') {
                // Para quincenal: sumar 15 días naturales desde la fecha de inicio
                $fecha_pago->addDays(15);
                // Asegurar que caiga en un día hábil (Lunes a Sábado)
                $fecha_pago = $this->getNextBusinessDay($fecha_pago);
            } 
            else {
                // Para semanal: sumar 7 días desde la fecha de inicio (caso por defecto si cambia frecuencia)
                $fecha_pago->addDays(7);
                $fecha_pago = $this->getNextBusinessDay($fecha_pago);
            }
        }


        $saldo = $this->monto_financiado;
        $capital_por_cuota = $this->monto_financiado / $numCuotas;
        
        // Calcular interés por cuota basado en la frecuencia de pago
        if ($this->frecuencia_pago === 'quincenal') {
            // Para quincenal, usar la cuota semanal como base y duplicarla
            $cuotaSemanal = $this->monto_financiado / $this->plazo_semanas + ($this->monto_financiado * ($this->tasa_interes_anual / 100)) / 52;
            // La cuota quincenal es el doble de la cuota semanal
            $cuotaQuincenal = $cuotaSemanal * 2;
            // Desglosar la cuota quincenal en capital e interés
            $capital_por_cuota = $this->monto_financiado / $numCuotas;
            $interes_por_cuota = $cuotaQuincenal - $capital_por_cuota;
        } else {
            $interes_por_cuota = ($this->monto_financiado * ($this->tasa_interes_anual / 100)) / $periodsPerYear;
        }

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
            // 1. Crear Contrato
            $contrato = Contrato::create([
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
                'estado' => 'borrador',
                'saldo_pendiente' => $this->monto_financiado,
                'cuotas_totales' => $this->getNumCuotas(),
                'cuotas_pagadas' => 0,
                'cuotas_vencidas' => 0
            ]);

            // 2. Crear Plan de Pagos
            foreach ($this->plan_proyectado as $cuota) {
                PlanPago::create([
                    'contrato_id' => $contrato->id,
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
            }

            // 3. Actualizar estado de la unidad
            $unidad = MotoUnidad::find($this->moto_unidad_id);
            $unidad->estado = 'reservado';
            $unidad->save();
            
            // 4. Registrar movimiento de inventario (salida/reserva por contrato)
            InventoryMovement::create([
                'moto_unidad_id' => $unidad->id,
                'tipo' => 'salida',
                'origen_sucursal_id' => $unidad->sucursal_id ?? $this->sucursal_id,
                'destino_sucursal_id' => null,
                'responsable_id' => $this->vendedor_id ?? auth()->id(),
                'cantidad' => 1,
                'observaciones' => "Reserva por contrato {$this->numero_contrato}",
                'occurred_at' => now()
            ]);
        });

        session()->flash('message', 'Contrato creado exitosamente en estado Borrador.');
        return redirect()->route('admin.contratos.index');
    }

    public function render()
    {
        return view('livewire.admin.contratos.create')->layout($this->getLayout());
    }
}