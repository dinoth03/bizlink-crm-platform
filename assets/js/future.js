/*BIZLINK FUTURE PAGE — future.js*/

/*NEURAL NETWORK CANVAS*/
(function initNeuralCanvas() {
  const canvas = document.getElementById('neuralCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, nodes = [], animFrame;

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', () => { resize(); initNodes(); });

  function initNodes() {
    nodes = [];
    const count = Math.floor((W * H) / 22000);
    for (let i = 0; i < count; i++) {
      nodes.push({
        x: Math.random() * W,
        y: Math.random() * H,
        vx: (Math.random() - 0.5) * 0.4,
        vy: (Math.random() - 0.5) * 0.4,
        r: Math.random() * 1.8 + 0.8,
      });
    }
  }
  initNodes();

  const CONN_DIST = 160;
  const COLORS = ['rgba(80,200,120,', 'rgba(79,140,255,', 'rgba(255,140,0,'];

  function draw() {
    ctx.clearRect(0, 0, W, H);
    // Move
    nodes.forEach(n => {
      n.x += n.vx; n.y += n.vy;
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
    });
    // Connections
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x;
        const dy = nodes[i].y - nodes[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < CONN_DIST) {
          const alpha = (1 - dist / CONN_DIST) * 0.18;
          const col = COLORS[Math.floor((i + j) % 3)];
          ctx.strokeStyle = col + alpha + ')';
          ctx.lineWidth = 0.7;
          ctx.beginPath();
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.stroke();
        }
      }
    }
    // Nodes
    nodes.forEach((n, i) => {
      const col = COLORS[i % 3];
      ctx.fillStyle = col + '0.5)';
      ctx.beginPath();
      ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
      ctx.fill();
    });
    animFrame = requestAnimationFrame(draw);
  }
  draw();
})();

/*CUSTOM CURSOR TRAIL*/
(function initCursor() {
  const trail = document.getElementById('cursorTrail');
  if (!trail) return;
  let mx = -100, my = -100, cx = -100, cy = -100;

  document.addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; });

  function animCursor() {
    cx += (mx - cx) * 0.12;
    cy += (my - cy) * 0.12;
    trail.style.left = (cx - 9) + 'px';
    trail.style.top  = (cy - 9) + 'px';
    requestAnimationFrame(animCursor);
  }
  animCursor();
})();

/*NAVBAR SCROLL*/
const navbar = document.getElementById('navbar');
const scrollTopBtn = document.getElementById('scrollTop');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 40);
  scrollTopBtn?.classList.toggle('visible', window.scrollY > 500);
});

/* MOBILE MENU*/
function toggleMobileMenu() {
  document.getElementById('mobileMenu')?.classList.toggle('hidden');
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.getElementById('mobileMenu')?.classList.add('hidden');
});

/*SCROLL REVEAL*/
const revealObs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObs.unobserve(e.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
document.querySelectorAll('.scroll-reveal').forEach(el => revealObs.observe(el));

/* HERO STAT COUNTERS*/
const HERO_STATS = [
  { id: 'stat1', target: 262,  suffix: 'B', decimals: 0 },
  { id: 'stat2', target: 40,   suffix: '%', decimals: 0 },
  { id: 'stat3', target: 40,   suffix: '%', decimals: 0 },
  { id: 'stat4', target: 300,  suffix: 'B', decimals: 0 },
];

function animCount(el, target, duration = 1800, decimals = 0) {
  let start = null;
  const step = ts => {
    if (!start) start = ts;
    const p = Math.min((ts - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    const val = ease * target;
    el.textContent = decimals > 0 ? val.toFixed(decimals) : Math.floor(val).toLocaleString();
    if (p < 1) requestAnimationFrame(step);
    else el.textContent = target.toLocaleString();
  };
  requestAnimationFrame(step);
}

const heroStripObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      HERO_STATS.forEach(({ id, target, decimals }) => {
        const el = document.getElementById(id);
        if (el) setTimeout(() => animCount(el, target, 1800, decimals), 200);
      });
      heroStripObs.unobserve(e.target);
    }
  });
}, { threshold: 0.4 });
const heroStrip = document.querySelector('.hero-stats-strip');
if (heroStrip) heroStripObs.observe(heroStrip);

