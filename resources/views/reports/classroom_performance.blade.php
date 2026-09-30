<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vistas de reportes PDF — contenido 100 % en español (RF-22)
|--------------------------------------------------------------------------
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>EduGT — Reporte</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a2e; }
    h1 { font-size: 18px; border-bottom: 2px solid #1a1a2e; padding-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; }
    th { background: #eee; }
    .meta { color: #555; margin-bottom: 10px; }
</style>
</head>
<body>
<h1>EduGT — Reporte académico</h1>
<p class="meta">Generado: {{ now()->timezone('America/Guatemala')->format('d/m/Y H:i') }}</p>

<section>
    <h2>Resumen general</h2>
    <table>
        <tr><th>Métrica</th><th>Valor</th></tr>
        <tr><td>Intentos totales</td><td>{{ $payload['metrics']['total_attempts'] ?? 0 }}</td></tr>
        <tr><td>Tasa de aprobación</td><td>{{ $payload['metrics']['pass_rate'] ?? 0 }} %</td></tr>
        <tr><td>Promedio general</td><td>{{ $payload['metrics']['avg_score'] ?? '—' }}</td></tr>
        <tr><td>Tasa de retención (14 días)</td><td>{{ $payload['metrics']['retention_rate'] ?? 0 }} %</td></tr>
    </table>
</section>

@if (!empty($payload['at_risk']))
<section>
    <h2>Estudiantes en riesgo de rezago</h2>
    <table>
        <tr><th>Estudiante</th><th>Intentos</th><th>Promedio</th></tr>
        @foreach ($payload['at_risk'] as $s)
            <tr><td>{{ $s['name'] ?? $s['user_id'] }}</td><td>{{ $s['attempts'] }}</td><td>{{ $s['avg_score'] }}</td></tr>
        @endforeach
    </table>
</section>
@endif

@if (!empty($payload['pre_post']))
<section>
    <h2>Comparación pretest ↔ postest</h2>
    <table>
        <tr><th>Métrica</th><th>Valor</th></tr>
        <tr><td>Estudiantes con par pre/post</td><td>{{ $payload['pre_post']['paired_students'] ?? 0 }}</td></tr>
        <tr><td>Promedio pretest</td><td>{{ $payload['pre_post']['pre_avg'] ?? '—' }}</td></tr>
        <tr><td>Promedio postest</td><td>{{ $payload['pre_post']['post_avg'] ?? '—' }}</td></tr>
        <tr><td>Ganancia media</td><td>{{ $payload['pre_post']['mean_gain'] ?? '—' }}</td></tr>
    </table>
</section>
@endif
</body>
</html>
