/* BIZLINK ADMIN PANEL (panel.js)
Admin Dashboard & Navigation Logic*/

/* ── State ── */
const state = {
  currentPage: 'dashboard',
  sidebarOpen: true,
  theme: localStorage.getItem('adminTheme') || 'light',
  notifications: [],
  notificationUserEmail: ''
};

function buildInitials(fullName, fallback = 'A') {
  return String(fullName || fallback)
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || fallback;
}

function applyAdminIdentity(user, profile = {}) {
  const fullName = String(user?.full_name || 'Admin').trim() || 'Admin';
  const firstName = fullName.split(' ')[0] || 'Admin';
  const email = String(user?.email || '').trim().toLowerCase();
  const phone = String(user?.phone || '').trim();
  const role = String(user?.role || 'admin').trim().toLowerCase();
  const adminLevel = String(profile?.admin_level || '').trim();
  const roleLabel = adminLevel || (role.charAt(0).toUpperCase() + role.slice(1));
  const businessName = 'BizLink CRM';
  const initials = buildInitials(fullName, 'A');

  state.notificationUserEmail = email;

  document.querySelectorAll('.sb-avatar, .tb-avatar, .um-avatar').forEach((el) => {
    el.textContent = initials;
  });

  const sidebarName = document.querySelector('.sb-user-name');
  const sidebarRole = document.querySelector('.sb-user-role');
  const userMenuName = document.querySelector('.um-profile strong');
  const userMenuEmail = document.querySelector('.um-profile span');
  const welcomeTitle = document.querySelector('.page-header .page-title');

  if (sidebarName) sidebarName.textContent = fullName;
  if (sidebarRole) sidebarRole.textContent = `${roleLabel} · ${businessName}`;
  if (userMenuName) userMenuName.textContent = fullName;
  if (userMenuEmail) userMenuEmail.textContent = email || '-';
  if (welcomeTitle && welcomeTitle.textContent.includes('Good morning')) {
    welcomeTitle.textContent = `Good morning, ${firstName} 👋`;
  }

  const settingsFullName = document.getElementById('adminSettingsFullName');
  const settingsEmail = document.getElementById('adminSettingsEmail');
  const settingsPhone = document.getElementById('adminSettingsPhone');
  const settingsRole = document.getElementById('adminSettingsRole');
  const settingsBusiness = document.getElementById('adminSettingsBusinessName');

  if (settingsFullName) settingsFullName.value = fullName;
  if (settingsEmail) settingsEmail.value = email || '';
  if (settingsPhone) settingsPhone.value = phone || '';
  if (settingsRole) settingsRole.value = roleLabel;
  if (settingsBusiness) settingsBusiness.value = businessName;
}

async function initializeAdminIdentity() {
  if (typeof authMe !== 'function') return;

  const identity = await authMe();
  if (!identity || !identity.user) {
    window.location.href = '../pages/index.html?reason=unauthorized';
    return;
  }

  const role = String(identity.user.role || '').toLowerCase();
  if (role !== 'admin') {
    const redirectMap = {
      admin: '../admin/dashboard.html',
      vendor: '../vendor/vendorpanel.html',
      customer: '../customer/dashboard.html'
    };
    window.location.href = redirectMap[role] || '../pages/index.html?reason=unauthorized';
    return;
  }

  applyAdminIdentity(identity.user, identity.profile || {});
}

const adminDashboardCache = {
  vendors: [],
  customers: [],
  orders: []
};

function renderTableState(tbody, colCount, text) {
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align:center;padding:20px;color:#888;">${text}</td></tr>`;
}

function renderBlockState(container, text) {
  if (!container) return;
  container.innerHTML = `<div class="widget-state empty">${text}</div>`;
}

/* NAVIGATION */
function navigate(page, el) {
  // Hide all pages
  document.querySelectorAll('.page').forEach(p => p.classList.add('hidden'));
  
  // Show target page
  const targetPage = document.getElementById(`page-${page}`);
  if (targetPage) {
    targetPage.classList.remove('hidden');
    state.currentPage = page;
    
    // Update sidebar active
    document.querySelectorAll('.sb-nav-item').forEach(item => item.classList.remove('active'));
    if (el) el.classList.add('active');
    
    // Update breadcrumb
    const pageNames = {
      dashboard: 'Dashboard',
      vendors: 'Vendors',
      customers: 'Customers',
      orders: 'Orders',
      analytics: 'Analytics',
      reports: 'Reports',
      marketplace: 'Marketplace',
      revenue: 'Revenue',
      invoices: 'Invoices',
      users: 'User Roles',
      settings: 'Settings',
      logs: 'Activity Logs'
    };
    document.getElementById('bcPage').textContent = pageNames[page] || 'Dashboard';
    
    // Populate page-specific content
    if (page === 'dashboard') renderDashboard();
    else if (page === 'vendors') renderVendorsTable();
    else if (page === 'customers') renderCustomersTable();
    else if (page === 'orders') renderOrdersTable();
    else if (page === 'analytics') renderAnalytics();
    else if (page === 'reports') renderReports();
  }
}

