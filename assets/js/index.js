/*BIZLINK AUTH – JAVASCRIPT  (auth.js)


/*State*/
const state = {
  tab:        'login',   // 'login' | 'signup'
  loginRole:  null,      // 'admin' | 'vendor' | 'customer'
  signupRole: null,
  step:       1,
  formData:   {}
};

/*TAB SWITCHER*/
function switchTab(tab) {
  state.tab = tab;

  document.getElementById('loginView').classList.toggle('hidden', tab !== 'login');
  document.getElementById('signupView').classList.toggle('hidden', tab !== 'signup');

  document.getElementById('tabLogin').classList.toggle('active', tab === 'login');
  document.getElementById('tabSignup').classList.toggle('active', tab !== 'login');

  // Reset signup if switching back
  if (tab === 'signup' && state.step > 1) {
    // keep state but re-render current step
    renderStep(state.step);
  }
}

/*LOGIN – ROLE SELECTION*/
function selectLoginRole(role, el) {
  state.loginRole = role;

  // Remove selected from all pills
  document.querySelectorAll('#loginRoleSelector .role-pill').forEach(p => p.classList.remove('selected'));
  el.classList.add('selected');

  // Sync brand panel highlight
  syncBrandPanel(role);

  // Colour submit button
  applyRoleColour(document.getElementById('loginSubmitBtn'), role);
}

/*SIGNUP – ROLE BIG CARDS*/
function selectSignupRole(role, el) {
  state.signupRole = role;

  document.querySelectorAll('.role-big-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');

  document.getElementById('step1Next').disabled = false;

  syncBrandPanel(role);
  applyRoleColour(document.getElementById('step1Next'), role);
}

/*BRAND PANEL SYNC*/
function syncBrandPanel(role) {
  ['admin', 'vendor', 'customer'].forEach(r => {
    const card = document.getElementById(`rp${capitalize(r)}`);
    card.classList.remove('role-active-admin', 'role-active-vendor', 'role-active-customer');
  });
  if (role) {
    document.getElementById(`rp${capitalize(role)}`).classList.add(`role-active-${role}`);
  }
}

/*STEP NAVIGATION*/
function goStep(n) {
  if (n === 2 && !state.signupRole) {
    showToast('Please select a role to continue', 'warn');
    return;
  }
  if (n === 3) {
    if (!validateStep2()) return;
    renderStep3();
  }
  if (n === 4) {
    renderReviewCard();
  }

  // Hide all steps
  [1, 2, 3, 4].forEach(i => {
    document.getElementById(`step${i}`)?.classList.add('hidden');
  });
  document.getElementById('successState')?.classList.add('hidden');

  // Show target step
  const target = document.getElementById(`step${n}`);
  if (target) target.classList.remove('hidden');

  state.step = n;
  updateStepIndicator(n);
  scrollToTop();
}

function updateStepIndicator(current) {
  [1, 2, 3, 4].forEach(i => {
    const dot  = document.getElementById(`stepDot${i}`);
    const line = document.getElementById(`stepLine${i}`);
    if (!dot) return;

    dot.classList.remove('active', 'done');
    if (i < current) dot.classList.add('done');
    else if (i === current) dot.classList.add('active');

    if (line) line.classList.toggle('done', i < current);
  });
}

/*STEP 2 VALIDATION*/
function validateStep2() {
  const firstName = document.getElementById('firstName').value.trim();
  const lastName  = document.getElementById('lastName').value.trim();
  const email     = document.getElementById('signupEmail').value.trim();
  const pass      = document.getElementById('signupPassword').value;
  const confirm   = document.getElementById('confirmPassword').value;

  if (!firstName || !lastName) { showToast('Please enter your full name', 'warn'); return false; }
  if (!isValidEmail(email))    { showToast('Please enter a valid email address', 'warn'); return false; }
  if (pass.length < 8)        { showToast('Password must be at least 8 characters', 'warn'); return false; }
  if (pass !== confirm)       { showToast('Passwords do not match', 'warn'); return false; }

  // Save to state
  state.formData.firstName = firstName;
  state.formData.lastName  = lastName;
  state.formData.email     = email;
  return true;
}

/*STEP 3 – SHOW ROLE QUESTIONS*/
function renderStep3() {
  const role = state.signupRole;
  ['adminQuestions', 'vendorQuestions', 'customerQuestions'].forEach(id => {
    document.getElementById(id).classList.add('hidden');
  });

  const map = { admin: 'adminQuestions', vendor: 'vendorQuestions', customer: 'customerQuestions' };
  document.getElementById(map[role]).classList.remove('hidden');

  const descMap = {
    admin:    'Tell us about your admin access',
    vendor:   'Tell us about your business',
    customer: 'Help us personalize your experience'
  };
  document.getElementById('step3Desc').textContent = descMap[role];

  // Colour step3 next button
  const nextBtn = document.querySelector('#step3 .step-next-btn');
  if (nextBtn) applyRoleColour(nextBtn, role);
}