/*AI TERMINAL ANIMATION*/
const TERMINAL_LINES = [
  { prefix: '$ ',  text: 'bizlink-ai init --mode=autonomous',         type: 'action',  delay: 0 },
  { prefix: '  ',  text: 'Loading BizLink AI Core v3.0.1...',         type: 'data',    delay: 600 },
  { prefix: '✓ ',  text: 'LLM Engine initialised — SL Language Model',type: 'success', delay: 1200 },
  { prefix: '✓ ',  text: 'Agent Factory: 5 agents online',            type: 'success', delay: 1800 },
  { prefix: '→ ',  text: 'Scanning pipeline for intent signals...',    type: 'action',  delay: 2400 },
  { prefix: '  ',  text: 'Signal: Amara Perera visited /pricing 3×',  type: 'data',    delay: 3000 },
  { prefix: '🧬 ', text: 'Intent score: 94% — HIGH PURCHASE INTENT', type: 'warn',    delay: 3500 },
  { prefix: '✍️ ', text: 'Drafting personalised outreach email...',    type: 'action',  delay: 4000 },
  { prefix: '✓ ',  text: 'Email generated in 0.8s — tone: warm/professional', type: 'success', delay: 4600 },
  { prefix: '🚀 ', text: 'Email sent to amara.perera@gmail.com',      type: 'success', delay: 5000 },
  { prefix: '✓ ',  text: 'CRM record updated — stage: Outreach Sent', type: 'success', delay: 5400 },
  { prefix: '📅 ', text: 'Follow-up scheduled: +3 days at 10:00 AM', type: 'data',    delay: 5800 },
  { prefix: '→ ',  text: 'Scanning for next high-priority signal...',  type: 'action',  delay: 6400 },
  { prefix: '  ',  text: 'Signal: Order #BL-9940 delivery delayed',   type: 'warn',    delay: 7000 },
  { prefix: '✓ ',  text: 'Auto-notification sent to Suresh Nimal',    type: 'success', delay: 7500 },
  { prefix: '📊 ', text: 'Daily summary: 12 tasks completed autonomously', type: 'success', delay: 8000 },
  { prefix: '$ ',  text: '',                                           type: 'cursor',  delay: 8600 },
];

let terminalStarted = false;
const termObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting && !terminalStarted) {
      terminalStarted = true;
      runTerminal();
      termObs.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });
const termEl = document.getElementById('terminalBody');
if (termEl) termObs.observe(termEl);

function runTerminal() {
  const body = document.getElementById('terminalBody');
  if (!body) return;
  body.innerHTML = '';

  TERMINAL_LINES.forEach(({ prefix, text, type, delay }) => {
    setTimeout(() => {
      const line = document.createElement('div');
      line.className = 't-line';
      if (type === 'cursor') {
        line.innerHTML = `<span class="t-prefix">${prefix}</span><span class="t-cursor"></span>`;
      } else {
        const cls = type === 'action'  ? 't-action'  :
                    type === 'success' ? 't-success'  :
                    type === 'warn'    ? 't-warn'     : 't-data';
        line.innerHTML = `<span class="t-prefix">${prefix}</span><span class="${cls}">${text}</span>`;
      }
      body.appendChild(line);
      body.scrollTop = body.scrollHeight;
    }, delay);
  });

  // Restart loop
  setTimeout(() => {
    terminalStarted = false;
    termObs.observe(document.getElementById('terminalBody'));
  }, 14000);
}

/*COMPARE TABLE DATA*/
const COMPARE_DATA = [
  { feature: 'Lead Management',          now: 'Manual entry & tracking',          future: '🤖 Autonomous AI agents qualify & route' },
  { feature: 'Customer Outreach',         now: 'Email written by sales rep',        future: '✨ Generative AI drafts & sends 24/7' },
  { feature: 'Sales Forecasting',         now: 'Monthly reports & spreadsheets',    future: '🔮 Real-time predictive pipeline AI' },
  { feature: 'Interface',                 now: 'Web dashboard & admin panel',       future: '🥽 Web + iOS + Android + AR/VR + Voice' },
  { feature: 'Language Support',          now: 'English + Sinhala labels',          future: '🌐 Full AI in Sinhala, Tamil & English' },
  { feature: 'Data Entry',                now: 'Manual form filling',               future: '📸 Camera-to-CRM, IoT auto-sync' },
  { feature: 'Customer Insights',         now: 'Historical reports',                future: '⚡ Real-time behavioural intent signals' },
  { feature: 'Vendor Scoring',            now: 'Manual green certification',        future: '🏭 Auto ESG scoring from IoT + AI' },
  { feature: 'Support',                   now: 'Human chat + email',               future: '🤖 AI agent handles 80% autonomously' },
  { feature: 'Security',                  now: 'Password + 2FA auth',              future: '🛡️ Biometric + post-quantum encryption' },
  { feature: 'Payments',                  now: 'Card & QR via web',                future: '💳 App + wearable + voice payment' },
  { feature: 'Analytics Depth',           now: 'Dashboards & KPI cards',           future: '🧬 Digital twin of your entire business' },
];