/* SIDEBAR TOGGLE */
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const mainWrap = document.getElementById('mainWrap');
  state.sidebarOpen = !state.sidebarOpen;
  sidebar.classList.toggle('open');
  if (window.innerWidth <= 900) {
    sidebar.classList.toggle('open');
  }
}

/* THEME TOGGLE */
function toggleTheme() {
  const body = document.body;
  const isDark = body.classList.toggle('dark');
  state.theme = isDark ? 'dark' : 'light';
  localStorage.setItem('adminTheme', state.theme);
  
  const themeBtn = document.getElementById('themeBtn');
  themeBtn.style.transform = 'rotate(180deg)';
  setTimeout(() => themeBtn.style.transform = '', 300);
}

// Apply saved theme on load
document.addEventListener('DOMContentLoaded', async () => {
  if (state.theme === 'dark') {
    document.body.classList.add('dark');
  }

  await initializeAdminIdentity();
  updateDate();
  await Promise.all([renderDashboard(), loadAdminNotifications()]);
  initActivityFeed();
  setInterval(updateDate, 60000);
});

/* DATE & TIME */
function updateDate() {
  const options = { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  const now = new Date().toLocaleDateString('en-US', options);
  document.getElementById('tbDate').textContent = now;
}

/* NOTIFICATIONS */
function toggleNotif() {
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
  document.getElementById('userMenu').classList.remove('open');
}

function getNotificationIcon(notification) {
  const type = notification.notification_type || 'system';
  if (type === 'order_status') return '📦';
  if (type === 'payment') return '💰';
  if (type === 'message') return '💬';
  if (type === 'review') return '⭐';
  if (type === 'commission') return '📈';
  if (type === 'promotion') return '🎉';
  return '🔔';
}

function getNotificationTone(notification) {
  if ((notification.priority || '').toLowerCase() === 'high') return 'amber';
  if ((notification.notification_type || '') === 'payment') return 'green';
  return 'navy';
}

function updateNotificationBadge() {
  const badge = document.querySelector('.notif-dot');
  if (!badge) return;

  const unreadCount = state.notifications.filter((notification) => !notification.is_read).length;
  badge.textContent = unreadCount;
  badge.style.display = unreadCount > 0 ? 'inline-flex' : 'none';
}

async function loadAdminNotifications() {
  if (typeof getNotifications !== 'function') {
    state.notifications = [];
    renderNotifications();
    updateNotificationBadge();
    return;
  }

  try {
    const response = await getNotifications({ email: state.notificationUserEmail, limit: 6 });
    if (response) {
      state.notifications = response.data || [];
      renderNotifications();
      updateNotificationBadge();
      return;
    }
  } catch (error) {
    console.error('Failed to load admin notifications:', error);
  }

  state.notifications = [];
  renderNotifications();
  updateNotificationBadge();
}

function renderNotifications() {
  const list = document.getElementById('notifList');
  if (!list) return;

  if (state.notifications.length === 0) {
    list.innerHTML = '<div class="np-item"><div class="np-text"><strong>All caught up</strong><span>No unread notifications right now.</span></div></div>';
    updateNotificationBadge();
    return;
  }

  list.innerHTML = state.notifications.map(n => `
    <div class="np-item ${n.is_read ? '' : 'is-clickable'}" onclick="markAdminNotificationRead(${Number(n.notification_id || 0)})" style="cursor:${n.is_read ? 'default' : 'pointer'};opacity:${n.is_read ? '0.8' : '1'};">
      <div class="np-icon ${getNotificationTone(n)}">${getNotificationIcon(n)}</div>
      <div class="np-text">
        <strong>${n.title}</strong>
        <span>${n.message}</span>
      </div>
    </div>
  `).join('');

  updateNotificationBadge();
}

async function markAdminNotificationRead(notificationId) {
  if (!notificationId) return;
  const target = state.notifications.find((notification) => Number(notification.notification_id) === Number(notificationId));
  if (!target || target.is_read) return;

  if (typeof markNotificationsRead === 'function') {
    const result = await markNotificationsRead({
      email: state.notificationUserEmail,
      notification_id: Number(notificationId)
    });

    if (result && result.success) {
      target.is_read = true;
      renderNotifications();
      return;
    }
  }

  target.is_read = true;
  renderNotifications();
}

async function clearNotifs() {
  if (typeof markNotificationsRead === 'function') {
    const result = await markNotificationsRead({
      email: state.notificationUserEmail,
      mark_all: true
    });

    if (result && result.success) {
      state.notifications = [];
      renderNotifications();
      showToast('Notifications marked as read', 'success');
      return;
    }
  }

  state.notifications = [];
  renderNotifications();
  showToast('Notifications cleared', 'success');
}

/* USER MENU */
function toggleUserMenu() {
  const menu = document.getElementById('userMenu');
  const panel = document.getElementById('notifPanel');
  menu.classList.toggle('open');
  panel.classList.remove('open');
}

/* LOGOUT */
function confirmLogout(event) {
  if (event) event.preventDefault();
  document.getElementById('logoutBackdrop').style.display = 'flex';
}

function cancelLogout(event) {
  if (event && event.target.id !== 'logoutBackdrop') return;
  document.getElementById('logoutBackdrop').style.display = 'none';
}

async function performLogout() {
  try {
    if (typeof authLogout === 'function') {
      await authLogout();
    }
  } catch (error) {
    console.warn('Logout request failed, redirecting anyway:', error);
  }

  window.location.href = '../pages/index.html';
}

/* DASHBOARD RENDERING */
async function renderDashboard() {
  renderTableState(document.getElementById('ordersBody'), 6, 'Loading recent orders...');
  renderBlockState(document.getElementById('approvalList'), 'Loading pending approvals...');
  renderBlockState(document.getElementById('topVendors'), 'Loading top vendors...');

  // Fetch real stats from database
  try {
    const s = await getDashboardStats();
    if (s) {
      // Update KPI card values and data-count attributes with real data
      const kpiCards = document.querySelectorAll('.kpi-card');
      const kpiData = [
        { value: s.total_revenue, format: 'currency' },
        { value: s.active_customers, format: 'number' },
        { value: s.total_orders, format: 'number' },
        { value: s.active_vendors, format: 'number' }
      ];
      kpiCards.forEach((card, i) => {
        if (kpiData[i]) {
          const el = card.querySelector('.kpi-value');
          if (el) {
            el.setAttribute('data-count', kpiData[i].value);
          }
          // Update sublabel for pending orders
          if (i === 2) {
            const sub = card.querySelector('.kpi-sublabel');
            if (sub) sub.textContent = s.pending_orders + ' pending fulfillment';
          }
        }
      });
    }
  } catch (e) {
    console.warn('Could not fetch dashboard stats, using defaults:', e);
  }

  try {
    const [vendors, customers, orders] = await Promise.all([getVendors(), getCustomers(), getOrders()]);
    adminDashboardCache.vendors = vendors || [];
    adminDashboardCache.customers = customers || [];
    adminDashboardCache.orders = orders || [];
  } catch (error) {
    console.warn('Could not hydrate admin dashboard cache:', error);
    adminDashboardCache.vendors = [];
    adminDashboardCache.customers = [];
    adminDashboardCache.orders = [];
  }

  // Animate KPI values
  animateCounters();
  
  // Render charts
  renderRevenueChart();
  renderDonutChart();
  
  // Render tables
  await renderRecentOrders();
  await renderApprovalList();
  await renderTopVendors();
  
  // Render activity feed
  await renderActivityFeed();
}

/* ── KPI Counter Animation ── */
function animateCounters() {
  const targets = {
    'spark1': 4820000,
    'spark2': 12480,
    'spark3': 3847,
    'spark4': 320
  };
  
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseInt(el.dataset.count);
    let current = 0;
    const step = target / 50;
    
    const timer = setInterval(() => {
      current += step;
      if (current >= target) {
        current = target;
        clearInterval(timer);
      }
      
      if (target > 1000000) {
        el.textContent = 'Rs. ' + (current / 1000000).toFixed(2) + 'M';
      } else if (target > 1000) {
        el.textContent = current.toLocaleString('en-IN', { maximumFractionDigits: 0 });
      } else {
        el.textContent = Math.round(current);
      }
    }, 30);
  });
  
  // Render sparklines
  ['spark1', 'spark2', 'spark3', 'spark4'].forEach((id, i) => {
    const container = document.getElementById(id);
    if (!container) return;
    container.innerHTML = '';
    
    const data = [45, 52, 38, 88, 72, 95, 68, 41, 76, 92, 85, 78];
    const max = Math.max(...data);
    
    data.forEach(val => {
      const bar = document.createElement('div');
      bar.className = 'spark-bar';
      bar.style.height = ((val / max) * 28) + 'px';
      container.appendChild(bar);
    });
  });
}

