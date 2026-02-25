//Navbar scroll effect
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  if (window.scrollY > 60) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

//Hamburger menu
const hamburger = document.getElementById('hamburger');
const navLinks = document.querySelector('.nav-links');
const navCta = document.querySelector('.nav-cta');

hamburger.addEventListener('click', () => {
  navLinks.classList.toggle('open');
  navCta.classList.toggle('open');
  hamburger.classList.toggle('open');
});

//Scroll Reveal
const revealElements = document.querySelectorAll(
  '.crm-benefit-item, .prob-card, .sol-card, .vendor-feat, .cust-benefit-card, .role-card, .s-pill, .sf-item, .vs-card, .industry-card, .trust-item, .prob-cards, .section-title, .section-sub, .section-label'
);

revealElements.forEach(el => {
  el.classList.add('scroll-reveal');
});

const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const delay = entry.target.dataset.delay || 0;
      setTimeout(() => {
        entry.target.classList.add('visible');
      }, parseInt(delay));
    }
  });
}, {
  threshold: 0.12,
  rootMargin: '0px 0px -40px 0px'
});

revealElements.forEach(el => revealObserver.observe(el));

//Counter Animation
const counters = document.querySelectorAll('.stat-num');

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const target = parseInt(entry.target.dataset.target);
      animateCounter(entry.target, target);
      counterObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.5 });

counters.forEach(counter => counterObserver.observe(counter));

function animateCounter(el, target) {
  let start = 0;
  const duration = 2000;
  const step = target / (duration / 16);

  const tick = () => {
    start += step;
    if (start >= target) {
      el.textContent = target.toLocaleString();
    } else {
      el.textContent = Math.floor(start).toLocaleString();
      requestAnimationFrame(tick);
    }
  };
  requestAnimationFrame(tick);
}

//Staggered Reveal for Grid Items
const gridSections = [
  { parent: '.crm-benefits', child: '.crm-benefit-item', delay: 80 },
  { parent: '.prob-cards', child: '.prob-card', delay: 100 },
  { parent: '.sol-cards', child: '.sol-card', delay: 80 },
  { parent: '.vendor-features', child: '.vendor-feat', delay: 70 },
  { parent: '.customer-benefits-grid', child: '.cust-benefit-card', delay: 90 },
  { parent: '.roles-grid', child: '.role-card', delay: 120 },
  { parent: '.suitable-pill-grid', child: '.s-pill', delay: 40 },
];

gridSections.forEach(({ parent, child, delay }) => {
  const parentEl = document.querySelector(parent);
  if (!parentEl) return;

  const children = parentEl.querySelectorAll(child);
  const parentObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        children.forEach((child, i) => {
          setTimeout(() => {
            child.classList.add('visible');
          }, i * delay);
        });
        parentObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  parentObserver.observe(parentEl);
});

//Active Nav Link Highlight
const sections = document.querySelectorAll('section[id]');
const navAncors = document.querySelectorAll('.nav-links a');

window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(section => {
    const top = section.offsetTop - 100;
    if (window.scrollY >= top) {
      current = section.getAttribute('id');
    }
  });

  navAncors.forEach(a => {
    a.style.color = '';
    a.style.background = '';
    if (a.getAttribute('href') === `#${current}`) {
      a.style.color = 'var(--text-primary)';
      a.style.background = 'var(--bg-card)';
    }
  });
});

//Parallax Orbs on Mouse Move
document.addEventListener('mousemove', (e) => {
  const { innerWidth, innerHeight } = window;
  const xRatio = (e.clientX / innerWidth - 0.5) * 30;
  const yRatio = (e.clientY / innerHeight - 0.5) * 30;

  const orbs = document.querySelectorAll('.orb');
  orbs.forEach((orb, i) => {
    const factor = (i + 1) * 0.4;
    orb.style.transform = `translate(${xRatio * factor}px, ${yRatio * factor}px)`;
  });

  // Float badges parallax
  const badges = document.querySelectorAll('.float-badge');
  badges.forEach((badge, i) => {
    const factor = (i + 1) * 0.2;
    badge.style.transform = `translate(${xRatio * factor}px, ${yRatio * factor}px)`;
  });
});

//Animate chart bars on load
window.addEventListener('load', () => {
  const bars = document.querySelectorAll('.bar');
  bars.forEach((bar, i) => {
    setTimeout(() => {
      bar.style.opacity = '1';
    }, 1200 + i * 100);
  });
});

//Smooth CTA hover ripple
document.querySelectorAll('.btn-hero-primary, .btn-cta-vendor, .btn-cta-explore, .btn-cta-contact, .role-cta').forEach(btn => {
  btn.addEventListener('mouseenter', function(e) {
    const ripple = document.createElement('span');
    ripple.style.cssText = `
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      width: 0; height: 0;
      left: ${e.offsetX}px;
      top: ${e.offsetY}px;
      transform: translate(-50%, -50%);
      animation: rippleOut 0.6s ease forwards;
      pointer-events: none;
    `;
    this.style.position = 'relative';
    this.style.overflow = 'hidden';
    this.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
  });
});

// Inject ripple keyframe
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
  @keyframes rippleOut {
    to {
      width: 300px;
      height: 300px;
      opacity: 0;
    }
  }
`;
document.head.appendChild(rippleStyle);

//Typing effect for Hero title
function setupTypingBadge() {
  const badge = document.querySelector('.hero-badge');
  if (!badge) return;

  const texts = [
    '🇱🇰 &nbsp;Designed for Sri Lanka',
    '⚡ &nbsp;Powered for SMEs',
    '🔗 &nbsp;Connect. Grow. Succeed.',
    '🌐 &nbsp;Go Digital Today',
  ];
  let idx = 0;

  setInterval(() => {
    idx = (idx + 1) % texts.length;
    badge.style.opacity = '0';
    badge.style.transform = 'translateY(4px)';
    badge.style.transition = 'all 0.3s';
    setTimeout(() => {
      badge.innerHTML = `<span class="badge-dot"></span> ${texts[idx]}`;
      badge.style.opacity = '1';
      badge.style.transform = 'translateY(0)';
    }, 350);
  }, 3000);
}
setupTypingBadge();

//Image placeholder hover text
document.querySelectorAll('.img-placeholder, .role-img-placeholder, .suitable-img-placeholder').forEach(el => {
  el.addEventListener('mouseenter', () => {
    el.style.cursor = 'pointer';
  });
});

//Close mobile nav on link click
document.querySelectorAll('.nav-links a').forEach(link => {
  link.addEventListener('click', () => {
    navLinks.classList.remove('open');
    navCta.classList.remove('open');
  });
});

//Section fade on first load
document.body.style.opacity = '0';
document.body.style.transition = 'opacity 0.4s';
window.addEventListener('load', () => {
  document.body.style.opacity = '1';
});

console.log('%c BizLink CRM Platform 🇱🇰 ', 'background: #50C878; color: white; font-size: 14px; padding: 8px 16px; border-radius: 4px;');
console.log('%c Built for Sri Lankan SMEs ', 'color: #FF8C00; font-size: 12px;');