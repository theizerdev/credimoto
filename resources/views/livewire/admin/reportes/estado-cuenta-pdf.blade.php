<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Estado de Cuenta</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        h2 { margin: 0 0 4px 0; color: #1e293b; font-size: 18px; }
        h3 { margin: 12px 0 6px 0; font-size: 13px; color: #334155; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; margin-bottom: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; font-size: 10px; }
        th { background: #f1f5f9; color: #475569; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .mt-1 { margin-top: 6px; }
        .mt-2 { margin-top: 10px; }
        .mb-2 { margin-bottom: 10px; }
        .small { font-size: 10px; color: #64748b; }
        .badge { display: inline-block; padding: 2px 5px; font-size: 9px; border-radius: 3px; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #a16207; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-primary { background: #e0e7ff; color: #4338ca; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 10px; margin-top: 6px; }
        .contract-banner { background: #eff6ff; border-left: 4px solid #3b82f6; border-top: 1px solid #dbeafe; border-right: 1px solid #dbeafe; border-bottom: 1px solid #dbeafe; padding: 6px 10px; margin-top: 6px; }
    </style>
</head>
<body>
    <h2>Estado de Cuenta</h2>
    <div class="small">Fecha de emisión: {{ $generatedAt }}</div>

    <div class="info-box">
        <strong>Cliente:</strong> {{ $cliente?->nombre }} {{ $cliente?->apellido }} &nbsp;|&nbsp;
        <strong>Documento:</strong> {{ $cliente?->tipo_documento }} {{ $cliente?->documento }} &nbsp;|&nbsp;
        <strong>Teléfono:</strong> {{ $cliente?->telefono ?? 'N/A' }}
    </div>

    @if(!empty($contratoSeleccionado))
        <div class="contract-banner">
            <strong style="color: #1d4ed8; font-size: 12px;">Contrato N° {{ $contratoSeleccionado->numero_contrato ?? $contratoSeleccionado->id }}</strong> &nbsp;|&nbsp;
            <span>
                <strong>Unidad:</strong> {{ $contratoSeleccionado->unidad->moto->marca ?? '' }} {{ $contratoSeleccionado->unidad->moto->modelo ?? '' }} ({{ $contratoSeleccionado->unidad->moto->anio ?? '' }}) &nbsp;|&nbsp;
                <strong>Estado:</strong> {{ ucfirst($contratoSeleccionado->estado) }} &nbsp;|&nbsp;
                <strong>Monto Financiado:</strong> ${{ number_format($contratoSeleccionado->monto_financiado ?? 0, 2) }}
            </span>
        </div>
    @endif

    <div class="mt-2" style="background: #f1f5f9; padding: 6px 10px; border-radius: 4px;">
        <strong>Total Pagado:</strong> ${{ number_format($result['resumen']['total_pagado'] ?? 0, 2) }}
        @if(!empty($result['resumen']['total_pagado_bs']) && $result['resumen']['total_pagado_bs'] > 0)
            <span style="color: #475569;">(Bs. {{ number_format($result['resumen']['total_pagado_bs'], 2) }})</span>
        @endif
        &nbsp;|&nbsp;
        <strong>Saldo Pendiente:</strong> ${{ number_format($result['resumen']['pendiente'] ?? 0, 2) }} &nbsp;|&nbsp;
        <strong>Próximo Vencimiento:</strong> {{ $result['resumen']['proximo_vencimiento'] ?? 'N/A' }} &nbsp;|&nbsp;
        <strong>Estado:</strong> 
        @php
            $est = $result['resumen']['estado'] ?? 'pendiente';
            $badgeCls = $est === 'moroso' ? 'badge-danger' : ($est === 'pendiente' ? 'badge-warning' : 'badge-success');
            $txt = $est === 'moroso' ? 'Moroso' : ($est === 'pendiente' ? 'Pendiente' : 'Al Día');
        @endphp
        <span class="badge {{ $badgeCls }}">{{ $txt }}</span>
    </div>
    
    <h3 class="mt-2">Contratos {{ !empty($contratoSeleccionado) ? '(Seleccionado)' : '' }}</h3>
    <table>
        <thead>
            <tr>
                <th>N° Contrato</th>
                <th>Unidad</th>
                <th>Fecha Inicio</th>
                <th>Estado</th>
                <th class="text-right">Monto Financiado</th>
                <th class="text-right">Saldo Pendiente</th>
            </tr>
        </thead>
        <tbody>
            @php
                $contratosList = !empty($contratoSeleccionado)
                    ? collect($result['contratos'])->where('id', $contratoSeleccionado->id)
                    : collect($result['contratos']);
            @endphp
            @forelse($contratosList as $c)
            <tr @if(!empty($contratoSeleccionado) && $c['id'] == $contratoSeleccionado->id) style="background: #eff6ff;" @endif>
                <td><strong>{{ $c['numero_contrato'] ?? $c['id'] }}</strong></td>
                <td>{{ $c['unidad']['moto']['marca'] ?? '' }} {{ $c['unidad']['moto']['modelo'] ?? '' }} ({{ $c['unidad']['moto']['anio'] ?? '' }})</td>
                <td>{{ \Carbon\Carbon::parse($c['created_at'])->format('d/m/Y') }}</td>
                <td>{{ ucfirst($c['estado'] ?? 'borrador') }}</td>
                <td class="text-right">${{ number_format($c['monto_financiado'] ?? 0, 2) }}</td>
                <td class="text-right">${{ number_format($c['saldo_pendiente'] ?? 0, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="small text-center">Sin contratos registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 class="mt-2">Cuotas Pagadas</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Contrato</th>
                <th style="width: 30%;">Descripción</th>
                <th style="width: 14%;">Fecha Pago</th>
                <th style="width: 14%;" class="text-right">Tasa (Bs.)</th>
                <th style="width: 15%;" class="text-right">Monto ($)</th>
                <th style="width: 15%;" class="text-right">Monto (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totUsd = 0;
                $totBs = 0;
            @endphp
            @forelse($result['cuotas_pagadas'] as $c)
            @php
                $mUsd = (float)($c['monto'] ?? 0);
                $mBs = (float)($c['monto_bs'] ?? 0);
                $totUsd += $mUsd;
                $totBs += $mBs;
            @endphp
            <tr>
                <td>#{{ $c['numero_contrato'] ?? $c['contrato_id'] }}</td>
                <td>{{ $c['descripcion'] }}</td>
                <td>{{ $c['fecha_pago'] ?: '-' }}</td>
                <td class="text-right">{{ !empty($c['tasa_cambio']) ? number_format($c['tasa_cambio'], 2) : '-' }}</td>
                <td class="text-right">${{ number_format($mUsd, 2) }}</td>
                <td class="text-right">Bs. {{ number_format($mBs, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="small text-center">Sin cuotas pagadas</td></tr>
            @endforelse
        </tbody>
        @if(!empty($result['cuotas_pagadas']) && count($result['cuotas_pagadas']) > 0)
        <tfoot>
            <tr style="background: #f1f5f9; font-weight: bold;">
                <td colspan="4" class="text-right"><strong>TOTAL PAGADO:</strong></td>
                <td class="text-right"><strong>${{ number_format($totUsd, 2) }}</strong></td>
                <td class="text-right"><strong>Bs. {{ number_format($totBs, 2) }}</strong></td>
            </tr>
        </tfoot>
        @endif
    </table>

    @if(!empty($result['cuotas_pagadas']) && count($result['cuotas_pagadas']) > 0)
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; margin-top: 4px; text-align: right; font-size: 10px;">
        <span style="margin-right: 18px;"><strong>Sumatoria Total en Divisa:</strong> <span style="color: #15803d; font-weight: bold; font-size: 11px;">${{ number_format($totUsd, 2) }}</span></span>
        <span><strong>Sumatoria Total en Bolívares:</strong> <span style="color: #1e40af; font-weight: bold; font-size: 11px;">Bs. {{ number_format($totBs, 2) }}</span></span>
    </div>
    @endif

    <h3 class="mt-2">Cuotas Pendientes</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Contrato</th>
                <th style="width: 15%;"># Cuota</th>
                <th style="width: 30%;">Vencimiento</th>
                <th style="width: 20%;">Estado</th>
                <th style="width: 20%;" class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($result['cuotas_pendientes'] as $c)
            <tr>
                <td>#{{ $c['numero_contrato'] ?? $c['contrato_id'] }}</td>
                <td>{{ $c['numero'] }}</td>
                <td>{{ $c['vencimiento'] }}</td>
                <td>{{ ucfirst($c['estado']) }}</td>
                <td class="text-right">${{ number_format($c['saldo'], 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="small text-center">Sin cuotas pendientes</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 class="mt-2">Historial de Pagos</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 25%;">N° Recibo</th>
                <th style="width: 20%;">Fecha</th>
                <th style="width: 20%;" class="text-right">Total</th>
                <th style="width: 20%;">Método</th>
                <th style="width: 15%;">Referencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($result['pagos'] as $p)
            <tr>
                <td>{{ $p['numero_completo'] ?? '' }}</td>
                <td>{{ !empty($p['fecha']) ? \Carbon\Carbon::parse($p['fecha'])->format('d/m/Y') : '' }}</td>
                <td class="text-right">${{ number_format($p['total'] ?? 0, 2) }}</td>
                <td>{{ ucfirst($p['metodo_pago'] ?? '') }}</td>
                <td>{{ $p['referencia'] ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="small text-center">Sin pagos registrados</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