/* ── Revenue Chart ── */
function renderRevenueChart() {
  const container = document.getElementById('revenueChart');
  if (!container) return;
  
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const data = [420, 680, 520, 890, 720, 950, 1180, 1040, 1320, 1520, 1410, 1680];
  const max = Math.max(...data);
  
  const labelsContainer = document.getElementById('revenueLabels');
  if (labelsContainer) {
    labelsContainer.innerHTML = months.map(m => `<div class="bl-label">${m}</div>`).join('');
  }
  
  container.innerHTML = data.map((val, i) => `
    <div class="bc-bar-group">
      <div class="bc-bar" style="height:${(val/max)*160}px;background:linear-gradient(135deg,#000080,#0000b3);" data-tip="Rs. ${(val/100).toFixed(1)}K"></div>
    </div>
  `).join('');
}

/* ── Donut Chart ── */
function renderDonutChart() {
  const svg = document.getElementById('donutSvg');
  if (!svg) return;
  
  const categories = [
    { name: 'Electronics', value: 28, color: '#000080' },
    { name: 'Fashion', value: 22, color: '#50C878' },
    { name: 'Grocery', value: 18, color: '#FF8C00' },
    { name: 'Others', value: 32, color: '#94a3b8' }
  ];
  
  let currentAngle = 0;
  svg.innerHTML = '';
  
  const legend = document.getElementById('donutLegend');
  legend.innerHTML = '';
  
  categories.forEach((cat, i) => {
    const sliceAngle = (cat.value / 100) * 360;
    const rad1 = (currentAngle * Math.PI) / 180;
    const rad2 = ((currentAngle + sliceAngle) * Math.PI) / 180;
    
    const x1 = 60 + 35 * Math.cos(rad1);
    const y1 = 60 + 35 * Math.sin(rad1);
    const x2 = 60 + 35 * Math.cos(rad2);
    const y2 = 60 + 35 * Math.sin(rad2);
    
    const largeArc = sliceAngle > 180 ? 1 : 0;
    
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', `M60,60 L${x1},${y1} A35,35 0 ${largeArc},1 ${x2},${y2} Z`);
    path.setAttribute('fill', cat.color);
    path.setAttribute('opacity', '0.85');
    svg.appendChild(path);
    
    const legendItem = document.createElement('div');
    legendItem.className = 'dl-item';
    legendItem.innerHTML = `
      <div class="dl-dot" style="background:${cat.color}"></div>
      <div class="dl-name">${cat.name}</div>
      <div class="dl-pct">${cat.value}%</div>
    `;
    legend.appendChild(legendItem);
    
    currentAngle += sliceAngle;
  });
}

