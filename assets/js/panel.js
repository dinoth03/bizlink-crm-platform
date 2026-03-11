/* BIZLINK ADMIN PANEL (panel.js)
Admin Dashboard & Navigation Logic*/

/* ── State ── */
const state = {
  currentPage: 'dashboard',
  sidebarOpen: true,
  theme: localStorage.getItem('adminTheme') || 'light',
  notifications: [
    { id: 1, icon: '📦', title: 'New Vendor Request', text: 'PowerIT Lanka applied for verification', type: 'navy' },
    { id: 2, icon: '✅', title: 'Order Completed', text: 'Order #OCT-2847 marked as delivered', type: 'green' },
    { id: 3, icon: '⚠️', title: 'Low Stock Alert', text: 'Electronics category inventory critical', type: 'amber' },
    { id: 4, icon: '💰', title: 'Revenue Update', text: 'Daily revenue reached target: Rs. 242K', type: 'green' }
  ]
};

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
document.addEventListener('DOMContentLoaded', () => {
  if (state.theme === 'dark') {
    document.body.classList.add('dark');
  }
  updateDate();
  renderDashboard();
  renderNotifications();
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

function renderNotifications() {
  const list = document.getElementById('notifList');
  list.innerHTML = state.notifications.map(n => `
    <div class="np-item">
      <div class="np-icon ${n.type}">${n.icon}</div>
      <div class="np-text">
        <strong>${n.title}</strong>
        <span>${n.text}</span>
      </div>
    </div>
  `).join('');
}

function clearNotifs() {
  state.notifications = [];
  state.notifications.push({ id: 1, icon: '✅', title: 'All cleared', text: 'Notifications have been cleared', type: 'green' });
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

/* DASHBOARD RENDERING */
async function renderDashboard() {
  // Fetch real stats from database
  try {
    const res = await fetch('http://localhost/bizlink-crm-platform/api/get_dashboard_stats.php');
    const json = await res.json();
    if (json.success) {
      const s = json.data;
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

  // Animate KPI values
  animateCounters();
  
  // Render charts
  renderRevenueChart();
  renderDonutChart();
  
  // Render tables
  renderRecentOrders();
  renderApprovalList();
  renderTopVendors();
  
  // Render activity feed
  renderActivityFeed();
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
  
  // Fallback if API fails
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:#888;">No orders found in database</td></tr>';
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

function renderApprovalList() {
  const list = document.getElementById('approvalList');
  if (!list) return;
  
  const vendors = [
    { emoji: '🏪', name: 'PowerIT Lanka', cat: 'IT Services' },
    { emoji: '👗', name: 'Stylewave Fashion', cat: 'Fashion' },
    { emoji: '🍜', name: 'Delish Foods', cat: 'Grocery' }
  ];
  
  list.innerHTML = vendors.map(v => `
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

function renderTopVendors() {
  const container = document.getElementById('topVendors');
  if (!container) return;
  
  const vendors = [
    { rank: 1, name: 'TechZone Lanka', cat: 'Electronics', amount: 'Rs. 1.2M', pct: 100 },
    { rank: 2, name: 'Fashion Plus', cat: 'Apparel', amount: 'Rs. 840K', pct: 70 },
    { rank: 3, name: 'Export Tea Co', cat: 'Beverages', amount: 'Rs. 620K', pct: 52 }
  ];
  
  container.innerHTML = vendors.map(v => `
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
  
  tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;color:#888;">No vendors found in database</td></tr>';
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
  
  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;color:#888;">No customers found in database</td></tr>';
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
  
  tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;color:#888;">No orders found in database</td></tr>';
}

/* ANALYTICS */
function renderAnalytics() {
  renderCustomerGrowthChart();
  renderProvinceList();
}

function renderCustomerGrowthChart() {
  const container = document.getElementById('customerGrowthChart');
  if (!container) return;
  
  const data = [120, 180, 250, 340, 420, 520, 640, 780, 920, 1040, 1180, 1480];
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

function renderProvinceList() {
  const container = document.getElementById('provinceList');
  if (!container) return;
  
  const provinces = [
    { name: 'Western', users: 4240, pct: 100 },
    { name: 'Central', users: 2840, pct: 67 },
    { name: 'Southern', users: 1920, pct: 45 },
    { name: 'Northern', users: 1240, pct: 29 }
  ];
  
  container.innerHTML = provinces.map(p => `
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
  // Simulate new activity every 15 seconds
  setInterval(() => {
    if (state.currentPage === 'dashboard') {
      renderActivityFeed();
    }
  }, 15000);
}

function renderActivityFeed() {
  const container = document.getElementById('activityFeed');
  if (!container) return;
  
  const activities = [
    { icon: '✅', msg: '<strong>PowerIT Lanka</strong> completed verification', type: 'green', time: '2 min ago' },
    { icon: '🛒', msg: '<strong>542 orders</strong> processed today', type: 'navy', time: '5 min ago' },
    { icon: '👤', msg: '<strong>128 new customers</strong> registered', type: 'amber', time: '12 min ago' },
    { icon: '⚠️', msg: 'Email service uptime <strong>87%</strong>', type: 'amber', time: '18 min ago' },
    { icon: '💰', msg: 'Daily revenue <strong>Rs. 242K</strong> - on target', type: 'green', time: '25 min ago' }
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

function exportReport() {
  showToast('Exporting report...', 'info');
  setTimeout(() => {
    showToast('Report exported successfully! (report.csv)', 'success');
  }, 1500);
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
