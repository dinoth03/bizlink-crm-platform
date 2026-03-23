/* BIZLINK – VENDOR DASHBOARD JS */
// NAVIGATION

function navigate(e, el, page) {
  if (e && e.preventDefault) e.preventDefault();
  document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('pageTitle').textContent = el.querySelector('.nav-label').textContent;
  showPage(page);
}

function goToPage(page) {
  const el = document.querySelector(`[data-page="${page}"]`);
  if (el) { el.click(); }
}

function showPage(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('page-' + page);
  if (target) {
    target.classList.add('active');
    onPageActivate(page);
  }
}

function onPageActivate(page) {
  if (page === 'dashboard')  { animateCounters(); initDashCharts(); renderRecentOrders(); }
  if (page === 'products')   { renderProducts(); }
  if (page === 'orders')     { renderOrders('all'); }
  if (page === 'customers')  { renderCustomers(); }
  if (page === 'analytics')  { initAnalyticsCharts(); renderBestSellers(); }
  if (page === 'payments')   { renderTransactions(); }
  if (page === 'reviews')    { renderReviews(); }
  if (page === 'promotions') { startCountdown(); }
}

// SIDEBAR TOGGLE 
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const btn = document.getElementById('menuToggle');
  sidebar.classList.toggle('open');
  btn.classList.toggle('open');
}

// Close sidebar on outside click (mobile)
document.addEventListener('click', (e) => {
  const sidebar = document.getElementById('sidebar');
  const btn = document.getElementById('menuToggle');
  if (window.innerWidth <= 840 && sidebar.classList.contains('open')) {
    if (!sidebar.contains(e.target) && !btn.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  }
});

// NOTIFICATION PANEL

function toggleNotif() {
  document.getElementById('notifPanel').classList.toggle('open');
}
document.addEventListener('click', (e) => {
  const wrap = document.querySelector('.notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('notifPanel').classList.remove('open');
  }
});


// COUNTER ANIMATION (same as home.js animateCounter)

let COUNTERS = { s1: { target:0, prefix:'Rs. ' }, s2: { target:0, prefix:'' }, s3: { target:0, prefix:'' }, s4: { target:0, prefix:'Rs. ' }, s5: { target:0, prefix:'Rs. ' } };
let countersRun = false;

function setCounterTargets(stats) {
  COUNTERS = {
    s1: { target: stats.todaySales, prefix: 'Rs. ' },
    s2: { target: stats.totalOrders, prefix: '' },
    s3: { target: stats.pendingOrders, prefix: '' },
    s4: { target: stats.totalEarnings, prefix: 'Rs. ' },
    s5: { target: stats.monthlyRevenue, prefix: 'Rs. ' }
  };
  countersRun = false;
}

function animateCounters() {
  if (countersRun) return;
  countersRun = true;
  Object.entries(COUNTERS).forEach(([id, { target, prefix }]) => {
    const el = document.getElementById(id);
    if (!el) return;
    let start = 0;
    const duration = 2000;
    const step = target / (duration / 16);
    const tick = () => {
      start += step;
      if (start >= target) {
        el.textContent = prefix + target.toLocaleString();
      } else {
        el.textContent = prefix + Math.floor(start).toLocaleString();
        requestAnimationFrame(tick);
      }
    };
    requestAnimationFrame(tick);
  });
}


// CHART.JS CHARTS
// BizLink colour palette
const VENDOR_GREEN   = '#50C878';
const VENDOR_DARK    = '#3d8c5f';
const ACCENT_BLUE    = '#4f8cff';
const ACCENT_ORANGE  = '#FF8C00';
const ACCENT_RED     = '#ff6b6b';
const CHART_GRID     = 'rgba(255,255,255,0.05)';
const CHART_TICK     = '#5a6a8a';

function tooltipDefaults() {
  return {
    backgroundColor: 'rgba(12,20,40,0.95)',
    borderColor: 'rgba(80,200,120,0.3)',
    borderWidth: 1,
    titleColor: VENDOR_GREEN,
    bodyColor: '#f0f4ff',
    padding: 12,
    cornerRadius: 10,
  };
}
function scaleDefaults(prefix = '') {
  return {
    x: { grid: { color: CHART_GRID }, ticks: { color: CHART_TICK, font: { family: "'DM Sans', sans-serif", size: 11 } } },
    y: { grid: { color: CHART_GRID }, ticks: { color: CHART_TICK, font: { family: "'DM Sans', sans-serif", size: 11 }, callback: v => prefix + v.toLocaleString() } }
  };
}

let chartsCreated = {};

function initDashCharts() {
  if (chartsCreated.dash) return;
  chartsCreated.dash = true;

  const last7Labels = [];
  const last7Data = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().split('T')[0];
    last7Labels.push(d.toLocaleDateString('en-US', { weekday: 'short' }));
    const dayTotal = dashboardData.orders
      .filter((o) => o.date === key && o.status !== 'cancelled')
      .reduce((sum, o) => sum + o.amount, 0);
    last7Data.push(dayTotal);
  }

  const statusMap = { delivered: 0, pending: 0, cancelled: 0, processing: 0 };
  dashboardData.orders.forEach((o) => {
    if (o.status === 'shipped') statusMap.processing += 1;
    else if (statusMap[o.status] !== undefined) statusMap[o.status] += 1;
  });
  const statusTotal = Object.values(statusMap).reduce((a, b) => a + b, 0) || 1;
  const donutData = [
    Math.round((statusMap.delivered / statusTotal) * 100),
    Math.round((statusMap.pending / statusTotal) * 100),
    Math.round((statusMap.cancelled / statusTotal) * 100),
    Math.round((statusMap.processing / statusTotal) * 100)
  ];

  // Sales line chart
  const sCtx = document.getElementById('salesChart');
  if (sCtx) {
    new Chart(sCtx, {
      type: 'line',
      data: {
        labels: last7Labels,
        datasets: [{
          label: 'Sales (Rs.)',
          data: last7Data,
          borderColor: VENDOR_GREEN,
          backgroundColor: 'rgba(80,200,120,0.08)',
          borderWidth: 2.5,
          pointBackgroundColor: VENDOR_GREEN,
          pointBorderColor: '#050a1a',
          pointBorderWidth: 2,
          pointRadius: 5,
          pointHoverRadius: 8,
          fill: true,
          tension: 0.45,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false }, tooltip: { ...tooltipDefaults(), callbacks: { label: c => `  Rs. ${c.raw.toLocaleString()}` } } },
        scales: scaleDefaults('Rs. ')
      }
    });
  }

  // Donut
  const dCtx = document.getElementById('donutChart');
  if (dCtx) {
    new Chart(dCtx, {
      type: 'doughnut',
      data: {
        labels: ['Delivered','Pending','Cancelled','Processing'],
        datasets: [{ data: donutData, backgroundColor: [VENDOR_GREEN, ACCENT_ORANGE, ACCENT_RED, ACCENT_BLUE], borderWidth: 0, hoverOffset: 8 }]
      },
      options: {
        cutout: '70%',
        plugins: { legend: { display: false }, tooltip: { ...tooltipDefaults(), callbacks: { label: c => `  ${c.label}: ${c.raw}%` } } },
        animation: { animateRotate: true, duration: 1200 }
      }
    });
  }
}

