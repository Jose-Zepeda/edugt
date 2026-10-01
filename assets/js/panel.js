/* ==========================================================================
   EduGT — Panel del estudiante (consume API real; RB-10: nunca calcula)
   ========================================================================== */
'use strict';

Auth.load();

// Guard: sin sesión → login
if (!Auth.token || !Auth.user) {
  window.location.href = 'index.html';
}

const $ = (sel) => document.querySelector(sel);

const TRACK_LABEL = { beginner: 'Ruta básica', intermediate: 'Ruta intermedia', advanced: 'Ruta avanzada' };

/* ---------------- Saludo ---------------- */
function renderGreeting() {
  const h = new Date().getHours();
  const kicker = h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
  $('#greeting-kicker').textContent = kicker;
  $('#greeting').textContent = `${Auth.user.name} 👋`;

  // El avatar AHORA es el logo de la plataforma (id avatar = <img>); el nombre va en title
  $('#avatar')?.setAttribute('title', Auth.user.name);

  $('#pill-track').textContent = TRACK_LABEL[Auth.user.learning_track] ?? 'Completa tu pretest';
}

/* ---------------- XP ---------------- */
async function loadXp() {
  const { data } = await api('/me/xp');
  $('#xp-level').textContent = data.xp_level;
  $('#xp-total').textContent = data.xp_total;
  $('#xp-floor').textContent = `${data.level_floor_xp} XP`;
  $('#xp-next').textContent = `${data.next_level_xp} XP`;
  $('#xp-bar').style.width = `${data.progress_to_next_level}%`;
}

/* ---------------- Racha ---------------- */
async function loadStreak() {
  const { data } = await api('/me/streak');
  $('#streak-current').textContent = data.current;
  $('#streak-longest').textContent = `${data.longest} días`;
}

/* ---------------- Misiones / ruta (RF-8) ---------------- */
async function loadMissions() {
  const { data } = await api('/me/missions');

  const container = $('#missions');
  container.innerHTML = '';

  let done = 0, total = 0;

  for (const mission of data.data) {
    const modBlocks = [];

    for (const mod of mission.modules) {
      const lessons = [];

      for (const l of mod.lessons) {
        total++;
        if (l.completed) done++;

        const state = l.completed ? 'done' : l.locked ? 'locked' : 'now';
        const statePill = {
          done: '<span class="pill-edugt pill-done"><i class="bi bi-check-lg"></i> Completada</span>',
          now: '<span class="pill-edugt pill-now"><i class="bi bi-play-fill"></i> Continuar</span>',
          locked: '<span class="pill-edugt pill-locked"><i class="bi bi-lock"></i> Bloqueada</span>',
        }[state];

        lessons.push(`
          <div class="d-flex align-items-center justify-content-between border rounded-4 px-3 py-2 ${state === 'locked' ? 'opacity-50' : ''}"
               style="border-color:var(--edugt-border)!important;">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon" style="background:${state === 'done' ? 'var(--edugt-green-soft)' : state === 'now' ? 'var(--edugt-primary-soft)' : '#EFEDF8'};color:${state === 'done' ? 'var(--edugt-green)' : 'var(--edugt-primary)'}">
                <i class="bi ${state === 'done' ? 'bi-check-lg' : state === 'now' ? 'bi-play-fill' : 'bi-lock'}"></i>
              </div>
              <div>
                <div class="fw-bold" style="font-size:.95rem;">${esc(l.title)}</div>
                <small style="color:var(--edugt-muted)">${l.estimated_minutes} min · ${l.xp_reward} XP</small>
              </div>
            </div>
            ${statePill}
          </div>`);
      }

      modBlocks.push(`
        <div class="mb-2">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 fw-bold mb-0">${esc(mod.title)}</h3>
            <small style="color:var(--edugt-muted)">Nivel requerido: ${mod.required_level}</small>
          </div>
          <div class="d-flex flex-column gap-2">${lessons.join('')}</div>
        </div>`);
    }

    container.innerHTML += `
      <article>
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="stat-icon" style="background:var(--edugt-pink-soft);color:var(--edugt-pink)">
            <i class="bi bi-rocket-takeoff-fill"></i>
          </div>
          <div>
            <h2 class="h6 fw-bold mb-0">${esc(mission.title)}</h2>
            <small style="color:var(--edugt-muted)">${esc(mission.description ?? '')}</small>
          </div>
        </div>
        ${modBlocks.join('')}
      </article>`;
  }

  $('#ruta-summary').textContent = `${done} de ${total} lecciones completadas`;
}

