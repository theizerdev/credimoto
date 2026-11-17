<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard - Resumen Ejecutivo</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 16px; }
        h2 { margin: 0 0 4px 0; color: #2b2c40; }
        h3 { margin: 16px 0 6px 0; color: #566a7f; border-bottom: 1px solid #d9dee3; padding-bottom: 4px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #d9dee3; padding: 5px 7px; text-align: left; }
        th { background: #f5f5f9; font-weight: bold; }
        .text-right { text-align: right; }
        .small { font-size: 10px; color: #697a8d; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-danger { background: #ffe0db; color: #ff3e1d; }
        .badge-warning { background: #fff2d6; color: #ffab00; }
        .badge-info { background: #d7f5fc; color: #03c3ec; }
    </style>
</head>
<body>
    <h2>Panel de Control - Resumen Ejecutivo</h2>
    <div class="small">Generado: {{ $generatedAt }} | Clientes en sistema: {{ $clienteCount }}</div>
    @if(!empty($filters))
        <div class="small">
            Filtros aplicados:
            Desde: {{ $filters['dateFrom'] ?: 'Todos' }} |
            Hasta: {{ $filters['dateTo'] ?: 'Todos' }} |
            Anticipación: {{ $filters['anticipationDays'] ?? 7 }} días |
            Monto: {{ $filters['minAmount'] !== null ? '$' . number_format($filters['minAmount'], 2) : 'Mín' }} - {{ $filters['maxAmount'] !== null ? '$' . number_format($filters['maxAmount'], 2) : 'Máx' }}
            @if(!empty($filters['searchClient'])) | Búsqueda: {{ $filters['searchClient'] }} @endif
        </div>
    @endif

    <h3>Métricas Generales</h3>
    <table>
        <thead>
            <tr>
                <th>Métrica</th>
                <th class="text-right">Valor</th>
                <th>Métrica</th>
                <th class="text-right">Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Clientes Totales (Activos / Con Mora)</td>
                <td class="text-right">{{ $metrics['clientes']['total'] ?? 0 }} ({{ $metrics['clientes']['activos'] ?? 0 }} / {{ $metrics['clientes']['con_mora'] ?? 0 }})</td>
                <td>Contratos Vigentes (Al día / Mora)</td>
                <td class="text-right">{{ $metrics['contratos']['vigentes'] ?? 0 }} ({{ $metrics['contratos']['al_dia'] ?? 0 }} / {{ $metrics['contratos']['vencidos'] ?? 0 }})</td>
            </tr>
            <tr>
                <td>Pagos Recibidos / Pendientes</td>
                <td class="text-right">{{ $metrics['pagos']['recibidos'] ?? 0 }} / {{ $metrics['pagos']['pendientes'] ?? 0 }}</td>
                <td>Cuotas Vencidas (Saldo Mora)</td>
                <td class="text-right">{{ $metrics['pagos']['morosos'] ?? 0 }} (${{ number_format($metrics['pagos']['saldo_mora'] ?? 0, 2) }})</td>
            </tr>
            <tr>
                <td>{{ $metrics['ingresos']['periodo_label'] ?? 'Ingresos Mensuales' }}</td>
                <td class="text-right">${{ number_format($metrics['ingresos']['mensual'] ?? 0, 2) }}</td>
                <td>Ingresos Anuales</td>
                <td class="text-right">${{ number_format($metrics['ingresos']['anual'] ?? 0, 2) }}</td>
            </tr>
            @if(!empty($charts['cartera']))
            <tr>
                <td>Cartera por Vencer</td>
                <td class="text-right">${{ number_format($charts['cartera']['saldo_por_vencer'] ?? 0, 2) }}</td>
                <td>Total Cobrado (Período/Global)</td>
                <td class="text-right">${{ number_format($charts['cartera']['total_cobrado'] ?? 0, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if(!empty($cuotas))
    <h3>Cuotas Próximas a Vencerse / Vencidas (Top {{ count($cuotas) }})</h3>
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Contrato</th>
                <th>Cuota</th>
                <th>Vencimiento</th>
                <th>Estado</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cuotas as $c)
            <tr>
                <td>{{ $c['cliente_nombre'] }}</td>
                <td>#{{ $c['numero_contrato'] }}</td>
                <td>#{{ $c['numero_cuota'] }}</td>
                <td>{{ $c['fecha_vencimiento_fmt'] }}</td>
                <td>{{ $c['dias_texto'] }}</td>
                <td class="text-right">${{ $c['saldo_pendiente_fmt'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($contratos))
    <h3>Contratos Próximos a Vencer / Activos (Top {{ count($contratos) }})</h3>
    <table>
        <thead>
            <tr>
                <th>Contrato</th>
                <th>Cliente</th>
                <th>Progreso</th>
                <th>Fecha Fin</th>
                <th>Estado</th>
                <th class="text-right">Saldo Pendiente</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contratos as $ct)
            <tr>
                <td>#{{ $ct['numero_contrato'] }}</td>
                <td>{{ $ct['cliente_nombre'] }}</td>
                <td>{{ $ct['progreso_texto'] }} ({{ $ct['progreso_pct'] }}%)</td>
                <td>{{ $ct['fecha_fin_fmt'] }}</td>
                <td>{{ ucfirst($ct['estado']) }} - {{ $ct['dias_texto'] }}</td>
                <td class="text-right">${{ $ct['saldo_pendiente_fmt'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="small" style="margin-top: 16px;">Este documento resume el estado del sistema conforme a los filtros seleccionados.</p>
</body>
</html>
