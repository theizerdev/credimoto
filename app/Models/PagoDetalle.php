<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoDetalle extends Model
{
    protected $fillable = [
        'pago_id',
        'cliente_id',
        'concepto_pago_id',
        'plan_pago_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal'
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    public function cliente()
    {
        return $this->belongsTo(\App\Models\Cliente::class, 'cliente_id');
    }

    public function conceptoPago()
    {
        return $this->belongsTo(ConceptoPago::class);
    }

    public function planPago()
    {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($detalle) {
            $detalle->subtotal = $detalle->cantidad * $detalle->precio_unitario;
        });
    }
}