/* ---------------- Insignias (RF-17) ---------------- */
async function loadBadges() {
  const { data } = await api('/me/badges');

  const box = $('#badges');
  box.innerHTML = '';

  const obtained = data.obtained.map((b) => `
    <div class="text-center" style="width:104px;">
      <div class="stat-icon mx-auto mb-2" style="width:56px;height:56px;border-radius:18px;background:var(--edugt-amber-soft);color:var(--edugt-amber);font-size:1.6rem;">
        <i class="bi bi-award-fill"></i>
      </div>
      <div class="fw-bold" style="font-size:.8rem;line-height:1.1">${esc(b.title)}</div>
      <small style="color:var(--edugt-muted);font-size:.7rem">${esc(b.description)}</small>
    </div>`);

  const locked = data.locked.map((b) => `
    <div class="text-center opacity-50" style="width:104px;">
      <div class="stat-icon mx-auto mb-2" style="width:56px;height:56px;border-radius:18px;background:#EFEDF8;color:var(--edugt-muted);font-size:1.6rem;">
        <i class="bi bi-lock"></i>
      </div>
      <div class="fw-bold" style="font-size:.8rem;line-height:1.1">${esc(b.title)}</div>
      <small style="color:var(--edugt-muted);font-size:.7rem">${esc(b.description)}</small>
    </div>`);

  box.innerHTML = obtained.join('') + locked.join('');
}

/* ---------------- Ranking (RF-18) ---------------- */
async function loadRankings() {
  const { data } = await api('/rankings?scope=global');

  const rows = data.data.slice(0, 8).map((r, i) => {
    const medal = ['🥇', '🥈', '🥉'][i] ?? `<span style="color:var(--edugt-muted);font-weight:700">${r.position}</span>`;
    const me = r.user_id === Auth.user.id;

    return `
      <div class="d-flex align-items-center gap-3 border rounded-4 px-3 py-2 ${me ? 'fw-bold' : ''}"
           style="border-color:var(--edugt-border)!important;${me ? 'background:var(--edugt-primary-soft);' : ''}">
        <div style="width:28px;text-align:center;font-size:1.05rem;">${medal}</div>
        <div class="flex-grow-1">${esc(r.name)}${me ? ' (tú)' : ''}</div>
        <div style="color:var(--edugt-muted);font-size:.85rem;">Nv. ${r.xp_level}</div>
        <div class="fw-bold" style="min-width:64px;text-align:right;">${r.xp_total} XP</div>
      </div>`;
  });

  $('#rankings').innerHTML = rows.join('') || '<p class="mb-0" style="color:var(--edugt-muted)">Aún no hay puntajes. ¡Sé el primero!</p>';
}

/* ---------------- Logout ---------------- */
async function logout() {
  try { await api('/auth/logout', { method: 'POST' }); } catch { /* token ya inválido */ }
  Auth.clear();
  window.location.href = 'index.html';
}
$('#btn-logout')?.addEventListener('click', logout);
$('#btn-logout-m')?.addEventListener('click', logout);

/* ---------------- CTA pretest (una sola acción dominante al iniciar) ---------------- */
function renderPretestCta() {
  const hasTrack = Boolean(Auth.user.learning_track);
  $('#cta-pretest').classList.toggle('d-none', hasTrack);
  $('#btn-pretest')?.addEventListener('click', () => {
    window.location.href = 'diagnostico.html?kind=pre';
  });
}

/* ---------------- Boot ---------------- */
(async function boot() {
  renderGreeting();
  renderPretestCta();

  try {
    await Promise.all([loadXp(), loadStreak()]);
    await Promise.all([loadMissions(), loadBadges(), loadRankings()]);
  } catch (err) {
    if (err.status === 401) {
      Auth.clear();
      window.location.href = 'index.html';
      return;
    }
    console.error(err);
  }
})();
