<?php
/*
|--------------------------------------------------------------------------
| Certificado Oficial de Finalización en PDF — EduGT (Dompdf Landscape)
|--------------------------------------------------------------------------
*/
$studentName = $payload['student_name'] ?? 'Estudiante';
$classroomName = $payload['classroom_name'] ?? 'Programa EduGT';
$institution = $payload['institution'] ?? 'Institución Educativa';
$gradeLevel = $payload['grade_level'] ?? '';
$academicYear = $payload['academic_year'] ?? date('Y');
$teacherName = $payload['teacher_name'] ?? 'Docente Titular';
$preScore = $payload['pre_score'] ?? null;
$postScore = $payload['post_score'] ?? 0;
$gain = $payload['gain'] ?? null;
$xpTotal = $payload['xp_total'] ?? 0;
$xpLevel = $payload['xp_level'] ?? 1;
$rankName = $payload['rank_name'] ?? 'Iniciado';
$badgesCount = $payload['badges_count'] ?? 0;
$modules = $payload['modules'] ?? [];
$issuedDate = $payload['issued_date'] ?? date('d/m/Y');
$folio = $payload['folio'] ?? 'EDUGT-00000';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Certificado Oficial EduGT — {{ $studentName }}</title>
<style>
    @page {
        margin: 22px 26px;
    }
    body {
        font-family: DejaVu Sans, sans-serif;
        color: #0f172a;
        margin: 0;
        padding: 0;
        background-color: #ffffff;
    }
    .outer-frame {
        border: 4px solid #4c1d95;
        padding: 6px;
        background-color: #faf5ff;
    }
    .inner-frame {
        border: 1.5px solid #7c3aed;
        background-color: #ffffff;
        padding: 24px 34px 20px 34px;
        text-align: center;
    }
    .top-kicker {
        font-size: 10px;
        font-weight: bold;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #6d28d9;
        margin-bottom: 4px;
    }
    .institution-title {
        font-size: 13px;
        font-weight: bold;
        color: #334155;
        margin-bottom: 10px;
    }
    .cert-main-title {
        font-size: 25px;
        font-weight: bold;
        color: #1e1b4b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0 0 6px 0;
    }
    .cert-subtitle {
        font-size: 11px;
        color: #475569;
        margin: 0 0 12px 0;
    }
    .student-name {
        font-size: 24px;
        font-weight: bold;
        color: #5b21b6;
        border-bottom: 2px solid #ddd6fe;
        display: inline-block;
        padding: 0 28px 5px 28px;
        margin: 2px 0 12px 0;
    }
    .cert-body-text {
        font-size: 11px;
        color: #334155;
        line-height: 1.55;
        width: 88%;
        margin: 0 auto 14px auto;
    }
    .modules-pill-wrap {
        margin: 0 auto 16px auto;
    }
    .module-pill {
        display: inline-block;
        background-color: #f3e8ff;
        border: 1px solid #c4b5fd;
        color: #4c1d95;
        font-size: 9.5px;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 12px;
        margin: 2px 4px;
    }
    .stats-table {
        width: 92%;
        margin: 0 auto 18px auto;
        border-collapse: collapse;
    }
    .stats-cell {
        width: 25%;
        border: 1px solid #e2e8f0;
        background-color: #f8fafc;
        padding: 8px 6px;
        text-align: center;
    }
    .stats-val {
        font-size: 14px;
        font-weight: bold;
        color: #4c1d95;
        display: block;
    }
    .stats-lbl {
        font-size: 8.5px;
        color: #64748b;
        text-transform: uppercase;
        margin-top: 2px;
        display: block;
    }
    .signatures-table {
        width: 92%;
        margin: 22px auto 8px auto;
        border-collapse: collapse;
    }
    .signatures-table td {
        width: 33.33%;
        text-align: center;
        vertical-align: bottom;
        padding: 0 10px;
    }
    .sig-line {
        border-top: 1px solid #475569;
        width: 80%;
        margin: 0 auto 5px auto;
    }
    .sig-name {
        font-size: 10px;
        font-weight: bold;
        color: #0f172a;
    }
    .sig-role {
        font-size: 8.5px;
        color: #64748b;
    }
    .seal-box {
        border: 1.5px dashed #7c3aed;
        background-color: #faf5ff;
        padding: 7px 10px;
        border-radius: 8px;
        display: inline-block;
    }
    .seal-folio {
        font-size: 9.5px;
        font-weight: bold;
        color: #5b21b6;
    }
    .seal-date {
        font-size: 8px;
        color: #64748b;
    }
</style>
</head>
<body>
<div class="outer-frame">
    <div class="inner-frame">
        <div class="top-kicker">Plataforma Educativa Gamificada · EduGT Guatemala</div>
        <div class="institution-title">{{ $institution }} — {{ $classroomName }} (Ciclo {{ $academicYear }})</div>

        <h1 class="cert-main-title">Certificado de Logro Académico</h1>
        <p class="cert-subtitle">Se otorga el presente reconocimiento oficial a:</p>

        <div class="student-name">{{ $studentName }}</div>

        <p class="cert-body-text">
            Por haber completado satisfactoriamente el programa formativo de <strong>Fundamentos de Lógica de Programación y Pensamiento Computacional</strong>,
            superando la totalidad de las lecciones interactivas, retos algorítmicos, evaluaciones modulares y la evaluación diagnóstica final (Post-test).
        </p>

        <div class="modules-pill-wrap">
            @foreach ($modules as $idx => $modTitle)
                <span class="module-pill">✓ Módulo {{ $idx + 1 }}: {{ preg_replace('/^Módulo\s*\d+\s*[:.-]?\s*/iu', '', $modTitle) }}</span>
            @endforeach
        </div>

        <table class="stats-table">
            <tr>
                <td class="stats-cell">
                    <span class="stats-val">{{ $postScore }} %</span>
                    <span class="stats-lbl">Evaluación Final (Post-test)</span>
                </td>
                <td class="stats-cell">
                    <span class="stats-val">
                        @if ($gain !== null)
                            {{ $gain >= 0 ? '+'.$gain.' pts' : $gain.' pts' }} (Pre: {{ $preScore }}%)
                        @else
                            Completado
                        @endif
                    </span>
                    <span class="stats-lbl">Evolución Diagnóstica</span>
                </td>
                <td class="stats-cell">
                    <span class="stats-val">Nivel {{ $xpLevel }} · {{ $rankName }}</span>
                    <span class="stats-lbl">{{ $xpTotal }} XP Acumulados</span>
                </td>
                <td class="stats-cell">
                    <span class="stats-val">{{ $badgesCount }} / 6</span>
                    <span class="stats-lbl">Insignias Desbloqueadas</span>
                </td>
            </tr>
        </table>

        <table class="signatures-table">
            <tr>
                <td>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $teacherName }}</div>
                    <div class="sig-role">Docente Titular · {{ $classroomName }}</div>
                </td>
                <td>
                    <div class="seal-box">
                        <div class="seal-folio">FOLIO: {{ $folio }}</div>
                        <div class="seal-date">Emitido el {{ $issuedDate }}</div>
                    </div>
                </td>
                <td>
                    <div class="sig-line"></div>
                    <div class="sig-name">Coordinación EduGT</div>
                    <div class="sig-role">Certificación de Pensamiento Computacional</div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
