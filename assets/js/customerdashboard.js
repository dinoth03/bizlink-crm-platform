    // NAVBAR SCROLL + HAMBURGER
    (function() {
      const navbar = document.getElementById('navbar');
      window.addEventListener('scroll', ()=> {
        navbar.classList.toggle('scrolled', window.scrollY > 60);
      });

      const hamburger = document.getElementById('hamburger');
      const navLinks = document.querySelector('.nav-links');
      const navCta = document.querySelector('.nav-cta');
      if (hamburger) {
        hamburger.addEventListener('click', ()=> {
          navLinks.classList.toggle('open');
          navCta.classList.toggle('open');
        });
      }
      // close on link click
      document.querySelectorAll('.nav-links a').forEach(a => a.addEventListener('click', ()=>{
        navLinks?.classList.remove('open');
        navCta?.classList.remove('open');
      }));

      // DATE
      const d = new Date();
      document.getElementById('currentDate') && (document.getElementById('currentDate').innerText = d.toLocaleDateString('en-US', { weekday:'short', year:'numeric', month:'short', day:'numeric' }));

      // COUNTER ANIMATION
      const counters = document.querySelectorAll('.stat-number');
      const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const target = parseInt(entry.target.dataset.target || '0');
            animateCounter(entry.target, target);
            counterObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.5 });
      counters.forEach(c => counterObserver.observe(c));

      function animateCounter(el, target) {
        let start = 0;
        const step = target / (2000/16);
        function tick() {
          start += step;
          if (start >= target) { el.textContent = target; }
          else { el.textContent = Math.floor(start); requestAnimationFrame(tick); }
        }
        requestAnimationFrame(tick);
      }

      // TOGGLE SWITCHES (profile)
      document.querySelectorAll('.toggle-switch').forEach(t => {
        t.addEventListener('click', function(e) {
          e.stopPropagation();
          this.classList.toggle('active');
        });
      });

      // simple page toggle for preview
      window.showDashboard = function() {
        document.getElementById('dashboard-view').style.display = 'block';
        document.getElementById('profile-view').style.display = 'none';
      };
      window.showProfile = function() {
        document.getElementById('dashboard-view').style.display = 'none';
        document.getElementById('profile-view').style.display = 'block';
      };

      // RIPPLE effect (optional)
      const style = document.createElement('style');
      style.textContent = `@keyframes rippleOut{to{width:300px;height:300px;opacity:0;}}`;
      document.head.appendChild(style);

    console.log('🇱🇰 BizLink Customer Portal – #FF8C00');
  })();

  // inline JS to show correct page based on URL filename (simple simulation)
  (function setPageFromUrl() {
      const path = window.location.pathname;
      if (path.includes('userprofile.html')) {
        document.getElementById('dashboard-view').style.display = 'none';
        document.getElementById('profile-view').style.display = 'block';
      } else {
        // default to dashboard (including dashboard.html)
        document.getElementById('dashboard-view').style.display = 'block';
        document.getElementById('profile-view').style.display = 'none';
      }
    })();