function initAnalyticsCharts() {
  if (chartsCreated.analytics) return;
  chartsCreated.analytics = true;

  const mCtx = document.getElementById('monthlyChart');
  if (mCtx) {
    new Chart(mCtx, {
      type: 'bar',
      data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{
          label: 'Revenue (Rs.)',
          data: [620000,892400,0,0,0,0,0,0,0,0,0,0],
          backgroundColor: 'rgba(80,200,120,0.2)',
          borderColor: VENDOR_GREEN,
          borderWidth: 1.5,
          borderRadius: 8,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { ...tooltipDefaults(), callbacks: { label: c => `  Rs. ${c.raw.toLocaleString()}` } } },
        scales: scaleDefaults('Rs. ')
      }
    });
  }

  const distCtx = document.getElementById('districtChart');
  if (distCtx) {
    new Chart(distCtx, {
      type: 'bar',
      data: {
        labels: ['Colombo','Gampaha','Kandy','Galle','Matara','Jaffna'],
        datasets: [{
          data: [42,22,14,9,7,6],
          backgroundColor: [VENDOR_GREEN,'rgba(80,200,120,0.6)','rgba(80,200,120,0.45)','rgba(80,200,120,0.35)','rgba(80,200,120,0.25)','rgba(80,200,120,0.15)'],
          borderWidth: 0,
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        plugins: { legend: { display: false }, tooltip: { ...tooltipDefaults(), callbacks: { label: c => `  ${c.raw}%` } } },
        scales: {
          x: { grid: { color: CHART_GRID }, ticks: { color: CHART_TICK, callback: v => v + '%' } },
          y: { grid: { display: false }, ticks: { color: CHART_TICK, font: { family: "'DM Sans', sans-serif", size: 11 } } }
        }
      }
    });
  }
}

// Chart tab toggle
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('ctab')) {
    e.target.closest('.chart-tabs').querySelectorAll('.ctab').forEach(t => t.classList.remove('active'));
    e.target.classList.add('active');
  }
});

// DATA

const DISTRICTS = ['Colombo','Gampaha','Kandy','Galle','Matara','Jaffna','Kurunegala','Ratnapura','Kalutara','Anuradhapura'];
const CUSTOMER_NAMES = ['Nimali Perera','Kasun Fernando','Kavya Rajapaksa','Thilini Dissanayake','Amal Bandara','Sanjaya Silva','Pooja Wickramasinghe','Ravindu Gunawardena','Chamari Herath','Dinesh Jayawardena','Hasini Ranaweera','Tharindu Samarasinghe'];

// Sri Lankan product photos (Unsplash free-to-use)
const PRODUCTS = [
  { name:'Ceylon Black Tea 500g', emoji:'🍵', price:850, stock:142, sku:'TEA-001', discount:10, status:'active',
    img:'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=400&q=80' },
  { name:'Coconut Oil 1L', emoji:'🥥', price:1200, stock:3, sku:'COC-002', discount:0, status:'active',
    img:'https://images.unsplash.com/photo-1526887520775-4b14b8aed897?w=400&q=80' },
  { name:'Handmade Batik Sarong', emoji:'🎨', price:3500, stock:28, sku:'BAT-003', discount:15, status:'active',
    img:'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&q=80' },
  { name:'Cinnamon Sticks 200g', emoji:'🌿', price:450, stock:0, sku:'CIN-004', discount:0, status:'out',
    img:'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?w=400&q=80' },
  { name:'Ayurvedic Body Oil', emoji:'🧴', price:2200, stock:67, sku:'AYU-005', discount:20, status:'active',
    img:'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?w=400&q=80' },
  { name:'Cardamom 100g', emoji:'🌱', price:680, stock:89, sku:'CAR-006', discount:0, status:'active',
    img:'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=400&q=80' },
  { name:'Wooden Elephant Carving', emoji:'🐘', price:4800, stock:12, sku:'WOD-007', discount:5, status:'active',
    img:'https://images.unsplash.com/photo-1585016495481-91613b765117?w=400&q=80' },
  { name:'Lemongrass Soap Bar', emoji:'🧼', price:320, stock:0, sku:'SOP-008', discount:0, status:'out',
    img:'https://images.unsplash.com/photo-1612196808214-b7e239e5f6b8?w=400&q=80' },
];

// Customer photos (diverse portraits from Unsplash)
const CUSTOMER_PHOTOS = [
  'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&q=80',
  'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&q=80',
  'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&q=80',
  'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&q=80',
  'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&q=80',
  'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=100&q=80',
  'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&q=80',
  'https://images.unsplash.com/photo-1552058544-f2b08422138a?w=100&q=80',
  'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=100&q=80',
  'https://images.unsplash.com/photo-1547425260-76bcadfb4f2c?w=100&q=80',
  'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&q=80',
  'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=100&q=80',
];

