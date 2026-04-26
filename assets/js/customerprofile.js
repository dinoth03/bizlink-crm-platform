 (function() {
    function formatMonthYear(dateText) {
      if (!dateText) return '—';
      const date = new Date(dateText);
      if (Number.isNaN(date.getTime())) return '—';
      return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    }

    function getInitials(fullName) {
      return String(fullName || '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'CU';
    }

    function applyCustomerProfile(identity) {
      if (!identity || !identity.user) return;

      const user = identity.user;
      const profile = identity.profile || {};
      const fullName = String(user.full_name || 'Customer').trim() || 'Customer';
      const email = String(user.email || '').trim() || '—';
      const phone = String(user.phone || '').trim() || '—';
      const city = String(user.city || '').trim();
      const country = String(user.country || '').trim();
      const preferredLanguage = String(profile.preferred_language || '').trim();
      const isVerified = Number(user.is_verified || 0) === 1;
      const status = String(user.account_status || '').toLowerCase();

      const profileName = document.getElementById('profileName');
      const profileBadge = document.getElementById('profileBadge');
      const profileEmail = document.getElementById('profileEmail');
      const profileLocation = document.getElementById('profileLocation');
      const profileMobile = document.getElementById('profileMobile');
      const profileLanguage = document.getElementById('profileLanguage');
      const profileMemberSince = document.getElementById('profileMemberSince');
      const profileAvatar = document.getElementById('profileAvatar');
      const settingsFullName = document.getElementById('settingsFullName');
      const settingsEmail = document.getElementById('settingsEmail');
      const settingsPhone = document.getElementById('settingsPhone');

      if (profileName) profileName.textContent = fullName;
      if (profileEmail) profileEmail.textContent = email;
      if (profileMobile) profileMobile.textContent = phone;
      if (profileLanguage) profileLanguage.textContent = preferredLanguage || 'English';
      if (profileMemberSince) profileMemberSince.textContent = formatMonthYear(user.created_at);
      if (settingsFullName) settingsFullName.textContent = fullName;
      if (settingsEmail) settingsEmail.textContent = email;
      if (settingsPhone) settingsPhone.textContent = phone;

      if (profileLocation) {
        const location = [city, country].filter(Boolean).join(', ');
        profileLocation.textContent = location || '—';
      }

      if (profileBadge) {
        if (status === 'active' && isVerified) {
          profileBadge.textContent = 'VERIFIED CUSTOMER';
        } else if (status === 'active') {
          profileBadge.textContent = 'ACTIVE CUSTOMER';
        } else {
          profileBadge.textContent = 'PENDING CUSTOMER';
        }
      }

      if (profileAvatar) {
        profileAvatar.textContent = getInitials(fullName);
      }
    }

    async function loadCustomerProfile() {
      if (typeof authMe !== 'function') return;
      const identity = await authMe(true);
      if (!identity || !identity.user) {
        return;
      }

      const role = String(identity.user.role || '').toLowerCase();
      if (role !== 'customer') {
        const redirects = {
          admin: '../admin/dashboard.html',
          vendor: '../vendor/vendorpanel.html',
          customer: 'dashboard.html'
        };
        window.location.href = redirects[role] || '../pages/index.html?reason=unauthorized';
        return;
      }

      applyCustomerProfile(identity);
    }

    // navbar scroll effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 60) navbar.classList.add('scrolled');
      else navbar.classList.remove('scrolled');
    });

    // hamburger menu
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.querySelector('.nav-links');
    const navCta = document.querySelector('.nav-cta');
    if (hamburger) {
      hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('open');
        navCta.classList.toggle('open');
      });
    }
    // close mobile menu when link clicked
    document.querySelectorAll('.nav-links a').forEach(a => {
      a.addEventListener('click', () => {
        navLinks?.classList.remove('open');
        navCta?.classList.remove('open');
      });
    });

    // display current date
    const dateSpan = document.getElementById('currentDate');
    if (dateSpan) {
      const d = new Date();
      dateSpan.innerText = d.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
    }

    // toggle switches functionality
    document.querySelectorAll('.toggle-switch').forEach(t => {
      t.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('active');
      });
    });

    // edit profile button – currently placeholder action
    document.getElementById('editProfileBtn')?.addEventListener('click', () => {
      alert('Profile editor will be connected soon.');
    });

    // sign out
    document.getElementById('customerProfileLogoutBtn')?.addEventListener('click', async () => {
      try {
        if (typeof authLogout === 'function') {
          await authLogout();
        }
      } catch (error) {
        console.warn('Profile logout request failed, redirecting anyway:', error);
      }
      window.location.href = '../pages/index.html';
    });

    // ripple effect for buttons (same as home)
    const style = document.createElement('style');
    style.textContent = `@keyframes rippleOut{to{width:300px;height:300px;opacity:0;}}`;
    document.head.appendChild(style);

    // optional counter animation on left card stats (quick)
    const counters = document.querySelectorAll('.stat-number'); // if any .stat-number present, but we don't use many; we add effect to small numbers
    // not needed, but keep smooth

    loadCustomerProfile();

    console.log('BizLink Customer Profile initialized');
  })();