/* ==========================================================================
   EduGT — Vista de lección interactiva (RF-9, RF-10)
   Markdown sanitizado con DOMPurify.
   El cronómetro mide tiempo real en página; el backend otorga el XP (RB-10).
   ========================================================================== */
'use strict';

Auth.load();
if (!Auth.token) {
  window.location.href = 'index.html';
}

const $ = (sel) => document.querySelector(sel);
const lessonId = new URLSearchParams(window.location.search).get('id');

// Cronómetro de tiempo invertido
let segundosEnPagina = 0;
const clock = setInterval(() => { segundosEnPagina++; }, 1000);

// Guardar tiempo al salir
window.addEventListener('beforeunload', () => {
  if (segundosEnPagina > 5 && lessonId) {
    navigator.sendBeacon?.(
      `${API_BASE}/lessons/${lessonId}/complete`,
      new Blob([JSON.stringify({ time_spent_seconds: segundosEnPagina })], { type: 'application/json' })
    );
  }
});

function showAlert(msg) {
  const el = $('#alert');
  if (el) {
    el.className = 'alert alert-danger';
    el.textContent = msg;
    el.classList.remove('d-none');
  }
}

/* ---------------- Render de la Lección ---------------- */
async function boot() {
  if (!lessonId) {
    $('#leccion-card').innerHTML = `
      <div class="text-center py-5">
        <i class="bi bi-exclamation-triangle text-warning fs-1 mb-2"></i>
        <p class="mb-0 text-muted">No se especificó ninguna lección para cargar.</p>
        <a href="estudiante.html" class="btn btn-edugt mt-3">Volver a mi ruta</a>
      </div>`;
    return;
  }

  try {
    const { data } = await api(`/lessons/${lessonId}`);
    const l = data.data;

    // Actualizar badge de módulo
    if ($('#lesson-track-badge')) {
      $('#lesson-track-badge').innerHTML = `<i class="bi bi-journal-code text-primary me-1"></i> Lección interactiva · ${l.estimated_minutes} min`;
    }

    // Markdown sanitizado
    const htmlContent = DOMPurify.sanitize(marked.parse(l.content_markdown ?? ''));

    // Bloque de Pseudocódigo con Simulador Interactivo Paso a Paso (Prueba de Escritorio)
    let pseudocodeHtml = '';
    let rawCodeForSim = '';
    if (l.pseudocode_json) {
      let codeText = '';
      let codeExplanation = '';

      if (typeof l.pseudocode_json === 'object') {
        codeText = l.pseudocode_json.codigo || l.pseudocode_json.ejemplo || JSON.stringify(l.pseudocode_json, null, 2);
        codeExplanation = l.pseudocode_json.explicacion || '';
      } else {
        codeText = String(l.pseudocode_json);
      }

      rawCodeForSim = codeText;
      const codeLines = codeText.split('\n');
      const linesHtml = codeLines.map((lineText, idx) => {
        const lineNum = idx + 1;
        const trimmed = lineText.trim();
        const isComment = trimmed.startsWith('//') || trimmed.startsWith('#');
        return `
          <div class="code-line-row" data-line="${lineNum}">
            <span class="code-line-arrow" data-arrow="${lineNum}"></span>
            <span class="code-line-num">${lineNum}</span>
            <span class="code-line-text ${isComment ? 'is-comment' : ''}">${esc(lineText || ' ')}</span>
          </div>`;
      }).join('');

      pseudocodeHtml = `
        <div class="code-window" id="lesson-code-window">
          <div class="code-window-header flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <div class="window-dots">
                <span class="window-dot dot-red"></span>
                <span class="window-dot dot-yellow"></span>
                <span class="window-dot dot-green"></span>
              </div>
              <span class="ms-2"><i class="bi bi-terminal me-1"></i> Pseudocódigo · Simulador de Prueba de Escritorio</span>
            </div>
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
              <button type="button" class="sim-toolbar-btn sim-btn-primary" id="btn-sim-step" title="Avanzar una instrucción">
                <i class="bi bi-play-fill"></i> Paso a paso
              </button>
              <button type="button" class="sim-toolbar-btn" id="btn-sim-auto" title="Ejecutar automáticamente línea por línea">
                <i class="bi bi-lightning-charge-fill text-warning"></i> Auto
              </button>
              <button type="button" class="sim-toolbar-btn d-none" id="btn-sim-reset" title="Reiniciar simulación">
                <i class="bi bi-arrow-counterclockwise"></i> Reiniciar
              </button>
              <button type="button" class="sim-toolbar-btn" id="btn-copy-code" title="Copiar pseudocódigo">
                <i class="bi bi-clipboard"></i> Copiar
              </button>
            </div>
          </div>

          <div class="code-lines-container" id="sim-code-lines">${linesHtml}</div>

          <!-- Panel de Prueba de Escritorio en Vivo (Memoria RAM + Consola) -->
          <div class="sim-workbench" id="sim-workbench">
            <div class="sim-step-banner">
              <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill px-2.5 py-1" id="sim-step-badge" style="background:#322B54;color:#C4B5FD;font-size:.75rem;">
                  Listo para ejecutar
                </span>
                <span id="sim-step-explanation" style="color:#E4E1F5;">
                  Haz clic en <strong>▶ Paso a paso</strong> o <strong>⚡ Auto</strong> para observar cómo cambian las variables en la memoria línea por línea.
                </span>
              </div>
            </div>

            <div class="row g-3">
              <!-- Columna 1: Memoria RAM (Variables) -->
              <div class="col-12 col-md-6">
                <div class="sim-panel-box">
                  <div class="sim-panel-title">
                    <span><i class="bi bi-cpu-fill text-info me-1"></i> Memoria RAM (Variables)</span>
                    <span id="sim-vars-count" class="badge rounded-pill" style="background:#241F3D;color:#A39DBE;font-size:.68rem;">0 activas</span>
                  </div>
                  <div id="sim-vars-body">
                    <div class="text-center py-3" style="color:#6E678F;font-size:.82rem;">
                      Aún no se han declarado variables en memoria.
                    </div>
                  </div>
                </div>
              </div>

              <!-- Columna 2: Consola de Salida (print) -->
              <div class="col-12 col-md-6">
                <div class="sim-panel-box">
                  <div class="sim-panel-title">
                    <span><i class="bi bi-window-terminal text-success me-1"></i> Consola de Salida (print)</span>
                    <span class="badge rounded-pill" style="background:#241F3D;color:#A39DBE;font-size:.68rem;">stdout</span>
                  </div>
                  <div id="sim-console-body">
                    <div class="text-center py-3" style="color:#6E678F;font-size:.82rem;">
                      Sin salidas en pantalla todavía.
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          ${codeExplanation ? `
            <div class="code-explanation-box">
              <i class="bi bi-info-circle-fill text-primary me-1"></i>
              <strong>¿Cómo funciona?</strong> ${esc(codeExplanation)}
            </div>` : ''}
        </div>`;
    }

    // Bloque de Reto Interactivo
    let retoHtml = '';
    if (l.pseudocode_json && l.pseudocode_json.reto) {
      const r = l.pseudocode_json.reto;
      const questionHtml = DOMPurify.sanitize(marked.parse(r.pregunta ?? ''));
      retoHtml = `
        <div id="lesson-challenge-box" class="card-edugt p-4 mb-4 border-2 mt-4" style="border: 2px solid ${l.completed ? 'var(--edugt-green)' : 'var(--edugt-primary)'}!important; background: ${l.completed ? 'rgba(16, 185, 129, 0.04)' : 'var(--edugt-bg-card)'};">
          <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <div class="stat-icon" style="width:40px;height:40px;background:${l.completed ? 'var(--edugt-green-soft)' : 'var(--edugt-primary-soft)'};color:${l.completed ? 'var(--edugt-green)' : 'var(--edugt-primary)'};font-size:1.2rem;border-radius:12px;">
                <i class="bi bi-puzzle-fill"></i>
              </div>
              <div>
                <h3 class="h6 fw-bold mb-0">Reto de Comprensión</h3>
                <small class="text-muted">Resuelve este acertijo para comprobar tu aprendizaje y activar la recompensa (+${l.xp_reward} XP).</small>
              </div>
            </div>
            ${l.completed ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Reto Superado</span>' : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill"><i class="bi bi-question-circle me-1"></i> Desafío Activo</span>'}
          </div>

          <div class="p-3 mb-3 rounded-3 lesson-markdown lesson-challenge-prompt" style="font-size:.98rem;">
            ${questionHtml}
          </div>

          <!-- Opciones interactivas -->
          <div class="d-flex flex-column gap-2 mb-3" id="challenge-options-list">
            ${r.opciones.map((opt, idx) => `
              <button type="button" class="btn text-start p-3 rounded-3 d-flex align-items-center justify-content-between challenge-option-btn ${l.completed && opt.es_correcta ? 'opt-correct' : ''}" data-index="${idx}" data-correct="${opt.es_correcta}">
                <div class="d-flex align-items-center gap-3">
                  <span class="challenge-option-letter">${String.fromCharCode(65 + idx)}</span>
                  <span class="challenge-option-text">${DOMPurify.sanitize(marked.parseInline(opt.texto ?? ''))}</span>
                </div>
                <i class="bi ${l.completed && opt.es_correcta ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'} option-check-icon"></i>
              </button>
            `).join('')}
          </div>

          <!-- Feedback interactivo -->
          <div id="challenge-feedback" class="p-3 rounded-3 ${l.completed ? 'alert alert-success d-flex align-items-center gap-2 mb-0' : 'd-none'}" style="font-size:.9rem;line-height:1.45;">
            ${l.completed ? `<i class="bi bi-check-circle-fill fs-5 text-success"></i> <div>¡Completado con éxito! Puedes repasar cuando desees.</div>` : ''}
          </div>
        </div>`;
    }

    // Render principal
    $('#leccion-card').innerHTML = `
      <!-- Cabecera de la Lección -->
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <span class="header-stat-pill">
            <i class="bi bi-clock text-primary"></i> ${l.estimated_minutes} min estimados
          </span>
          <span class="header-stat-pill">
            <i class="bi bi-lightning-charge-fill text-warning"></i> +${l.xp_reward} XP
          </span>
        </div>
        <span class="pill-edugt ${l.completed ? 'pill-done' : 'pill-now'}" id="pill-estado">
          ${l.completed ? '<i class="bi bi-check2-circle"></i> Completado' : '<i class="bi bi-play-fill"></i> En progreso'}
        </span>
      </div>

      <!-- Contenido Teórico -->
      <article class="lesson-markdown mb-4">
        ${htmlContent}
      </article>

      <!-- Bloque de Pseudocódigo -->
      ${pseudocodeHtml}

      <!-- Bloque de Reto / Acertijo -->
      ${retoHtml}

      <!-- Barra de Acción Final -->
      <div class="text-center mt-5 pt-4 border-top" style="border-color:var(--edugt-border)!important;">
        <button class="btn btn-edugt btn-lg px-5 shadow-sm" id="btn-completar">
          ${l.completed 
            ? '<i class="bi bi-arrow-repeat"></i> Ya completado · Registrar repaso' 
            : `<i class="bi bi-check-circle-fill"></i> ¡Entendido! Completar lección (+${l.xp_reward} XP)`}
        </button>
        <p class="mt-2 mb-0 text-muted" style="font-size:.85rem">
          <i class="bi bi-stopwatch me-1"></i> Tiempo de estudio en esta sesión: <strong id="tiempo-vivo">0 s</strong>
        </p>
      </div>`;

    // Interactividad del Reto
    if (l.pseudocode_json && l.pseudocode_json.reto) {
      const r = l.pseudocode_json.reto;
      const btnCompletar = $('#btn-completar');

      if (!l.completed) {
        btnCompletar.disabled = true;
        btnCompletar.classList.add('opacity-50');
        btnCompletar.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Responde el reto para desbloquear la lección';
      }

      document.querySelectorAll('.challenge-option-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          if (l.completed) return;

          const idx = parseInt(btn.dataset.index, 10);
          const opt = r.opciones[idx];
          const fb = $('#challenge-feedback');

          // Reset styles
          document.querySelectorAll('.challenge-option-btn').forEach(b => {
            b.classList.remove('opt-correct', 'opt-incorrect');
            const icon = b.querySelector('.option-check-icon');
            if (icon) icon.className = 'bi bi-circle option-check-icon text-muted';
          });

          if (opt.es_correcta) {
            btn.classList.add('opt-correct');
            const icon = btn.querySelector('.option-check-icon');
            if (icon) icon.className = 'bi bi-check-circle-fill option-check-icon text-success';

            fb.className = 'p-3 rounded-3 alert alert-success d-flex align-items-center gap-2 mb-0 animate-fade-in';
            fb.innerHTML = `<i class="bi bi-check-circle-fill fs-5 text-success"></i> <div>${DOMPurify.sanitize(marked.parseInline(opt.feedback ?? ''))}</div>`;
            fb.classList.remove('d-none');

            btnCompletar.disabled = false;
            btnCompletar.classList.remove('opacity-50');
            btnCompletar.classList.add('btn-pulse');
            btnCompletar.innerHTML = `<i class="bi bi-check-circle-fill"></i> ¡Entendido! Completar lección (+${l.xp_reward} XP) 🎉`;
          } else {
            btn.classList.add('opt-incorrect');
            const icon = btn.querySelector('.option-check-icon');
            if (icon) icon.className = 'bi bi-x-circle-fill option-check-icon text-danger';

            fb.className = 'p-3 rounded-3 alert alert-danger d-flex align-items-center gap-2 mb-0 animate-fade-in';
            fb.innerHTML = `<i class="bi bi-exclamation-circle-fill fs-5 text-danger"></i> <div>${DOMPurify.sanitize(marked.parseInline(opt.feedback ?? ''))}</div>`;
            fb.classList.remove('d-none');

            btnCompletar.disabled = true;
            btnCompletar.classList.add('opacity-50');
            btnCompletar.classList.remove('btn-pulse');
            btnCompletar.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Intenta de nuevo para desbloquear la lección';
          }
        });
      });
    }

    // Inicializar Simulador Paso a Paso y botón copiar
    if (rawCodeForSim) {
      initPseudocodeSimulator(rawCodeForSim);
    }

    $('#btn-copy-code')?.addEventListener('click', (e) => {
      if (rawCodeForSim) {
        navigator.clipboard.writeText(rawCodeForSim);
        const btn = e.currentTarget;
        btn.innerHTML = '<i class="bi bi-check-lg text-success"></i> ¡Copiado!';
        setTimeout(() => {
          btn.innerHTML = '<i class="bi bi-clipboard"></i> Copiar';
        }, 2000);
      }
    });

    // Reloj visible
    const tEl = $('#tiempo-vivo');
    setInterval(() => {
      if (tEl) {
        const mins = Math.floor(segundosEnPagina / 60);
        const secs = segundosEnPagina % 60;
        tEl.textContent = mins > 0 ? `${mins}m ${secs < 10 ? '0' : ''}${secs}s` : `${secs} s`;
      }
    }, 1000);

    $('#btn-completar').addEventListener('click', completar);
  } catch (err) {
    if (err.status === 403) {
      const needsPretest = !Auth.user?.learning_track;
      $('#leccion-card').innerHTML = `
        <div class="text-center py-5">
          <div class="stat-icon mx-auto mb-3" style="width:64px;height:64px;background:#F1EFFB;color:var(--edugt-muted);font-size:2rem;">
            <i class="bi bi-lock-fill"></i>
          </div>
          <h2 class="h5 fw-bold mt-2">${needsPretest ? 'Completa primero tu Diagnóstico Inicial' : 'Lección bloqueada'}</h2>
          <p class="text-muted mb-4">${needsPretest
            ? 'Para desbloquear el Módulo 1 debes realizar primero la Prueba Diagnóstica Inicial (Pre-test).'
            : 'Completa la lección anterior y su evaluación para desbloquear este contenido.'}</p>
          <a href="${needsPretest ? 'diagnostico.html?kind=pre' : 'estudiante.html'}" class="btn btn-edugt">
            ${needsPretest ? '<i class="bi bi-compass-fill me-1"></i> Realizar Pre-test Diagnóstico' : 'Volver a mi ruta'}
          </a>
        </div>`;
      return;
    }
    showAlert(err.message || 'Error al cargar la lección');
  }
}

