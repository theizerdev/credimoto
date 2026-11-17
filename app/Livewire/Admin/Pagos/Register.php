<?php

namespace App\Livewire\Admin\Pagos;

use App\Traits\HasDynamicLayout;
use Livewire\Component;
use App\Models\Contrato;
use App\Models\PlanPago;
use App\Models\ConceptoPago;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;

class Register extends Component
{
    use HasDynamicLayout;

    public $contrato;
    public $cuotas_pendientes = [];
    public $cuotas_seleccionadas = []; // IDs de cuotas a pagar
    
    // Datos del Pago
    public $fecha_pago;
    public $metodo_pago = 'efectivo';
    public $referencia = '';
    public $monto_recibido = 0;
    public $observaciones = '';
    
    // Totales calculados
    public $total_a_pagar = 0;
    public $mora_total = 0;

    protected $rules = [
        'cuotas_seleccionadas' => 'required|array|min:1',
        'fecha_pago' => 'required|date',
        'metodo_pago' => 'required|string',
        'referencia' => 'nullable|string|max:50',
        'monto_recibido' => 'required|numeric|min:0.01',
        'observaciones' => 'nullable|string|max:255',
    ];

    public function mount(Contrato $contrato)
    {
        $this->contrato = $contrato;
        $this->fecha_pago = date('Y-m-d');
        $this->loadCuotasPendientes();
    }

    public function loadCuotasPendientes()
    {
        $this->cuotas_pendientes = $this->contrato->planPagos()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencido'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();
    }

    public function updatedCuotasSeleccionadas()
    {
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $this->total_a_pagar = 0;
        $this->mora_total = 0; // Aquí se podría implementar cálculo de mora dinámica

        foreach ($this->cuotas_seleccionadas as $cuotaId) {
            $cuota = $this->cuotas_pendientes->find($cuotaId);
            if ($cuota) {
                $this->total_a_pagar += $cuota->saldo_pendiente;
            }
        }
        
        // Sugerir monto recibido igual al total
        $this->monto_recibido = $this->total_a_pagar;
    }

    public function toggleCuota($cuotaId)
    {
        if (in_array($cuotaId, $this->cuotas_seleccionadas)) {
            $this->cuotas_seleccionadas = array_diff($this->cuotas_seleccionadas, [$cuotaId]);
        } else {
            // Validar orden secuencial (opcional pero recomendado)
            // Aquí permitimos selección libre por ahora
            $this->cuotas_seleccionadas[] = $cuotaId;
        }
        $this->calculateTotal();
    }

    public function save()
    {
        $this->validate();

        if ($this->monto_recibido < $this->total_a_pagar) {
            $this->addError('monto_recibido', 'El monto recibido debe cubrir el total de las cuotas seleccionadas.');
            return;
        }

        DB::transaction(function () {
            // 1. Registrar Pago Global
            // Aquí asumiremos que existe un modelo Pago general, si no, lo creamos o adaptamos.
            // Para simplificar, actualizaremos directamente las cuotas y el contrato.
            
            // TODO: Crear tabla de Pagos (historial) si no existe. 
            // Por ahora actualizamos PlanPago.

            foreach ($this->cuotas_seleccionadas as $cuotaId) {
                $cuota = PlanPago::find($cuotaId);
                
                $cuota->update([
                    'estado' => 'pagado',
                    'fecha_pago_real' => $this->fecha_pago,
                    'monto_pagado' => $cuota->monto_pagado + $cuota->saldo_pendiente, // Asumiendo pago total
                    'saldo_pendiente' => 0
                ]);
            }

            // 2. Actualizar Contrato
            $pagadas = $this->contrato->planPagos()->where('numero_cuota', '>', 0)->where('estado', 'pagado')->count();
            $saldo_contrato = $this->contrato->planPagos()->sum('saldo_pendiente');
            
            $estado_contrato = $saldo_contrato <= 0 ? 'completado' : 'activo';

            $this->contrato->update([
                'cuotas_pagadas' => $pagadas,
                'saldo_pendiente' => $saldo_contrato,
                'estado' => $estado_contrato
            ]);
        });

        session()->flash('message', 'Pago registrado correctamente.');
        return redirect()->route('admin.contratos.show', $this->contrato->id);
    }

    public function render()
    {
        return view('livewire.admin.pagos.register')->layout($this->getLayout());
    }
}