/*STEP 4 – REVIEW CARD*/
function renderReviewCard() {
  const role = state.signupRole;
  const { firstName, lastName, email } = state.formData;

  const roleLabelMap = { admin: '👑 Administrator', vendor: '💼 Vendor', customer: '🛍️ Customer' };
  const roleColourMap = {
    admin: 'style="background:var(--admin-gradient);color:#fff;"',
    vendor: 'style="background:var(--vendor-gradient);color:#fff;"',
    customer: 'style="background:var(--customer-gradient);color:#fff;"'
  };

  document.getElementById('reviewCard').innerHTML = `
    <div class="review-row">
      <span class="review-key">Full Name</span>
      <span class="review-val">${firstName} ${lastName}</span>
    </div>
    <div class="review-row">
      <span class="review-key">Email</span>
      <span class="review-val">${email}</span>
    </div>
    <div class="review-row">
      <span class="review-key">Account Type</span>
      <span class="review-role-pill ${roleColourMap[role]}">${roleLabelMap[role]}</span>
    </div>
    <div class="review-row">
      <span class="review-key">Platform</span>
      <span class="review-val">BizLink CRM 🇱🇰</span>
    </div>
  `;

  // Colour final submit
  const finalBtn = document.getElementById('finalSubmitBtn');
  applyRoleColour(finalBtn, role);
}

/*TERMS CHECKBOX*/
function toggleFinalBtn(checkbox) {
  document.getElementById('finalSubmitBtn').disabled = !checkbox.checked;
}

/*HANDLE SIGNUP SUBMIT*/
function handleSignup() {
  const role = state.signupRole;
  const { firstName } = state.formData;

  // Hide all steps
  [1, 2, 3, 4].forEach(i => document.getElementById(`step${i}`)?.classList.add('hidden'));
  const successEl = document.getElementById('successState');
  successEl.classList.remove('hidden');

  // Update success screen
  document.getElementById('successMsg').innerHTML = `Welcome to BizLink, <strong>${firstName}</strong>!<br/>Your account is ready.`;

  const roleTagMap = {
    admin:    { text: '👑 Administrator', style: 'background:var(--admin-gradient);color:#fff;' },
    vendor:   { text: '💼 Vendor Account', style: 'background:var(--vendor-gradient);color:#fff;' },
    customer: { text: '🛍️ Customer Account', style: 'background:var(--customer-gradient);color:#fff;' }
  };
  const tag = roleTagMap[role];
  const tagEl = document.getElementById('successRoleTag');
  tagEl.textContent = tag.text;
  tagEl.style.cssText = tag.style;

  // Reset step indicator to all done
  updateStepIndicator(5);

  // Update all step dots to done
  [1, 2, 3, 4].forEach(i => {
    const dot = document.getElementById(`stepDot${i}`);
    if (dot) { dot.classList.remove('active'); dot.classList.add('done'); }
    const line = document.getElementById(`stepLine${i}`);
    if (line) line.classList.add('done');
  });

  showToast('Account created successfully! 🎉', 'success');
  scrollToTop();

  // Update success button to go to correct dashboard
  const successBtn = document.querySelector('.success-btn');
  if (successBtn) {
    const dashboardLink = getDashboardLink(role);
    successBtn.href = dashboardLink;
    successBtn.onclick = () => {
      window.location.href = dashboardLink;
      return false;
    };
  }

  // Redirect to dashboard after 3 seconds
  setTimeout(() => {
    window.location.href = getDashboardLink(role);
  }, 3000);
}

/*HANDLE LOGIN SUBMIT*/
function handleLogin(e) {
  e.preventDefault();
  if (!state.loginRole) {
    showToast('Please select your role first', 'warn');
    return;
  }
  const btn = document.getElementById('loginSubmitBtn');
  btn.innerHTML = '<span class="btn-text">Signing In...</span><span class="btn-icon">⏳</span>';
  btn.disabled = true;

  setTimeout(() => {
    btn.innerHTML = '<span class="btn-text">Sign In</span><span class="btn-icon">→</span>';
    btn.disabled = false;
    showToast(`Welcome back! Redirecting to ${capitalize(state.loginRole)} dashboard...`, 'success');
    
    // Redirect to dashboard based on role (paths are relative to pages/index.html)
    const dashboardMap = {
      admin:    '../admin/panel.html',
      vendor:   '../vendor/vendorpanel.html',
      customer: '../customer/dashboard.html'
    };
    setTimeout(() => {
      window.location.href = dashboardMap[state.loginRole];
    }, 1500);
  }, 1600);
}

/*PASSWORD STRENGTH*/
function checkPasswordStrength(val) {
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');

  let strength = 0;
  if (val.length >= 8)             strength++;
  if (/[A-Z]/.test(val))          strength++;
  if (/[0-9]/.test(val))          strength++;
  if (/[^A-Za-z0-9]/.test(val))   strength++;

  const levels = [
    { pct: '0%',   color: 'transparent', text: 'Enter password' },
    { pct: '25%',  color: '#ff4d4d',     text: 'Weak' },
    { pct: '50%',  color: '#ffa500',     text: 'Fair' },
    { pct: '75%',  color: '#facc15',     text: 'Good' },
    { pct: '100%', color: '#50C878',     text: 'Strong ✓' },
  ];
  const lvl = levels[strength];
  fill.style.width      = lvl.pct;
  fill.style.background = lvl.color;
  label.textContent     = lvl.text;
  label.style.color     = lvl.color === 'transparent' ? 'var(--text-3)' : lvl.color;
}

