/**
 * AthleteHub — Auth Page JavaScript
 * Tab switching, password toggle, strength indicator,
 * client-side validation, and toast notifications.
 */

document.addEventListener('DOMContentLoaded', () => {

  // ══════════════════════════════════════
  //  TAB SWITCHING
  // ══════════════════════════════════════
  const tabLogin      = document.getElementById('tabLogin');
  const tabRegister   = document.getElementById('tabRegister');
  const panelLogin    = document.getElementById('panelLogin');
  const panelRegister = document.getElementById('panelRegister');

  function switchTab(tab) {
    if (tab === 'login') {
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
      panelLogin.classList.remove('hidden');
      panelRegister.classList.add('hidden');
    } else {
      tabRegister.classList.add('active');
      tabLogin.classList.remove('active');
      panelRegister.classList.remove('hidden');
      panelLogin.classList.add('hidden');
    }
    // Clear all field errors when switching
    clearAllErrors();
  }

  // Tab button clicks
  if (tabLogin) {
    tabLogin.addEventListener('click', () => switchTab('login'));
  }
  if (tabRegister) {
    tabRegister.addEventListener('click', () => switchTab('register'));
  }

  // Switch links ("Don't have an account?" / "Already have an account?")
  document.querySelectorAll('.auth-switch-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      switchTab(link.dataset.tab);
    });
  });

  // Auto-open register tab from URL param ?tab=register
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('tab') === 'register') {
    switchTab('register');
  }


  // ══════════════════════════════════════
  //  PASSWORD SHOW / HIDE TOGGLE
  // ══════════════════════════════════════
  function setupPasswordToggle(toggleId, inputId) {
    const toggle = document.getElementById(toggleId);
    const input  = document.getElementById(inputId);
    if (!toggle || !input) return;

    toggle.addEventListener('click', () => {
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      const icon = toggle.querySelector('.material-icons-round');
      icon.textContent = isPassword ? 'visibility' : 'visibility_off';
    });
  }

  setupPasswordToggle('toggleLoginPass', 'loginPassword');
  setupPasswordToggle('toggleRegPass', 'regPassword');
  setupPasswordToggle('toggleRegConfirmPass', 'regConfirmPassword');


  // ══════════════════════════════════════
  //  PASSWORD STRENGTH INDICATOR
  // ══════════════════════════════════════
  const regPassword   = document.getElementById('regPassword');
  const strengthBar   = document.getElementById('strengthBar');
  const strengthLabel = document.getElementById('strengthLabel');

  if (regPassword && strengthBar && strengthLabel) {
    regPassword.addEventListener('input', () => {
      const val   = regPassword.value;
      let score = 0;

      if (val.length >= 8)           score++;
      if (/[A-Z]/.test(val))         score++;
      if (/[0-9]/.test(val))         score++;
      if (/[^A-Za-z0-9]/.test(val))  score++;

      // Remove all strength classes
      strengthBar.className = 'strength-bar';
      strengthLabel.className = 'strength-label';
      strengthLabel.textContent = '';

      if (val.length === 0) return;

      if (score <= 1) {
        strengthBar.classList.add('strength-weak');
        strengthLabel.classList.add('label-weak');
        strengthLabel.textContent = 'Weak';
      } else if (score === 2) {
        strengthBar.classList.add('strength-fair');
        strengthLabel.classList.add('label-fair');
        strengthLabel.textContent = 'Fair';
      } else if (score === 3) {
        strengthBar.classList.add('strength-good');
        strengthLabel.classList.add('label-good');
        strengthLabel.textContent = 'Good';
      } else {
        strengthBar.classList.add('strength-strong');
        strengthLabel.classList.add('label-strong');
        strengthLabel.textContent = 'Strong';
      }
    });
  }


  // ══════════════════════════════════════
  //  LIVE PASSWORD MATCH CHECK
  // ══════════════════════════════════════
  const regConfirmPassword = document.getElementById('regConfirmPassword');

  if (regConfirmPassword && regPassword) {
    regConfirmPassword.addEventListener('input', () => {
      const errSpan = document.getElementById('regConfirmPasswordError');
      if (regConfirmPassword.value && regConfirmPassword.value !== regPassword.value) {
        setFieldError(regConfirmPassword, errSpan, 'Passwords do not match');
      } else {
        clearFieldError(regConfirmPassword, errSpan);
        if (regConfirmPassword.value && regConfirmPassword.value === regPassword.value) {
          regConfirmPassword.classList.add('success');
        }
      }
    });
  }


  // ══════════════════════════════════════
  //  FORM VALIDATION — LOGIN
  // ══════════════════════════════════════
  const loginForm = document.getElementById('loginForm');

  if (loginForm) {
    loginForm.addEventListener('submit', (e) => {
      let valid = true;

      const email    = document.getElementById('loginEmail');
      const password = document.getElementById('loginPassword');
      const emailErr = document.getElementById('loginEmailError');
      const passErr  = document.getElementById('loginPasswordError');

      clearFieldError(email, emailErr);
      clearFieldError(password, passErr);

      // Email
      if (!email.value.trim()) {
        setFieldError(email, emailErr, 'Email is required');
        valid = false;
      } else if (!isValidEmail(email.value.trim())) {
        setFieldError(email, emailErr, 'Enter a valid email address');
        valid = false;
      }

      // Password
      if (!password.value) {
        setFieldError(password, passErr, 'Password is required');
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
        shakeForm(loginForm);
      }
    });
  }


  // ══════════════════════════════════════
  //  FORM VALIDATION — REGISTER
  // ══════════════════════════════════════
  const registerForm = document.getElementById('registerForm');

  if (registerForm) {
    registerForm.addEventListener('submit', (e) => {
      let valid = true;

      const fields = {
        firstName:       { el: document.getElementById('regFirstName'),       err: document.getElementById('regFirstNameError') },
        lastName:        { el: document.getElementById('regLastName'),        err: document.getElementById('regLastNameError') },
        email:           { el: document.getElementById('regEmail'),           err: document.getElementById('regEmailError') },
        password:        { el: document.getElementById('regPassword'),        err: document.getElementById('regPasswordError') },
        confirmPassword: { el: document.getElementById('regConfirmPassword'), err: document.getElementById('regConfirmPasswordError') },
        role:            { el: document.getElementById('regRole'),            err: document.getElementById('regRoleError') },
      };

      // Clear all errors
      Object.values(fields).forEach(f => clearFieldError(f.el, f.err));

      // First name
      if (!fields.firstName.el.value.trim()) {
        setFieldError(fields.firstName.el, fields.firstName.err, 'First name is required');
        valid = false;
      }

      // Last name
      if (!fields.lastName.el.value.trim()) {
        setFieldError(fields.lastName.el, fields.lastName.err, 'Last name is required');
        valid = false;
      }

      // Email
      if (!fields.email.el.value.trim()) {
        setFieldError(fields.email.el, fields.email.err, 'Email is required');
        valid = false;
      } else if (!isValidEmail(fields.email.el.value.trim())) {
        setFieldError(fields.email.el, fields.email.err, 'Enter a valid email address');
        valid = false;
      }

      // Password
      if (!fields.password.el.value) {
        setFieldError(fields.password.el, fields.password.err, 'Password is required');
        valid = false;
      } else if (fields.password.el.value.length < 8) {
        setFieldError(fields.password.el, fields.password.err, 'Minimum 8 characters required');
        valid = false;
      }

      // Confirm Password
      if (!fields.confirmPassword.el.value) {
        setFieldError(fields.confirmPassword.el, fields.confirmPassword.err, 'Please confirm your password');
        valid = false;
      } else if (fields.confirmPassword.el.value !== fields.password.el.value) {
        setFieldError(fields.confirmPassword.el, fields.confirmPassword.err, 'Passwords do not match');
        valid = false;
      }

      // Role
      if (!fields.role.el.value) {
        setFieldError(fields.role.el, fields.role.err, 'Please select your role');
        valid = false;
      }

      if (!valid) {
        e.preventDefault();
        shakeForm(registerForm);
      }
    });
  }


  // ══════════════════════════════════════
  //  GOOGLE SSO BUTTON (Placeholder)
  // ══════════════════════════════════════
  const googleBtn = document.getElementById('googleLoginBtn');
  if (googleBtn) {
    googleBtn.addEventListener('click', () => {
      showToast('Google login coming in V2', 'info');
    });
  }

  // Forgot password link
  const forgotLink = document.getElementById('forgotPasswordLink');
  if (forgotLink) {
    forgotLink.addEventListener('click', (e) => {
      e.preventDefault();
      showToast('Password reset coming in V2', 'info');
    });
  }


  // ══════════════════════════════════════
  //  HELPER FUNCTIONS
  // ══════════════════════════════════════

  /**
   * Validate email format
   */
  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  /**
   * Set error state on a field
   */
  function setFieldError(input, errorSpan, message) {
    if (input) input.classList.add('error');
    if (input) input.classList.remove('success');
    if (errorSpan) errorSpan.textContent = message;
  }

  /**
   * Clear error state on a field
   */
  function clearFieldError(input, errorSpan) {
    if (input) input.classList.remove('error', 'success');
    if (errorSpan) errorSpan.textContent = '';
  }

  /**
   * Clear all error states on the page
   */
  function clearAllErrors() {
    document.querySelectorAll('.glass-input.error').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.glass-input.success').forEach(el => el.classList.remove('success'));
    document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
  }

  /**
   * Shake animation on form
   */
  function shakeForm(form) {
    form.classList.add('shake');
    setTimeout(() => form.classList.remove('shake'), 400);
  }

  /**
   * Clear input error styling on focus
   */
  document.querySelectorAll('.glass-input').forEach(input => {
    input.addEventListener('focus', () => {
      input.classList.remove('error');
      // Clear sibling error span
      const errorSpan = input.closest('.form-group')?.querySelector('.field-error');
      if (errorSpan) errorSpan.textContent = '';
    });
  });


  // ══════════════════════════════════════
  //  TOAST NOTIFICATION
  // ══════════════════════════════════════

  /**
   * Show a toast notification
   * @param {string} message
   * @param {string} type - 'success' | 'error' | 'info' | 'warning'
   * @param {number} duration - ms (default 3500)
   */
  window.showToast = function(message, type = 'info', duration = 3500) {
    let container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.id = 'toastContainer';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
      success: 'check_circle',
      error:   'error',
      info:    'info',
      warning: 'warning'
    };

    toast.innerHTML = `
      <span class="material-icons-round">${icons[type] || 'info'}</span>
      <span class="toast-message">${message}</span>
      <button class="toast-close" aria-label="Close">
        <span class="material-icons-round">close</span>
      </button>
    `;

    // Close button
    toast.querySelector('.toast-close').addEventListener('click', () => {
      dismissToast(toast);
    });

    container.appendChild(toast);

    // Auto-dismiss
    setTimeout(() => dismissToast(toast), duration);
  };

  function dismissToast(toast) {
    if (!toast || toast.classList.contains('toast-exit')) return;
    toast.classList.add('toast-exit');
    toast.addEventListener('animationend', () => toast.remove());
  }

});
