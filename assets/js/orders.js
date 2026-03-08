/* BizLink Admin - Orders Management */

// Application State
const ordersState = {
  currentPage: 'orders',
  sidebarOpen: true,
  theme: localStorage.getItem('adminTheme') || 'light',
  sortField: 'date',
  sortDirection: 'desc',
  selectedOrders: [],
  allOrders: []
};

// Mock Orders Data
const mockOrders = [
  {
    id: 'ORD#2847',
    customer: 'Amara Perera',
    email: 'amara@example.com',
    phone: '+94 77 123 4567',
    product: 'Laptop 15" Gold',
    quantity: 1,
    price: 'Rs. 189,000',
    total: 'Rs. 189,000',
    vendor: 'TechZone Lanka',
    vendorCategory: 'Electronics',
    vendorRating: 4.8,
    vendorEmail: 'contact@techzone.lk',
    province: 'Western',
    status: 'completed',
    date: '2024-02-23',
    shipping: 'Standard Express',
    tracking: 'TZ-2847-567890',
    deliveryEst: '2024-02-26',
    address: '42/1 Main St, Colombo 3, Western Province',
    timeline: [
      { step: 'placed', date: '2024-02-23', time: '10:30 AM' },
      { step: 'payment', date: '2024-02-23', time: '10:35 AM' },
      { step: 'processing', date: '2024-02-23', time: '02:15 PM' },
      { step: 'shipped', date: '2024-02-24', time: '08:45 AM' },
      { step: 'delivered', date: '2024-02-25', time: '03:20 PM' }
    ]
  },
  {
    id: 'ORD#2846',
    customer: 'Kasun Silva',
    email: 'kasun@example.com',
    phone: '+94 76 987 6543',
    product: 'Cotton Saree',
    quantity: 2,
    price: 'Rs. 4,475',
    total: 'Rs. 8,950',
    vendor: 'Fashion Plus',
    vendorCategory: 'Fashion',
    vendorRating: 4.6,
    vendorEmail: 'info@fashionplus.lk',
    province: 'Central',
    status: 'processing',
    date: '2024-02-23',
    shipping: 'Standard Courier',
    tracking: 'FP-2846-123456',
    deliveryEst: '2024-02-28',
    address: '15 Hill Street, Kandy, Central Province',
    timeline: [
      { step: 'placed', date: '2024-02-23', time: '02:45 PM' },
      { step: 'payment', date: '2024-02-23', time: '02:50 PM' },
      { step: 'processing', date: '2024-02-23', time: '04:00 PM' },
      { step: 'shipped', date: '2024-02-26', time: '10:00 AM' },
      { step: 'delivered', date: null, time: 'Pending' }
    ]
  },
  {
    id: 'ORD#2845',
    customer: 'Nirmala K',
    email: 'nirmala@example.com',
    phone: '+94 71 234 5678',
    product: 'Premium Black Tea (1kg)',
    quantity: 3,
    price: 'Rs. 1,067',
    total: 'Rs. 3,200',
    vendor: 'Grocery Mart',
    vendorCategory: 'Grocery',
    vendorRating: 4.4,
    vendorEmail: 'sales@grocerymart.lk',
    province: 'Southern',
    status: 'completed',
    date: '2024-02-22',
    shipping: 'Standard Express',
    tracking: 'GM-2845-654321',
    deliveryEst: '2024-02-24',
    address: '88 Beach Road, Galle, Southern Province',
    timeline: [
      { step: 'placed', date: '2024-02-22', time: '11:20 AM' },
      { step: 'payment', date: '2024-02-22', time: '11:25 AM' },
      { step: 'processing', date: '2024-02-22', time: '12:00 PM' },
      { step: 'shipped', date: '2024-02-23', time: '06:15 AM' },
      { step: 'delivered', date: '2024-02-24', time: '02:45 PM' }
    ]
  },
  {
    id: 'ORD#2844',
    customer: 'Roshan W',
    email: 'roshan@example.com',
    phone: '+94 70 111 2222',
    product: 'Organic Vegetables Bundle',
    quantity: 1,
    price: 'Rs. 12,400',
    total: 'Rs. 12,400',
    vendor: 'Agriculture Pro',
    vendorCategory: 'Agriculture',
    vendorRating: 4.3,
    vendorEmail: 'orders@agripro.lk',
    province: 'North Central',
    status: 'completed',
    date: '2024-02-21',
    shipping: 'Fresh Express',
    tracking: 'AP-2844-789012',
    deliveryEst: '2024-02-23',
    address: '234 Farm Lane, Anuradhapura, North Central',
    timeline: [
      { step: 'placed', date: '2024-02-21', time: '08:00 AM' },
      { step: 'payment', date: '2024-02-21', time: '08:05 AM' },
      { step: 'processing', date: '2024-02-21', time: '09:30 AM' },
      { step: 'shipped', date: '2024-02-22', time: '05:00 AM' },
      { step: 'delivered', date: '2024-02-23', time: '10:30 AM' }
    ]
  },
  {
    id: 'ORD#2843',
    customer: 'Lakshmi D',
    email: 'lakshmi@example.com',
    phone: '+94 75 555 6666',
    product: 'Export Grade Tea (500g)',
    quantity: 5,
    price: 'Rs. 13,560',
    total: 'Rs. 67,800',
    vendor: 'Export Tea Co',
    vendorCategory: 'Beverages',
    vendorRating: 4.9,
    vendorEmail: 'export@teatrade.lk',
    province: 'Western',
    status: 'cancelled',
    date: '2024-02-20',
    shipping: 'International Express',
    tracking: 'ETC-2843-345678',
    deliveryEst: '2024-03-05',
    address: '567 Trade Street, Colombo 1, Western Province',
    timeline: [
      { step: 'placed', date: '2024-02-20', time: '09:15 AM' },
      { step: 'payment', date: '2024-02-20', time: '09:20 AM' },
      { step: 'cancelled', date: '2024-02-21', time: '11:00 AM' },
      { step: 'refund', date: '2024-02-21', time: '02:30 PM' },
      { step: 'completed', date: null, time: 'N/A' }
    ]
  },
  {
    id: 'ORD#2842',
    customer: 'Dilini Perera',
    email: 'dilini@example.com',
    phone: '+94 78 999 8888',
    product: 'Handmade Ceramic Pot',
    quantity: 1,
    price: 'Rs. 5,200',
    total: 'Rs. 5,200',
    vendor: 'Artisan Crafts',
    vendorCategory: 'Handicrafts',
    vendorRating: 4.7,
    vendorEmail: 'hello@artisancrafts.lk',
    province: 'Western',
    status: 'pending',
    date: '2024-02-25',
    shipping: 'Standard Courier',
    tracking: 'AC-2842-901234',
    deliveryEst: '2024-03-02',
    address: '99 Artist Lane, Colombo 7, Western Province',
    timeline: [
      { step: 'placed', date: '2024-02-25', time: '03:50 PM' },
      { step: 'payment', date: '2024-02-25', time: '03:55 PM' },
      { step: 'processing', date: '2024-02-26', time: '10:00 AM' },
      { step: 'shipped', date: null, time: 'Pending' },
      { step: 'delivered', date: null, time: 'Pending' }
    ]
  }
];

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  // Load theme
  if (ordersState.theme === 'dark') {
    document.body.classList.add('dark');
  }

  // Render initial data
  updateDate();
  ordersState.allOrders = [...mockOrders];
  renderOrdersTable();

  // Update date every minute
  setInterval(updateDate, 60000);
});