function buildCompareTable() {
  const container = document.getElementById('compareRows');
  if (!container) return;
  container.innerHTML = COMPARE_DATA.map((row, i) => `
    <div class="ct-row" style="animation-delay:${i * 0.04}s">
      <div class="cr-feature">${row.feature}</div>
      <div class="cr-now">${row.now}</div>
      <div class="cr-future"><span class="cr-chip">${row.future}</span></div>
    </div>
  `).join('');
}
buildCompareTable();

/* PILLAR CARD 3D TILT*/
document.querySelectorAll('.ai-cap-card, .wf-card, .pillar-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const rect = card.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width  - 0.5;
    const y = (e.clientY - rect.top)  / rect.height - 0.5;
    card.style.transform = `translateY(-10px) rotateX(${y * -5}deg) rotateY(${x * 6}deg)`;
    card.style.transition = 'box-shadow 0.15s, border-color 0.35s';
  });
  card.addEventListener('mouseleave', () => {
    card.style.transform = '';
    card.style.transition = 'all 0.4s cubic-bezier(0.22,1,0.36,1)';
  });
});

/*HEX GRID RIPPLE*/
document.querySelectorAll('.thg-item').forEach(item => {
  item.addEventListener('click', e => {
    const r = document.createElement('span');
    const rect = item.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height) * 2;
    r.style.cssText = `
      position:absolute;border-radius:50%;pointer-events:none;z-index:10;
      width:${size}px;height:${size}px;
      top:${e.clientY - rect.top - size/2}px;
      left:${e.clientX - rect.left - size/2}px;
      background:rgba(var(--c-rgb,80,200,120),0.15);
      transform:scale(0);animation:hexRipple 0.7s ease-out forwards;
    `;
    item.style.position = 'relative';
    item.style.overflow = 'hidden';
    item.appendChild(r);
    setTimeout(() => r.remove(), 720);
  });
});
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `@keyframes hexRipple{to{transform:scale(1);opacity:0;}}`;
document.head.appendChild(rippleStyle);

/*ROADMAP PROGRESS LINE ANIMATE*/
const roadmapObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const fill = e.target.querySelector('.rtpl-fill');
      if (fill) fill.style.animationPlayState = 'running';
    }
  });
}, { threshold: 0.2 });
document.querySelectorAll('.roadmap-track').forEach(el => roadmapObs.observe(el));

/*ARCHITECTURE LAYER HOVER GLOW*/
document.querySelectorAll('.arch-layer').forEach(layer => {
  layer.addEventListener('mouseenter', () => {
    layer.querySelectorAll('.ali').forEach((ali, i) => {
      setTimeout(() => ali.classList.add('visible'), i * 40);
    });
  });
});

/*PHONE MOCKUP SCREEN TABS*/
const screens = ['📊 Stats', '💬 Chat', '🤖 AI', '📦 Orders', '🌿 ESG'];
let activeScreen = 0;
setInterval(() => {
  const navItems = document.querySelectorAll('.bms-ni');
  if (!navItems.length) return;
  navItems[activeScreen]?.classList.remove('active');
  activeScreen = (activeScreen + 1) % navItems.length;
  navItems[activeScreen]?.classList.add('active');
}, 2800);

/*PHONE BOTTOM NAV CYCLE*/
let activeNav = 0;
setInterval(() => {
  const navs = document.querySelectorAll('.bnav');
  if (!navs.length) return;
  navs[activeNav]?.classList.remove('active');
  activeNav = (activeNav + 1) % navs.length;
  navs[activeNav]?.classList.add('active');
}, 3200);

/*IMPACT COUNTERS (general)*/
const generalObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.querySelectorAll('[data-count]').forEach(el => {
        animCount(el, parseInt(el.dataset.count), 1800);
        el.removeAttribute('data-count');
      });
      generalObs.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });
document.querySelectorAll('.section').forEach(s => generalObs.observe(s));

/*SMOOTH ANCHOR SCROLL*/
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

