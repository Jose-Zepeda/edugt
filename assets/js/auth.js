/* ==========================================================================
   EduGT — Login / Registro (RF-1, RF-2, RF-3)
   ========================================================================== */
'use strict';

Auth.load();

const $ = (sel) => document.querySelector(sel);

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

  try {
    const { data } = await api('/auth/login', {
      method: 'POST',
      auth: false,
      body: {
        email: $('#login-email').value.trim(),
        password: $('#login-password').value,
      },
    });

    // 423 teacher pendiente llega como error con code — aquí solo 200
    Auth.save(data.token, data.user);
    goPanel(data.user);
  } catch (err) {
    if (err.code === 'TEACHER_PENDING_APPROVAL') {
      showAlert('Tu cuenta de docente está pendiente de aprobación. Te avisaremos cuando sea activada.');
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

  try {
    const { data } = await api('/auth/register', {
      method: 'POST',
      auth: false,
      body: {
        name: $('#reg-name').value.trim(),
        email: $('#reg-email').value.trim(),
        password: $('#reg-password').value,
        role: role || 'student',
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
    if (err.errors) {
      const first = Object.values(err.errors)[0];
      showAlert(Array.isArray(first) ? first[0] : String(first));
    } else {
      showAlert(err.message);
    }
  }
});

/* ---------------- Alternar vistas ---------------- */
$('#link-to-register')?.addEventListener('click', (e) => {
  e.preventDefault();
  $('#view-login').classList.add('d-none');
  $('#view-register').classList.remove('d-none');
  hideAlert();
});

$('#link-to-login')?.addEventListener('click', (e) => {
  e.preventDefault();
  $('#view-register').classList.add('d-none');
  $('#view-login').classList.remove('d-none');
  hideAlert();
});

/* ---------------- Sesión activa → panel directamente ---------------- */
if (Auth.token && Auth.user) {
  goPanel(Auth.user);
}