// ═══ DATE & TIME ═══
function updateDate() {
  const options = { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  const now = new Date().toLocaleDateString('en-US', options);
  document.getElementById('tbDate').textContent = now;
}

// ═══ THEME TOGGLE ═══
function toggleTheme() {
  const body = document.body;
  const isDark = body.classList.toggle('dark');
  ordersState.theme = isDark ? 'dark' : 'light';
  localStorage.setItem('adminTheme', ordersState.theme);

  const themeBtn = document.getElementById('themeBtn');
  themeBtn.style.transform = 'rotate(180deg)';
  setTimeout(() => (themeBtn.style.transform = ''), 300);
}

// ═══ SIDEBAR TOGGLE ═══
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  ordersState.sidebarOpen = !ordersState.sidebarOpen;
  sidebar.classList.toggle('open');
  if (window.innerWidth <= 900) {
    sidebar.classList.toggle('open');
  }
}

// ═══ NOTIFICATIONS ═══
function toggleNotif() {
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
  document.getElementById('userMenu').classList.remove('open');
}

function clearNotifs() {
  showToast('Notifications cleared', 'success');
  document.getElementById('notifPanel').classList.remove('open');
}

// ═══ USER MENU ═══
function toggleUserMenu() {
  const menu = document.getElementById('userMenu');
  const panel = document.getElementById('notifPanel');
  menu.classList.toggle('open');
  panel.classList.remove('open');
}

// ═══ LOGOUT ═══
function confirmLogout(event) {
  if (event) event.preventDefault();
  document.getElementById('logoutBackdrop').style.display = 'flex';
}

function cancelLogout(event) {
  if (event && event.target.id !== 'logoutBackdrop') return;
  document.getElementById('logoutBackdrop').style.display = 'none';
}