/* ---------------- Acción Completar Lección ---------------- */
async function completar() {
  const btn = $('#btn-completar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Guardando progreso...';

  try {
    const { data } = await api(`/lessons/${lessonId}/complete`, {
      method: 'POST',
      body: { time_spent_seconds: segundosEnPagina },
    });

    clearInterval(clock);

    $('#leccion-card').innerHTML = `
      <div class="text-center py-5 animate-fade-in">
        <div class="stat-icon mx-auto mb-3" style="width:76px;height:76px;background:var(--edugt-green-soft);color:var(--edugt-green);font-size:2.2rem;border-radius:24px;">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <h2 class="h4 fw-bold mb-2">
          ${data.xp_awarded ? `¡Excelente trabajo! +${data.xp_amount} XP` : '¡Repaso registrado con éxito!'}
        </h2>
        ${data.new_badges && data.new_badges.length > 0 ? `
          <div class="mb-3">
            <span class="badge px-3 py-2 rounded-pill shadow-xs" style="font-size:.95rem;background:var(--edugt-primary-soft);color:var(--edugt-primary);border:1px solid var(--edugt-primary);">
              <i class="bi bi-award-fill me-1"></i> ¡Nueva insignia conseguida: ${esc(data.new_badges[0].title)}!
            </span>
          </div>` : ''}
        <p class="text-muted mb-4" style="max-width:480px;margin:0 auto;font-size:.95rem;">
          ${data.xp_awarded 
            ? 'Has completado esta lección. Tu experiencia ha sido sumada y la siguiente lección en tu ruta de aprendizaje ya está disponible.' 
            : 'Tu tiempo de estudio ha sido añadido al registro de tu progreso.'}
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
          <a href="estudiante.html" class="btn btn-edugt btn-lg px-4 shadow-sm">
            <i class="bi bi-map"></i> Volver a mi ruta
          </a>
        </div>
      </div>`;
  } catch (err) {
    showAlert(err.message || 'Error al guardar el progreso');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Completar lección';
  }
}

/* ==========================================================================
   Motor del Simulador Paso a Paso (Prueba de Escritorio en Vivo)
   Soporta variables, operaciones, if/else anidados, while, for/range y print
   ========================================================================== */
function stripInlineComment(line) {
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    if (line[i] === '"' && line[i - 1] !== '\\') {
      inQuotes = !inQuotes;
    } else if (!inQuotes && line[i] === '/' && line[i + 1] === '/') {
      return line.slice(0, i).trimEnd();
    } else if (!inQuotes && line[i] === '#' && (i === 0 || /\s/.test(line[i - 1]))) {
      return line.slice(0, i).trimEnd();
    }
  }
  return line.trimEnd();
}