/* TABLES */
async function renderRecentOrders() {
  const tbody = document.getElementById('ordersBody');
  if (!tbody) return;
  
  try {
    const res = await fetch('http://localhost/bizlink-crm-platform/api/get_orders.php');
    const json = await res.json();
    if (json.success && json.data.length > 0) {
      tbody.innerHTML = json.data.slice(0, 5).map(o => {
        const timeAgo = getTimeAgo(o.created_at);
        const status = o.order_status.charAt(0).toUpperCase() + o.order_status.slice(1);
        return `
          <tr>
            <td>${o.order_number}</td>
            <td>${o.customer_name || 'N/A'}</td>
            <td>${o.email || 'N/A'}</td>
            <td>Rs. ${Number(o.total_amount).toLocaleString()}</td>
            <td><span class="status-badge badge-${o.order_status}">${status}</span></td>
            <td>${timeAgo}</td>
          </tr>
        `;
      }).join('');
      return;
    }
  } catch (e) {
    console.warn('Could not fetch orders from API:', e);
  }
  
  // Show empty-state row when API is unavailable
  renderTableState(tbody, 6, 'No orders found in database');
}

function getTimeAgo(dateStr) {
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return mins + ' min ago';
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return hrs + ' hours ago';
  const days = Math.floor(hrs / 24);
  return days + ' days ago';
}

async function renderApprovalList() {
  const list = document.getElementById('approvalList');
  if (!list) return;

  let vendors = adminDashboardCache.vendors;
  if (!vendors || vendors.length === 0) {
    try {
      vendors = await getVendors();
      adminDashboardCache.vendors = vendors || [];
    } catch (error) {
      console.warn('Could not load approval list vendors:', error);
      vendors = [];
    }
  }

  const pendingVendors = (vendors || [])
    .filter((vendor) => String(vendor.vendor_status || '').toLowerCase() === 'pending')
    .slice(0, 4)
    .map((vendor, index) => ({
      emoji: ['🏪', '👗', '🍜', '🛠️'][index % 4],
      name: vendor.shop_name || 'Vendor',
      cat: vendor.business_category || 'General'
    }));

  if (pendingVendors.length === 0) {
    list.innerHTML = '<div class="approval-item"><div class="ai-info"><span class="ai-name">No pending approvals</span><span class="ai-cat">All vendor applications are up to date</span></div></div>';
    return;
  }

  list.innerHTML = pendingVendors.map(v => `
    <div class="approval-item">
      <div class="ai-avatar">${v.emoji}</div>
      <div class="ai-info">
        <span class="ai-name">${v.name}</span>
        <span class="ai-cat">${v.cat}</span>
      </div>
      <div class="ai-actions">
        <button class="ai-btn ok" onclick="approveVendor('${v.name}')">✓</button>
        <button class="ai-btn no" onclick="rejectVendor('${v.name}')">✕</button>
      </div>
    </div>
  `).join('');
}