const AVATAR_COLORS = ['#50C878','#4f8cff','#FF8C00','#a29bfe','#fd79a8','#74b9ff','#55efc4','#fdcb6e','#e17055','#81ecec','#636e72','#b2bec3'];

const ORDER_STATUSES = ['pending','processing','shipped','delivered','cancelled'];
const SAMPLE_ORDERS = Array.from({length:22}, (_, i) => ({
  id: 'LK-' + String(900 - i).padStart(5,'0'),
  customer: CUSTOMER_NAMES[i % CUSTOMER_NAMES.length],
  district: DISTRICTS[i % DISTRICTS.length],
  items: Math.floor(Math.random() * 5) + 1,
  amount: Math.floor(Math.random() * 15000) + 500,
  payment: i % 4 === 0 ? 'unpaid' : 'paid',
  status: ORDER_STATUSES[i % ORDER_STATUSES.length],
  date: `2026-02-${String(Math.max(1, 24 - i)).padStart(2,'0')}`
}));

const dashboardData = {
  vendors: [],
  orders: [],
  rawOrders: [],
  products: [],
  customers: [],
  activeVendor: null,
  notifications: [],
  loadedFromApi: false
};

function getVendorNotificationIcon(notification) {
  const type = notification.notification_type || 'system';
  if (type === 'order_status') return '🛒';
  if (type === 'payment') return '✅';
  if (type === 'review') return '⭐';
  if (type === 'message') return '💬';
  if (type === 'promotion') return '🎉';
  return '🔔';
}

function renderVendorNotifications() {
  const list = document.getElementById('vendorNotifList');
  const badge = document.querySelector('.notif-dot');
  if (!list) return;

  if (dashboardData.notifications.length === 0) {
    list.innerHTML = '<div class="notif-item">All caught up. No unread notifications.</div>';
    if (badge) badge.style.display = 'none';
    return;
  }

  list.innerHTML = dashboardData.notifications.map((notification) => `
    <div class="notif-item ${notification.is_read ? '' : 'is-new'}" onclick="markVendorNotificationRead(${Number(notification.notification_id || 0)})" style="cursor:${notification.is_read ? 'default' : 'pointer'};opacity:${notification.is_read ? '0.75' : '1'};">${getVendorNotificationIcon(notification)} <strong>${notification.title}</strong><br>${notification.message}</div>
  `).join('');

  if (badge) {
    const unreadCount = dashboardData.notifications.filter((notification) => !notification.is_read).length;
    badge.textContent = String(unreadCount);
    badge.style.display = unreadCount > 0 ? 'inline-flex' : 'none';
  }
}

async function markVendorNotificationRead(notificationId) {
  if (!notificationId) return;
  const activeVendor = dashboardData.activeVendor;
  if (!activeVendor) return;

  const target = dashboardData.notifications.find((notification) => Number(notification.notification_id) === Number(notificationId));
  if (!target || target.is_read) return;

  if (typeof markNotificationsRead === 'function') {
    const result = await markNotificationsRead({
      email: activeVendor.email,
      notification_id: Number(notificationId)
    });

    if (result && result.success) {
      target.is_read = true;
      renderVendorNotifications();
      return;
    }
  }

  target.is_read = true;
  renderVendorNotifications();
}

async function loadVendorNotifications(activeVendor) {
  if (!activeVendor || typeof getNotifications !== 'function') {
    dashboardData.notifications = [];
    renderVendorNotifications();
    return;
  }

  try {
    const response = await getNotifications({ email: activeVendor.email, limit: 5 });
    if (response) {
      dashboardData.notifications = response.data || [];
      renderVendorNotifications();
      return;
    }
  } catch (error) {
    console.error('Failed to load vendor notifications:', error);
  }

  dashboardData.notifications = [];
  renderVendorNotifications();
}

async function markVendorNotificationsRead() {
  const activeVendor = dashboardData.activeVendor;
  if (!activeVendor || typeof markNotificationsRead !== 'function') return;

  const result = await markNotificationsRead({
    email: activeVendor.email,
    mark_all: true
  });

  if (result && result.success) {
    dashboardData.notifications = [];
    renderVendorNotifications();
  }
}

function normalizeOrderStatus(status) {
  if (status === 'out_for_delivery') return 'shipped';
  if (status === 'returned') return 'cancelled';
  return status || 'pending';
}

function mapApiOrder(row) {
  return {
    id: row.order_number,
    customer: row.customer_name || 'Unknown Customer',
    customerEmail: (row.email || '').trim().toLowerCase(),
    district: row.city || 'Sri Lanka',
    items: Number(row.quantity || 1),
    amount: Number(row.total_amount || 0),
    payment: ['unpaid', 'failed'].includes((row.payment_status || '').toLowerCase()) ? 'unpaid' : 'paid',
    status: normalizeOrderStatus(row.order_status),
    date: (row.order_date || row.created_at || '').split(' ')[0],
    createdAt: row.created_at || row.order_date || '',
    product: row.product_name || 'Order Item',
    paymentMethod: row.payment_method || 'Online',
    vendor: row.vendor_name || 'Unknown Vendor',
    vendorEmail: (row.vendor_email || '').trim().toLowerCase()
  };
}

function mapApiProduct(row, index) {
  const stock = Number(row.stock_quantity || 0);
  return {
    name: row.product_name,
    emoji: ['📦', '🛍️', '🌿', '🧴', '💻', '🛒'][index % 6],
    price: Number(row.base_price || 0),
    stock,
    sku: `PRD-${String(row.product_id).padStart(3, '0')}`,
    discount: 0,
    status: Number(row.product_status) === 1 ? (stock > 0 ? 'active' : 'out') : 'draft',
    img: ''
  };
}