function splitPrintArgs(argsStr) {
  const args = [];
  let cur = '';
  let inQuotes = false;
  let depth = 0;
  for (let i = 0; i < argsStr.length; i++) {
    const ch = argsStr[i];
    if (ch === '"' && argsStr[i - 1] !== '\\') {
      inQuotes = !inQuotes;
      cur += ch;
    } else if (!inQuotes && ch === '(') {
      depth++;
      cur += ch;
    } else if (!inQuotes && ch === ')') {
      depth--;
      cur += ch;
    } else if (!inQuotes && depth === 0 && ch === ',') {
      args.push(cur.trim());
      cur = '';
    } else {
      cur += ch;
    }
  }
  if (cur.trim()) args.push(cur.trim());
  return args;
}

function evalPseudoExpr(exprStr, env) {
  const trimmed = exprStr.trim();
  if (!trimmed) return undefined;

  // Reemplazar operadores lógicos fuera de comillas (and -> &&, or -> ||, not -> !)
  const parts = trimmed.split(/(".*?")/g);
  const jsExpr = parts.map((segment, idx) => {
    if (idx % 2 === 1) return segment; // literal string intacto
    return segment
      .replace(/\band\b/g, '&&')
      .replace(/\bor\b/g, '||')
      .replace(/\bnot\b/g, '!');
  }).join('');

  const keys = Object.keys(env);
  const vals = keys.map((k) => env[k].value);
  try {
    // eslint-disable-next-line no-new-func
    const fn = new Function(...keys, `return (${jsExpr});`);
    const res = fn(...vals);
    if (typeof res === 'number' && !Number.isInteger(res)) {
      return Number(res.toFixed(2));
    }
    return res;
  } catch {
    return trimmed;
  }
}