async function renderTopVendors() {
  const container = document.getElementById('topVendors');
  if (!container) return;

  let orders = adminDashboardCache.orders;
  if (!orders || orders.length === 0) {
    try {
      orders = await getOrders();
      adminDashboardCache.orders = orders || [];
    } catch (error) {
      console.warn('Could not load top vendors:', error);
      orders = [];
    }
  }

  const revenueByVendor = {};
  (orders || []).forEach((order) => {
    const name = order.vendor_name || 'Unknown Vendor';
    const category = order.vendor_category || 'General';
    if (!revenueByVendor[name]) {
      revenueByVendor[name] = { category, revenue: 0 };
    }
    revenueByVendor[name].revenue += Number(order.total_amount || 0);
  });

  const topVendors = Object.entries(revenueByVendor)
    .sort((a, b) => b[1].revenue - a[1].revenue)
    .slice(0, 3)
    .map(([name, meta], index, arr) => {
      const max = arr.length > 0 ? arr[0][1].revenue : 1;
      return {
        rank: index + 1,
        name,
        cat: meta.category,
        amount: `Rs. ${Math.round(meta.revenue).toLocaleString()}`,
        pct: Math.max(10, Math.round((meta.revenue / Math.max(max, 1)) * 100))
      };
    });

  if (topVendors.length === 0) {
    container.innerHTML = '<div class="tv-item"><div class="tv-info"><span class="tv-name">No vendor sales data yet</span><span class="tv-cat">Data appears after orders are placed</span></div></div>';
    return;
  }

  container.innerHTML = topVendors.map(v => `
    <div class="tv-item">
      <div class="tv-rank">${v.rank}</div>
      <div class="tv-info">
        <span class="tv-name">${v.name}</span>
        <span class="tv-cat">${v.cat}</span>
      </div>
      <div class="tv-bar-col">
        <span class="tv-amount">${v.amount}</span>
        <div class="tv-bar-mini"><div class="tv-bar-fill" style="width:${v.pct}%"></div></div>
      </div>
    </div>
  `).join('');
}

/* VENDORS TABLE */
async function renderVendorsTable() {
  const tbody = document.getElementById('vendorsBody');
  if (!tbody) return;
  
  try {
    const res = await fetch('http://localhost/bizlink-crm-platform/api/get_vendors.php');
    const json = await res.json();
    if (json.success && json.data.length > 0) {
      tbody.innerHTML = json.data.map(v => {
        const status = v.vendor_status === 'verified' ? 'Active' : v.vendor_status.charAt(0).toUpperCase() + v.vendor_status.slice(1);
        const statusClass = v.vendor_status === 'verified' ? 'active' : v.vendor_status;
        return `
          <tr>
            <td><input type="checkbox"/></td>
            <td><strong>${v.shop_name}</strong></td>
            <td>—</td>
            <td>—</td>
            <td>—</td>
            <td>${v.total_products}</td>
            <td>—</td>
            <td><span class="status-badge badge-${statusClass}">${status}</span></td>
            <td>
              <button class="tbl-action view" onclick="viewVendor('${v.shop_name}')">View</button>
              ${v.vendor_status === 'pending' ? '<button class="tbl-action approve" onclick="approveVendor(\'' + v.shop_name + '\')">Approve</button>' : ''}
            </td>
          </tr>
        `;
      }).join('');
      return;
    }
  } catch (e) {
    console.warn('Could not fetch vendors from API:', e);
  }
  
  renderTableState(tbody, 9, 'No vendors found in database');
}

function filterVendorTable(query) {
  const tbody = document.getElementById('vendorsBody');
  if (!tbody) return;
  
  const rows = tbody.querySelectorAll('tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
  });
}

function filterVendorStatus(status) {
  showToast(`Filtering by: ${status}`, 'info');
}

/* CUSTOMERS TABLE */
async function renderCustomersTable() {
  const tbody = document.getElementById('customersBody');
  if (!tbody) return;
  
  try {
    const res = await fetch('http://localhost/bizlink-crm-platform/api/get_customers.php');
    const json = await res.json();
    if (json.success && json.data.length > 0) {
      tbody.innerHTML = json.data.map(c => {
        const status = c.customer_status === 'active' ? 'Active' : (c.customer_status || 'N/A');
        const joined = c.created_at ? new Date(c.created_at).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) : '—';
        return `
          <tr>
            <td><input type="checkbox"/></td>
            <td><strong>${c.full_name || 'N/A'}</strong></td>
            <td>${c.email || '—'}</td>
            <td>${c.city || '—'}</td>
            <td>${c.total_orders || 0}</td>
            <td>Rs. ${Number(c.total_spent || 0).toLocaleString()}</td>
            <td>${joined}</td>
            <td><span class="status-badge badge-active">${status}</span></td>
          </tr>
        `;
      }).join('');
      return;
    }
  } catch (e) {
    console.warn('Could not fetch customers from API:', e);
  }
  
  renderTableState(tbody, 8, 'No customers found in database');
}