function pickActiveVendor(vendors, orders) {
  if (!vendors || vendors.length === 0) return null;

  const counts = {};
  orders.forEach((o) => {
    counts[o.vendor] = (counts[o.vendor] || 0) + 1;
  });
  let chosen = vendors[0];
  let max = -1;
  vendors.forEach((v) => {
    const c = counts[v.vendor_name] || 0;
    if (c > max) {
      max = c;
      chosen = v;
    }
  });
  return chosen;
}

function filterVendorOrders(orders, activeVendor) {
  if (!orders || orders.length === 0) return [];

  const vendorName = activeVendor ? activeVendor.vendor_name : '';
  const vendorEmail = activeVendor ? String(activeVendor.email || '').trim().toLowerCase() : '';

  let filtered = [];
  if (vendorName) {
    filtered = orders.filter((o) => o.vendor === vendorName);
  }

  if (filtered.length === 0 && vendorEmail) {
    filtered = orders.filter((o) => o.vendorEmail === vendorEmail);
  }

  return filtered.length > 0 ? filtered : orders;
}

function computeStats(orders) {
  const today = new Date().toISOString().split('T')[0];
  const now = new Date();
  const month = now.getMonth();
  const year = now.getFullYear();
  const todaySales = orders.filter((o) => o.date === today && o.status !== 'cancelled').reduce((sum, o) => sum + o.amount, 0);
  const totalOrders = orders.length;
  const pendingOrders = orders.filter((o) => ['pending', 'processing', 'shipped'].includes(o.status)).length;
  const totalEarnings = orders.filter((o) => o.status === 'delivered').reduce((sum, o) => sum + o.amount, 0);
  const monthlyRevenue = orders.filter((o) => {
    const d = new Date(o.date);
    return d.getFullYear() === year && d.getMonth() === month && o.status !== 'cancelled';
  }).reduce((sum, o) => sum + o.amount, 0);

  return { todaySales, totalOrders, pendingOrders, totalEarnings, monthlyRevenue };
}

function updateVendorIdentity(activeVendor) {
  if (!activeVendor) return;
  const firstName = (activeVendor.vendor_name || '').split(' ')[0] || 'Vendor';
  const profileName = document.querySelector('.profile-name');
  const profileRole = document.querySelector('.profile-role');
  const profileAvatar = document.querySelector('.profile-avatar');
  const welcomeName = document.querySelector('.vendor-text');
  const welcomeSub = document.querySelector('.welcome-sub');
  const settingsBusinessName = document.getElementById('settingsBusinessName');
  const settingsOwnerName = document.getElementById('settingsOwnerName');
  const settingsEmail = document.getElementById('settingsEmail');
  const searchInput = document.querySelector('.search-bar input');
  const supportGreeting = document.querySelector('#chatMessages .chat-bubble.bot');

  const initials = (activeVendor.vendor_name || 'V')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || 'V';

  const todayText = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  });
  const category = activeVendor.business_category || 'Business';

  if (profileName) profileName.textContent = activeVendor.vendor_name;
  if (profileRole) profileRole.textContent = `Vendor ${activeVendor.vendor_status === 'verified' ? '✅ Verified' : '• Pending Verification'}`;
  if (profileAvatar) profileAvatar.textContent = initials;
  if (welcomeName) welcomeName.textContent = firstName;
  if (welcomeSub) welcomeSub.textContent = `${todayText} · ${category} Seller`;
  if (settingsBusinessName) settingsBusinessName.value = activeVendor.vendor_name || '';
  if (settingsOwnerName) settingsOwnerName.value = activeVendor.vendor_name || '';
  if (settingsEmail) settingsEmail.value = activeVendor.email || '';
  if (searchInput && activeVendor.email) {
    searchInput.value = activeVendor.email;
    searchInput.readOnly = true;
  }
  if (supportGreeting) {
    supportGreeting.textContent = `👋 Hello ${firstName}! How can we help you today?`;
  }
}

async function loadVendorDashboardData() {
  try {
    const [vendors, orders, products, customers] = await Promise.all([getVendors(), getOrders(), getProducts(), getCustomers()]);
    if (vendors && orders && products) {
      dashboardData.vendors = vendors;
      const mappedOrders = orders.map(mapApiOrder);
      const activeVendor = pickActiveVendor(vendors, mappedOrders);
      const vendorName = activeVendor ? activeVendor.vendor_name : null;
      dashboardData.orders = filterVendorOrders(mappedOrders, activeVendor);
      dashboardData.rawOrders = [...dashboardData.orders];
      dashboardData.products = products
        .filter((p) => !vendorName || p.shop_name === vendorName)
        .map(mapApiProduct);
      dashboardData.customers = customers || [];
      dashboardData.activeVendor = activeVendor;
      dashboardData.loadedFromApi = true;

      if (dashboardData.products.length === 0) {
        dashboardData.products = products.map(mapApiProduct);
      }

      updateVendorIdentity(activeVendor);
      setCounterTargets(computeStats(dashboardData.orders));

      const ordersBadge = document.querySelector('.nav-item[data-page="orders"] .nav-badge');
      if (ordersBadge) {
        const pending = dashboardData.orders.filter((order) => ['pending', 'processing', 'shipped'].includes(order.status)).length;
        ordersBadge.textContent = String(pending);
      }
      return;
    }
  } catch (error) {
    console.error('Vendor dashboard API load failed:', error);
  }

  dashboardData.orders = [...SAMPLE_ORDERS];
  dashboardData.rawOrders = [...dashboardData.orders];
  dashboardData.products = [...PRODUCTS];
  dashboardData.customers = [];
  dashboardData.loadedFromApi = false;
  setCounterTargets(computeStats(dashboardData.orders));
}