function inferPseudoType(val, rawExpr = '') {
  if (typeof val === 'boolean') return 'bool';
  if (typeof val === 'string') return 'string';
  if (typeof val === 'number') {
    if (!Number.isInteger(val) || /\d+\.\d+/.test(rawExpr)) return 'float';
    return 'int';
  }
  return 'dato';
}

function formatPseudoVal(val, type) {
  if (type === 'string') return `"${val}"`;
  if (type === 'bool') return val ? 'true' : 'false';
  return String(val);
}

function buildPseudocodeTrace(codeText) {
  const rawLines = codeText.split('\n');
  const items = [];

  rawLines.forEach((raw, idx) => {
    const clean = stripInlineComment(raw);
    if (!clean.trim()) return;
    const indent = clean.search(/\S/);
    items.push({
      line: idx + 1,
      indent: indent >= 0 ? indent : 0,
      text: clean.trim(),
    });
  });

  function parseBlock(startIdx, minIndent) {
    const stmts = [];
    let i = startIdx;

    while (i < items.length) {
      const it = items[i];
      if (it.indent < minIndent) break;
      if (it.text === 'else:') break;

      if (it.text.startsWith('if ') && it.text.endsWith(':')) {
        const cond = it.text.slice(3, -1).trim();
        const thenRes = parseBlock(i + 1, it.indent + 1);
        i = thenRes.nextIndex;
        let elseBody = [];
        if (i < items.length && items[i].indent === it.indent && items[i].text === 'else:') {
          const elseLine = items[i].line;
          const elseRes = parseBlock(i + 1, it.indent + 1);
          elseBody = elseRes.stmts;
          i = elseRes.nextIndex;
          stmts.push({ type: 'if', line: it.line, elseLine, cond, thenBody: thenRes.stmts, elseBody });
        } else {
          stmts.push({ type: 'if', line: it.line, cond, thenBody: thenRes.stmts, elseBody });
        }
      } else if (it.text.startsWith('while ') && it.text.endsWith(':')) {
        const cond = it.text.slice(6, -1).trim();
        const bodyRes = parseBlock(i + 1, it.indent + 1);
        i = bodyRes.nextIndex;
        stmts.push({ type: 'while', line: it.line, cond, body: bodyRes.stmts });
      } else if (it.text.startsWith('for ') && it.text.endsWith(':')) {
        const m = it.text.match(/^for\s+([a-zA-Z_]\w*)\s+in\s+range\(([^)]+)\)\s*:$/);
        const bodyRes = parseBlock(i + 1, it.indent + 1);
        i = bodyRes.nextIndex;
        if (m) {
          stmts.push({
            type: 'for',
            line: it.line,
            varName: m[1],
            rangeArgs: m[2].split(',').map((s) => s.trim()),
            body: bodyRes.stmts,
          });
        }
      } else if (it.text.startsWith('print(') && it.text.endsWith(')')) {
        const inner = it.text.slice(6, -1);
        stmts.push({ type: 'print', line: it.line, args: splitPrintArgs(inner) });
        i++;
      } else if (it.text.includes('=')) {
        const eqIdx = it.text.indexOf('=');
        const varName = it.text.slice(0, eqIdx).trim();
        const expr = it.text.slice(eqIdx + 1).trim();
        stmts.push({ type: 'assign', line: it.line, varName, expr });
        i++;
      } else {
        i++;
      }
    }

    return { stmts, nextIndex: i };
  }

  const ast = parseBlock(0, 0).stmts;
  const env = {};
  const outputs = [];
  const trace = [];
  let safetyCounter = 0;

  function snapshot(line, explanation, changedVar = null) {
    const varsCopy = {};
    Object.keys(env).forEach((k) => {
      varsCopy[k] = { value: env[k].value, type: env[k].type };
    });
    trace.push({
      line,
      explanation,
      changedVar,
      vars: varsCopy,
      outputs: [...outputs],
    });
  }

  function execBlock(stmts) {
    for (const s of stmts) {
      if (++safetyCounter > 120) break;

      if (s.type === 'assign') {
        const val = evalPseudoExpr(s.expr, env);
        const type = inferPseudoType(val, s.expr);
        env[s.varName] = { value: val, type };
        snapshot(
          s.line,
          `Línea ${s.line}: Se guarda <strong>${esc(s.varName)} = ${esc(formatPseudoVal(val, type))}</strong> (tipo <code>${type}</code>).`,
          s.varName
        );
      } else if (s.type === 'print') {
        const renderedArgs = s.args.map((a) => evalPseudoExpr(a, env));
        const outLine = renderedArgs.join(' ');
        outputs.push(outLine);
        snapshot(
          s.line,
          `Línea ${s.line}: Imprime en pantalla → <strong>${esc(outLine)}</strong>`
        );
      } else if (s.type === 'if') {
        const condVal = Boolean(evalPseudoExpr(s.cond, env));
        snapshot(
          s.line,
          `Línea ${s.line}: Evalúa condición <code>${esc(s.cond)}</code> → <strong>${condVal ? 'true (entra al bloque if)' : 'false (salta al camino alternativo)'}</strong>`
        );
        if (condVal) {
          execBlock(s.thenBody);
        } else if (s.elseBody && s.elseBody.length > 0) {
          if (s.elseLine) {
            snapshot(s.elseLine, `Línea ${s.elseLine}: Entra a la rama <code>else</code> porque la condición anterior fue <code>false</code>.`);
          }
          execBlock(s.elseBody);
        }
      } else if (s.type === 'while') {
        let loopGuard = 0;
        while (loopGuard++ < 25) {
          const condVal = Boolean(evalPseudoExpr(s.cond, env));
          snapshot(
            s.line,
            `Línea ${s.line}: Evalúa bucle <code>while ${esc(s.cond)}</code> → <strong>${condVal ? 'true (ejecuta vuelta del bucle)' : 'false (finaliza el bucle)'}</strong>`
          );
          if (!condVal) break;
          execBlock(s.body);
        }
      } else if (s.type === 'for') {
        const nums = s.rangeArgs.map((r) => Number(evalPseudoExpr(r, env)));
        const start = nums.length === 1 ? 0 : nums[0];
        const end = nums.length === 1 ? nums[0] : nums[1];
        for (let v = start; v < end; v++) {
          env[s.varName] = { value: v, type: 'int' };
          snapshot(
            s.line,
            `Línea ${s.line}: Bucle <code>for</code> → la variable <strong>${esc(s.varName)}</strong> toma el valor <strong>${v}</strong>.`,
            s.varName
          );
          execBlock(s.body);
        }
      }
    }
  }

  execBlock(ast);
  return trace;
}