/* ORDERS TABLE */
async function renderOrdersTable() {
  const tbody = document.getElementById('ordersFullBody');
  if (!tbody) return;
  
  try {
    const res = await fetch('http://localhost/bizlink-crm-platform/api/get_orders.php');
    const json = await res.json();
    if (json.success && json.data.length > 0) {
      tbody.innerHTML = json.data.map(o => {
        const status = o.order_status.charAt(0).toUpperCase() + o.order_status.slice(1);
        const date = new Date(o.created_at).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
        return `
          <tr>
            <td><input type="checkbox"/></td>
            <td><strong>${o.order_number}</strong></td>
            <td>${o.customer_name || 'N/A'}</td>
            <td>—</td>
            <td>${o.email || '—'}</td>
            <td>Rs. ${Number(o.total_amount).toLocaleString()}</td>
            <td>—</td>
            <td><span class="status-badge badge-${o.order_status}">${status}</span></td>
            <td>${date}</td>
          </tr>
        `;
      }).join('');
      return;
    }
  } catch (e) {
    console.warn('Could not fetch orders from API:', e);
  }
  
  renderTableState(tbody, 9, 'No orders found in database');
}

/* ANALYTICS */
function renderAnalytics() {
  renderCustomerGrowthChart();
  renderProvinceList();
}

async function renderCustomerGrowthChart() {
  const container = document.getElementById('customerGrowthChart');
  if (!container) return;

  let customers = adminDashboardCache.customers;
  if (!customers || customers.length === 0) {
    try {
      customers = await getCustomers();
      adminDashboardCache.customers = customers || [];
    } catch (error) {
      console.warn('Could not load customer growth data:', error);
      customers = [];
    }
  }

  const monthlyCounts = new Array(12).fill(0);
  const currentYear = new Date().getFullYear();
  (customers || []).forEach((customer) => {
    const createdAt = customer.created_at ? new Date(customer.created_at) : null;
    if (!createdAt || Number.isNaN(createdAt.getTime())) return;
    if (createdAt.getFullYear() !== currentYear) return;
    monthlyCounts[createdAt.getMonth()] += 1;
  });

  if (!monthlyCounts.some((n) => n > 0)) {
    renderBlockState(container, 'No customer growth data available yet.');
    return;
  }

  const data = monthlyCounts;
  const max = Math.max(...data);
  
  const labelsContainer = document.getElementById('customerGrowthLabels');
  if (labelsContainer) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    labelsContainer.innerHTML = months.map(m => `<div class="bl-label">${m}</div>`).join('');
  }
  
  container.innerHTML = data.map(val => `
    <div class="bc-bar-group">
      <div class="bc-bar" style="height:${(val/max)*160}px;background:linear-gradient(135deg,#50C878,#3d8c5f);" data-tip="${val} customers"></div>
    </div>
  `).join('');
}

async function renderProvinceList() {
  const container = document.getElementById('provinceList');
  if (!container) return;

  let customers = adminDashboardCache.customers;
  if (!customers || customers.length === 0) {
    try {
      customers = await getCustomers();
      adminDashboardCache.customers = customers || [];
    } catch (error) {
      console.warn('Could not load province list data:', error);
      customers = [];
    }
  }

  const provinceLookup = {
    colombo: 'Western', gampaha: 'Western', kalutara: 'Western',
    kandy: 'Central', matale: 'Central', nuwaraeliya: 'Central',
    galle: 'Southern', matara: 'Southern', hambantota: 'Southern',
    jaffna: 'Northern', kilinochchi: 'Northern', mannar: 'Northern', mullaitivu: 'Northern', vavuniya: 'Northern',
    anuradhapura: 'North Central', polonnaruwa: 'North Central',
    kurunegala: 'North Western', puttalam: 'North Western',
    ratnapura: 'Sabaragamuwa', kegalle: 'Sabaragamuwa',
    badulla: 'Uva', monaragala: 'Uva',
    trincomalee: 'Eastern', batticaloa: 'Eastern', ampara: 'Eastern'
  };

  const counts = {};
  (customers || []).forEach((customer) => {
    const city = String(customer.city || '').toLowerCase().replace(/\s+/g, '');
    const province = provinceLookup[city] || 'Other';
    counts[province] = (counts[province] || 0) + 1;
  });

  const provinces = Object.entries(counts)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    .map(([name, users]) => ({ name, users }));
  const maxUsers = provinces.length ? provinces[0].users : 1;

  if (provinces.length === 0) {
    renderBlockState(container, 'No province distribution data available yet.');
    return;
  }

  const finalProvinces = provinces.map((p) => ({ ...p, pct: Math.max(8, Math.round((p.users / maxUsers) * 100)) }));

  container.innerHTML = finalProvinces.map(p => `
    <div class="prov-item">
      <div class="prov-row">
        <span class="prov-name">${p.name}</span>
        <span class="prov-num">${p.users}</span>
      </div>
      <div class="prov-bar"><div class="prov-fill" style="width:${p.pct}%;background:#50C878;"></div></div>
    </div>
  `).join('');
}