/*HERO VISUAL — Data nodes auto-animate*/
(function animDataNodes() {
  const nodes = document.querySelectorAll('.data-node');
  let idx = 0;
  setInterval(() => {
    nodes.forEach(n => n.style.opacity = '0.5');
    nodes[idx]?.style.setProperty('opacity', '1');
    nodes[idx]?.style.setProperty('transform', 'scale(1.15)');
    setTimeout(() => {
      nodes[idx]?.style.setProperty('transform', '');
    }, 600);
    idx = (idx + 1) % nodes.length;
  }, 1200);
})();

/*ORBS MOUSE PARALLAX*/
(function orbParallax() {
  const orbs = document.querySelectorAll('.global-orbs .orb');
  document.addEventListener('mousemove', e => {
    const xPct = (e.clientX / window.innerWidth  - 0.5) * 2;
    const yPct = (e.clientY / window.innerHeight - 0.5) * 2;
    orbs.forEach((orb, i) => {
      const factor = (i + 1) * 10;
      orb.style.transform = `translate(${xPct * factor}px, ${yPct * factor}px)`;
    });
  });
})();

/*FLOW STEP ACTIVE CYCLE*/
(function flowCycle() {
  const steps = document.querySelectorAll('.fs-step');
  if (!steps.length) return;
  let curr = 0;
  steps[curr].classList.add('active-step');
  setInterval(() => {
    steps[curr].classList.remove('active-step');
    curr = (curr + 1) % steps.length;
    steps[curr].classList.add('active-step');
  }, 1500);
})();

/*TECH HEX — Staggered entry on scroll*/
const hexObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.querySelectorAll('.thg-item').forEach((item, i) => {
        setTimeout(() => {
          item.style.opacity = '1';
          item.style.transform = 'translateY(0) scale(1)';
        }, i * 80);
      });
      hexObs.unobserve(e.target);
    }
  });
}, { threshold: 0.2 });
document.querySelectorAll('.tech-hex-grid').forEach(el => {
  el.querySelectorAll('.thg-item').forEach(item => {
    item.style.opacity = '0';
    item.style.transform = 'translateY(20px) scale(0.92)';
    item.style.transition = 'all 0.5s cubic-bezier(0.22,1,0.36,1)';
  });
  hexObs.observe(el);
});

/*FLOATING VCTA BADGES*/
(function vctaBadgeFloat() {
  const badges = document.querySelectorAll('.vcf-badge');
  badges.forEach((b, i) => {
    b.style.animationDelay = `${i * 0.5}s`;
  });
})();

/*BROWSER MOCKUP — Typing loop*/
(function browserTyping() {
  const msgs = [
    'Found 5 hot leads. Should I reach out?',
    'Revenue up 12% — forecast upgraded.',
    '3 orders need fulfilment today.',
    'Vendor GreenFarm SL rated 5 stars!',
    'AI drafted 8 follow-up emails.',
  ];
  const el = document.querySelector('.typing-anim span:not(.t-dot)');
  if (!el) return;
  let mi = 0;
  setInterval(() => {
    mi = (mi + 1) % msgs.length;
    if (el) el.textContent = msgs[mi];
  }, 4000);
})();

/*COMPARE TABLE — Row hover glow*/
document.addEventListener('mouseover', e => {
  const row = e.target.closest('.ct-row');
  if (row) {
    row.querySelectorAll('.cr-chip').forEach(c => {
      c.style.background = 'rgba(80,200,120,0.15)';
      c.style.borderColor = 'rgba(80,200,120,0.3)';
    });
  }
});
document.addEventListener('mouseout', e => {
  const row = e.target.closest('.ct-row');
  if (row) {
    row.querySelectorAll('.cr-chip').forEach(c => {
      c.style.background = '';
      c.style.borderColor = '';
    });
  }
});

/* ══════════════════════════════════
   CONSOLE SIGNATURE
══════════════════════════════════ */
console.log('%c ⚡ BizLink — The Future of CRM ⚡ ', 'background:linear-gradient(135deg,#000080,#50C878);color:#fff;font-size:14px;padding:6px 18px;border-radius:6px;font-weight:800;');
console.log('%c AI Agents · Mobile App · Web 3.0 · XR · 2030 Vision ', 'color:#50C878;font-size:11px;letter-spacing:0.05em;');
console.log('%c Built for Sri Lanka 🇱🇰 · bizlink.lk ', 'color:#4f8cff;font-size:11px;');