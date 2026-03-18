/* ============================================================
   BIZLINK SUSTAINABILITY — sustainability.js
   ============================================================ */

/* ── Navbar scroll ── */
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 40);
  document.getElementById('scrollTop').classList.toggle('visible', window.scrollY > 400);
});

/* ── Mobile menu ── */
function toggleMenu() {
  document.getElementById('mobileMenu').classList.toggle('hidden');
}

/* ── Scroll reveal ── */
const reveals = document.querySelectorAll('.scroll-reveal');
const revealObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObs.unobserve(e.target);
    }
  });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
reveals.forEach(el => revealObs.observe(el));

/* ── Bar fills (triggered on scroll) ── */
const barObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.querySelectorAll('.isg-bar-fill').forEach(bar => {
        bar.style.width = bar.style.width; // trigger CSS transition
      });
      barObs.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });
document.querySelectorAll('.id-stats-grid').forEach(el => barObs.observe(el));

/* ── Counter animation ── */
function animateCounter(el, target, duration = 1800, prefix = '', suffix = '') {
  const isLarge = target > 999;
  let start = null;
  const step = (ts) => {
    if (!start) start = ts;
    const progress = Math.min((ts - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.floor(eased * target);
    if (isLarge && target >= 1000) {
      el.textContent = current.toLocaleString();
    } else {
      el.textContent = current;
    }
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = target >= 1000 ? target.toLocaleString() : target;
  };
  requestAnimationFrame(step);
}

const counterObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      // Hero counters
      e.target.querySelectorAll('.hc-num[data-target]').forEach(el => {
        animateCounter(el, parseInt(el.dataset.target), 2000);
      });
      // Impact counters
      e.target.querySelectorAll('.isg-num[data-target]').forEach(el => {
        animateCounter(el, parseInt(el.dataset.target), 1800);
      });
      counterObs.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });

document.querySelectorAll('.hero-counters, .id-stats-grid').forEach(el => counterObs.observe(el));

/* ── Floating particles ── */
function createParticles() {
  const container = document.getElementById('particles');
  if (!container) return;
  const count = 28;
  const colors = ['rgba(80,200,120,0.7)', 'rgba(79,140,255,0.5)', 'rgba(255,140,0,0.4)'];

  for (let i = 0; i < count; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.cssText = `
      left: ${Math.random() * 100}%;
      top: ${Math.random() * 100}%;
      width: ${Math.random() * 3 + 1.5}px;
      height: ${Math.random() * 3 + 1.5}px;
      background: ${colors[Math.floor(Math.random() * colors.length)]};
      --dur: ${Math.random() * 10 + 7}s;
      --delay: ${Math.random() * 8}s;
    `;
    container.appendChild(p);
  }
}
createParticles();

/* ── Pillar card tilt effect ── */
document.querySelectorAll('.pillar-card').forEach(card => {
  card.addEventListener('mousemove', (e) => {
    const rect = card.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width - 0.5;
    const y = (e.clientY - rect.top) / rect.height - 0.5;
    card.style.transform = `translateY(-10px) rotateX(${y * -5}deg) rotateY(${x * 5}deg)`;
    card.style.transition = 'box-shadow 0.2s, border-color 0.35s';
  });
  card.addEventListener('mouseleave', () => {
    card.style.transform = '';
    card.style.transition = 'all 0.4s cubic-bezier(0.22,1,0.36,1)';
  });
});

/* ── What card ripple on click ── */
document.querySelectorAll('.what-card').forEach(card => {
  card.addEventListener('click', (e) => {
    const ripple = document.createElement('span');
    const rect = card.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    ripple.style.cssText = `
      position:absolute;border-radius:50%;
      width:${size}px;height:${size}px;
      top:${e.clientY - rect.top - size/2}px;
      left:${e.clientX - rect.left - size/2}px;
      background:rgba(80,200,120,0.12);
      transform:scale(0);
      animation:rippleAnim 0.6s ease-out forwards;
      pointer-events:none;z-index:10;
    `;
    card.style.position = 'relative';
    card.appendChild(ripple);
    setTimeout(() => ripple.remove(), 650);
  });
});

