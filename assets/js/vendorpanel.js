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
  const pagesWrap = document.querySelector('.pages-wrap');
  if (target) {
    if (pagesWrap) {
      pagesWrap.scrollTop = 0;
    }
    window.scrollTo(0, 0);
    target.classList.add('active');
    onPageActivate(page);
  }
}

// Load real analytics data from API
async function loadDashboardAnalytics() {
  try {
    const response = await fetch('../api/vendor_analytics.php');
    if (!response.ok) throw new Error('Failed to fetch analytics');
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Analytics failed');
    
    const data = result.data || {};
    
    // Update stat cards with real data
    const s1 = document.getElementById('s1');
    const s2 = document.getElementById('s2');
    const s3 = document.getElementById('s3');
    const s4 = document.getElementById('s4');
    const s5 = document.getElementById('s5');
    
    if (s1) s1.textContent = 'Rs. ' + (data.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    if (s2) s2.textContent = (data.total_orders || 0).toString();
    if (s3) s3.textContent = (data.pending_orders || 0).toString();
    if (s4) s4.textContent = 'Rs. ' + (data.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    if (s5) s5.textContent = 'Rs. ' + (data.total_revenue || 0).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    
    // Store for chart rendering
    window.vendorAnalytics = data;
    
  } catch (error) {
    console.error('Error loading dashboard analytics:', error);
  }
}

// Render dashboard chart with real analytics data
let vendorSalesRange = 'week';

function normalizeSalesPoints(analytics) {
  const rawSeries = Array.isArray(analytics.sales_last_30_days)
    ? analytics.sales_last_30_days
    : (Array.isArray(analytics.sales_by_day) ? analytics.sales_by_day : []);

  return rawSeries
    .map((row) => ({
      date: String(row.day || row.date || '').slice(0, 10),
      revenue: Number(row.revenue || 0)
    }))
    .filter((row) => row.date && !Number.isNaN(new Date(row.date).getTime()))
    .sort((a, b) => a.date.localeCompare(b.date));
}

function buildSalesSeries(points, days) {
  const totalsByDate = new Map();
  points.forEach((point) => {
    totalsByDate.set(point.date, (totalsByDate.get(point.date) || 0) + Number(point.revenue || 0));
  });

  const labels = [];
  const data = [];

  for (let i = days - 1; i >= 0; i--) {
    const day = new Date();
    day.setHours(0, 0, 0, 0);
    day.setDate(day.getDate() - i);
    const key = day.toISOString().slice(0, 10);
    const value = Number(totalsByDate.get(key) || 0);

    labels.push(days === 7
      ? day.toLocaleDateString('en-US', { weekday: 'short' })
      : day.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    data.push(value);
  }

  return { labels, data };
}

function renderDashboardChartWithAnalytics() {
  const canvas = document.getElementById('salesChart');
  if (!canvas) return;

  if (window.vendorSalesChart && typeof window.vendorSalesChart.destroy === 'function') {
    window.vendorSalesChart.destroy();
  }

  canvas.style.height = '240px';
  
  const analytics = window.vendorAnalytics || {};
  const allPoints = normalizeSalesPoints(analytics);
  const rangeDays = vendorSalesRange === 'month' ? 30 : 7;
  const chartTitle = document.querySelector('.chart-wide .card-title');
  const rangeHint = document.getElementById('salesRangeHint');
  if (chartTitle) {
    chartTitle.textContent = vendorSalesRange === 'month'
      ? 'Sales Overview — Last 30 Days'
      : 'Sales Overview — Last 7 Days';
  }
  if (rangeHint) {
    rangeHint.textContent = vendorSalesRange === 'month'
      ? 'Showing monthly trend (last 30 days)'
      : 'Showing weekly trend';
  }

  const series = buildSalesSeries(allPoints, rangeDays);
  
  window.vendorSalesChart = new Chart(canvas, {
    type: 'line',
    data: {
      labels: series.labels.length > 0 ? series.labels : ['No data'],
      datasets: [{
        label: 'Sales (Rs.)',
        data: series.data.length > 0 ? series.data : [0],
        borderColor: VENDOR_GREEN,
        backgroundColor: 'rgba(80, 200, 120, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 5,
        pointBackgroundColor: VENDOR_GREEN,
        pointBorderColor: '#fff',
        pointBorderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 2.8,
      plugins: { 
        legend: { display: true, labels: { color: CHART_TICK, font: { size: 11 } } } 
      },
      scales: {
        y: { beginAtZero: true, ticks: { color: CHART_TICK, callback: v => 'Rs. ' + v.toLocaleString() }, grid: { color: CHART_GRID } },
        x: { ticks: { color: CHART_TICK }, grid: { display: false } }
      }
    }
  });
}

function renderDashboardDonutWithAnalytics() {
  const canvas = document.getElementById('donutChart');
  if (!canvas) return;

  if (window.vendorDonutChart && typeof window.vendorDonutChart.destroy === 'function') {
    window.vendorDonutChart.destroy();
  }

  const statusPercentages = computeStatusPercentages(dashboardData.orders);
  updateDonutLegend(statusPercentages);

  window.vendorDonutChart = new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: ['Delivered', 'Pending', 'Cancelled', 'Processing'],
      datasets: [{
        data: [
          statusPercentages.delivered,
          statusPercentages.pending,
          statusPercentages.cancelled,
          statusPercentages.processing
        ],
        backgroundColor: [VENDOR_GREEN, ACCENT_ORANGE, ACCENT_RED, ACCENT_BLUE],
        borderWidth: 0,
        hoverOffset: 8
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 1,
      cutout: '70%',
      plugins: {
        legend: { display: false },
        tooltip: {
          ...tooltipDefaults(),
          callbacks: { label: (ctx) => `  ${ctx.label}: ${ctx.raw}%` }
        }
      },
      animation: { animateRotate: true, duration: 900 }
    }
  });
}

function onPageActivate(page) {
  if (page === 'dashboard')  { loadDashboardAnalytics().then(() => { animateCounters(); renderDashboardChartWithAnalytics(); renderDashboardDonutWithAnalytics(); renderRecentOrders(); }); }
  if (page === 'products')   { renderProducts(); }
  if (page === 'orders')     { renderOrders('all'); }
  if (page === 'customers')  { renderCustomers(); }
  if (page === 'analytics')  { initAnalyticsCharts(); renderBestSellers(); }
  if (page === 'payments')   { renderTransactions(); }
  if (page === 'reviews')    { renderReviews(); }
  if (page === 'promotions') { startCountdown(); }
  if (page === 'verification') { loadVerificationStatus(); }
}

async function vendorLogout() {
  try {
    if (typeof authLogout === 'function') {
      await authLogout();
    }
  } catch (error) {
    console.warn('Vendor logout request failed, redirecting anyway:', error);
  }

  window.location.href = '../pages/index.html';
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

  const statusPercentages = computeStatusPercentages(dashboardData.orders);
  updateDonutLegend(statusPercentages);
  const donutData = [
    statusPercentages.delivered,
    statusPercentages.pending,
    statusPercentages.cancelled,
    statusPercentages.processing
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

  const monthlyTotals = new Array(12).fill(0);
  dashboardData.rawOrders.forEach((order) => {
    const date = new Date(order.date || order.createdAt || '');
    if (Number.isNaN(date.getTime()) || order.status === 'cancelled') return;
    monthlyTotals[date.getMonth()] += Number(order.amount || 0);
  });

  const districtTotals = {};
  dashboardData.rawOrders.forEach((order) => {
    const key = order.district || 'Sri Lanka';
    districtTotals[key] = (districtTotals[key] || 0) + 1;
  });

  const topDistricts = Object.entries(districtTotals)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 6);
  const districtLabels = topDistricts.map(([name]) => name);
  const districtCounts = topDistricts.map(([, count]) => count);
  const districtMax = districtCounts.length ? Math.max(...districtCounts) : 1;
  const districtPercents = districtCounts.map((count) => Math.round((count / districtMax) * 100));

  const mCtx = document.getElementById('monthlyChart');
  if (mCtx) {
    new Chart(mCtx, {
      type: 'bar',
      data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{
          label: 'Revenue (Rs.)',
          data: monthlyTotals,
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
        labels: districtLabels,
        datasets: [{
          data: districtPercents,
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
    const tabsWrap = e.target.closest('.chart-tabs');
    if (!tabsWrap) return;
    tabsWrap.querySelectorAll('.ctab').forEach((t) => {
      t.classList.remove('active');
      t.setAttribute('aria-pressed', 'false');
    });
    e.target.classList.add('active');
    e.target.setAttribute('aria-pressed', 'true');

    const tabText = (e.target.textContent || '').trim().toLowerCase();
    vendorSalesRange = tabText === 'month' ? 'month' : 'week';
    renderDashboardChartWithAnalytics();
  }
});

// DATA

const DISTRICTS = ['Colombo','Gampaha','Kandy','Galle','Matara','Jaffna','Kurunegala','Ratnapura','Kalutara','Anuradhapura'];
const CUSTOMER_NAMES = ['Nimali Perera','Kasun Fernando','Kavya Rajapaksa','Thilini Dissanayake','Amal Bandara','Sanjaya Silva','Pooja Wickramasinghe','Ravindu Gunawardena','Chamari Herath','Dinesh Jayawardena','Hasini Ranaweera','Tharindu Samarasinghe'];

// Sri Lankan product photos (Unsplash free-to-use)
const PRODUCTS = [];

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

const dashboardData = {
  vendors: [],
  orders: [],
  rawOrders: [],
  products: [],
  customers: [],
  activeVendor: null,
  notifications: [],
  loadedFromApi: false,
  sessionUser: null,
  sessionProfile: null
};

function renderWidgetState(container, text, tone = 'empty') {
  if (!container) return;
  container.innerHTML = `<div class="widget-state ${tone}">${text}</div>`;
}

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
  const imageUrl = String(row.image_url || '').trim();
  return {
    id: Number(row.product_id || 0),
    name: row.product_name,
    emoji: ['📦', '🛍️', '🌿', '🧴', '💻', '🛒'][index % 6],
    price: Number(row.base_price || 0),
    stock,
    sku: `PRD-${String(row.product_id).padStart(3, '0')}`,
    discount: 0,
    status: (Number(row.product_status) === 1 || row.is_active == 1) ? (stock > 0 ? 'active' : 'out') : 'draft',
    img: imageUrl || ''
  };
}

function pickActiveVendor(vendors, orders) {
  if (!vendors || vendors.length === 0) return null;

  const sessionEmail = String(dashboardData.sessionUser?.email || '').trim().toLowerCase();
  const sessionUserId = Number(dashboardData.sessionUser?.user_id || 0);
  const exact = vendors.find((v) => {
    const vendorEmail = String(v.email || v.business_email || '').trim().toLowerCase();
    const vendorUserId = Number(v.user_id || 0);
    return (sessionEmail && vendorEmail === sessionEmail) || (sessionUserId > 0 && vendorUserId === sessionUserId);
  });
  if (exact) return exact;

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

function filterOrdersForActiveVendor(orders, activeVendor) {
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

  return filtered.length > 0 ? filtered : [];
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

function computeStatusPercentages(orders) {
  const statusMap = { delivered: 0, pending: 0, cancelled: 0, processing: 0 };

  orders.forEach((order) => {
    const status = String(order.status || '').toLowerCase();
    if (status === 'shipped') {
      statusMap.processing += 1;
    } else if (statusMap[status] !== undefined) {
      statusMap[status] += 1;
    }
  });

  const total = Object.values(statusMap).reduce((sum, value) => sum + value, 0);
  if (total <= 0) {
    return { delivered: 0, pending: 0, cancelled: 0, processing: 0 };
  }

  return {
    delivered: Math.round((statusMap.delivered / total) * 100),
    pending: Math.round((statusMap.pending / total) * 100),
    cancelled: Math.round((statusMap.cancelled / total) * 100),
    processing: Math.round((statusMap.processing / total) * 100)
  };
}

function updateDonutLegend(statusPercentages) {
  const deliveredEl = document.getElementById('donutDeliveredPct');
  const pendingEl = document.getElementById('donutPendingPct');
  const cancelledEl = document.getElementById('donutCancelledPct');
  const processingEl = document.getElementById('donutProcessingPct');

  if (deliveredEl) deliveredEl.textContent = `${statusPercentages.delivered}%`;
  if (pendingEl) pendingEl.textContent = `${statusPercentages.pending}%`;
  if (cancelledEl) cancelledEl.textContent = `${statusPercentages.cancelled}%`;
  if (processingEl) processingEl.textContent = `${statusPercentages.processing}%`;
}

function computeVendorRating(activeVendor, orders) {
  const apiRating = Number(
    activeVendor?.avg_rating ||
    activeVendor?.vendor_rating ||
    activeVendor?.rating ||
    0
  );

  if (apiRating > 0) {
    return Math.min(5, Math.max(0, apiRating));
  }

  if (!Array.isArray(orders) || orders.length === 0) {
    return 0;
  }

  // Fallback estimate if the backend payload doesn't include rating fields.
  const delivered = orders.filter((order) => order.status === 'delivered').length;
  const cancelled = orders.filter((order) => order.status === 'cancelled').length;
  const score = 3.8 + ((delivered / orders.length) * 1.2) - ((cancelled / orders.length) * 0.8);
  return Math.min(5, Math.max(0, Number(score.toFixed(1))));
}

function updateRatingUI(activeVendor, orders) {
  const ratingValueEl = document.getElementById('vendorRatingValue');
  const ratingStarsEl = document.getElementById('vendorRatingStars');
  const rating = computeVendorRating(activeVendor, orders);

  if (ratingValueEl) {
    ratingValueEl.textContent = `${rating.toFixed(1)} / 5`;
  }

  if (ratingStarsEl) {
    const filledStars = Math.max(0, Math.min(5, Math.round(rating)));
    ratingStarsEl.textContent = `${'★'.repeat(filledStars)}${'☆'.repeat(5 - filledStars)}`;
  }
}

function updateMonthlyRevenueLabel() {
  const monthLabelEl = document.getElementById('monthlyRevenueMonthLabel');
  if (!monthLabelEl) return;

  const now = new Date();
  monthLabelEl.textContent = now.toLocaleDateString('en-US', {
    month: 'long',
    year: 'numeric'
  });
}

function updateDashboardDynamicMeta() {
  updateRatingUI(dashboardData.activeVendor, dashboardData.orders);
  updateDonutLegend(computeStatusPercentages(dashboardData.orders));
  updateMonthlyRevenueLabel();
}

function updateVendorIdentity(activeVendor) {
  const sessionUser = dashboardData.sessionUser || {};
  const sessionProfile = dashboardData.sessionProfile || {};
  const businessName = String(activeVendor?.vendor_name || sessionProfile?.business_name || 'Business').trim();
  const displayName = String(sessionUser.full_name || businessName || 'Vendor').trim();
  const firstName = displayName.split(' ')[0] || 'Vendor';
  const displayEmail = String(sessionUser.email || activeVendor?.email || activeVendor?.business_email || '').trim().toLowerCase();
  const profileName = document.querySelector('.profile-name');
  const profileRole = document.querySelector('.profile-role');
  const profileAvatar = document.querySelector('.profile-avatar');
  const profileEmail = document.getElementById('vendorProfileEmail');
  const welcomeName = document.querySelector('.vendor-text');
  const welcomeSub = document.querySelector('.welcome-sub');
  const settingsBusinessName = document.getElementById('settingsBusinessName');
  const settingsOwnerName = document.getElementById('settingsOwnerName');
  const settingsEmail = document.getElementById('settingsEmail');
  const searchInput = document.querySelector('.search-bar input');
  const supportGreeting = document.querySelector('#chatMessages .chat-bubble.bot');

  const initials = (displayName || 'V')
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
  const category = activeVendor?.business_category || 'Business';

  if (profileName) profileName.textContent = displayName || 'Vendor';
  if (profileRole) profileRole.textContent = `${businessName} ${activeVendor?.vendor_status === 'verified' ? '• ✅ Verified' : '• Pending Verification'}`;
  if (profileEmail) profileEmail.textContent = displayEmail || '-';
  if (profileAvatar) profileAvatar.textContent = initials;
  if (welcomeName) welcomeName.textContent = firstName;
  if (welcomeSub) welcomeSub.textContent = `${todayText} · ${businessName} · ${category} Seller`;
  if (settingsBusinessName) settingsBusinessName.value = businessName || '';
  if (settingsOwnerName) settingsOwnerName.value = displayName || '';
  if (settingsEmail) settingsEmail.value = displayEmail || '';
  if (searchInput && displayEmail) {
    searchInput.value = displayEmail;
    searchInput.readOnly = true;
  }
  if (supportGreeting) {
    supportGreeting.textContent = `👋 Hello ${firstName}! How can we help you today?`;
  }
}

async function loadVendorDashboardData() {
  const recentOrdersBody = document.getElementById('recentOrdersBody');
  const ordersTableBody = document.getElementById('ordersTableBody');
  const productsGrid = document.getElementById('productsGrid');
  renderWidgetState(recentOrdersBody, 'Loading recent orders...', 'loading');
  renderWidgetState(ordersTableBody, 'Loading order list...', 'loading');
  renderWidgetState(productsGrid, 'Loading products...', 'loading');

  try {
    if (typeof authMe !== 'function') {
      window.location.href = '../pages/index.html?reason=unauthorized';
      return;
    }

    const identity = await authMe();
    if (!identity || !identity.user) {
      window.location.href = '../pages/index.html?reason=unauthorized';
      return;
    }

    if (String(identity.user.role || '').toLowerCase() !== 'vendor') {
      // If mismatch, go back to the router with the intended role
      // This will trigger the "Session Conflict" page in dashboard.php
      window.location.href = `../dashboard.php?role=vendor`;
      return;
    }

    dashboardData.sessionUser = identity.user;
    dashboardData.sessionProfile = identity.profile || null;

    const [vendors, orders, products, customers] = await Promise.all([
      getVendors(), 
      getOrders(), 
      getProducts({ own_only: 1 }), 
      getCustomers()
    ]);
    if (vendors && orders && products) {
      dashboardData.vendors = vendors;
      const mappedOrders = orders.map(mapApiOrder);
      const activeVendor = pickActiveVendor(vendors, mappedOrders);
      dashboardData.orders = filterOrdersForActiveVendor(mappedOrders, activeVendor);
      dashboardData.rawOrders = [...dashboardData.orders];
      
      // No longer filtering by shop_name on frontend as API handles it via own_only=1
      dashboardData.products = products.map(mapApiProduct);
      allProducts = [...dashboardData.products];

      dashboardData.customers = customers || [];
      dashboardData.activeVendor = activeVendor;
      dashboardData.loadedFromApi = true;

      updateVendorIdentity(activeVendor);
      setCounterTargets(computeStats(dashboardData.orders));
      updateDashboardDynamicMeta();

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

  dashboardData.orders = [];
  dashboardData.rawOrders = [];
  dashboardData.products = [];
  dashboardData.customers = [];
  dashboardData.activeVendor = null;
  dashboardData.loadedFromApi = false;
  setCounterTargets(computeStats(dashboardData.orders));
  updateDashboardDynamicMeta();
}


// RENDER RECENT ORDERS
function renderRecentOrders() {
  const tbody = document.getElementById('recentOrdersBody');
  if (!tbody || tbody.children.length) return;

  if (!dashboardData.orders.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No recent orders available.</td></tr>';
    return;
  }

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
  // Delegate to the new function
  filterVendorOrders(filter === 'all' ? 'all' : filter);
}

function filterOrders(filter, btn) {
  filterVendorOrders(filter, btn);
}

// RENDER PRODUCTS (using API)
let allProducts = [];

function renderProducts(list = null) {
  loadVendorProducts(1);
}

async function handleDeleteProduct(event, productId) {
  if (event && typeof event.stopPropagation === 'function') {
    event.stopPropagation();
  }

  const id = Number(productId || 0);
  if (id <= 0) {
    window.alert('Invalid product selected.');
    return;
  }

  const targetProduct = allProducts.find((product) => Number(product.id) === id);
  const productName = targetProduct ? targetProduct.name : 'this product';
  const confirmed = window.confirm(`Delete ${productName}? This action cannot be undone.`);
  if (!confirmed) {
    return;
  }

  if (typeof deleteVendorProduct !== 'function') {
    window.alert('Delete service is not available right now.');
    return;
  }

  const result = await deleteVendorProduct(id);
  if (!result || !result.success) {
    window.alert(result && result.message ? result.message : 'Failed to delete product.');
    return;
  }

  await loadVendorDashboardData();
  allProducts = [...dashboardData.products];
  renderProducts();
  showToast(`🗑 ${productName} deleted`, 'remove');
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

  return [];
}

function renderCustomers() {
  const grid = document.getElementById('customersGrid');
  if (!grid) return;

  const customers = getCustomerCardData();
  if (customers.length === 0) {
    renderWidgetState(grid, 'No customer insights available yet.', 'empty');
    return;
  }

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
    return [];
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

  if (transactions.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No payment transactions available yet.</td></tr>';
    return;
  }

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
  const platformFee = 20; // Fixed fee of Rs. 20.00
  const withdrawable = Math.max(0, Math.round(earnings * 0.6));
  const paymentStatsCards = document.querySelectorAll('#page-payments .stats-grid .stat-card .stat-value');
  if (paymentStatsCards[0]) paymentStatsCards[0].textContent = `Rs. ${earnings.toLocaleString()}`;
  if (paymentStatsCards[1]) paymentStatsCards[1].textContent = `Rs. ${withdrawable.toLocaleString()}`;
  if (paymentStatsCards[2]) paymentStatsCards[2].textContent = `Rs. 20,00`;
}


// BEST SELLERS
function deriveBestSellers() {
  if (dashboardData.rawOrders.length === 0) {
    return [];
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

  if (bestSellers.length === 0) {
    renderWidgetState(el, 'No sales data yet to rank best sellers.', 'empty');
    return;
  }

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
    return [];
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

  if (reviews.length === 0) {
    renderWidgetState(el, 'No customer reviews yet.', 'empty');
    const ratingBig = document.querySelector('#page-reviews .rating-big');
    const ratingCount = document.querySelector('#page-reviews .rating-count');
    if (ratingBig) ratingBig.textContent = '0.0';
    if (ratingCount) ratingCount.textContent = 'Based on 0 reviews';
    return;
  }

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
      <div class="form-group"><label>Product Name</label><input id="vendorProductName" type="text" placeholder="e.g. Ceylon Green Tea 250g" class="form-input" /></div>
      <div class="form-group"><label>Category</label>
        <select id="vendorProductCategory" class="form-input">
          <option value="grocery">Grocery</option>
          <option value="fashion">Fashion</option>
          <option value="home">Home</option>
          <option value="electronics">Electronics</option>
          <option value="health">Health</option>
          <option value="agriculture">Agriculture</option>
          <option value="construction">Construction</option>
          <option value="office">Office</option>
          <option value="packaging">Packaging</option>
          <option value="industrial">Industrial</option>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label>Price (Rs.)</label><input id="vendorProductPrice" type="number" min="0" step="0.01" placeholder="0.00" class="form-input" /></div>
        <div class="form-group"><label>Discount (%)</label><input id="vendorProductDiscount" type="number" min="0" max="100" placeholder="0" class="form-input" /></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group"><label>Stock Quantity</label><input id="vendorProductStock" type="number" min="0" placeholder="0" class="form-input" /></div>
        <div class="form-group"><label>SKU Code</label><input id="vendorProductSku" type="text" placeholder="e.g. TEA-009" class="form-input" /></div>
      </div>
      <div class="form-group"><label>Product Description</label><textarea id="vendorProductDescription" class="form-input" rows="3" placeholder="Product description…"></textarea></div>
      <div class="form-group">
        <label>Product Image from Computer</label>
        <input id="vendorProductImageFile" type="file" accept="image/*" class="form-input" />
        <small style="display:block;color:var(--text-muted);margin-top:6px;">Choose an image from your computer. You can also paste an image URL below as a fallback.</small>
      </div>
      <div class="form-group"><label>Product Image URL</label><input id="vendorProductImage" type="url" placeholder="https://…" class="form-input" /></div>
      <div class="form-group"><label>Status</label><select id="vendorProductStatus" class="form-input"><option value="active">Active</option><option value="draft">Draft</option></select></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
        <button class="btn-outline-vendor" onclick="closeModal()">Cancel</button>
        <button class="btn-vendor" id="vendorSaveProductBtn" onclick="submitVendorProduct(event)">Save Product</button>
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

async function submitVendorProduct(event) {
  if (event && event.preventDefault) event.preventDefault();

  const saveBtn = document.getElementById('vendorSaveProductBtn');
  const payload = {
    product_name: String(document.getElementById('vendorProductName')?.value || '').trim(),
    category: String(document.getElementById('vendorProductCategory')?.value || '').trim(),
    price: Number(document.getElementById('vendorProductPrice')?.value || 0),
    discount_percentage: Number(document.getElementById('vendorProductDiscount')?.value || 0),
    quantity_in_stock: Number(document.getElementById('vendorProductStock')?.value || 0),
    sku: String(document.getElementById('vendorProductSku')?.value || '').trim(),
    product_description: String(document.getElementById('vendorProductDescription')?.value || '').trim(),
    primary_image_url: String(document.getElementById('vendorProductImage')?.value || '').trim(),
    status: String(document.getElementById('vendorProductStatus')?.value || 'active').trim().toLowerCase()
  };
  const imageFile = document.getElementById('vendorProductImageFile')?.files?.[0] || null;

  if (!payload.product_name || !payload.category || payload.price <= 0) {
    window.alert('Please enter product name, category, and a valid price.');
    return;
  }

  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    formData.append(key, String(value));
  });
  if (imageFile) {
    formData.append('product_image', imageFile);
  }

  if (typeof addVendorProduct !== 'function') {
    window.alert('Product service is not available right now.');
    return;
  }

  if (typeof authMe === 'function') {
    const identity = await authMe(false);
    const role = String(identity?.user?.role || '').toLowerCase();
    if (!identity || !identity.user || role !== 'vendor') {
      window.alert('Your vendor session is not active. Please sign in again as a vendor.');
      window.location.href = '../pages/index.html?reason=unauthorized';
      return;
    }
  }

  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
  }

  try {
    const result = await addVendorProduct(formData);
    if (!result || !result.success) {
      window.alert(result && result.message ? result.message : 'Failed to save product.');
      return;
    }

    await loadVendorDashboardData();
    allProducts = [...dashboardData.products];
    renderProducts();
    closeModal();
    window.alert('Product added successfully. It is now available in marketplace.');
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Product';
    }
  }
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
  const activeSupportPage = document.getElementById('page-support');
  if (!activeSupportPage || !activeSupportPage.classList.contains('active')) return;

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
let countdownIntervalId = null;

function startCountdown() {
  const target = new Date('2026-04-13T00:00:00');
  const el = document.getElementById('countdown');
  if (!el) return;

  if (countdownIntervalId) {
    clearInterval(countdownIntervalId);
    countdownIntervalId = null;
  }

  const update = () => {
    const diff = target - new Date();
    if (diff <= 0) {
      el.textContent = '🎉 Sale is LIVE!';
      if (countdownIntervalId) {
        clearInterval(countdownIntervalId);
        countdownIntervalId = null;
      }
      return;
    }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    el.textContent = `${d}d  ${String(h).padStart(2,'0')}h  ${String(m).padStart(2,'0')}m  ${String(s).padStart(2,'0')}s`;
  };
  update();
  countdownIntervalId = setInterval(update, 1000);
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
  // allProducts is already set inside loadVendorDashboardData
  onPageActivate('dashboard');

  const params = new URLSearchParams(window.location.search);
  if (params.get('payment') === 'success') {
    setTimeout(() => {
      window.alert('Premium Plan Activated! 💎\nThank you for upgrading. Your premium features are now available.');
    }, 500);
  }
});

console.log('%c BizLink Vendor Dashboard 🇱🇰 ', 'background: #50C878; color: white; font-size: 14px; padding: 8px 16px; border-radius: 4px;');
console.log('%c Built for Sri Lankan Vendors ', 'color: #FF8C00; font-size: 12px;');

/* ============ PRODUCT MANAGEMENT ============ */
let currentProductPage = 1;
let currentProductFilter = '';
let editingProductId = null;

async function loadVendorProducts(page = 1) {
  try {
    currentProductPage = page;
    const searchQuery = document.getElementById('productSearchInput')?.value || '';
    currentProductFilter = searchQuery;

    const params = new URLSearchParams({
      page: page,
      limit: 20,
      search: searchQuery
    });

    const response = await fetch(`../api/vendor_get_products.php?${params}`, {
      method: 'GET',
      credentials: 'include'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    
    if (!data.success) {
      showToast(data.message || 'Failed to load products', 'error');
      return;
    }

    displayVendorProducts(data.data.products, data.data.pagination);
  } catch (error) {
    console.error('Error loading products:', error);
    showToast('Error loading products', 'error');
  }
}

function displayVendorProducts(products, pagination) {
  const tbody = document.getElementById('productsTableBody');
  const countEl = document.getElementById('productCount');
  
  if (!tbody) return;

  if (products.length === 0) {
    tbody.innerHTML = `<tr style="text-align:center;"><td colspan="7" style="padding:2rem;color:var(--text-muted);">No products yet. <button class="btn-vendor" onclick="showModal('addProduct')" style="background:none;border:none;color:var(--vendor-color);text-decoration:underline;cursor:pointer;">Add one now</button></td></tr>`;
    countEl.textContent = '0 products';
    return;
  }

  tbody.innerHTML = products.map(p => `
    <tr>
      <td><strong>${escapeHtml(p.product_name)}</strong></td>
      <td>${escapeHtml(p.category)}</td>
      <td>Rs. ${(p.price).toFixed(2)}</td>
      <td>${p.stock_quantity}</td>
      <td><span class="badge ${p.is_active ? 'b-delivered' : 'b-pending'}">${p.is_active ? 'Active' : 'Inactive'}</span></td>
      <td>${new Date(p.created_at).toLocaleDateString()}</td>
      <td>
        <button class="action-btn" onclick="editProduct(${p.product_id})">Edit</button>
        <button class="action-btn" style="color:#ff6b6b;" onclick="deleteProduct(${p.product_id}, '${escapeHtml(p.product_name)}')">Delete</button>
      </td>
    </tr>
  `).join('');

  countEl.textContent = `${pagination.total} product${pagination.total !== 1 ? 's' : ''}`;

  // Pagination
  const paginationEl = document.getElementById('productsPagination');
  if (paginationEl && pagination.pages > 1) {
    let html = '';
    for (let i = 1; i <= pagination.pages; i++) {
      html += `<button class="btn-sm-vendor ${i === pagination.page ? 'active' : ''}" onclick="loadVendorProducts(${i})">${i}</button> `;
    }
    paginationEl.innerHTML = html;
  }
}

async function editProduct(productId) {
  try {
    const response = await fetch(`../api/vendor_get_products.php?search=&page=1&limit=999`, {
      method: 'GET',
      credentials: 'include'
    });

    if (!response.ok) throw new Error('Failed to fetch products');
    const data = await response.json();
    const product = data.data.products.find(p => p.product_id === productId);

    if (!product) {
      showToast('Product not found', 'error');
      return;
    }

    editingProductId = productId;
    document.getElementById('vendorProductName').value = product.product_name;
    document.getElementById('vendorProductCategory').value = product.category;
    document.getElementById('vendorProductPrice').value = product.price;
    document.getElementById('vendorProductStock').value = product.stock_quantity;
    document.getElementById('vendorProductDescription').value = product.description || '';
    document.getElementById('vendorProductImage').value = product.image_url || '';
    document.getElementById('vendorProductStatus').value = product.is_active ? 'active' : 'draft';
    
    document.getElementById('vendorSaveProductBtn').textContent = 'Update Product';
    showModal('addProduct');
  } catch (error) {
    console.error('Error editing product:', error);
    showToast('Error loading product details', 'error');
  }
}

async function submitVendorProduct(e) {
  e.preventDefault();
  
  const name = document.getElementById('vendorProductName')?.value.trim();
  const category = document.getElementById('vendorProductCategory')?.value;
  const price = parseFloat(document.getElementById('vendorProductPrice')?.value || 0);
  const stock = parseInt(document.getElementById('vendorProductStock')?.value || 0);
  const description = document.getElementById('vendorProductDescription')?.value.trim();
  const imageUrl = document.getElementById('vendorProductImage')?.value.trim();
  const isActive = document.getElementById('vendorProductStatus')?.value === 'active' ? 1 : 0;

  if (!name || !category || price <= 0 || stock < 0) {
    showToast('Please fill in all required fields correctly', 'error');
    return;
  }

  try {
    const btn = document.getElementById('vendorSaveProductBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const isEdit = editingProductId !== null;
    const endpoint = isEdit ? '../api/vendor_update_product.php' : '../api/vendor_create_product.php';
    
    const payload = {
      product_name: name,
      category: category,
      price: price,
      stock_quantity: stock,
      description: description,
      image_url: imageUrl,
      is_active: isActive
    };

    if (isEdit) {
      payload.product_id = editingProductId;
    }

    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await response.json();

    if (!data.success) {
      showToast(data.message || 'Failed to save product', 'error');
      return;
    }

    showToast(isEdit ? 'Product updated successfully' : 'Product created successfully', 'success');
    closeModal();
    editingProductId = null;
    document.getElementById('vendorSaveProductBtn').textContent = 'Save Product';
    loadVendorProducts(1);
  } catch (error) {
    console.error('Error saving product:', error);
    showToast('Error saving product', 'error');
  } finally {
    document.getElementById('vendorSaveProductBtn').disabled = false;
  }
}

async function deleteProduct(productId, productName) {
  if (!confirm(`Delete "${productName}"? This action cannot be undone.`)) {
    return;
  }

  try {
    const response = await fetch('../api/vendor_delete_product.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId })
    });

    const data = await response.json();

    if (!data.success) {
      showToast(data.message || 'Failed to delete product', 'error');
      return;
    }

    showToast('Product deleted successfully', 'success');
    loadVendorProducts(currentProductPage);
  } catch (error) {
    console.error('Error deleting product:', error);
    showToast('Error deleting product', 'error');
  }
}

/* ============ ORDER MANAGEMENT ============ */
let vendorOrdersCache = [];
let currentOrderFilter = 'all';

async function loadVendorOrders() {
  try {
    const response = await fetch('../api/get_vendor_orders.php', {
      method: 'GET',
      credentials: 'include'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    
    if (!data.success) {
      showToast(data.message || 'Failed to load orders', 'error');
      return;
    }

    vendorOrdersCache = data.data || [];
    filterVendorOrders(currentOrderFilter);
  } catch (error) {
    console.error('Error loading orders:', error);
    showToast('Error loading orders', 'error');
  }
}

function filterVendorOrders(status, btn = null) {
  currentOrderFilter = status;
  
  if (btn) {
    document.querySelectorAll('.otab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }

  let filtered = vendorOrdersCache;
  if (status !== 'all') {
    filtered = vendorOrdersCache.filter(o => o.status === status);
  }

  displayVendorOrders(filtered);
}

function displayVendorOrders(orders) {
  const tbody = document.getElementById('ordersTableBody');
  if (!tbody) return;

  if (orders.length === 0) {
    tbody.innerHTML = `<tr style="text-align:center;"><td colspan="8" style="padding:2rem;color:var(--text-muted);">No orders found</td></tr>`;
    return;
  }

  tbody.innerHTML = orders.map(o => {
    const statusColor = {
      'pending': 'b-pending',
      'processing': 'b-processing',
      'shipped': 'b-shipped',
      'delivered': 'b-delivered',
      'cancelled': 'b-cancelled'
    }[o.status] || 'b-pending';

    const statusLabel = o.status.charAt(0).toUpperCase() + o.status.slice(1);
    
    return `
      <tr>
        <td><strong>${o.order_number || `#${o.order_id}`}</strong></td>
        <td>${escapeHtml(o.customer_name || 'N/A')}</td>
        <td>${o.quantity || 1} item${o.quantity !== 1 ? 's' : ''}</td>
        <td>Rs. ${(o.total_amount || 0).toFixed(2)}</td>
        <td>${o.payment_status ? o.payment_status.charAt(0).toUpperCase() + o.payment_status.slice(1) : 'Pending'}</td>
        <td><span class="badge ${statusColor}">${statusLabel}</span></td>
        <td>${new Date(o.order_date).toLocaleDateString()}</td>
        <td>
          <button class="action-btn" onclick="showOrderDetails(${o.order_id})">View</button>
          ${o.status !== 'delivered' && o.status !== 'cancelled' ? `<button class="action-btn" onclick="updateOrderStatusModal(${o.order_id}, '${o.status}')">Update</button>` : ''}
        </td>
      </tr>
    `;
  }).join('');
}

async function updateOrderStatusModal(orderId, currentStatus) {
  const statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
  const nextStatuses = statuses.filter(s => s !== currentStatus && statuses.indexOf(s) > statuses.indexOf(currentStatus));

  if (nextStatuses.length === 0) {
    showToast('Order is already in final status', 'info');
    return;
  }

  const statusSelect = `
    <select id="newOrderStatus" class="form-input">
      ${nextStatuses.map(s => `<option value="${s}">${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
    </select>
  `;

  const html = `
    <div class="form-group">
      <label>Update Order Status</label>
      ${statusSelect}
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
      <button class="btn-outline-vendor" onclick="closeModal()">Cancel</button>
      <button class="btn-vendor" onclick="updateVendorOrderStatus(${orderId})">Update Status</button>
    </div>
  `;

  document.getElementById('modalTitle').textContent = '📦 Update Order Status';
  document.getElementById('modalContent').innerHTML = html;
  document.getElementById('modalBackdrop').classList.add('open');
}

async function updateVendorOrderStatus(orderId) {
  const newStatus = document.getElementById('newOrderStatus')?.value;
  
  if (!newStatus) {
    showToast('Please select a status', 'error');
    return;
  }

  try {
    const response = await fetch('../api/update_order_status.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderId,
        status: newStatus
      })
    });

    const data = await response.json();

    if (!data.success) {
      showToast(data.message || 'Failed to update order', 'error');
      return;
    }

    showToast('Order status updated successfully', 'success');
    closeModal();
    loadVendorOrders();
  } catch (error) {
    console.error('Error updating order:', error);
    showToast('Error updating order status', 'error');
  }
}

function showOrderDetails(orderId) {
  const order = vendorOrdersCache.find(o => o.order_id === orderId);
  if (!order) {
    showToast('Order not found', 'error');
    return;
  }

  const html = `
    <div style="padding:12px;border-left:3px solid var(--vendor-color);background:rgba(80,200,120,0.05);margin-bottom:16px;border-radius:4px;">
      <strong>${order.order_number || `Order #${order.order_id}`}</strong><br>
      <small>${new Date(order.order_date).toLocaleString()}</small>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
      <div><strong>Customer:</strong> ${escapeHtml(order.customer_name || 'N/A')}</div>
      <div><strong>Status:</strong> <span class="badge">${order.status.toUpperCase()}</span></div>
      <div><strong>Total Amount:</strong> Rs. ${(order.total_amount || 0).toFixed(2)}</div>
      <div><strong>Payment:</strong> ${order.payment_status ? order.payment_status.toUpperCase() : 'PENDING'}</div>
      <div><strong>Items:</strong> ${order.quantity || 1}</div>
      <div><strong>Ordered:</strong> ${new Date(order.order_date).toLocaleDateString()}</div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
      <button class="btn-outline-vendor" onclick="closeModal()">Close</button>
    </div>
  `;

  document.getElementById('modalTitle').textContent = '📋 Order Details';
  document.getElementById('modalContent').innerHTML = html;
  document.getElementById('modalBackdrop').classList.add('open');
}

/* ============ HELPERS ============ */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position:fixed;bottom:20px;right:20px;
    padding:12px 20px;border-radius:6px;
    background:${type === 'success' ? 'var(--vendor-color)' : type === 'error' ? '#ff6b6b' : '#4f8cff'};
    color:white;z-index:9999;
    font-size:14px;font-weight:600;animation:slideIn 0.3s ease;
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Add toast animations to head if not present
if (!document.querySelector('style[data-toast-anim]')) {
  const style = document.createElement('style');
  style.setAttribute('data-toast-anim', '1');
  style.textContent = `
    @keyframes slideIn { from { transform: translateX(400px); opacity:0; } to { transform: translateX(0); opacity:1; } }
    @keyframes slideOut { to { transform: translateX(400px); opacity:0; } }
  `;
  document.head.appendChild(style);
}

/* ============ VERIFICATION (KYC) ============ */

async function loadVerificationStatus() {
  try {
    const response = await fetch('../api/vendor_get_verification_status.php');
    const result = await response.json();

    if (!result.success) {
      showToast(result.message || 'Failed to load verification status', 'error');
      return;
    }

    const data = result.data;
    updateKycUI(data);
  } catch (error) {
    console.error('Error loading verification status:', error);
  }
}

function updateKycUI(data) {
  const statusBanner = document.getElementById('kycStatusBanner');
  const statusBadge = document.getElementById('kycStatusBadge');
  const statusTitle = document.getElementById('kycStatusTitle');
  const statusDesc = document.getElementById('kycStatusDesc');

  if (!statusBanner || !statusBadge) return;

  const status = data.kyc_status || 'not_started';
  
  // Reset
  statusBanner.className = 'verification-status-banner ' + status;
  statusBadge.textContent = status.replace('_', ' ').toUpperCase();

  if (status === 'verified') {
    statusTitle.textContent = 'Business Verified ✅';
    statusDesc.textContent = 'Congratulations! Your business is fully verified. You have full access to all BizLink features.';
    statusBanner.style.background = 'rgba(80, 200, 120, 0.15)';
    statusBanner.style.borderColor = 'var(--vendor-color)';
  } else if (status === 'pending') {
    statusTitle.textContent = 'Verification Under Review ⏳';
    statusDesc.textContent = 'Your documents have been submitted and are currently being reviewed by our team. This usually takes 1-3 business days.';
    statusBanner.style.background = 'rgba(255, 140, 0, 0.15)';
    statusBanner.style.borderColor = 'var(--accent-orange)';
  } else if (status === 'rejected') {
    statusTitle.textContent = 'Verification Rejected ❌';
    statusDesc.textContent = 'Some of your documents were rejected. Please review the reasons below and re-upload the correct documents.';
    statusBanner.style.background = 'rgba(255, 107, 107, 0.15)';
    statusBanner.style.borderColor = 'var(--accent-red)';
  }

  // Update individual documents
  const docs = data.documents || [];
  const types = ['business_license', 'tax_certificate', 'identity_proof'];
  
  types.forEach(type => {
    const doc = docs.find(d => d.document_type === type);
    const statusEl = document.getElementById(`status-${type}`);
    const rejectEl = document.getElementById(`reject-${type}`);
    const btnEl = document.querySelector(`[onclick="document.getElementById('file-${type}').click()"]`);

    if (doc) {
      if (statusEl) {
          statusEl.textContent = doc.status.toUpperCase();
          statusEl.className = 'doc-status ' + doc.status;
      }
      
      if (btnEl) {
          if (doc.status === 'verified') {
            btnEl.disabled = true;
            btnEl.textContent = 'Verified';
            if (rejectEl) rejectEl.classList.add('hidden');
          } else if (doc.status === 'rejected') {
            btnEl.textContent = 'Re-upload';
            if (rejectEl) {
                rejectEl.textContent = 'Reason: ' + (doc.rejection_reason || 'Invalid document');
                rejectEl.classList.remove('hidden');
            }
          } else {
            btnEl.textContent = 'Replace';
            if (rejectEl) rejectEl.classList.add('hidden');
          }
      }
    }
  });
}

async function uploadDoc(type) {
  const fileInput = document.getElementById(`file-${type}`);
  if (!fileInput.files.length) return;

  const file = fileInput.files[0];
  const formData = new FormData();
  formData.append('document', file);
  formData.append('document_type', type);

  // Get CSRF token if needed
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  
  try {
    showToast(`Uploading ${type.replace('_', ' ')}...`, 'info');
    
    const response = await fetch('../api/vendor_upload_document.php', {
      method: 'POST',
      body: formData,
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
    });

    const result = await response.json();

    if (result.success) {
      showToast('Document uploaded successfully!', 'success');
      loadVerificationStatus();
    } else {
      showToast(result.message || 'Upload failed', 'error');
    }
  } catch (error) {
    console.error('Error uploading document:', error);
    showToast('An error occurred during upload', 'error');
  } finally {
    fileInput.value = ''; // Reset input
  }
}