// Close menus on click outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.tb-icon-btn') && !e.target.closest('.notif-panel')) {
    document.getElementById('notifPanel')?.classList.remove('open');
  }
  if (!e.target.closest('.tb-avatar-wrap') && !e.target.closest('.user-menu')) {
    document.getElementById('userMenu')?.classList.remove('open');
  }
});

//  ORDERS TABLE RENDERING
function renderOrdersTable() {
  const tbody = document.getElementById('ordersBody');
  if (!tbody) return;

  let filteredOrders = [...ordersState.allOrders];

  // Apply filters
  const searchTerm = document.querySelector('.fb-input')?.value?.toLowerCase() || '';
  const statusFilter = document.getElementById('statusFilter')?.value || '';
  const categoryFilter = document.getElementById('categoryFilter')?.value || '';
  const dateFrom = document.getElementById('dateFrom')?.value || '';
  const dateTo = document.getElementById('dateTo')?.value || '';

  filteredOrders = filteredOrders.filter((order) => {
    // Search filter
    if (searchTerm) {
      const matchesSearch =
        order.id.toLowerCase().includes(searchTerm) ||
        order.customer.toLowerCase().includes(searchTerm) ||
        order.vendor.toLowerCase().includes(searchTerm);
      if (!matchesSearch) return false;
    }

    // Status filter
    if (statusFilter && order.status !== statusFilter) return false;

    // Category filter
    if (categoryFilter && !order.vendorCategory.toLowerCase().includes(categoryFilter)) return false;

    // Date range filter
    if (dateFrom && order.date < dateFrom) return false;
    if (dateTo && order.date > dateTo) return false;

    return true;
  });

  // Sort
  filteredOrders.sort((a, b) => {
    let aVal, bVal;

    switch (ordersState.sortField) {
      case 'orderId':
        aVal = a.id;
        bVal = b.id;
        break;
      case 'customer':
        aVal = a.customer;
        bVal = b.customer;
        break;
      case 'product':
        aVal = a.product;
        bVal = b.product;
        break;
      case 'vendor':
        aVal = a.vendor;
        bVal = b.vendor;
        break;
      case 'amount':
        aVal = parseInt(a.total.replace(/\D/g, ''));
        bVal = parseInt(b.total.replace(/\D/g, ''));
        break;
      case 'status':
        aVal = a.status;
        bVal = b.status;
        break;
      case 'date':
        aVal = new Date(a.date);
        bVal = new Date(b.date);
        break;
      default:
        aVal = a.date;
        bVal = b.date;
    }

    if (typeof aVal === 'string') {
      return ordersState.sortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    }
    return ordersState.sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
  });

  // Render rows
  tbody.innerHTML = filteredOrders
    .map(
      (order) => `
    <tr>
      <td><input type="checkbox" value="${order.id}" onchange="updateSelectedOrders()"/></td>
      <td><strong>${order.id}</strong></td>
      <td>${order.customer}</td>
      <td>${order.product}</td>
      <td>${order.vendor}</td>
      <td>${order.total}</td>
      <td>${order.province}</td>
      <td><span class="status-badge badge-${order.status}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></td>
      <td>${new Date(order.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: '2-digit' })}</td>
      <td style="text-align: center;">
        <button class="tbl-action view" onclick="viewOrderDetails('${order.id}')">View</button>
      </td>
    </tr>
  `
    )
    .join('');
}

//  FILTERING & SORTING
function filterOrders() {
  renderOrdersTable();
}

function resetFilters() {
  document.querySelector('.fb-input').value = '';
  document.getElementById('statusFilter').value = '';
  document.getElementById('categoryFilter').value = '';
  document.getElementById('dateFrom').value = '';
  document.getElementById('dateTo').value = '';
  renderOrdersTable();
  showToast('Filters reset', 'info');
}

function sortTable(field) {
  if (ordersState.sortField === field) {
    ordersState.sortDirection = ordersState.sortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    ordersState.sortField = field;
    ordersState.sortDirection = 'asc';
  }
  renderOrdersTable();
}

//  SELECTION
function selectAll(checkbox) {
  const table = checkbox.closest('table');
  const checkboxes = table.querySelectorAll('input[type="checkbox"]:not(.cb-all)');
  checkboxes.forEach((cb) => {
    cb.checked = checkbox.checked;
  });
  updateSelectedOrders();
}

function updateSelectedOrders() {
  const checkboxes = document.querySelectorAll('input[type="checkbox"]:not(.cb-all)');
  ordersState.selectedOrders = Array.from(checkboxes)
    .filter((cb) => cb.checked)
    .map((cb) => cb.value);
}