// Inject ripple keyframe
const style = document.createElement('style');
style.textContent = `@keyframes rippleAnim{to{transform:scale(2.5);opacity:0;}}`;
document.head.appendChild(style);

/* ── Timeline card reveal stagger ── */
const tlObs = new IntersectionObserver((entries) => {
  entries.forEach((e, i) => {
    if (e.isIntersecting) {
      setTimeout(() => e.target.classList.add('visible'), i * 120);
      tlObs.unobserve(e.target);
    }
  });
}, { threshold: 0.15 });
document.querySelectorAll('.tl-item').forEach(el => tlObs.observe(el));

/* ── Ring SVG animated on enter ── */
const ringObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const arc = e.target.querySelector('.ring-arc');
      if (arc) arc.style.animationPlayState = 'running';
    }
  });
}, { threshold: 0.4 });
document.querySelectorAll('.co2-ring-wrap').forEach(el => ringObs.observe(el));

/* ── Pledge box — promise items hover pulse ── */
document.querySelectorAll('.pp-item').forEach((item, i) => {
  item.style.transitionDelay = `${i * 0.06}s`;
});

/* ── Smooth anchor scrolling ── */
document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', e => {
    const target = document.querySelector(link.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

/* ── Parallax on hero visual ── */
window.addEventListener('scroll', () => {
  const visual = document.querySelector('.sus-hero-visual');
  if (visual) {
    const scrolled = window.scrollY;
    visual.style.transform = `translateY(${scrolled * 0.12}px)`;
  }
});

/* ── Keyboard: close mobile menu on Escape ── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.getElementById('mobileMenu')?.classList.add('hidden');
});

/* ── SL context facts — animated entry ── */
const factObs = new IntersectionObserver((entries) => {
  entries.forEach((e, idx) => {
    if (e.isIntersecting) {
      const delay = parseFloat(e.target.style.getPropertyValue('--delay') || '0');
      setTimeout(() => e.target.classList.add('visible'), delay * 1000);
      factObs.unobserve(e.target);
    }
  });
}, { threshold: 0.15 });
document.querySelectorAll('.slc-fact').forEach(el => factObs.observe(el));

/* ── Impact card bar fills on view ── */
const impactObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.querySelectorAll('.isg-bar-fill').forEach(bar => {
        const target = bar.style.width;
        bar.style.width = '0';
        requestAnimationFrame(() => {
          setTimeout(() => { bar.style.width = target; }, 100);
        });
      });
      impactObs.unobserve(e.target);
    }
  });
}, { threshold: 0.25 });
document.querySelectorAll('.impact-dashboard').forEach(el => impactObs.observe(el));

/* ── Active nav link highlight ── */
const sections = document.querySelectorAll('section[id]');
const navLinks = document.querySelectorAll('.nav-links a[href^="#"]');
const sectionObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      navLinks.forEach(a => {
        a.classList.toggle('nav-active', a.getAttribute('href') === `#${e.target.id}`);
      });
    }
  });
}, { threshold: 0.45 });
sections.forEach(s => sectionObs.observe(s));

/* ── Leaf orb — pause animation on hover ── */
const leafOrb = document.querySelector('.leaf-orb');
if (leafOrb) {
  leafOrb.addEventListener('mouseenter', () => {
    leafOrb.querySelectorAll('.leaf-ring').forEach(r => r.style.animationPlayState = 'paused');
  });
  leafOrb.addEventListener('mouseleave', () => {
    leafOrb.querySelectorAll('.leaf-ring').forEach(r => r.style.animationPlayState = 'running');
  });
}

console.log('%c 🌿 BizLink Sustainability ', 'background:#50C878;color:#050a1a;font-size:13px;padding:5px 14px;border-radius:6px;font-weight:800;');
console.log('%c Building greener businesses in Sri Lanka ', 'color:#50C878;font-size:11px;');