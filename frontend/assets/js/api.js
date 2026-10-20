/* ==========================================================================
   EduGT — Cliente de API (contrato §7 del plan backend)
   RB-10: el navegador NUNCA calcula score/XP/desbloqueos; solo consume.
   ========================================================================== */
'use strict';

// 1. Inicializar tema inmediatamente en todas las páginas para evitar parpadeos
(function initGlobalTheme() {
  const savedTheme = localStorage.getItem('edugt_theme') || localStorage.getItem('edugt-theme') || 'light';
  if (savedTheme === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
})();

const API_BASE = ['5500', '5501', '3000', '5173'].includes(window.location.port)
  ? `http://${window.location.hostname || 'localhost'}:8080/api/v1`
  : '/api/v1';

/** Token Sanctum en memoria + sincronización localStorage/sessionStorage. */
const Auth = {
  token: null,
  user: null,

  load() {
    const lsToken = localStorage.getItem('edugt_token');
    const ssToken = sessionStorage.getItem('edugt_token');
    const rawToken = (lsToken && lsToken !== 'undefined' && lsToken !== 'null')
      ? lsToken
      : (ssToken && ssToken !== 'undefined' && ssToken !== 'null' ? ssToken : null);

    this.token = rawToken;
    if (rawToken) {
      sessionStorage.setItem('edugt_token', rawToken);
      localStorage.setItem('edugt_token', rawToken);
    }

    try {
      const rawUser = localStorage.getItem('edugt_user') || sessionStorage.getItem('edugt_user');
      this.user = rawUser && rawUser !== 'undefined' ? JSON.parse(rawUser) : null;
      if (this.user) {
        const serialized = JSON.stringify(this.user);
        sessionStorage.setItem('edugt_user', serialized);
        localStorage.setItem('edugt_user', serialized);
      }
    } catch {
      this.user = null;
    }
  },

  save(token, user) {
    const cleanToken = (token && token !== 'undefined' && token !== 'null') ? token : this.token;
    if (cleanToken) {
      this.token = cleanToken;
      sessionStorage.setItem('edugt_token', cleanToken);
      localStorage.setItem('edugt_token', cleanToken);
    }
    if (user !== undefined) {
      this.user = user;
      const serialized = JSON.stringify(user);
      sessionStorage.setItem('edugt_user', serialized);
      localStorage.setItem('edugt_user', serialized);
    }
  },

  clear() {
    this.token = null;
    this.user = null;
    sessionStorage.removeItem('edugt_token');
    sessionStorage.removeItem('edugt_user');
    localStorage.removeItem('edugt_token');
    localStorage.removeItem('edugt_user');
  },
};

// Inicializar sesión inmediatamente al cargar api.js (antes de settings.js y support.js)
Auth.load();

// Mantener sincronizadas múltiples pestañas si se inicia/cierra sesión en otra pestaña
window.addEventListener('storage', (e) => {
  if (e.key === 'edugt_token' || e.key === 'edugt_user' || e.key === null) {
    Auth.load();
  }
});

/** Escape básico: todo dato remoto se escapa antes de innerHTML (XSS, tesis §Seguridad) */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

/** Petición con formato de error común {message, errors?, code} */
async function api(path, { method = 'GET', body = null, auth = true } = {}) {
  if (auth && !Auth.token) {
    Auth.load();
  }

  const buildHeaders = () => {
    const h = { Accept: 'application/json' };
    if (body) h['Content-Type'] = 'application/json';
    if (auth && Auth.token) h.Authorization = `Bearer ${Auth.token}`;
    return h;
  };

  let res = await fetch(`${API_BASE}${path}`, {
    method,
    headers: buildHeaders(),
    body: body ? JSON.stringify(body) : null,
  });

  // Si devuelve 401 pero otra pestaña actualizó el token en localStorage, reintentar 1 vez
  if (res.status === 401 && auth) {
    const latestToken = localStorage.getItem('edugt_token');
    if (latestToken && latestToken !== 'undefined' && latestToken !== Auth.token) {
      Auth.load();
      res = await fetch(`${API_BASE}${path}`, {
        method,
        headers: buildHeaders(),
        body: body ? JSON.stringify(body) : null,
      });
    }
  }

  // 204 sin cuerpo
  if (res.status === 204) return { ok: true, data: null };

  let data = null;
  try { data = await res.json(); } catch { /* cuerpo vacío */ }

  if (!res.ok) {
    if (res.status === 401 && auth) {
      Auth.clear();
      const err = new Error('Tu sesión expiró o se cerró en otra pestaña. Inicia sesión nuevamente.');
      err.status = 401;
      err.code = data?.code || 'UNAUTHENTICATED';
      setTimeout(() => {
        if (!window.location.pathname.endsWith('index.html') && window.location.pathname !== '/') {
          window.location.href = 'index.html';
        }
      }, 1200);
      throw err;
    }

    const err = new Error(data?.message || `Error ${res.status}`);
    err.status = res.status;
    err.code = data?.code;
    err.errors = data?.errors;
    throw err;
  }

  return { ok: true, data };
}