//  ORDER DETAIL MODAL
function viewOrderDetails(orderId) {
  const order = ordersState.allOrders.find((o) => o.id === orderId);
  if (!order) return;

  // Populate modal with order data
  document.getElementById('modalOrderId').textContent = order.id;
  document.getElementById('modalOrderDate').textContent = `Placed on ${new Date(order.date).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  })}`;

  // Customer info
  document.getElementById('detailCustomer').textContent = order.customer;
  document.getElementById('detailEmail').textContent = order.email;
  document.getElementById('detailPhone').textContent = order.phone;
  document.getElementById('detailProvince').textContent = order.province;

  // Order details
  document.getElementById('detailProduct').textContent = order.product;
  document.getElementById('detailQty').textContent = order.quantity;
  document.getElementById('detailPrice').textContent = order.price;
  document.getElementById('detailTotal').textContent = order.total;

  // Vendor info
  document.getElementById('detailVendor').textContent = order.vendor;
  document.getElementById('detailVendorCat').textContent = order.vendorCategory;
  document.getElementById('detailRating').textContent = `⭐ ${order.vendorRating}`;
  document.getElementById('detailVendorEmail').textContent = order.vendorEmail;

  // Delivery
  document.getElementById('detailShipping').textContent = order.shipping;
  document.getElementById('detailTracking').textContent = order.tracking;
  document.getElementById('detailDelivery').textContent = new Date(order.deliveryEst).toLocaleDateString();
  document.getElementById('detailAddress').textContent = order.address;

  // Timeline
  renderTimeline(order.timeline);

  // Show modal
  document.getElementById('orderModal').style.display = 'flex';
  document.getElementById('orderDetailBox').classList.add('open');
}

function renderTimeline(timeline) {
  timeline.forEach((step, index) => {
    const timelineEl = document.getElementById(`timeline${index + 1}`);
    const dateEl = document.getElementById(`timelineDate${index + 1}`);

    if (timelineEl) {
      timelineEl.style.opacity = step.date ? '1' : '0.5';
      timelineEl.className = `timeline-item ${step.step}`;
    }
    if (dateEl) {
      dateEl.textContent = step.date ? new Date(step.date).toLocaleDateString() + ' @ ' + step.time : step.time;
    }
  });
}

function closeOrderModal(event) {
  if (event && event.target.id !== 'orderModal') return;
  document.getElementById('orderModal').style.display = 'none';
  document.getElementById('orderDetailBox').classList.remove('open');
}

//  ORDER ACTIONS
function updateOrderStatus() {
  const statusSelect = document.createElement('select');
  statusSelect.style.padding = '8px';
  statusSelect.innerHTML = `
    <option value="">Select new status...</option>
    <option value="pending">⋯ Pending</option>
    <option value="processing">⏳ Processing</option>
    <option value="completed">✓ Completed</option>
    <option value="cancelled">✕ Cancelled</option>
  `;

  const modal = document.getElementById('orderDetailBox');
  const tempContainer = document.createElement('div');
  tempContainer.style.padding = '16px';
  tempContainer.style.display = 'flex';
  tempContainer.style.gap = '12px';
  tempContainer.innerHTML = `
    <select id="newStatus" style="padding: 8px; flex: 1; border: 1px solid #e5e7eb; border-radius: 6px;">
      <option value="">Select new status...</option>
      <option value="pending">⋯ Pending</option>
      <option value="processing">⏳ Processing</option>
      <option value="completed">✓ Completed</option>
      <option value="cancelled">✕ Cancelled</option>
    </select>
    <button style="padding: 8px 16px; background: #000080; color: white; border: none; border-radius: 6px; cursor: pointer;">Update</button>
  `;

  tempContainer.querySelector('button').onclick = () => {
    const newStatus = tempContainer.querySelector('select').value;
    if (newStatus) {
      showToast(`Order status updated to: ${newStatus}`, 'success');
      closeOrderModal();
    }
  };

  modal.querySelector('.order-detail-body').appendChild(tempContainer);
}

function downloadInvoice() {
  showToast('📥 Invoice downloading...', 'info');
  setTimeout(() => {
    showToast('Invoice downloaded successfully!', 'success');
  }, 1500);
}

function exportOrders() {
  showToast('📊 Exporting orders to CSV...', 'info');
  setTimeout(() => {
    showToast('Orders exported successfully (orders.csv)', 'success');

    // Simulate CSV download
    const csv = ordersState.allOrders
      .map((o) => `${o.id},${o.customer},${o.product},${o.vendor},${o.total},${o.status},${o.date}`)
      .join('\n');

    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    link.download = 'orders-export.csv';
    link.click();
  }, 1500);
}

//  TOAST NOTIFICATIONS
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

console.log('%c BizLink Orders Management Loaded ✓ ', 'background: #000080; color: white; font-size: 14px; padding: 6px 14px; border-radius: 4px;');
