/* ==========================================================================
   EduGT — Cliente de API (contrato §7 del plan backend)
   RB-10: el navegador NUNCA calcula score/XP/desbloqueos; solo consume.
   ========================================================================== */
'use strict';

const API_BASE = 'http://localhost:8080/api/v1';

/** Token Sanctum en memoria + respaldo sessionStorage (no localStorage: seguridad tesis). */
const Auth = {
  token: null,
  user: null,

  load() {
    this.token = sessionStorage.getItem('edugt_token') || localStorage.getItem('edugt_token');
    try {
      const rawUser = sessionStorage.getItem('edugt_user') || localStorage.getItem('edugt_user');
      this.user = JSON.parse(rawUser || 'null');
    } catch { this.user = null; }
  },

  save(token, user) {
    this.token = token;
    this.user = user;
    sessionStorage.setItem('edugt_token', token);
    sessionStorage.setItem('edugt_user', JSON.stringify(user));
    localStorage.setItem('edugt_token', token);
    localStorage.setItem('edugt_user', JSON.stringify(user));
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

/** Escape básico: todo dato remoto se escapa antes de innerHTML (XSS, tesis §Seguridad) */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

/** Petición con formato de error común {message, errors?, code} */
async function api(path, { method = 'GET', body = null, auth = true } = {}) {
  const headers = { Accept: 'application/json' };
  if (body) headers['Content-Type'] = 'application/json';
  if (auth && Auth.token) headers.Authorization = `Bearer ${Auth.token}`;

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : null,
  });

  // 204 sin cuerpo
  if (res.status === 204) return { ok: true, data: null };

  let data = null;
  try { data = await res.json(); } catch { /* cuerpo vacío */ }

  if (!res.ok) {
    const err = new Error(data?.message || `Error ${res.status}`);
    err.status = res.status;
    err.code = data?.code;
    err.errors = data?.errors;
    throw err;
  }

  return { ok: true, data };
}
