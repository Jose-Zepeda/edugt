/* ==========================================================================
   EduGT — Login / Registro (RF-1, RF-2, RF-3)
   Protección Anti-bot: Cloudflare Turnstile + Honeypot
   ========================================================================== */
'use strict';

Auth.load();

const $ = (sel) => document.querySelector(sel);

const TURNSTILE_SITE_KEY = '1x00000000000000000000AA'; // Clave de prueba de Cloudflare (solo para localhost)
const IS_LOCALHOST = ['localhost', '127.0.0.1'].includes(window.location.hostname);
const SHOULD_RENDER_TURNSTILE = TURNSTILE_SITE_KEY !== '1x00000000000000000000AA';
let loginWidgetId = null;
let regWidgetId = null;

// Ajustar enlace de Google OAuth dinámicamente según el entorno
const btnGoogle = document.getElementById('btn-google');
if (btnGoogle && typeof API_BASE !== 'undefined') {
  btnGoogle.href = `${API_BASE}/auth/google/redirect`;
}

/* ---------------- Tema Claro / Oscuro en Login ---------------- */
function syncAuthThemeIcon() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const icon = document.getElementById('auth-theme-icon');
  if (icon) {
    icon.className = isDark ? 'bi bi-sun-fill text-warning' : 'bi bi-moon-stars-fill';
  }
}

syncAuthThemeIcon();

document.getElementById('auth-theme-toggle')?.addEventListener('click', () => {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const next = isDark ? 'light' : 'dark';
  if (next === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
  localStorage.setItem('edugt_theme', next);
  localStorage.setItem('edugt-theme', next);
  syncAuthThemeIcon();
});

function renderTurnstile() {
  if (!SHOULD_RENDER_TURNSTILE || typeof turnstile === 'undefined') return;
  const curTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';

  const loginContainer = document.getElementById('turnstile-login');
  if (loginContainer && loginWidgetId === null && !loginContainer.hasChildNodes()) {
    try {
      loginContainer.classList.remove('d-none');
      loginWidgetId = turnstile.render('#turnstile-login', {
        sitekey: TURNSTILE_SITE_KEY,
        theme: curTheme,
      });
    } catch (err) {
      console.warn('Turnstile login render:', err);
    }
  }

  const regContainer = document.getElementById('turnstile-register');
  if (regContainer && regWidgetId === null && !regContainer.hasChildNodes()) {
    try {
      regContainer.classList.remove('d-none');
      regWidgetId = turnstile.render('#turnstile-register', {
        sitekey: TURNSTILE_SITE_KEY,
        theme: curTheme,
      });
    } catch (err) {
      console.warn('Turnstile reg render:', err);
    }
  }
}

// Inicializar Turnstile cuando el script de Cloudflare esté disponible
if (typeof turnstile !== 'undefined') {
  renderTurnstile();
} else {
  const checkTurnstile = setInterval(() => {
    if (typeof turnstile !== 'undefined') {
      clearInterval(checkTurnstile);
      renderTurnstile();
    }
  }, 100);
  setTimeout(() => clearInterval(checkTurnstile), 10000);
}

function showAlert(message, kind = 'danger') {
  const el = $('#alert');
  el.className = `alert alert-${kind}`;
  el.textContent = message;
}

function hideAlert() {
  $('#alert').classList.add('d-none');
}

/** Redirige al panel según el rol */
function goPanel(user) {
  const dest = user.role === 'teacher' ? 'docente.html'
    : user.role === 'admin' ? 'admin.html'
      : 'estudiante.html';
  window.location.href = dest;
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('google_status') === 'pending') {
  showAlert('Tu cuenta de docente con Google fue registrada y está pendiente de aprobación por el administrador.', 'info');
}

/* ---------------- Login tradicional ---------------- */
$('#form-login')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert();

  const btn = $('#btn-login');
  btn.disabled = true;
  btn.textContent = 'Entrando...';

  let token = '';
  if (typeof turnstile !== 'undefined' && loginWidgetId !== null) {
    token = turnstile.getResponse(loginWidgetId) || '';
  }

  try {
    const { data } = await api('/auth/login', {
      method: 'POST',
      auth: false,
      body: {
        email: $('#login-email').value.trim(),
        password: $('#login-password').value,
        turnstile_token: token,
      },
    });

    // 423 teacher pendiente llega como error con code — aquí solo 200
    Auth.save(data.token, data.user);
    goPanel(data.user);
  } catch (err) {
    if (typeof turnstile !== 'undefined' && loginWidgetId !== null) {
      turnstile.reset(loginWidgetId);
    }

    if (err.code === 'TEACHER_PENDING_APPROVAL') {
      showAlert('Tu cuenta de docente está pendiente de aprobación. Te avisaremos cuando sea activada.');
    } else if (err.errors?.turnstile_token) {
      showAlert(err.errors.turnstile_token[0]);
    } else if (err.errors?.email) {
      const emailErr = err.errors.email[0];
      showAlert(emailErr === 'validation.email' ? 'Credenciales invalidas, revisa el correo o la contraseña' : emailErr);
    } else if (err.errors?.password) {
      showAlert('Credenciales invalidas, revisa el correo o la contraseña');
    } else if (err.message === 'validation.email' || err.status === 422) {
      showAlert('Credenciales invalidas, revisa el correo o la contraseña');
    } else {
      showAlert(err.message || 'Credenciales invalidas, revisa el correo o la contraseña');
    }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});

/* ---------------- Registro (RB-01: solo student|teacher) ---------------- */
$('#form-register')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert();

  const role = document.querySelector('input[name="role"]:checked')?.value;
  let token = '';
  if (typeof turnstile !== 'undefined' && regWidgetId !== null) {
    token = turnstile.getResponse(regWidgetId) || '';
  }

  const btn = $('#btn-register');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Creando cuenta...';
  }

  try {
    const { data } = await api('/auth/register', {
      method: 'POST',
      auth: false,
      body: {
        name: $('#reg-name').value.trim(),
        email: $('#reg-email').value.trim(),
        password: $('#reg-password').value,
        role: role || 'student',
        turnstile_token: token,
      },
    });

    if (data.status === 'pending_approval') {
      showAlert('¡Cuenta creada! Tu cuenta de docente está pendiente de aprobación administrativa.', 'info');
      setTimeout(() => { window.location.href = 'index.html'; }, 2500);
      return;
    }

    Auth.save(data.token, data.user);
    goPanel(data.user);
  } catch (err) {
    if (typeof turnstile !== 'undefined' && regWidgetId !== null) {
      turnstile.reset(regWidgetId);
    }

    if (err.errors) {
      const first = Object.values(err.errors)[0];
      showAlert(Array.isArray(first) ? first[0] : String(first));
    } else {
      showAlert(err.message);
    }
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Crear cuenta';
    }
  }
});

/* ---------------- Alternar vistas ---------------- */
$('#link-to-register')?.addEventListener('click', (e) => {
  e.preventDefault();
  $('#view-login').classList.add('d-none');
  $('#view-register').classList.remove('d-none');
  hideAlert();
  renderTurnstile();
});

$('#link-to-login')?.addEventListener('click', (e) => {
  e.preventDefault();
  $('#view-register').classList.add('d-none');
  $('#view-login').classList.remove('d-none');
  hideAlert();
  renderTurnstile();
});

/* ---------------- Sesión activa → panel directamente ---------------- */
if (Auth.token && Auth.user) {
  goPanel(Auth.user);
}

