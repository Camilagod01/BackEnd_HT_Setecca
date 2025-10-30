<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Estado de cuenta</title>
  <style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 12px; color: #111; }
    h1 { font-size: 18px; margin: 0 0 8px; }
    h2 { font-size: 14px; margin: 16px 0 6px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #ddd; padding: 6px; }
    th { background: #f5f5f5; text-align: left; }
    .right { text-align: right; }
    .muted { color: #666; }
  </style>
</head>
<body>
  @php
    $cur = $s['currency'] ?? 'CRC';
    $fx  = $s['exchange_rate'] ?? null;
    $fmt = fn($n) => number_format((float)$n, 2, '.', ',').' '.$cur;
  @endphp

  <h1>Estado de cuenta</h1>
  <p class="muted">
    Empleado: <strong>{{ $s['employee']['name'] ?? '' }}</strong>
    ({{ $s['employee']['code'] ?? '' }})<br>
    Período: {{ $s['period']['from'] ?? '' }} — {{ $s['period']['to'] ?? '' }}<br>
    Moneda: {{ $cur }}@if($fx) — Tipo cambio: {{ number_format((float)$fx, 2) }} CRC/USD @endif
  </p>

  <h2>Resumen de horas</h2>
  <table>
    <tr><th>Concepto</th><th class="right">Horas</th></tr>
    <tr><td>1x (incluye feriados no trabajados)</td><td class="right">{{ $s['hours']['regular_1x'] ?? 0 }}</td></tr>
    <tr><td>Extra 1.5x</td><td class="right">{{ $s['hours']['overtime_15'] ?? 0 }}</td></tr>
    <tr><td>Doble 2x</td><td class="right">{{ $s['hours']['double_20'] ?? 0 }}</td></tr>
  </table>

  <h2>Ingresos</h2>
  <table>
    <tr><th>Detalle</th><th class="right">Monto</th></tr>
    @foreach(($s['incomes'] ?? []) as $i)
      <tr><td>{{ $i['label'] }}</td><td class="right">{{ $fmt($i['amount'] ?? 0) }}</td></tr>
    @endforeach
    <tr><th>Total bruto</th><th class="right">{{ $fmt($s['total_gross'] ?? 0) }}</th></tr>
  </table>

  <h2>Deducciones</h2>
  <table>
    <tr><th>Detalle</th><th class="right">Monto</th></tr>
    @foreach(($s['deductions'] ?? []) as $d)
      <tr><td>{{ $d['label'] }}</td><td class="right">{{ $fmt($d['amount'] ?? 0) }}</td></tr>
    @endforeach
    <tr><th>Total deducciones</th><th class="right">{{ $fmt($s['total_deductions'] ?? 0) }}</th></tr>
  </table>

  <h2>Neto a pagar</h2>
  <table>
    <tr><th>Neto</th><th class="right">{{ $fmt($s['net'] ?? 0) }}</th></tr>
  </table>

  <p class="muted">Generado por HT SETECCA — {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