// RENDER RECENT ORDERS
function renderRecentOrders() {
  const tbody = document.getElementById('recentOrdersBody');
  if (!tbody || tbody.children.length) return;
  tbody.innerHTML = dashboardData.orders.slice(0,7).map(o => `
    <tr>
      <td style="font-family:'Playfair Display',serif;font-size:0.78rem;color:var(--vendor-color);font-weight:700">${o.id}</td>
      <td>${o.customer}</td>
      <td style="color:var(--text-muted)">📍 ${o.district}</td>
      <td style="font-family:'Playfair Display',serif;font-size:0.85rem;color:var(--vendor-color);font-weight:700">Rs. ${o.amount.toLocaleString()}</td>
      <td><span class="badge b-${o.status}">${o.status}</span></td>
      <td style="color:var(--text-muted)">${o.date}</td>
    </tr>
  `).join('');
}


// RENDER ALL ORDERS TABLE
function renderOrders(filter) {
  const tbody = document.getElementById('ordersTableBody');
  if (!tbody) return;
  const source = dashboardData.orders.length ? dashboardData.orders : SAMPLE_ORDERS;
  const data = filter === 'all' ? source : source.filter(o => o.status === filter);

  if (data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;color:var(--text-muted)">No orders found.</td></tr>';
    return;
  }

  tbody.innerHTML = data.map(o => `
    <tr>
      <td style="font-family:'Playfair Display',serif;font-size:0.75rem;color:var(--vendor-color);font-weight:700">${o.id}</td>
      <td>${o.customer}</td>
      <td style="color:var(--text-muted)">📍 ${o.district}</td>
      <td style="text-align:center;color:var(--text-secondary)">${o.items}</td>
      <td style="font-family:'Playfair Display',serif;font-size:0.82rem;color:var(--vendor-color);font-weight:700">Rs. ${o.amount.toLocaleString()}</td>
      <td><span class="badge b-${o.payment}">${o.payment}</span></td>
      <td><span class="badge b-${o.status}">${o.status}</span></td>
      <td style="color:var(--text-muted)">${o.date}</td>
      <td><button class="action-btn">📄 Invoice</button></td>
    </tr>
  `).join('');
}