/*TOGGLE PASSWORD VISIBILITY*/
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🙈';
  } else {
    input.type = 'password';
    btn.textContent = '👁️';
  }
}

/*APPLY ROLE COLOUR TO A BUTTON*/
function applyRoleColour(btn, role) {
  if (!btn) return;
  const gradients = {
    admin:    { bg: 'var(--admin-gradient)',    shadow: 'var(--admin-glow)' },
    vendor:   { bg: 'var(--vendor-gradient)',   shadow: 'var(--vendor-glow)' },
    customer: { bg: 'var(--customer-gradient)', shadow: 'var(--customer-glow)' },
  };
  const g = gradients[role];
  if (!g) return;
  btn.style.background  = g.bg;
  btn.style.boxShadow   = `0 6px 24px ${g.shadow}`;
}

/*TOAST NOTIFICATION*/
function showToast(msg, type = 'info') {
  // Remove existing
  document.querySelectorAll('.bizlink-toast').forEach(t => t.remove());

  const colors = {
    success: { bg: 'rgba(80,200,120,0.12)', border: 'rgba(80,200,120,0.35)', icon: '✅' },
    warn:    { bg: 'rgba(255,200,0,0.1)',   border: 'rgba(255,200,0,0.3)',   icon: '⚠️' },
    info:    { bg: 'rgba(79,140,255,0.1)',  border: 'rgba(79,140,255,0.3)',  icon: 'ℹ️' },
  };
  const c = colors[type] || colors.info;

  const toast = document.createElement('div');
  toast.className = 'bizlink-toast';
  toast.innerHTML = `${c.icon} ${msg}`;
  toast.style.cssText = `
    position: fixed;
    bottom: 28px;
    left: 50%;
    transform: translateX(-50%) translateY(10px);
    background: ${c.bg};
    border: 1px solid ${c.border};
    backdrop-filter: blur(16px);
    color: #f0f4ff;
    padding: 13px 22px;
    border-radius: 40px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    font-weight: 500;
    z-index: 9999;
    white-space: nowrap;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    transition: transform 0.4s cubic-bezier(0.22,1,0.36,1), opacity 0.4s;
    opacity: 0;
  `;
  document.body.appendChild(toast);

  // Animate in
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
  });

  // Fade out
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(-50%) translateY(10px)';
    setTimeout(() => toast.remove(), 400);
  }, 3200);
}

/*HELPERS*/
function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function scrollToTop() {
  document.querySelector('.form-panel').scrollTo({ top: 0, behavior: 'smooth' });
}

function getDashboardLink(role) {
  const dashboardMap = {
    admin:    '../admin/panel.html',
    vendor:   '../vendor/vendorpanel.html',
    customer: '../customer/dashboard.html'
  };
  return dashboardMap[role] || 'index.html';
}

/*INPUT FOCUS – DYNAMIC BORDER GLOW BY ROLE*/
function applyInputGlow() {
  const role = state.signupRole || state.loginRole;
  if (!role) return;
  const colorMap = {
    admin:    { border: 'var(--admin-border)',    shadow: 'var(--admin-light)' },
    vendor:   { border: 'var(--vendor-border)',   shadow: 'var(--vendor-light)' },
    customer: { border: 'var(--customer-border)', shadow: 'var(--customer-light)' },
  };
  const c = colorMap[role];
  document.querySelectorAll('.input-wrap:focus-within').forEach(wrap => {
    wrap.style.borderColor = c.border;
    wrap.style.boxShadow   = `0 0 0 3px ${c.shadow}`;
  });
}

/*INIT*/
document.addEventListener('DOMContentLoaded', () => {
  // Default to login tab
  switchTab('login');

  // Global focus tracking to apply role glow on inputs
  document.querySelectorAll('.input-wrap input').forEach(input => {
    input.addEventListener('focus', () => {
      const wrap = input.closest('.input-wrap');
      const role = state.signupRole || state.loginRole;
      if (!role) return;
      const borderMap = { admin: 'var(--admin-border)', vendor: 'var(--vendor-border)', customer: 'var(--customer-border)' };
      const shadowMap = { admin: 'var(--admin-light)',  vendor: 'var(--vendor-light)',  customer: 'var(--customer-light)' };
      wrap.style.borderColor = borderMap[role];
      wrap.style.boxShadow   = `0 0 0 3px ${shadowMap[role]}`;
    });
    input.addEventListener('blur', () => {
      const wrap = input.closest('.input-wrap');
      wrap.style.borderColor = '';
      wrap.style.boxShadow   = '';
    });
  });

  console.log('%c BizLink Auth 🇱🇰 ', 'background: #50C878; color: white; font-size: 14px; padding: 6px 14px; border-radius: 4px;');
});