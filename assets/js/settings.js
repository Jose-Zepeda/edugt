/* ==========================================================================
   EduGT — Módulo de Ajustes, Perfil, Seguridad y Apariencia (Modo Claro/Oscuro)
   ========================================================================== */
'use strict';

// 1. Inicializar tema inmediatamente al cargar script para evitar parpadeos
(function initTheme() {
  const savedTheme = localStorage.getItem('edugt_theme') || 'light';
  if (savedTheme === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
})();

const EDUGT_AVATARS = [
  { id: 'avatar-01', url: 'assets/img/avatars/avatar-01.jpg', name: 'Bot Compilador { }' },
  { id: 'avatar-02', url: 'assets/img/avatars/avatar-02.jpg', name: 'Ciber-Depurador' },
  { id: 'avatar-03', url: 'assets/img/avatars/avatar-03.jpg', name: 'Guardián de Variables' },
  { id: 'avatar-04', url: 'assets/img/avatars/avatar-04.jpg', name: 'Oráculo Condicional' },
  { id: 'avatar-05', url: 'assets/img/avatars/avatar-05.jpg', name: 'Corredor de Bucles' },
  { id: 'avatar-06', url: 'assets/img/avatars/avatar-06.jpg', name: 'Arquitecto de Algoritmos' },
  { id: 'avatar-07', url: 'assets/img/avatars/avatar-07.jpg', name: 'Ninja del Pseudocódigo' },
  { id: 'avatar-08', url: 'assets/img/avatars/avatar-08.jpg', name: 'Explorador Binario' },
  { id: 'avatar-09', url: 'assets/img/avatars/avatar-09.jpg', name: 'Centinela Booleano' },
  { id: 'avatar-10', url: 'assets/img/avatars/avatar-10.jpg', name: 'Gran Maestro Sintáctico' },
];

window.EDUGT_AVATARS = EDUGT_AVATARS;

const Settings = {
  modalInstance: null,
  selectedAvatarUrl: null,

  init() {
    this.ensureAvatarPickerMounted();
    this.bindEvents();
    this.syncThemeUI();
  },

  ensureAvatarPickerMounted() {
    const form = document.getElementById('form-settings-profile');
    if (!form || document.getElementById('settings-avatar-picker-grid')) return;

    const submitWrap = form.querySelector('.d-flex.justify-content-end');
    const wrapper = document.createElement('div');
    wrapper.className = 'mb-4';
    wrapper.innerHTML = `
      <div class="d-flex align-items-center justify-content-between mb-2">
        <label class="form-label fw-bold mb-0" style="font-size:.9rem;color:var(--edugt-text);">
          <i class="bi bi-robot text-primary me-1"></i> Elige tu Avatar de Programador
        </label>
        <small class="text-muted" id="settings-selected-avatar-label" style="font-size:.78rem;">Inicial clásica</small>
      </div>
      <div id="settings-avatar-picker-grid" class="d-grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(76px, 1fr));"></div>
    `;

    if (submitWrap) {
      form.insertBefore(wrapper, submitWrap);
    } else {
      form.appendChild(wrapper);
    }
  },

  renderAvatarPickerGrid() {
    this.ensureAvatarPickerMounted();
    const grid = document.getElementById('settings-avatar-picker-grid');
    if (!grid) return;

    const u = (typeof Auth !== 'undefined' && Auth.user) ? Auth.user : {};
    const initial = (u.name ? u.name.charAt(0) : 'U').toUpperCase();
    const currentUrl = this.selectedAvatarUrl || '';

    const activeAvatarMeta = EDUGT_AVATARS.find((a) => a.url === currentUrl);
    const labelEl = document.getElementById('settings-selected-avatar-label');
    if (labelEl) {
      labelEl.textContent = activeAvatarMeta ? activeAvatarMeta.name : 'Inicial clásica';
    }

    const isDefaultSelected = !currentUrl;

    let html = `
      <button type="button"
              class="btn p-2 rounded-4 d-flex flex-column align-items-center justify-content-center gap-1 position-relative"
              data-select-avatar=""
              title="Usar mi inicial (${initial})"
              style="background:var(--edugt-card-subtle);border:2px solid ${isDefaultSelected ? 'var(--edugt-primary)' : 'var(--edugt-border)'};transition:all .15s ease;">
        <div class="d-flex align-items-center justify-content-center fw-bold shadow-xs"
             style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6D4AEF,#8B5CF6);color:#fff;font-size:1.2rem;">
          ${initial}
        </div>
        <span class="text-truncate w-100 text-center fw-semibold" style="font-size:.68rem;color:var(--edugt-text);">Inicial</span>
        ${isDefaultSelected ? '<span class="position-absolute top-0 end-0 translate-middle-y me-1 badge rounded-pill bg-primary" style="font-size:.6rem;padding:2px 5px;"><i class="bi bi-check-lg"></i></span>' : ''}
      </button>
    `;

    html += EDUGT_AVATARS.map((av) => {
      const isSelected = currentUrl === av.url;
      return `
        <button type="button"
                class="btn p-2 rounded-4 d-flex flex-column align-items-center justify-content-center gap-1 position-relative"
                data-select-avatar="${av.url}"
                title="${av.name}"
                style="background:var(--edugt-card-subtle);border:2px solid ${isSelected ? 'var(--edugt-primary)' : 'var(--edugt-border)'};transition:all .15s ease;">
          <img src="${av.url}" alt="${av.name}" width="48" height="48" style="width:48px;height:48px;object-fit:cover;border-radius:14px;" loading="lazy">
          <span class="text-truncate w-100 text-center fw-semibold" style="font-size:.68rem;color:var(--edugt-text);">${av.name.split(' ')[0]}</span>
          ${isSelected ? '<span class="position-absolute top-0 end-0 translate-middle-y me-1 badge rounded-pill bg-primary" style="font-size:.6rem;padding:2px 5px;"><i class="bi bi-check-lg"></i></span>' : ''}
        </button>`;
    }).join('');

    grid.innerHTML = html;

    grid.querySelectorAll('[data-select-avatar]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const val = btn.getAttribute('data-select-avatar') || null;
        this.selectedAvatarUrl = val;
        this.updateModalAvatarPreview();
        this.renderAvatarPickerGrid();
        this.updateProfileButtonState();
      });
    });
  },

  updateModalAvatarPreview() {
    const u = (typeof Auth !== 'undefined' && Auth.user) ? Auth.user : {};
    const nameInput = document.getElementById('settings-name');
    const displayName = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : (u.name || 'U');
    const initial = (displayName.charAt(0) || 'U').toUpperCase();
    const avatarChar = document.getElementById('settings-avatar-char');
    if (!avatarChar) return;

    const avatarBox = avatarChar.parentElement;
    if (this.selectedAvatarUrl) {
      avatarChar.innerHTML = `<img src="${this.selectedAvatarUrl}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:18px;display:block;">`;
      if (avatarBox) {
        avatarBox.style.padding = '0';
        avatarBox.style.overflow = 'hidden';
      }
    } else {
      avatarChar.textContent = initial;
    }
  },

  getModal() {
    const el = document.getElementById('modal-settings');
    if (!el) return null;
    if (!this.modalInstance && window.bootstrap && window.bootstrap.Modal) {
      this.modalInstance = new bootstrap.Modal(el);
    }
    return this.modalInstance;
  },

  open() {
    if (typeof Auth !== 'undefined') {
      if (!Auth.user) Auth.load();
    }
    this.populateUserData();
    this.syncThemeUI();
    const modal = this.getModal();
    if (modal) modal.show();
  },

  close() {
    const modal = this.getModal();
    if (modal) modal.hide();
  },

  populateUserData() {
    const u = (typeof Auth !== 'undefined' && Auth.user) ? Auth.user : {};
    const nameInput = document.getElementById('settings-name');
    const emailInput = document.getElementById('settings-email');
    const roleBadge = document.getElementById('settings-role-badge');
    const trackBadge = document.getElementById('settings-track-badge');

    if (nameInput) nameInput.value = u.name || '';
    if (emailInput) emailInput.value = u.email || '';

    this.selectedAvatarUrl = u.avatar_url || null;
    this.updateModalAvatarPreview();
    this.renderAvatarPickerGrid();

    if (roleBadge) {
      if (u.role === 'teacher') {
        roleBadge.className = 'badge bg-primary text-white border-0 px-2.5 py-1';
        roleBadge.innerHTML = '<i class="bi bi-person-badge-fill me-1"></i> Docente Verificado';
      } else {
        roleBadge.className = 'badge bg-purple-subtle text-purple border px-2.5 py-1';
        roleBadge.style.background = 'var(--edugt-primary-soft)';
        roleBadge.style.color = 'var(--edugt-primary)';
        roleBadge.innerHTML = '<i class="bi bi-mortarboard-fill me-1"></i> Estudiante Activo';
      }
    }

    if (trackBadge) {
      const trackName = u.learning_track === 'advanced' ? 'Ruta Avanzada' : 'Ruta Fundamental';
      trackBadge.textContent = trackName;
    }

    // Reset alert messages
    const profileAlert = document.getElementById('settings-profile-alert');
    const passAlert = document.getElementById('settings-pass-alert');
    if (profileAlert) profileAlert.className = 'alert d-none';
    if (passAlert) passAlert.className = 'alert d-none';

    // Limpiar formulario de contraseña y recalcular estado de los botones
    document.getElementById('form-settings-password')?.reset();
    this.updateProfileButtonState();
    this.updatePasswordButtonState();
  },

  syncThemeUI() {
    const currentTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const lightCard = document.getElementById('theme-card-light');
    const darkCard = document.getElementById('theme-card-dark');

    if (lightCard && darkCard) {
      if (currentTheme === 'dark') {
        darkCard.classList.add('active');
        lightCard.classList.remove('active');
      } else {
        lightCard.classList.add('active');
        darkCard.classList.remove('active');
      }
    }
  },

  setTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('edugt_theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('edugt_theme', 'light');
    }
    this.syncThemeUI();
  },

  async handleProfileSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-profile');
    const alertBox = document.getElementById('settings-profile-alert');
    const nameInput = document.getElementById('settings-name');
    const newName = nameInput ? nameInput.value.trim() : '';

    if (!newName) {
      this.showAlert(alertBox, 'Por favor, ingresa tu nombre completo.', 'danger');
      return;
    }

    const currentName = ((Auth.user && Auth.user.name) || '').trim();
    const currentAvatar = (Auth.user && Auth.user.avatar_url) || null;
    const newAvatar = this.selectedAvatarUrl || null;

    if (newName === currentName && newAvatar === currentAvatar) {
      return;
    }

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
    }

    try {
      const res = await api('/me/profile', {
        method: 'PUT',
        body: { name: newName, avatar_url: newAvatar }
      });

      if (res && res.data && res.data.user) {
        Auth.save(Auth.token, res.data.user);
      } else if (typeof Auth !== 'undefined' && Auth.user) {
        Auth.user.name = newName;
        Auth.user.avatar_url = newAvatar;
        Auth.save(Auth.token, Auth.user);
      }

      this.showAlert(alertBox, '¡Perfil y avatar actualizados exitosamente!', 'success');

      // Actualizar saludo o elementos en pantalla sin recargar
      const greeting = document.getElementById('greeting');
      if (greeting && Auth.user) {
        const firstName = Auth.user.name.split(' ')[0];
        if (Auth.user.role === 'teacher') {
          greeting.textContent = `Docente ${Auth.user.name}`;
        } else {
          greeting.textContent = `¡Hola, ${firstName}!`;
        }
      }
      this.updateModalAvatarPreview();
      this.renderAvatarPickerGrid();

      if (typeof loadRankings === 'function') {
        loadRankings().catch(() => {});
      }

    } catch (err) {
      this.showAlert(alertBox, err.message || 'No se pudo actualizar el perfil.', 'danger');
    } finally {
      if (btn) {
        btn.innerHTML = '<i class="bi bi-check2 me-1"></i> Guardar cambios';
      }
      this.updateProfileButtonState();
    }
  },

  async handlePasswordSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-password');
    const alertBox = document.getElementById('settings-pass-alert');
    const curPass = document.getElementById('settings-current-pass')?.value || '';
    const newPass = document.getElementById('settings-new-pass')?.value || '';
    const confPass = document.getElementById('settings-confirm-pass')?.value || '';

    if (!curPass || !newPass || !confPass) {
      this.showAlert(alertBox, 'Completa todos los campos para actualizar tu contraseña.', 'danger');
      return;
    }
    if (newPass.length < 8) {
      this.showAlert(alertBox, 'La nueva contraseña debe tener al menos 8 caracteres.', 'danger');
      return;
    }
    if (newPass !== confPass) {
      this.showAlert(alertBox, 'Las contraseñas no coinciden.', 'danger');
      return;
    }

    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Actualizando...';
    }

    try {
      await api('/me/password', {
        method: 'PUT',
        body: {
          current_password: curPass,
          password: newPass,
          password_confirmation: confPass,
        }
      });

      this.showAlert(alertBox, '¡Contraseña actualizada exitosamente!', 'success');
      document.getElementById('form-settings-password')?.reset();
    } catch (err) {
      this.showAlert(alertBox, err.message || 'Error al actualizar la contraseña.', 'danger');
    } finally {
      if (btn) {
        btn.innerHTML = '<i class="bi bi-shield-lock me-1"></i> Actualizar contraseña';
      }
      this.updatePasswordButtonState();
    }
  },

  showAlert(el, msg, type) {
    if (!el) return;
    el.className = `alert alert-${type} py-2 px-3 mb-3 d-flex align-items-center gap-2`;
    el.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'}"></i> <div>${typeof esc === 'function' ? esc(msg) : msg}</div>`;
  },

  /** Habilita "Guardar cambios" si el nombre o el avatar seleccionado cambiaron. */
  updateProfileButtonState() {
    const btn = document.getElementById('btn-save-profile');
    const nameInput = document.getElementById('settings-name');
    if (!btn || !nameInput) return;

    const currentName = ((typeof Auth !== 'undefined' && Auth.user && Auth.user.name) || '').trim();
    const currentAvatar = (typeof Auth !== 'undefined' && Auth.user && Auth.user.avatar_url) || null;
    const typed = nameInput.value.trim();
    const avatarChanged = (this.selectedAvatarUrl || null) !== currentAvatar;
    btn.disabled = !(typed.length >= 2 && (typed !== currentName || avatarChanged));
  },

  /** Habilita "Actualizar contraseña" solo con los 3 campos llenos y nueva = confirmación. */
  updatePasswordButtonState() {
    const btn = document.getElementById('btn-save-password');
    if (!btn) return;

    const cur = document.getElementById('settings-current-pass')?.value || '';
    const nw = document.getElementById('settings-new-pass')?.value || '';
    const conf = document.getElementById('settings-confirm-pass')?.value || '';

    const allFilled = cur !== '' && nw !== '' && conf !== '';
    const longEnough = nw.length >= 8;
    const matches = nw === conf;

    btn.disabled = !(allFilled && longEnough && matches);

    // Mensaje de ayuda bajo el campo de confirmación
    let hint = document.getElementById('settings-pass-hint');
    if (!hint) {
      const confInput = document.getElementById('settings-confirm-pass');
      if (!confInput) return;
      hint = document.createElement('small');
      hint.id = 'settings-pass-hint';
      hint.style.fontSize = '.78rem';
      confInput.closest('.input-group')?.insertAdjacentElement('afterend', hint);
    }

    if (nw !== '' && !longEnough) {
      hint.className = 'd-block mt-1 text-danger';
      hint.textContent = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } else if (conf !== '' && !matches) {
      hint.className = 'd-block mt-1 text-danger';
      hint.textContent = 'Las contraseñas no coinciden.';
    } else if (conf !== '' && matches && longEnough) {
      hint.className = 'd-block mt-1 text-success';
      hint.textContent = 'Las contraseñas coinciden.';
    } else {
      hint.className = 'd-none';
      hint.textContent = '';
    }
  },

  bindEvents() {
    document.querySelectorAll('[data-action="open-settings"]').forEach(btn => {
      btn.addEventListener('click', () => this.open());
    });

    document.getElementById('form-settings-profile')?.addEventListener('submit', (e) => this.handleProfileSubmit(e));
    document.getElementById('form-settings-password')?.addEventListener('submit', (e) => this.handlePasswordSubmit(e));

    document.getElementById('settings-name')?.addEventListener('input', () => this.updateProfileButtonState());
    ['settings-current-pass', 'settings-new-pass', 'settings-confirm-pass'].forEach(id => {
      document.getElementById(id)?.addEventListener('input', () => this.updatePasswordButtonState());
    });

    this.updateProfileButtonState();
    this.updatePasswordButtonState();

    document.getElementById('theme-card-light')?.addEventListener('click', () => this.setTheme('light'));
    document.getElementById('theme-card-dark')?.addEventListener('click', () => this.setTheme('dark'));
  }
};

window.Settings = Settings;
document.addEventListener('DOMContentLoaded', () => Settings.init());