/* REPORTS */
function renderReports() {
  const container = document.getElementById('reportsGrid');
  if (!container) return;
  
  const reports = [
    { icon: '📊', title: 'Sales Report', desc: 'Detailed breakdown of monthly sales by category and vendor', meta: 'Updated daily' },
    { icon: '👥', title: 'Customer Analytics', desc: 'Demographics, behavior, and retention metrics', meta: 'Weekly update' },
    { icon: '💰', title: 'Revenue Report', desc: 'Commission tracking and payout status', meta: 'Real-time' },
    { icon: '📦', title: 'Inventory Report', desc: 'Product stock levels and fulfillment metrics', meta: 'Hourly sync' },
    { icon: '⚠️', title: 'Compliance Report', desc: 'Vendor verification and policy violations', meta: 'Manual review' },
    { icon: '🌐', title: 'Market Insights', desc: 'Trends, competitive analysis, growth opportunities', meta: 'Monthly' }
  ];
  
  container.innerHTML = reports.map(r => `
    <div class="report-card" onclick="showToast('Opening ${r.title}...', 'info')">
      <div class="rc-icon">${r.icon}</div>
      <div class="rc-title">${r.title}</div>
      <div class="rc-desc">${r.desc}</div>
      <div class="rc-meta">${r.meta}</div>
    </div>
  `).join('');
}

/* ACTIVITY FEED */
function initActivityFeed() {
  renderActivityFeed();
  // Refresh activity from live data every 15 seconds
  setInterval(() => {
    if (state.currentPage === 'dashboard') {
      renderActivityFeed();
    }
  }, 15000);
}

async function renderActivityFeed() {
  const container = document.getElementById('activityFeed');
  if (!container) return;

  let stats = null;
  try {
    stats = await getDashboardStats();
  } catch (error) {
    console.warn('Could not load activity stats:', error);
  }

  const pendingVendors = adminDashboardCache.vendors.filter((vendor) => String(vendor.vendor_status || '').toLowerCase() === 'pending').length;
  const today = new Date().toISOString().split('T')[0];
  const todaysOrders = adminDashboardCache.orders.filter((order) => String(order.created_at || order.order_date || '').startsWith(today)).length;
  const todaysRevenue = adminDashboardCache.orders
    .filter((order) => String(order.created_at || order.order_date || '').startsWith(today))
    .reduce((sum, order) => sum + Number(order.total_amount || 0), 0);

  const activities = [
    { icon: '✅', msg: `<strong>${stats?.active_vendors || adminDashboardCache.vendors.length}</strong> active vendors on platform`, type: 'green', time: 'Just now' },
    { icon: '🛒', msg: `<strong>${todaysOrders}</strong> orders placed today`, type: 'navy', time: 'Live' },
    { icon: '👤', msg: `<strong>${stats?.active_customers || adminDashboardCache.customers.length}</strong> active customers this month`, type: 'amber', time: 'Live' },
    { icon: '⏳', msg: `<strong>${pendingVendors}</strong> vendors pending verification`, type: 'amber', time: 'Live' },
    { icon: '💰', msg: `Today's revenue at <strong>Rs. ${Math.round(todaysRevenue).toLocaleString()}</strong>`, type: 'green', time: 'Live' }
  ];
  
  container.innerHTML = activities.map(a => `
    <div class="af-item">
      <div class="af-icon" style="background:${a.type==='green'?'#f0fdf4':a.type==='amber'?'#fffbeb':'#f0f0fa'};color:${a.type==='green'?'#16a34a':a.type==='amber'?'#d97706':'#000080'};">${a.icon}</div>
      <div class="af-msg">${a.msg}</div>
      <div class="af-time">${a.time}</div>
    </div>
  `).join('');
}

/* MODAL FUNCTIONS */
function showNewVendorModal() {
  const backdrop = document.getElementById('modalBackdrop');
  const body = document.getElementById('modalBody');
  
  body.innerHTML = `
    <div class="sf-group">
      <label>Business Name</label>
      <input type="text" class="sf-input" placeholder="e.g. TechZone Lanka"/>
    </div>
    <div class="sf-group">
      <label>Business Registration No.</label>
      <input type="text" class="sf-input" placeholder="e.g. PV/00123"/>
    </div>
    <div class="sf-group">
      <label>Email Address</label>
      <input type="email" class="sf-input" placeholder="contact@vendor.com"/>
    </div>
    <div class="sf-group">
      <label>Industry</label>
      <select class="sf-input sf-select">
        <option>Electronics</option>
        <option>Fashion</option>
        <option>Grocery</option>
        <option>Agriculture</option>
        <option>IT Services</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn-outline" onclick="closeModalDirect()">Cancel</button>
      <button class="btn-primary" onclick="submitVendorForm()">Add Vendor</button>
    </div>
  `;
  
  backdrop.classList.add('open');
}

