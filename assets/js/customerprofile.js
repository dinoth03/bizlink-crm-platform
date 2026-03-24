 (function() {
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

    // edit profile button – just a demo alert (but matches customer theme)
    document.getElementById('editProfileBtn')?.addEventListener('click', () => {
      alert('✎ Profile editor would open (demo). In real app you can update details.');
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

    console.log('🇱🇰 BizLink Customer Profile – #FF8C00 style');
  })();