function filterOrders(filter, btn) {
  document.querySelectorAll('.otab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderOrders(filter);
}


// RENDER PRODUCTS (with images)
let allProducts = [...PRODUCTS];

function renderProducts(list = allProducts) {
  const grid = document.getElementById('productsGrid');
  if (!grid) return;
  if (list.length === 0) {
    grid.innerHTML = '<div style="padding:20px;color:var(--text-muted)">No products available.</div>';
    return;
  }
  grid.innerHTML = list.map(p => {
    const outStock = p.stock === 0;
    const lowStock = p.stock > 0 && p.stock <= 5;
    const statusColor = outStock ? '#ff6b6b' : lowStock ? '#FF8C00' : 'var(--vendor-color)';
    const statusLabel = outStock ? '❌ Out of Stock' : lowStock ? '⚠ Low Stock' : '✅ Active';
    return `
      <div class="product-card">
        <div class="product-img">
          <img src="${p.img}" alt="${p.name}" loading="lazy"
            onerror="this.style.display='none'" />
          <span class="product-emoji">${p.emoji}</span>
          <div class="product-img-label">Product Image</div>
        </div>
        <div class="product-body">
          <div class="product-name">${p.name}</div>
          <div class="product-sku">SKU: ${p.sku}</div>
          <div class="product-price-row">
            <span class="product-price">Rs. ${p.price.toLocaleString()}</span>
            ${p.discount ? `<span class="product-disc">-${p.discount}%</span>` : ''}
          </div>
          <div class="product-footer">
            <span class="stock-txt ${outStock ? 'out-stock' : lowStock ? 'low-stock' : ''}">${outStock ? 'Out of stock' : 'Stock: ' + p.stock}</span>
            <span style="font-size:0.68rem;color:${statusColor}">${statusLabel}</span>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

function filterProducts(q) {
  const f = allProducts.filter(p => p.name.toLowerCase().includes(q.toLowerCase()) || p.sku.toLowerCase().includes(q.toLowerCase()));
  renderProducts(f);
}

function filterByStatus(s) {
  const f = s ? allProducts.filter(p => {
    if (s === 'Active') return p.status === 'active';
    if (s === 'Out of Stock') return p.status === 'out';
    if (s === 'Draft') return p.status === 'draft';
    return true;
  }) : allProducts;
  renderProducts(f);
}


// RENDER CUSTOMERS (API backed)
function getCustomerCardData() {
  if (dashboardData.customers.length > 0) {
    return dashboardData.customers.map((customer, index) => {
      const email = String(customer.email || '').trim().toLowerCase();
      const purchases = dashboardData.orders.filter((order) => String(order.customerEmail || '').toLowerCase() === email).length;
      const totalSpent = dashboardData.orders
        .filter((order) => String(order.customerEmail || '').toLowerCase() === email)
        .reduce((sum, order) => sum + Number(order.amount || 0), 0);
      const tag = purchases >= 5 ? 'vip' : purchases >= 2 ? 'frequent' : 'new';
      const name = customer.full_name || customer.name || 'Customer';
      return {
        name,
        district: customer.city || 'Sri Lanka',
        tag,
        purchases,
        totalSpent,
        initials: name.split(' ').map((n) => n[0]).join('').slice(0, 2).toUpperCase() || 'CU',
        photo: CUSTOMER_PHOTOS[index % CUSTOMER_PHOTOS.length],
        color: AVATAR_COLORS[index % AVATAR_COLORS.length]
      };
    });
  }

  return CUSTOMER_NAMES.map((name, i) => ({
    name,
    district: DISTRICTS[i % DISTRICTS.length],
    tag: ['vip', 'frequent', 'new', 'frequent', 'vip', 'new', 'frequent', 'new', 'vip', 'frequent', 'new', 'frequent'][i],
    purchases: Math.floor(Math.random() * 60) + 2,
    totalSpent: (Math.floor(Math.random() * 60) + 2) * 1800,
    initials: name.split(' ').map((n) => n[0]).join(''),
    photo: CUSTOMER_PHOTOS[i % CUSTOMER_PHOTOS.length],
    color: AVATAR_COLORS[i % AVATAR_COLORS.length]
  }));
}

function renderCustomers() {
  const grid = document.getElementById('customersGrid');
  if (!grid) return;

  const customers = getCustomerCardData();
  grid.innerHTML = customers.map((c) => {
    const tagClass = c.tag === 'vip' ? 't-vip' : c.tag === 'frequent' ? 't-frequent' : 't-new';
    const tagLabel = c.tag === 'vip' ? '👑 VIP' : c.tag === 'frequent' ? '🔄 Frequent Buyer' : '🆕 New Customer';
    return `
      <div class="customer-card">
        <div class="cust-img-wrap" style="background:${c.color}22">
          <img src="${c.photo}" alt="${c.name}" loading="lazy" onerror="this.style.display='none'" />
          <span class="cust-initials" style="color:${c.color}">${c.initials}</span>
        </div>
        <div>
          <div class="cust-name">${c.name}</div>
          <div class="cust-location">📍 ${c.district}</div>
          <span class="cust-tag ${tagClass}">${tagLabel}</span>
          <div class="cust-meta">${c.purchases} purchase${c.purchases !== 1 ? 's' : ''} · Rs. ${Math.round(c.totalSpent || 0).toLocaleString()} spent</div>
        </div>
      </div>
    `;
  }).join('');

  const customerStatsCards = document.querySelectorAll('#page-customers .stats-grid .stat-card .stat-value');
  const vip = customers.filter((c) => c.tag === 'vip').length;
  const frequent = customers.filter((c) => c.tag === 'frequent').length;
  const newCustomers = customers.filter((c) => c.tag === 'new').length;
  if (customerStatsCards[0]) customerStatsCards[0].textContent = customers.length.toLocaleString();
  if (customerStatsCards[1]) customerStatsCards[1].textContent = vip.toLocaleString();
  if (customerStatsCards[2]) customerStatsCards[2].textContent = frequent.toLocaleString();
  if (customerStatsCards[3]) customerStatsCards[3].textContent = newCustomers.toLocaleString();
}


// RENDER TRANSACTIONS
function deriveTransactions() {
  if (dashboardData.rawOrders.length === 0) {
    return [
      { id: 'TXN-8841', type: 'Sale', amount: '+Rs. 4,800', method: 'Card', date: '2026-02-24', status: 'delivered' },
      { id: 'TXN-8840', type: 'Withdrawal', amount: '-Rs. 25,000', method: 'Bank Transfer', date: '2026-02-23', status: 'processing' },
      { id: 'TXN-8839', type: 'Sale', amount: '+Rs. 1,200', method: 'eZ Cash', date: '2026-02-23', status: 'delivered' },
      { id: 'TXN-8838', type: 'Refund', amount: '-Rs. 850', method: 'Card', date: '2026-02-22', status: 'cancelled' }
    ];
  }

  const sales = dashboardData.rawOrders.slice(0, 8).map((order, index) => {
    const signed = ['cancelled'].includes(order.status) ? -Math.round(order.amount * 0.15) : Math.round(order.amount);
    return {
      id: `TXN-${String(9200 - index).padStart(4, '0')}`,
      type: signed >= 0 ? 'Sale' : 'Refund',
      amount: `${signed >= 0 ? '+' : '-'}Rs. ${Math.abs(signed).toLocaleString()}`,
      method: order.paymentMethod || (signed >= 0 ? 'Online' : 'Adjustment'),
      date: order.date || new Date().toISOString().split('T')[0],
      status: order.status
    };
  });

  return sales;
}

function renderTransactions() {
  const tbody = document.getElementById('txnBody');
  if (!tbody) return;
  const transactions = deriveTransactions();

  tbody.innerHTML = transactions.map((t) => {
    const isPos = t.amount.startsWith('+');
    const amtColor = isPos ? 'var(--vendor-color)' : '#ff6b6b';
    return `
      <tr>
        <td style="font-family:'Playfair Display',serif;font-size:0.75rem;color:var(--vendor-color);font-weight:700">${t.id}</td>
        <td style="color:var(--text-secondary)">${t.type}</td>
        <td style="font-family:'Playfair Display',serif;font-weight:700;color:${amtColor}">${t.amount}</td>
        <td style="color:var(--text-muted)">${t.method}</td>
        <td style="color:var(--text-muted)">${t.date}</td>
        <td><span class="badge b-${t.status}">${t.status}</span></td>
      </tr>
    `;
  }).join('');

  const earnings = dashboardData.rawOrders.reduce((sum, order) => sum + (order.status === 'cancelled' ? 0 : Number(order.amount || 0)), 0);
  const platformFee = Math.round(earnings * 0.05);
  const withdrawable = Math.max(0, Math.round(earnings * 0.6));
  const paymentStatsCards = document.querySelectorAll('#page-payments .stats-grid .stat-card .stat-value');
  if (paymentStatsCards[0]) paymentStatsCards[0].textContent = `Rs. ${earnings.toLocaleString()}`;
  if (paymentStatsCards[1]) paymentStatsCards[1].textContent = `Rs. ${withdrawable.toLocaleString()}`;
  if (paymentStatsCards[2]) paymentStatsCards[2].textContent = `Rs. ${platformFee.toLocaleString()}`;
}


// BEST SELLERS
function deriveBestSellers() {
  if (dashboardData.rawOrders.length === 0) {
    return [
      { name: 'Ceylon Black Tea 500g', emoji: '🍵', sales: 'Rs. 102,000', pct: 90 },
      { name: 'Ayurvedic Body Oil', emoji: '🧴', sales: 'Rs. 88,000', pct: 78 },
      { name: 'Handmade Batik Sarong', emoji: '🎨', sales: 'Rs. 75,500', pct: 67 },
      { name: 'Wooden Elephant Carving', emoji: '🐘', sales: 'Rs. 62,400', pct: 55 },
      { name: 'Coconut Oil 1L', emoji: '🥥', sales: 'Rs. 48,000', pct: 42 }
    ];
  }

  const totals = {};
  dashboardData.rawOrders.forEach((order) => {
    const key = order.product || 'Order Item';
    if (!totals[key]) {
      totals[key] = { amount: 0, count: 0 };
    }
    totals[key].amount += Number(order.amount || 0);
    totals[key].count += Number(order.items || 1);
  });

  const sorted = Object.entries(totals)
    .sort((a, b) => b[1].amount - a[1].amount)
    .slice(0, 5);
  const maxAmount = sorted.length ? sorted[0][1].amount : 1;

  return sorted.map(([name, stat], index) => ({
    name,
    emoji: ['🍵', '🧴', '🎨', '🐘', '🥥'][index % 5],
    sales: `Rs. ${Math.round(stat.amount).toLocaleString()}`,
    pct: Math.max(8, Math.round((stat.amount / maxAmount) * 100))
  }));
}

function renderBestSellers() {
  const el = document.getElementById('bestSellersList');
  if (!el) return;
  const bestSellers = deriveBestSellers();
  el.innerHTML = bestSellers.map((b,i) => `
    <div class="bs-item">
      <span class="bs-rank">#${i+1}</span>
      <span class="bs-emoji">${b.emoji}</span>
      <span class="bs-name">${b.name}</span>
      <div class="bs-bar-wrap"><div class="bs-bar" style="width:0%" data-w="${b.pct}%"></div></div>
      <span class="bs-val">${b.sales}</span>
    </div>
  `).join('');
  // Animate bars after paint
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      document.querySelectorAll('.bs-bar').forEach(bar => {
        bar.style.width = bar.dataset.w;
      });
    });
  });
}


// REVIEWS (derived from live order/customer data)
function deriveReviews() {
  if (dashboardData.rawOrders.length === 0) {
    return [
      { name: 'Nimali Perera', product: 'Ceylon Black Tea', stars: 5, text: 'Absolutely love this tea! Best quality I have found online in Sri Lanka. Fast delivery to Kandy too.', date: '2026-02-22', photo: CUSTOMER_PHOTOS[0], color: AVATAR_COLORS[0] },
      { name: 'Kasun Fernando', product: 'Coconut Oil 1L', stars: 4, text: 'Good quality oil, very pure. Would be great if packaging was a bit stronger for shipping long distance.', date: '2026-02-20', photo: CUSTOMER_PHOTOS[1], color: AVATAR_COLORS[1] },
      { name: 'Tharindu Silva', product: 'Batik Sarong', stars: 5, text: 'Amazing craftsmanship. The colors are vibrant and the fabric feels premium. Very happy with my purchase!', date: '2026-02-18', photo: CUSTOMER_PHOTOS[3], color: AVATAR_COLORS[3] },
      { name: 'Chamari Herath', product: 'Ayurvedic Body Oil', stars: 3, text: 'Product is decent but shipping took longer than expected. Quality is as described in the listing though.', date: '2026-02-15', photo: CUSTOMER_PHOTOS[8], color: AVATAR_COLORS[8] }
    ];
  }

  const templates = {
    5: 'Excellent service and product quality. Will order again from this store.',
    4: 'Great overall experience. Delivery and quality were both good.',
    3: 'Product was okay and delivery was average. Could improve packaging.'
  };

  return dashboardData.rawOrders
    .filter((order) => order.status === 'delivered')
    .slice(0, 4)
    .map((order, index) => {
      const stars = Math.max(3, 5 - (index % 3));
      return {
        name: order.customer,
        product: order.product || 'Order Item',
        stars,
        text: templates[stars],
        date: order.date,
        photo: CUSTOMER_PHOTOS[index % CUSTOMER_PHOTOS.length],
        color: AVATAR_COLORS[index % AVATAR_COLORS.length]
      };
    });
}

function renderReviews() {
  const el = document.getElementById('reviewsList');
  if (!el) return;
  const reviews = deriveReviews();

  el.innerHTML = reviews.map(r => `
    <div class="review-card">
      <div class="review-avatar" style="background:${r.color}22">
        <img src="${r.photo}" alt="${r.name}" loading="lazy" onerror="this.style.display='none'" />
        <span class="review-avatar-txt" style="color:${r.color}">${r.name.split(' ').map(n=>n[0]).join('')}</span>
      </div>
      <div class="review-body">
        <div class="review-top">
          <div>
            <div class="review-name">${r.name}</div>
            <div class="review-product">📦 ${r.product}</div>
          </div>
          <div style="text-align:right">
            <div class="review-stars">${'★'.repeat(r.stars)}${'☆'.repeat(5-r.stars)}</div>
            <div class="review-date">${r.date}</div>
          </div>
        </div>
        <div class="review-text">"${r.text}"</div>
        <button class="review-reply">💬 Reply to Review</button>
      </div>
    </div>
  `).join('');

  const totalReviews = Math.max(1, dashboardData.rawOrders.filter((order) => order.status === 'delivered').length);
  const averageRating = reviews.length
    ? (reviews.reduce((sum, review) => sum + review.stars, 0) / reviews.length).toFixed(1)
    : '4.5';
  const ratingBig = document.querySelector('#page-reviews .rating-big');
  const ratingCount = document.querySelector('#page-reviews .rating-count');
  if (ratingBig) ratingBig.textContent = averageRating;
  if (ratingCount) ratingCount.textContent = `Based on ${totalReviews.toLocaleString()} reviews`;
}


// MODAL SYSTEM

const MODALS = {
  addProduct: {
    title: '📦 Add New Product',
    html: `
      <div class="form-group"><label>Product Name</label><input type="text" placeholder="e.g. Ceylon Green Tea 250g" class="form-input" /></div>
      <div class="form-group"><label>Category</label>
        <select class="form-input"><option>Food & Beverages</option><option>Clothing</option><option>Crafts</option><option>Cosmetics</option><option>Spices</option></select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label>Price (Rs.)</label><input type="number" placeholder="0.00" class="form-input" /></div>
        <div class="form-group"><label>Discount (%)</label><input type="number" placeholder="0" class="form-input" /></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label>Stock Quantity</label><input type="number" placeholder="0" class="form-input" /></div>
        <div class="form-group"><label>SKU Code</label><input type="text" placeholder="e.g. TEA-009" class="form-input" /></div>
      </div>
      <div class="form-group"><label>Product Description</label><textarea class="form-input" rows="3" placeholder="Product description…"></textarea></div>
      <div class="form-group"><label>Product Image URL</label><input type="url" placeholder="https://…" class="form-input" /></div>
      <div class="form-group"><label>Status</label><select class="form-input"><option>Active</option><option>Draft</option></select></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
        <button class="btn-outline-vendor" onclick="closeModal()">Cancel</button>
        <button class="btn-vendor" onclick="closeModal()">Save Product</button>
      </div>
    `
  },
  addCoupon: {
    title: '🎟 Create Coupon Code',
    html: `
      <div class="form-group"><label>Coupon Code</label><input type="text" placeholder="e.g. POSON20" class="form-input" style="text-transform:uppercase;letter-spacing:3px;font-family:'Playfair Display',serif" /></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label>Discount Type</label><select class="form-input"><option>Percentage (%)</option><option>Flat Amount (Rs.)</option></select></div>
        <div class="form-group"><label>Discount Value</label><input type="number" placeholder="0" class="form-input" /></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label>Start Date</label><input type="date" class="form-input" /></div>
        <div class="form-group"><label>End Date</label><input type="date" class="form-input" /></div>
      </div>
      <div class="form-group"><label>Apply To</label><select class="form-input"><option>All Products</option><option>Selected Products</option><option>Selected Category</option></select></div>
      <div class="form-group"><label>Usage Limit</label><input type="number" placeholder="Unlimited" class="form-input" /></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
        <button class="btn-outline-vendor" onclick="closeModal()">Cancel</button>
        <button class="btn-vendor" onclick="closeModal()">Create Coupon</button>
      </div>
    `
  }
};

function showModal(type) {
  const cfg = MODALS[type];
  if (!cfg) return;
  document.getElementById('modalTitle').textContent = cfg.title;
  document.getElementById('modalContent').innerHTML = cfg.html;
  document.getElementById('modalBackdrop').classList.add('open');
}
function closeModal() {
  document.getElementById('modalBackdrop').classList.remove('open');
}


// FAQ TOGGLE
function toggleFaq(el) {
  const body = el.closest('.glass-card').querySelector('.support-body');
  const arrow = el.querySelector('.faq-arrow');
  if (!body) return;
  const open = body.classList.toggle('open-body');
  if (arrow) arrow.style.transform = open ? 'rotate(90deg)' : '';
}


// LIVE CHAT
function sendChat() {
  const input = document.getElementById('chatInput');
  const msgs = document.getElementById('chatMessages');
  if (!input || !msgs || !input.value.trim()) return;
  const userBubble = document.createElement('div');
  userBubble.className = 'chat-bubble user';
  userBubble.textContent = input.value;
  msgs.appendChild(userBubble);
  input.value = '';
  msgs.scrollTop = msgs.scrollHeight;
  setTimeout(() => {
    const botBubble = document.createElement('div');
    botBubble.className = 'chat-bubble bot';
    botBubble.textContent = '🤝 Thanks for reaching out! A BizLink support agent will respond shortly. Ticket: #' + Math.floor(Math.random() * 90000 + 10000);
    msgs.appendChild(botBubble);
    msgs.scrollTop = msgs.scrollHeight;
  }, 1200);
}


// COUNTDOWN (Avurudu flash sale)
function startCountdown() {
  const target = new Date('2026-04-13T00:00:00');
  const el = document.getElementById('countdown');
  if (!el) return;
  const update = () => {
    const diff = target - new Date();
    if (diff <= 0) { el.textContent = '🎉 Sale is LIVE!'; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    el.textContent = `${d}d  ${String(h).padStart(2,'0')}h  ${String(m).padStart(2,'0')}m  ${String(s).padStart(2,'0')}s`;
  };
  update();
  setInterval(update, 1000);
}


// RIPPLE EFFECT on buttons (same as home.js)
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-vendor');
  if (!btn) return;
  const ripple = document.createElement('span');
  const rect = btn.getBoundingClientRect();
  ripple.style.cssText = `
    position:absolute;border-radius:50%;background:rgba(255,255,255,0.2);
    width:0;height:0;left:${e.clientX-rect.left}px;top:${e.clientY-rect.top}px;
    transform:translate(-50%,-50%);animation:rippleOut 0.6s ease forwards;pointer-events:none;
  `;
  btn.appendChild(ripple);
  setTimeout(() => ripple.remove(), 600);
});
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `@keyframes rippleOut { to { width:300px;height:300px;opacity:0; } }`;
document.head.appendChild(rippleStyle);


// PARALLAX ORBS on mousemove (same as home.js)
document.addEventListener('mousemove', (e) => {
  const { innerWidth, innerHeight } = window;
  const xR = (e.clientX / innerWidth  - 0.5) * 20;
  const yR = (e.clientY / innerHeight - 0.5) * 20;
  // Subtly move the welcome banner's pseudo glow
  const banner = document.querySelector('.welcome-banner');
  if (banner) {
    banner.style.setProperty('--mx', xR * 0.3 + 'px');
    banner.style.setProperty('--my', yR * 0.3 + 'px');
  }
});


// INIT (same pattern as home.js body fade)
document.body.style.opacity = '0';
document.body.style.transition = 'opacity 0.4s';
window.addEventListener('load', async () => {
  document.body.style.opacity = '1';
  await loadVendorDashboardData();
  await loadVendorNotifications(dashboardData.activeVendor);
  allProducts = dashboardData.products.length ? [...dashboardData.products] : [...PRODUCTS];
  onPageActivate('dashboard');
});

console.log('%c BizLink Vendor Dashboard 🇱🇰 ', 'background: #50C878; color: white; font-size: 14px; padding: 8px 16px; border-radius: 4px;');
console.log('%c Built for Sri Lankan Vendors ', 'color: #FF8C00; font-size: 12px;');