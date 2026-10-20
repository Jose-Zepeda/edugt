<?php
/*
|--------------------------------------------------------------------------
| Vistas de reportes PDF — contenido 100 % en español (RF-22, RB-12)
| Expediente Integral de Rendimiento Académico y Eficacia Pedagógica
|--------------------------------------------------------------------------
*/
$meta = $payload['meta'] ?? [];
$metrics = $payload['metrics'] ?? [];
$modulesSummary = $payload['modules_summary'] ?? [];
$students = $payload['students'] ?? [];
$prePost = $payload['pre_post'] ?? [];
$dimensions = $payload['dimensions'] ?? [];
$atRisk = $payload['at_risk'] ?? [];
$topFailed = $payload['top_failed_questions'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>EduGT — Reporte Académico Integral</title>
<style>
    @page {
        margin: 26px 28px 34px 28px;
    }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9.5px;
        color: #1e293b;
        line-height: 1.38;
    }
    /* Encabezado institucional */
    .header-box {
        border: 1.5px solid #0f766e;
        background-color: #f0fdfa;
        padding: 10px 14px;
        margin-bottom: 12px;
    }
    .header-title {
        font-size: 15px;
        font-weight: bold;
        color: #0f766e;
        margin: 0 0 2px 0;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .header-subtitle {
        font-size: 10px;
        color: #334155;
        margin: 0 0 8px 0;
        font-weight: bold;
    }
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
    }
    .meta-table td {
        border: none;
        padding: 2px 6px 2px 0;
        font-size: 9px;
        color: #334155;
    }
    .meta-label {
        font-weight: bold;
        color: #0f172a;
    }

    /* Tarjetas KPI */
    .kpi-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    .kpi-cell {
        width: 25%;
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        padding: 7px 8px;
        text-align: center;
    }
    .kpi-value {
        font-size: 14px;
        font-weight: bold;
        color: #0f766e;
        display: block;
    }
    .kpi-caption {
        font-size: 8px;
        color: #475569;
        text-transform: uppercase;
        margin-top: 2px;
        display: block;
    }

    /* Títulos de sección */
    h2 {
        font-size: 11.5px;
        color: #0f172a;
        background-color: #e2e8f0;
        border-left: 4px solid #0f766e;
        padding: 4px 8px;
        margin: 13px 0 6px 0;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    p.section-note {
        font-size: 8.5px;
        color: #475569;
        margin: 0 0 6px 0;
    }

    /* Tablas de datos */
    table.data-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    table.data-table th,
    table.data-table td {
        border: 1px solid #cbd5e1;
        padding: 4px 6px;
        text-align: left;
        vertical-align: middle;
        font-size: 8.8px;
    }
    table.data-table th {
        background-color: #0f172a;
        color: #ffffff;
        font-weight: bold;
        font-size: 8.3px;
        text-transform: uppercase;
    }
    table.data-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }
    .text-center { text-align: center !important; }
    .text-right { text-align: right !important; }
    .font-bold { font-weight: bold; }
    .muted { color: #64748b; font-size: 8px; }

    /* Barra de progreso horizontal compatible con Dompdf */
    .pbar-wrap {
        width: 100%;
        background-color: #e2e8f0;
        height: 7px;
        border: 1px solid #cbd5e1;
        overflow: hidden;
        margin-top: 2px;
    }
    .pbar-fill {
        height: 7px;
        background-color: #0d9488;
    }
    .pbar-fill-complete {
        height: 7px;
        background-color: #16a34a;
    }

    /* Etiquetas de estado */
    .badge {
        display: inline-block;
        padding: 1px 5px;
        font-size: 8px;
        font-weight: bold;
        border-radius: 3px;
    }
    .badge-ok {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .badge-warn {
        background-color: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .badge-danger {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .badge-neutral {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    /* Firmas y pie */
    .signature-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 28px;
    }
    .signature-table td {
        width: 45%;
        border: none;
        text-align: center;
        padding-top: 24px;
        font-size: 8.8px;
        color: #334155;
    }
    .sig-line {
        border-top: 1px solid #475569;
        width: 75%;
        margin: 0 auto 4px auto;
    }
    .footer-note {
        margin-top: 12px;
        border-top: 1px solid #cbd5e1;
        padding-top: 5px;
        font-size: 7.8px;
        color: #64748b;
        text-align: center;
    }
</style>
</head>
<body>

<!-- 1. ENCABEZADO INSTITUCIONAL -->
<div class="header-box">
    <div class="header-title">EduGT — Reporte Académico y Eficacia Pedagógica</div>
    <div class="header-subtitle">Aula / Cohorte: {{ $meta['title'] ?? 'Reporte General' }}</div>
    <table class="meta-table">
        <tr>
            <td><span class="meta-label">Institución:</span> {{ $meta['institution'] ?? '—' }}</td>
            <td><span class="meta-label">Grado / Nivel:</span> {{ $meta['grade_level'] ?? '—' }}</td>
            <td><span class="meta-label">Ciclo escolar:</span> {{ $meta['academic_year'] ?? date('Y') }}</td>
        </tr>
        <tr>
            <td><span class="meta-label">Docente titular:</span> {{ $meta['teacher_name'] ?? '—' }} ({{ $meta['teacher_email'] ?? '—' }})</td>
            <td><span class="meta-label">Código de aula:</span> {{ $meta['code'] ?? '—' }}</td>
            <td><span class="meta-label">Fecha de emisión:</span> {{ $meta['generated_at'] ?? now()->timezone('America/Guatemala')->format('d/m/Y H:i') }}</td>
        </tr>
    </table>
</div>

<!-- KPIS GLOBALES -->
<table class="kpi-table">
    <tr>
        <td class="kpi-cell">
            <span class="kpi-value">{{ $meta['students_count'] ?? 0 }}</span>
            <span class="kpi-caption">Estudiantes Inscritos</span>
        </td>
        <td class="kpi-cell">
            <span class="kpi-value">{{ $meta['avg_course_progress'] ?? 0 }} %</span>
            <span class="kpi-caption">Avance Curricular Medio</span>
        </td>
        <td class="kpi-cell">
            <span class="kpi-value">{{ $metrics['pass_rate'] ?? 0 }} %</span>
            <span class="kpi-caption">Aprobación de Evaluaciones ({{ $metrics['passed_attempts'] ?? 0 }}/{{ $metrics['total_attempts'] ?? 0 }})</span>
        </td>
        <td class="kpi-cell">
            <span class="kpi-value">{{ $metrics['retention_rate'] ?? 0 }} %</span>
            <span class="kpi-caption">Retención Activa (14 días)</span>
        </td>
    </tr>
</table>

<!-- 2. RENDIMIENTO POR MÓDULO CURRICULAR -->
<h2>1. Desempeño por Módulo Curricular</h2>
<p class="section-note">Avance de lectura interactiva y desempeño en las evaluaciones sumativas de los 3 módulos del currículo de Pensamiento Computacional.</p>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 34%;">Módulo Curricular</th>
            <th class="text-center" style="width: 14%;">Etapas / Lecciones</th>
            <th class="text-center" style="width: 18%;">Estudiantes con Lecciones Completas</th>
            <th class="text-center" style="width: 16%;">Estudiantes con Quiz Aprobado (≥70%)</th>
            <th class="text-center" style="width: 9%;">Intentos Quiz</th>
            <th class="text-center" style="width: 9%;">Promedio Quiz</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($modulesSummary as $mod)
            <tr>
                <td class="font-bold">{{ $mod['title'] }}</td>
                <td class="text-center">{{ $mod['lessons_count'] }} etapas</td>
                <td class="text-center">{{ $mod['students_completed_lessons'] }} / {{ $meta['students_count'] ?? 0 }}</td>
                <td class="text-center">
                    <span class="badge {{ $mod['students_passed_quiz'] > 0 ? 'badge-ok' : 'badge-neutral' }}">
                        {{ $mod['students_passed_quiz'] }} / {{ $meta['students_count'] ?? 0 }} ({{ $mod['pass_rate'] }} %)
                    </span>
                </td>
                <td class="text-center">{{ $mod['quiz_attempts'] }}</td>
                <td class="text-center font-bold">{{ $mod['avg_quiz_score'] !== null ? $mod['avg_quiz_score'].' %' : '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center muted">No hay módulos publicados registrados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<!-- 3. NÓMINA DETALLADA DE ESTUDIANTES -->
<h2>2. Nómina Detallada de Progreso y Calificaciones por Estudiante</h2>
<p class="section-note">Detalle individual de diagnóstico inicial (Pre-test), avance en las 12 etapas del curso, mejores notas por módulo (M1, M2, M3), evaluación final (Post-test) y gamificación.</p>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 21%;">Estudiante</th>
            <th class="text-center" style="width: 10%;">Pre-test</th>
            <th style="width: 22%;">Progreso Curricular</th>
            <th class="text-center" style="width: 7%;">M1</th>
            <th class="text-center" style="width: 7%;">M2</th>
            <th class="text-center" style="width: 7%;">M3</th>
            <th class="text-center" style="width: 9%;">Post-test</th>
            <th class="text-center" style="width: 8%;">Ganancia</th>
            <th class="text-center" style="width: 9%;">XP / Medallas</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($students as $st)
            <tr>
                <td>
                    <div class="font-bold">{{ $st['name'] }}</div>
                    <div class="muted">{{ $st['email'] }}</div>
                </td>
                <td class="text-center">
                    @if ($st['pretest_score'] !== null)
                        <span class="badge badge-ok">{{ $st['pretest_score'] }} %</span>
                        <div class="muted">{{ $st['track_label'] }}</div>
                    @else
                        <span class="badge badge-warn">Pendiente</span>
                    @endif
                </td>
                <td>
                    <div>
                        <span class="font-bold">{{ $st['progress_percent'] }} %</span>
                        <span class="muted">({{ $st['completed_lessons'] }}/{{ $st['total_lessons'] }} etapas)</span>
                    </div>
                    <div class="pbar-wrap">
                        <div class="{{ $st['progress_percent'] >= 100 ? 'pbar-fill-complete' : 'pbar-fill' }}" style="width: {{ max(0, min(100, $st['progress_percent'])) }}%;"></div>
                    </div>
                    <div class="muted" style="margin-top: 2px;">{{ $st['current_stage_label'] }}</div>
                </td>
                @for ($mIdx = 0; $mIdx < 3; $mIdx++)
                    @php
                        $ms = $st['module_scores'][$mIdx] ?? null;
                    @endphp
                    <td class="text-center">
                        @if ($ms && ($ms['score'] ?? null) !== null)
                            <span class="badge {{ $ms['passed'] ? 'badge-ok' : 'badge-danger' }}">
                                {{ $ms['score'] }}%
                            </span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                @endfor
                <td class="text-center">
                    @if ($st['posttest_score'] !== null)
                        <span class="badge badge-ok">{{ $st['posttest_score'] }} %</span>
                    @else
                        <span class="muted">Pendiente</span>
                    @endif
                </td>
                <td class="text-center font-bold">
                    @if ($st['gain'] !== null)
                        <span style="color: {{ $st['gain'] >= 0 ? '#15803d' : '#b91c1c' }};">
                            {{ $st['gain'] >= 0 ? '+'.$st['gain'] : $st['gain'] }} pts
                        </span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td class="text-center">
                    <div class="font-bold">{{ $st['xp_total'] }} XP</div>
                    <div class="muted">Niv. {{ $st['xp_level'] }} · {{ $st['badges_count'] }}/6 med.</div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center muted">No hay estudiantes inscritos en esta aula todavía.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<!-- 4. EFICACIA PEDAGÓGICA: PRE-TEST VS POST-TEST E ÍNDICE DE HAKE -->
<h2>3. Eficacia Pedagógica (Pre-test ↔ Post-test y Ganancia Normalizada de Hake)</h2>
<p class="section-note">Evaluación cuasi-experimental del aprendizaje mediante pruebas diagnósticas pareadas y desglose en las 7 dimensiones del pensamiento computacional.</p>
<table class="data-table">
    <thead>
        <tr>
            <th>Con Pre-test</th>
            <th>Con Post-test</th>
            <th>Muestra Pareada (Pre + Post)</th>
            <th>Promedio Pre-test</th>
            <th>Promedio Post-test</th>
            <th>Ganancia Absoluta (Δ)</th>
            <th>Índice de Hake (g)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center font-bold">{{ $prePost['pre_students'] ?? 0 }} est.</td>
            <td class="text-center font-bold">{{ $prePost['post_students'] ?? 0 }} est.</td>
            <td class="text-center font-bold">{{ $prePost['paired_students'] ?? 0 }} pares</td>
            <td class="text-center">
                {{ $prePost['pre_avg'] !== null ? $prePost['pre_avg'].' %' : ($prePost['overall_pre_avg'] !== null ? $prePost['overall_pre_avg'].' % (gral.)' : '—') }}
            </td>
            <td class="text-center">
                {{ $prePost['post_avg'] !== null ? $prePost['post_avg'].' %' : ($prePost['overall_post_avg'] !== null ? $prePost['overall_post_avg'].' % (gral.)' : '—') }}
            </td>
            <td class="text-center font-bold">
                @if (($prePost['mean_gain'] ?? null) !== null)
                    {{ $prePost['mean_gain'] >= 0 ? '+'.$prePost['mean_gain'] : $prePost['mean_gain'] }} pts
                @else
                    —
                @endif
            </td>
            <td class="text-center font-bold">
                @if (($prePost['hake_g'] ?? null) !== null)
                    g = {{ $prePost['hake_g'] }} ({{ $prePost['hake_level'] ?? '' }})
                @else
                    Sin muestra pareada
                @endif
            </td>
        </tr>
    </tbody>
</table>

@if (!empty($dimensions))
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 46%;">Dimensión de Pensamiento Computacional</th>
            <th class="text-center" style="width: 18%;">Exactitud Pre-test</th>
            <th class="text-center" style="width: 18%;">Exactitud Post-test</th>
            <th class="text-center" style="width: 18%;">Evolución (Δ)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($dimensions as $dim)
            @php
                $dimDelta = ($dim['pre_avg'] !== null && $dim['post_avg'] !== null)
                    ? round($dim['post_avg'] - $dim['pre_avg'], 1)
                    : null;
            @endphp
            <tr>
                <td class="font-bold">{{ $dim['label'] }}</td>
                <td class="text-center">{{ $dim['pre_avg'] !== null ? $dim['pre_avg'].' %' : '—' }}</td>
                <td class="text-center">{{ $dim['post_avg'] !== null ? $dim['post_avg'].' %' : '—' }}</td>
                <td class="text-center font-bold">
                    @if ($dimDelta !== null)
                        <span style="color: {{ $dimDelta >= 0 ? '#15803d' : '#b91c1c' }};">
                            {{ $dimDelta >= 0 ? '+'.$dimDelta : $dimDelta }} pts
                        </span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- 5. ALERTAS TEMPRANAS Y REACTIVOS CRÍTICOS -->
<h2>4. Alertas Tempranas de Rezago y Reactivos con Mayor Dificultad</h2>
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="width: 48%; vertical-align: top; padding-right: 6px; border: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th colspan="3">Estudiantes en Riesgo (Promedio &lt; 60% con ≥ 2 intentos)</th>
                    </tr>
                    <tr>
                        <th>Estudiante</th>
                        <th class="text-center">Intentos</th>
                        <th class="text-center">Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($atRisk as $risk)
                        <tr>
                            <td>
                                <div class="font-bold">{{ $risk['name'] }}</div>
                                <div class="muted">{{ $risk['email'] ?? '' }}</div>
                            </td>
                            <td class="text-center">{{ $risk['attempts'] }}</td>
                            <td class="text-center"><span class="badge badge-danger">{{ $risk['avg_score'] }} %</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center muted">Sin estudiantes en alerta de rezago crítico.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
        <td style="width: 52%; vertical-align: top; padding-left: 6px; border: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th colspan="3">Reactivos con Mayor Índice de Error en Evaluaciones</th>
                    </tr>
                    <tr>
                        <th>Módulo / Pregunta</th>
                        <th class="text-center">Respuestas</th>
                        <th class="text-center">% Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topFailed as $tf)
                        <tr>
                            <td>
                                <div class="font-bold">{{ $tf['module_title'] }}</div>
                                <div class="muted">{{ $tf['statement'] }}</div>
                            </td>
                            <td class="text-center">{{ $tf['total'] }}</td>
                            <td class="text-center"><span class="badge badge-warn">{{ $tf['error_rate'] }} %</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center muted">Aún no se registran respuestas en cuestionarios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>

<!-- BLOQUE DE FIRMA Y VALIDACIÓN INSTITUCIONAL -->
<table class="signature-table">
    <tr>
        <td>
            <div class="sig-line"></div>
            <div class="font-bold">{{ $meta['teacher_name'] ?? 'Docente Titular' }}</div>
            <div class="muted">Firma del Catedrático / Docente Encargado</div>
        </td>
        <td style="width: 10%;"></td>
        <td>
            <div class="sig-line"></div>
            <div class="font-bold">Dirección Académica / Coordinación</div>
            <div class="muted">Sello y Visto Bueno — {{ $meta['institution'] ?? 'Institución Educativa' }}</div>
        </td>
    </tr>
</table>

<div class="footer-note">
    Documento generado automáticamente por la plataforma educativa gamificada <strong>EduGT</strong> el {{ $meta['generated_at'] ?? now()->timezone('America/Guatemala')->format('d/m/Y H:i') }} (Hora de Guatemala).
</div>

</body>
</html>