function closeModal(event) {
  if (event.target.id === 'modalBackdrop') {
    closeModalDirect();
  }
}

function closeModalDirect() {
  document.getElementById('modalBackdrop').classList.remove('open');
}

function submitVendorForm() {
  showToast('Vendor added successfully!', 'success');
  closeModalDirect();
  renderVendorsTable();
}

/* ACTION FUNCTIONS */
function approveVendor(vendorName) {
  showToast(`✅ ${vendorName} approved!`, 'success');
  renderApprovalList();
  renderVendorsTable();
}

function rejectVendor(vendorName) {
  showToast(`❌ ${vendorName} rejected`, 'info');
  renderApprovalList();
}

function viewVendor(vendorName) {
  showToast(`Viewing ${vendorName}...`, 'info');
}

function selectAll(checkbox) {
  const table = checkbox.closest('table');
  const checkboxes = table.querySelectorAll('input[type="checkbox"]');
  checkboxes.forEach(cb => cb.checked = checkbox.checked);
  showToast(checkbox.checked ? 'Selected all' : 'Cleared selection', 'info');
}

async function loadScriptOnce(src) {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[data-src="${src}"]`);
    if (existing) {
      if (existing.getAttribute('data-loaded') === 'true') {
        resolve();
        return;
      }
      existing.addEventListener('load', () => resolve(), { once: true });
      existing.addEventListener('error', () => reject(new Error('Failed to load script')), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.setAttribute('data-src', src);
    script.addEventListener('load', () => {
      script.setAttribute('data-loaded', 'true');
      resolve();
    }, { once: true });
    script.addEventListener('error', () => reject(new Error('Failed to load script')), { once: true });
    document.head.appendChild(script);
  });
}

function getPdfFileName() {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  const hh = String(now.getHours()).padStart(2, '0');
  const min = String(now.getMinutes()).padStart(2, '0');
  return `bizlink-dashboard-${yyyy}${mm}${dd}-${hh}${min}.pdf`;
}

async function exportReport() {
  const page = document.getElementById('page-dashboard');
  if (!page) {
    showToast('Dashboard container not found', 'error');
    return;
  }

  showToast('Preparing PDF export...', 'info');

  try {
    if (typeof window.html2canvas === 'undefined') {
      await loadScriptOnce('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
    }
    if (typeof window.jspdf === 'undefined') {
      await loadScriptOnce('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
    }

    const canvas = await window.html2canvas(page, {
      scale: 2,
      useCORS: true,
      backgroundColor: '#0b1220'
    });

    const imgData = canvas.toDataURL('image/png');
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4'
    });

    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const margin = 10;
    const usableWidth = pageWidth - margin * 2;
    const usableHeight = pageHeight - margin * 2;

    const imgWidth = usableWidth;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    let heightLeft = imgHeight;
    let position = margin;

    pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
    heightLeft -= usableHeight;

    while (heightLeft > 0) {
      position = heightLeft - imgHeight + margin;
      pdf.addPage();
      pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
      heightLeft -= usableHeight;
    }

    pdf.save(getPdfFileName());
    showToast('Report exported successfully (.pdf)', 'success');
  } catch (error) {
    console.error('PDF export failed:', error);
    showToast('PDF library failed to load. Opening print dialog...', 'info');
    window.print();
  }
}

function setPeriod(btn) {
  document.querySelectorAll('.chart-period').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  showToast(`Changed to ${btn.textContent}`, 'info');
  renderRevenueChart();
}

/* TOAST NOTIFICATIONS */
function showToast(msg, type = 'info') {
  const stack = document.getElementById('toastStack');
  const toast = document.createElement('div');
  toast.className = `toast-item ${type}`;
  toast.textContent = msg;
  stack.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'toastOut 0.35s cubic-bezier(0.22,1,0.36,1) forwards';
    setTimeout(() => toast.remove(), 350);
  }, 3500);
}

/*CLICK OUTSIDE TO CLOSE MENUS */
document.addEventListener('click', (e) => {
  if (!e.target.closest('.tb-icon-btn') && !e.target.closest('.notif-panel')) {
    document.getElementById('notifPanel').classList.remove('open');
  }
  if (!e.target.closest('.tb-avatar-wrap') && !e.target.closest('.user-menu')) {
    document.getElementById('userMenu').classList.remove('open');
  }
});

console.log('%c BizLink Admin Panel Loaded ⚙️ ', 'background: #000080; color: white; font-size: 14px; padding: 6px 14px; border-radius: 4px;');