function initPseudocodeSimulator(codeText) {
  const trace = buildPseudocodeTrace(codeText);
  if (!trace || trace.length === 0) return;

  let stepIdx = -1;
  let autoTimer = null;
  const visitedLines = new Set();

  const btnStep = $('#btn-sim-step');
  const btnAuto = $('#btn-sim-auto');
  const btnReset = $('#btn-sim-reset');
  const badgeEl = $('#sim-step-badge');
  const expEl = $('#sim-step-explanation');
  const varsCountEl = $('#sim-vars-count');
  const varsBodyEl = $('#sim-vars-body');
  const consoleBodyEl = $('#sim-console-body');

  function stopAuto() {
    if (autoTimer) {
      clearInterval(autoTimer);
      autoTimer = null;
    }
    if (btnAuto) {
      btnAuto.innerHTML = '<i class="bi bi-lightning-charge-fill text-warning"></i> Auto';
    }
  }

  function renderStepState() {
    // Limpiar resaltado de líneas
    document.querySelectorAll('#sim-code-lines .code-line-row').forEach((row) => {
      const ln = Number(row.dataset.line);
      row.classList.remove('is-active');
      row.classList.toggle('is-visited', visitedLines.has(ln));
      const arrow = row.querySelector('.code-line-arrow');
      if (arrow) arrow.textContent = '';
    });

    if (stepIdx < 0) {
      if (badgeEl) {
        badgeEl.textContent = 'Listo para ejecutar';
        badgeEl.style.background = '#322B54';
        badgeEl.style.color = '#C4B5FD';
      }
      if (expEl) {
        expEl.innerHTML = 'Haz clic en <strong>▶ Paso a paso</strong> o <strong>⚡ Auto</strong> para observar cómo cambian las variables en la memoria línea por línea.';
      }
      if (varsCountEl) varsCountEl.textContent = '0 activas';
      if (varsBodyEl) {
        varsBodyEl.innerHTML = '<div class="text-center py-3" style="color:#6E678F;font-size:.82rem;">Aún no se han declarado variables en memoria.</div>';
      }
      if (consoleBodyEl) {
        consoleBodyEl.innerHTML = '<div class="text-center py-3" style="color:#6E678F;font-size:.82rem;">Sin salidas en pantalla todavía.</div>';
      }
      if (btnStep) {
        btnStep.innerHTML = '<i class="bi bi-play-fill"></i> Paso a paso';
      }
      btnReset?.classList.add('d-none');
      return;
    }

    btnReset?.classList.remove('d-none');
    const cur = trace[stepIdx];
    visitedLines.add(cur.line);

    const activeRow = document.querySelector(`#sim-code-lines .code-line-row[data-line="${cur.line}"]`);
    if (activeRow) {
      activeRow.classList.add('is-active');
      const arrow = activeRow.querySelector('.code-line-arrow');
      if (arrow) arrow.textContent = '▶';
    }

    const isLast = stepIdx >= trace.length - 1;
    if (badgeEl) {
      badgeEl.textContent = isLast ? `Completado (${trace.length}/${trace.length})` : `Paso ${stepIdx + 1} de ${trace.length}`;
      badgeEl.style.background = isLast ? '#065F46' : '#322B54';
      badgeEl.style.color = isLast ? '#6EE7B7' : '#C4B5FD';
    }
    if (expEl) {
      expEl.innerHTML = cur.explanation;
    }

    // Renderizar tabla de memoria RAM (variables)
    const varNames = Object.keys(cur.vars);
    if (varsCountEl) {
      varsCountEl.textContent = `${varNames.length} ${varNames.length === 1 ? 'variable' : 'variables'}`;
    }
    if (varsBodyEl) {
      if (varNames.length === 0) {
        varsBodyEl.innerHTML = '<div class="text-center py-3" style="color:#6E678F;font-size:.82rem;">Sin variables modificadas aún.</div>';
      } else {
        varsBodyEl.innerHTML = varNames.map((name) => {
          const info = cur.vars[name];
          const isChanged = cur.changedVar === name;
          return `
            <div class="sim-var-row ${isChanged ? 'just-updated' : ''}">
              <div class="d-flex align-items-center gap-2">
                <span class="fw-bold" style="color:#F8FAFC;">${esc(name)}</span>
                <span class="sim-var-type">${esc(info.type)}</span>
              </div>
              <div class="fw-bold" style="color:${isChanged ? '#6EE7B7' : '#C4B5FD'};">
                ${esc(formatPseudoVal(info.value, info.type))}
              </div>
            </div>`;
        }).join('');
      }
    }

    // Renderizar consola de salida (print)
    if (consoleBodyEl) {
      if (cur.outputs.length === 0) {
        consoleBodyEl.innerHTML = '<div class="text-center py-3" style="color:#6E678F;font-size:.82rem;">Esperando instrucción print(...)</div>';
      } else {
        consoleBodyEl.innerHTML = cur.outputs.map((lineOut) => `
          <div class="sim-console-line">
            <span style="color:#6E678F;margin-right:6px;">&gt;</span>${esc(lineOut)}
          </div>
        `).join('');
      }
    }

    if (btnStep) {
      btnStep.innerHTML = isLast
        ? '<i class="bi bi-arrow-repeat"></i> Reiniciar pasos'
        : '<i class="bi bi-skip-end-fill"></i> Siguiente paso';
    }

    if (isLast) {
      stopAuto();
    }
  }

  btnStep?.addEventListener('click', () => {
    stopAuto();
    if (stepIdx >= trace.length - 1) {
      stepIdx = -1;
      visitedLines.clear();
      renderStepState();
      return;
    }
    stepIdx++;
    renderStepState();
  });

  btnAuto?.addEventListener('click', () => {
    if (autoTimer) {
      stopAuto();
      return;
    }
    if (stepIdx >= trace.length - 1) {
      stepIdx = -1;
      visitedLines.clear();
    }
    if (btnAuto) {
      btnAuto.innerHTML = '<i class="bi bi-pause-fill text-warning"></i> Pausar';
    }
    stepIdx++;
    renderStepState();
    autoTimer = setInterval(() => {
      if (stepIdx >= trace.length - 1) {
        stopAuto();
        return;
      }
      stepIdx++;
      renderStepState();
    }, 950);
  });

  btnReset?.addEventListener('click', () => {
    stopAuto();
    stepIdx = -1;
    visitedLines.clear();
    renderStepState();
  });
}

boot();
