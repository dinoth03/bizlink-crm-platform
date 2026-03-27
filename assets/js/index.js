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

  const adminCodeField = document.getElementById('adminCodeField');
  if (adminCodeField) {
    const codeInput = document.getElementById('adminVerifyCode');
    const resendLink = adminCodeField.querySelector('a');
    const isAdmin = role === 'admin';
    adminCodeField.style.opacity = isAdmin ? '1' : '0.6';
    if (codeInput) {
      codeInput.disabled = !isAdmin;
      if (!isAdmin) {
        codeInput.value = '';
      }
    }
    if (resendLink) {
      resendLink.style.pointerEvents = isAdmin ? 'auto' : 'none';
    }
  }
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
  const phone     = document.getElementById('phoneNumber').value.trim();
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
  state.formData.phone     = phone;
  state.formData.password  = pass;
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

function collectRoleProfileData(role) {
  if (role === 'admin') {
    return {
      accessCode: document.getElementById('adminAccessCode')?.value?.trim() || '',
      department: document.getElementById('adminDepartment')?.value?.trim() || '',
      title: document.getElementById('adminRoleTitle')?.value?.trim() || '',
      accessReason: document.getElementById('adminAccessReason')?.value?.trim() || ''
    };
  }

  if (role === 'vendor') {
    const employeeRange = document.querySelector('input[name="empSize"]:checked')?.value || '';
    return {
      businessName: document.getElementById('vendorBusinessName')?.value?.trim() || '',
      businessRegNo: document.getElementById('vendorBusinessRegNo')?.value?.trim() || '',
      industry: document.getElementById('vendorIndustry')?.value?.trim() || '',
      locationProvince: document.getElementById('vendorLocationProvince')?.value?.trim() || '',
      employeeRange,
      referralSource: document.getElementById('vendorReferralSource')?.value?.trim() || ''
    };
  }

  if (role === 'customer') {
    const preferredLanguage = document.querySelector('input[name="lang"]:checked')?.value || 'en';
    const lookingFor = document.querySelector('input[name="custLook"]:checked')?.value || '';
    return {
      city: document.getElementById('customerCity')?.value?.trim() || '',
      preferredLanguage,
      lookingFor,
      referralSource: document.getElementById('customerReferralSource')?.value?.trim() || ''
    };
  }

  return {};
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
async function handleSignup() {
  const role = state.signupRole;
  const { firstName } = state.formData;
  const fullName = `${state.formData.firstName || ''} ${state.formData.lastName || ''}`.trim();
  const email = (state.formData.email || '').trim().toLowerCase();
  const password = state.formData.password || '';
  const phone = state.formData.phone || '';

  if (!role) {
    showToast('Please select account role.', 'warn');
    return;
  }

  if (!password || password.length < 8) {
    showToast('Please enter a valid password.', 'warn');
    return;
  }

  const finalBtn = document.getElementById('finalSubmitBtn');
  if (finalBtn) {
    finalBtn.disabled = true;
    finalBtn.innerHTML = '<span class="btn-text">Creating Account...</span><span class="btn-icon">⏳</span>';
  }

  const profile = collectRoleProfileData(role);

  let signupResult = null;
  if (typeof authSignup === 'function') {
    signupResult = await authSignup({
      role,
      email,
      password,
      first_name: state.formData.firstName || '',
      last_name: state.formData.lastName || '',
      phone,
      profile
    });
  }

  if (!signupResult || !signupResult.success) {
    if (finalBtn) {
      finalBtn.disabled = false;
      finalBtn.innerHTML = '<span class="btn-text">Create Account</span><span class="btn-icon">🚀</span>';
    }
    showToast((signupResult && signupResult.message) || 'Signup failed. Please check your backend connection.', 'warn');
    return;
  }

  // Hide all steps
  [1, 2, 3, 4].forEach(i => document.getElementById(`step${i}`)?.classList.add('hidden'));
  const successEl = document.getElementById('successState');
  successEl.classList.remove('hidden');

  // Update success screen
  let successMsg = `Welcome to BizLink, <strong>${firstName}</strong>!<br/>Account created successfully.`;
  if (role === 'admin' && signupResult.verification_required) {
    successMsg = `Welcome to BizLink, <strong>${firstName}</strong>!<br/>Please check your email and enter the 6-digit admin verification code before login.`;
  } else if (signupResult.requires_admin_approval) {
    successMsg = `Welcome to BizLink, <strong>${firstName}</strong>!<br/>Your account is pending admin approval. You can login after approval.`;
  }
  document.getElementById('successMsg').innerHTML = successMsg;

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

  showToast((signupResult && signupResult.message) || 'Account created.', 'success');
  scrollToTop();

  const redirectLink = '../pages/index.html';
  const successBtn = document.querySelector('.success-btn');
  if (successBtn) {
    successBtn.href = redirectLink;
    successBtn.querySelector('.btn-text').textContent = 'Go to Sign In';
    successBtn.onclick = () => {
      window.location.href = redirectLink;
      return false;
    };
  }

  if (role === 'admin' && signupResult.verification_method === 'code') {
    if (signupResult.verification_code) {
      window.prompt('Local development admin verification code:', signupResult.verification_code);
    }
  } else if (signupResult.verification_link) {
    window.prompt('Local development verification link:', signupResult.verification_link);
  }

  setTimeout(() => {
    window.location.href = redirectLink;
  }, 3500);
}

/*HANDLE LOGIN SUBMIT*/
async function handleLogin(e) {
  e.preventDefault();
  if (!state.loginRole) {
    showToast('Please select your role first', 'warn');
    return;
  }
  const loginEmail = (document.getElementById('loginEmail')?.value || '').trim().toLowerCase();
  if (!isValidEmail(loginEmail)) {
    showToast('Please enter a valid email address', 'warn');
    return;
  }
  const loginPassword = document.getElementById('loginPassword')?.value || '';
  if (!loginPassword) {
    showToast('Please enter your password', 'warn');
    return;
  }

  const btn = document.getElementById('loginSubmitBtn');
  btn.innerHTML = '<span class="btn-text">Signing In...</span><span class="btn-icon">⏳</span>';
  btn.disabled = true;

  const result = typeof authLogin === 'function'
    ? await authLogin({ role: state.loginRole, email: loginEmail, password: loginPassword })
    : null;

  btn.innerHTML = '<span class="btn-text">Sign In</span><span class="btn-icon">→</span>';
  btn.disabled = false;

  if (!result || !result.success) {
    const errorCode = String(result && result.code ? result.code : '').toUpperCase();

    if (errorCode === 'EMAIL_NOT_VERIFIED' && state.loginRole === 'admin') {
      const codeInput = (document.getElementById('adminVerifyCode')?.value || '').trim();
      if (!/^\d{6}$/.test(codeInput)) {
        showToast('Enter your 6-digit admin verification code in the code field.', 'warn');
        return;
      }

      if (typeof authVerifyAdminCode === 'function') {
        const verifyResult = await authVerifyAdminCode({ email: loginEmail, code: codeInput });
        if (verifyResult && verifyResult.success) {
          showToast('Admin verified. Sign in once more to continue.', 'success');
          const codeEl = document.getElementById('adminVerifyCode');
          if (codeEl) {
            codeEl.value = '';
          }
        } else {
          showToast((verifyResult && verifyResult.message) || 'Verification failed.', 'warn');
        }
        return;
      }
    }

    if (errorCode === 'ACCOUNT_PENDING_APPROVAL') {
      showToast('Your account is waiting for admin approval.', 'info');
      return;
    }

    showToast((result && result.message) || 'Login failed. Please check backend API.', 'warn');
    return;
  }

  const dashboardLink = result.dashboard || getDashboardLink(state.loginRole);
  showToast(`Welcome back! Redirecting to ${capitalize(state.loginRole)} dashboard...`, 'success');

  setTimeout(() => {
    window.location.href = dashboardLink;
  }, 900);
}

async function resendAdminCodeFromLogin(event) {
  if (event) {
    event.preventDefault();
  }

  if (state.loginRole !== 'admin') {
    showToast('Select Admin role first.', 'warn');
    return;
  }

  const loginEmail = (document.getElementById('loginEmail')?.value || '').trim().toLowerCase();
  if (!isValidEmail(loginEmail)) {
    showToast('Enter admin email first, then resend code.', 'warn');
    return;
  }

  if (typeof authResendVerification !== 'function') {
    showToast('Resend service unavailable.', 'warn');
    return;
  }

  const resendResult = await authResendVerification({ role: 'admin', email: loginEmail });
  showToast((resendResult && resendResult.message) || 'Verification code resend requested.', 'info');
  if (resendResult && resendResult.verification_code) {
    window.prompt('Local development admin verification code:', resendResult.verification_code);
  }
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
  if (role === 'admin' || role === 'vendor' || role === 'customer') {
    return '../dashboard.php';
  }
  return 'index.html';
}

async function triggerForgotPasswordFlow() {
  const email = window.prompt('Enter your account email to reset password:');
  if (!email) return;

  if (!isValidEmail(email)) {
    showToast('Please enter a valid email address.', 'warn');
    return;
  }

  if (typeof authForgotPassword !== 'function') {
    showToast('Password reset service is unavailable.', 'warn');
    return;
  }

  const result = await authForgotPassword({ email: String(email).trim().toLowerCase() });
  showToast((result && result.message) || 'If your account exists, a reset link was generated.', 'info');

  if (result && result.reset_link) {
    window.prompt('Local development reset link:', result.reset_link);
  }
}

async function handleAuthQueryFlows() {
  const params = new URLSearchParams(window.location.search);
  const reason = params.get('reason') || '';
  const verifyToken = params.get('verify_token') || '';
  const resetToken = params.get('reset_token') || '';

  if (reason === 'session_expired' || reason === 'unauthorized') {
    showToast('Your session expired. Please sign in again.', 'info');
  } else if (reason === 'too_many_requests') {
    showToast('Too many requests. Please wait a moment and try again.', 'warn');
  }

  if (verifyToken && typeof authVerifyEmail === 'function') {
    const verifyResult = await authVerifyEmail({ token: verifyToken });
    showToast((verifyResult && verifyResult.message) || 'Verification failed.', verifyResult && verifyResult.success ? 'success' : 'warn');
    params.delete('verify_token');
    const nextVerify = params.toString();
    window.history.replaceState({}, '', nextVerify ? `?${nextVerify}` : window.location.pathname);
    switchTab('login');
  }

  if (resetToken && typeof authResetPassword === 'function') {
    const newPassword = window.prompt('Enter your new password (min 8 characters):');
    if (newPassword && newPassword.length >= 8) {
      const confirmPassword = window.prompt('Confirm your new password:');
      if (confirmPassword === newPassword) {
        const resetResult = await authResetPassword({ token: resetToken, password: newPassword });
        showToast((resetResult && resetResult.message) || 'Password reset failed.', resetResult && resetResult.success ? 'success' : 'warn');
      } else {
        showToast('Passwords do not match.', 'warn');
      }
    } else if (newPassword) {
      showToast('Password must be at least 8 characters.', 'warn');
    }

    params.delete('reset_token');
    const nextReset = params.toString();
    window.history.replaceState({}, '', nextReset ? `?${nextReset}` : window.location.pathname);
    switchTab('login');
  }
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
document.addEventListener('DOMContentLoaded', async () => {
  // Default to login tab
  switchTab('login');

  const forgotLink = document.querySelector('.forgot-link');
  if (forgotLink) {
    forgotLink.addEventListener('click', (event) => {
      event.preventDefault();
      triggerForgotPasswordFlow();
    });
  }

  await handleAuthQueryFlows();

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