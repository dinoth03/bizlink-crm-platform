/*CRM PLATFORM — script.js
   Dark mode · Modal · Scroll reveal · Counters · Tilt*/

(function () {
  'use strict';

  /*  DARK MODE TOGGLE  */
  const html        = document.documentElement;
  const themeToggle = document.getElementById('themeToggle');
  const THEME_KEY   = 'crm-theme';

  function applyTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem(THEME_KEY, theme);
  }

  // Restore saved preference
  const saved = localStorage.getItem(THEME_KEY);
  if (saved) applyTheme(saved);
  else if (window.matchMedia('(prefers-color-scheme: dark)').matches) applyTheme('dark');

  themeToggle.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
  });


  /* COUNTER ANIMATION */
  function animateCounter(el) {
    const target = parseInt(el.dataset.target, 10);
    const duration = 1600;
    const start    = performance.now();
    function step(now) {
      const elapsed  = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease     = 1 - Math.pow(1 - progress, 4); // ease-out quart
      const current  = Math.round(ease * target);
      el.textContent = current.toLocaleString();
      if (progress < 1) requestAnimationFrame(step);
      else el.textContent = target === 98 ? '98' : target.toLocaleString();
    }
    requestAnimationFrame(step);
  }

  let countersStarted = false;
  function startCountersWhenVisible() {
    if (countersStarted) return;
    const counters = document.querySelectorAll('.hc-n[data-target]');
    if (!counters.length) return;
    const first = counters[0].closest('.hero-counters');
    if (!first) return;
    const rect = first.getBoundingClientRect();
    if (rect.top < window.innerHeight - 50) {
      countersStarted = true;
      counters.forEach(el => animateCounter(el));
    }
  }


  /* SCROLL REVEAL */
  const io = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          io.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.08 }
  );

  document.querySelectorAll('.sh, .ad-card, .svc-card').forEach(el => io.observe(el));
  document.querySelectorAll('.reveal').forEach(el => {
    // hero reveals are CSS animation-based — just leave them
    el.style.animationPlayState = 'running';
  });

  window.addEventListener('scroll', startCountersWhenVisible, { passive: true });
  startCountersWhenVisible(); // check on load too


  /*CARD 3D TILT */
  document.querySelectorAll('.ad-card, .svc-card').forEach(card => {
    card.addEventListener('mousemove', e => {
      const r  = card.getBoundingClientRect();
      const x  = (e.clientX - r.left) / r.width  - 0.5;
      const y  = (e.clientY - r.top)  / r.height - 0.5;
      card.style.transform = `translateY(-6px) scale(1.02) perspective(700px) rotateX(${y * -5}deg) rotateY(${x * 5}deg)`;
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });


  /*  MODAL  */
  const backdrop = document.getElementById('modalBd');
  const modal    = document.getElementById('modal');
  const closeBtn = document.getElementById('modalX');
  const saveBtn  = document.getElementById('mSave');

  const mImg     = document.getElementById('mImg');
  const mLogo    = document.getElementById('mLogo');
  const mName    = document.getElementById('mName');
  const mCat     = document.getElementById('mCat');
  const mRating  = document.getElementById('mRating');
  const mDesc    = document.getElementById('mDesc');
  const mLoc     = document.getElementById('mLoc');
  const mProds   = document.getElementById('mProds');
  const mSince   = document.getElementById('mSince');
  const mVisit   = document.getElementById('mVisit');
  const mPriceRow = document.getElementById('mPriceRow');
  const mPrice   = document.getElementById('mPrice');

  function gradientFrom(hex) {
    return `linear-gradient(135deg, ${hex}, ${darken(hex, 40)})`;
  }
  function darken(hex, amt) {
    const n = parseInt(hex.slice(1), 16);
    const r = Math.max(0, (n >> 16) - amt);
    const g = Math.max(0, ((n >> 8) & 0xff) - amt);
    const b = Math.max(0, (n & 0xff) - amt);
    return `rgb(${r},${g},${b})`;
  }

  function openModal(card) {
    const d    = card.dataset;
    const img  = card.querySelector('img');

    mImg.src             = img ? img.src : '';
    mImg.alt             = d.vendor || '';
    mLogo.textContent    = d.init || '??';
    mLogo.style.background = gradientFrom(d.color || '#4f7cff');
    mName.textContent    = d.vendor   || '';
    mCat.textContent     = d.cat      || '';
    mRating.textContent  = d.rating   || '';
    mDesc.textContent    = d.desc     || '';
    mLoc.textContent     = d.loc      || '';
    mProds.textContent   = d.prods    || '';
    mSince.textContent   = d.since    || '';
    mVisit.style.background = gradientFrom(d.color || '#4f7cff');

    if (d.price) {
      mPrice.textContent   = d.price;
      mPriceRow.style.display = 'flex';
    } else {
      mPriceRow.style.display = 'none';
    }

    saveBtn.classList.remove('saved');
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
    modal.scrollTop = 0;
  }

  function closeModal() {
    backdrop.classList.remove('open');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.ad-card, .svc-card').forEach(card => {
    card.addEventListener('click', () => openModal(card));
  });

  closeBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // Save toggle
  saveBtn.addEventListener('click', e => {
    e.stopPropagation();
    saveBtn.classList.toggle('saved');
  });


  /*  NAVBAR SCROLL SHADOW  */
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', () => {
    navbar.style.boxShadow = window.scrollY > 10
      ? '0 4px 24px rgba(0,0,0,0.1)'
      : 'none';
  }, { passive: true });


  /*  NAV LINK ACTIVE STATE  */
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
      document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
      link.classList.add('active');
    });
  });


  /*  LOAD MORE FUNCTIONALITY  */
  const productBtn = document.getElementById('loadMoreProducts');
  const servicesBtn = document.getElementById('loadMoreServices');
  const productsGrid = document.getElementById('productsGrid');
  const servicesGrid = document.getElementById('servicesGrid');

  if (productBtn) {
    productBtn.addEventListener('click', () => {
      productsGrid.classList.add('show-all');
      productBtn.style.display = 'none';
      // Smooth scroll to reveal new items
      setTimeout(() => {
        productsGrid.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 100);
    });
  }

  if (servicesBtn) {
    servicesBtn.addEventListener('click', () => {
      servicesGrid.classList.add('show-all');
      servicesBtn.style.display = 'none';
      // Smooth scroll to reveal new items
      setTimeout(() => {
        servicesGrid.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 100);
    });
  }

})();