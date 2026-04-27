(function () {
  const navbar = document.getElementById('navbar');
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.querySelector('.nav-links');
  const navCta = document.querySelector('.nav-cta');

  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 60);
    });
  }

  if (hamburger) {
    hamburger.addEventListener('click', () => {
      navLinks?.classList.toggle('open');
      navCta?.classList.toggle('open');
    });
  }

  document.querySelectorAll('.nav-links a').forEach((a) => {
    a.addEventListener('click', () => {
      navLinks?.classList.remove('open');
      navCta?.classList.remove('open');
    });
  });

  const dateEl = document.getElementById('currentDate');
  if (dateEl) {
    dateEl.innerText = new Date().toLocaleDateString('en-US', {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }

  document.querySelectorAll('.toggle-switch').forEach((t) => {
    t.addEventListener('click', function (e) {
      e.stopPropagation();
      this.classList.toggle('active');
    });
  });

  const style = document.createElement('style');
  style.textContent = '@keyframes rippleOut{to{width:300px;height:300px;opacity:0;}}';
  document.head.appendChild(style);
})();

function animateCounter(el, target) {
  let start = 0;
  const step = Math.max(1, target / (2000 / 16));

  function tick() {
    start += step;
    if (start >= target) {
      el.textContent = target.toLocaleString();
    } else {
      el.textContent = Math.floor(start).toLocaleString();
      requestAnimationFrame(tick);
    }
  }

  requestAnimationFrame(tick);
}

function startCounterAnimation() {
  const counters = document.querySelectorAll('.stat-number');
  const counterObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const target = parseInt(entry.target.dataset.target || '0', 10);
          animateCounter(entry.target, target);
          counterObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );

  counters.forEach((c) => counterObserver.observe(c));
}

function normalizeStatus(status) {
  const map = {
    delivered: 'Delivered',
    shipped: 'Shipped',
    processing: 'Processing',
    pending: 'Pending',
    out_for_delivery: 'Out for delivery',
    cancelled: 'Cancelled',
    returned: 'Returned'
  };
  return map[(status || '').toLowerCase()] || 'Pending';
}

function normalizePaymentStatus(status) {
  const map = {
    unpaid: 'Unpaid',
    paid: 'Paid',
    partially_paid: 'Partially paid',
    failed: 'Payment failed',
    refunded: 'Refunded'
  };
  return map[(status || '').toLowerCase()] || 'Unpaid';
}

function canPayOrder(order) {
  const paymentStatus = String(order?.payment_status || '').toLowerCase();
  return ['unpaid', 'failed', 'partially_paid'].includes(paymentStatus);
}

function getOrderPaymentAction(order) {
  if (!canPayOrder(order)) {
    return '';
  }

  return `<button type="button" class="btn-customer" style="padding:8px 18px;font-size:0.82rem;" onclick="payOrderWithStripe(${Number(order.order_id || 0)}, this)">Pay with Stripe</button>`;
}

function showStripePaymentNoticeFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const paymentStatus = (params.get('payment_status') || '').toLowerCase();
  if (paymentStatus === '') return;

  if (paymentStatus === 'success') {
    window.alert('Payment successful. Order status will update shortly.');
  } else if (paymentStatus === 'cancelled') {
    window.alert('Payment was cancelled. You can try again any time.');
  }

  params.delete('payment_status');
  params.delete('session_id');
  const query = params.toString();
  const cleanUrl = window.location.pathname + (query ? `?${query}` : '') + window.location.hash;
  window.history.replaceState({}, '', cleanUrl);
}

const customerDashboardState = {
  customerId: null,
  userId: null,
  customerEmail: '',
  customerName: 'Customer',
  notifications: [],
  role: 'customer'
};

function applyCustomerIdentity(identity) {
  if (!identity) return;
  const user = identity.user || {};
  const role = String(user.role || 'customer');
  const fullName = String(user.full_name || customerDashboardState.customerName || 'Customer').trim();
  const email = String(user.email || customerDashboardState.customerEmail || '').trim().toLowerCase();

  customerDashboardState.userId = Number(user.user_id || 0) || null;
  customerDashboardState.customerName = fullName || 'Customer';
  customerDashboardState.customerEmail = email;
  customerDashboardState.role = role;

  const nameEl = document.getElementById('customerName');
  if (nameEl) {
    nameEl.textContent = (fullName.split(' ')[0] || 'Customer');
  }

  const roleEmailEl = document.getElementById('customerRoleEmail');
  if (roleEmailEl) {
    roleEmailEl.textContent = `Role: ${role.charAt(0).toUpperCase() + role.slice(1)} · Email: ${email || '-'}`;
  }

  const profileFullName = document.getElementById('profileFullName');
  const profileEmail = document.getElementById('profileEmail');
  if (profileFullName) profileFullName.textContent = fullName || 'Customer';
  if (profileEmail) profileEmail.textContent = email || '-';
}

function setCustomerLoadingStates() {
  const recentOrdersList = document.getElementById('recentOrdersList');
  const allOrdersList = document.getElementById('allOrdersList');
  const recommendedVendorsList = document.getElementById('recommendedVendorsList');
  const customerNotificationsList = document.getElementById('customerNotificationsList');

  if (recentOrdersList) {
    recentOrdersList.innerHTML = '<div class="order-item"><span>Loading recent orders...</span><span class="order-status">Loading</span></div>';
  }
  if (allOrdersList) {
    allOrdersList.innerHTML = '<div class="all-order-row"><div><strong>Loading order history...</strong><span>Fetching your latest purchases.</span></div><span class="order-status">Loading</span></div>';
  }
  if (recommendedVendorsList) {
    recommendedVendorsList.innerHTML = '<div class="vendor-mini">Loading recommended vendors...</div>';
  }
  if (customerNotificationsList) {
    customerNotificationsList.innerHTML = '<div class="customer-notif-item"><div class="customer-notif-icon">🔔</div><div><strong>Loading notifications...</strong><p>Fetching your latest updates.</p></div></div>';
  }
}

function applyCustomerChatLinks() {
  const chatUrl = '../pages/chat.html';
  const adminChatUrl = '../pages/chat.html?chatRole=admin';

  const messageCenterLink = document.getElementById('messageCenterLink');
  const supportNavLink = document.getElementById('supportNavLink');
  if (messageCenterLink) messageCenterLink.href = chatUrl;
  if (supportNavLink) supportNavLink.href = adminChatUrl;
}

function getCustomerNotificationIcon(notification) {
  const type = (notification.notification_type || 'system').toLowerCase();
  if (type === 'order_status') return '📦';
  if (type === 'payment') return '💳';
  if (type === 'message') return '💬';
  if (type === 'promotion') return '🎉';
  if (type === 'review') return '⭐';
  return '🔔';
}

function updateCustomerNotificationSummary() {
  const summary = document.getElementById('customerNotifSummary');
  if (!summary) return;

  const unread = customerDashboardState.notifications.filter((n) => !n.is_read).length;
  if (unread === 0) {
    summary.textContent = '✅ You are all caught up';
  } else if (unread === 1) {
    summary.textContent = '🔔 You have 1 unread notification';
  } else {
    summary.textContent = `🔔 You have ${unread} unread notifications`;
  }
}

function renderCustomerNotifications(notifications) {
  const list = document.getElementById('customerNotificationsList');
  if (!list) return;

  if (!notifications || notifications.length === 0) {
    list.innerHTML = `
      <div class="customer-notif-item">
        <div class="customer-notif-icon">✅</div>
        <div>
          <strong>All clear</strong>
          <p>No unread updates right now.</p>
        </div>
      </div>
    `;
    updateCustomerNotificationSummary();
    return;
  }

  list.innerHTML = notifications
    .map(
      (notification) => `
      <div class="customer-notif-item ${notification.is_read ? 'read' : 'unread'}" role="button" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();markCustomerNotificationRead(${Number(notification.notification_id || 0)});}" onclick="markCustomerNotificationRead(${Number(notification.notification_id || 0)})">
        <div class="customer-notif-icon">${getCustomerNotificationIcon(notification)}</div>
        <div>
          <strong>${notification.title}</strong>
          <p>${notification.message}</p>
        </div>
      </div>
    `
    )
    .join('');

  updateCustomerNotificationSummary();
}

async function loadCustomerNotifications(email) {
  customerDashboardState.customerEmail = email || customerDashboardState.customerEmail;

  if (typeof getNotifications !== 'function') {
    customerDashboardState.notifications = [];
    renderCustomerNotifications(customerDashboardState.notifications);
    return;
  }

  try {
    const response = await getNotifications({
      email: customerDashboardState.customerEmail,
      limit: 6
    });

    if (response && Array.isArray(response.data)) {
      customerDashboardState.notifications = response.data;
      renderCustomerNotifications(customerDashboardState.notifications);
      return;
    }
  } catch (error) {
    console.error('Customer notifications load failed:', error);
  }

  customerDashboardState.notifications = [];
  renderCustomerNotifications(customerDashboardState.notifications);
}

window.markCustomerNotificationRead = async function (notificationId) {
  if (!notificationId) return;

  const target = customerDashboardState.notifications.find(
    (notification) => Number(notification.notification_id) === Number(notificationId)
  );
  if (!target || target.is_read) return;

  if (typeof markNotificationsRead === 'function') {
    const result = await markNotificationsRead({
      email: customerDashboardState.customerEmail,
      notification_id: Number(notificationId)
    });

    if (result && result.success) {
      target.is_read = true;
      renderCustomerNotifications(customerDashboardState.notifications);
      return;
    }
  }

  target.is_read = true;
  renderCustomerNotifications(customerDashboardState.notifications);
};

window.markAllCustomerNotificationsRead = async function () {
  if (typeof markNotificationsRead === 'function') {
    const result = await markNotificationsRead({
      email: customerDashboardState.customerEmail,
      mark_all: true
    });

    if (result && result.success) {
      customerDashboardState.notifications = [];
      renderCustomerNotifications(customerDashboardState.notifications);
      return;
    }
  }

  customerDashboardState.notifications = [];
  renderCustomerNotifications(customerDashboardState.notifications);
};

function renderRecentOrders(orders) {
  const container = document.getElementById('recentOrdersList');
  if (!container) return;

  if (!orders || orders.length === 0) {
    container.innerHTML = '<div class="order-item"><span>No recent orders</span> <span class="order-status">-</span></div>';
    return;
  }

  container.innerHTML = orders
    .slice(0, 9)
    .map(
      (o) => `
      <div class="order-item">
        <span><strong>#${o.order_number}</strong> - ${o.product_name || 'Order Item'}</span>
        <span class="order-status">${normalizeStatus(o.order_status)}</span>
      </div>
    `
    )
    .join('');
}

function renderAllOrders(orders) {
  const container = document.getElementById('allOrdersList');
  if (!container) return;

  if (!orders || orders.length === 0) {
    container.innerHTML = `
      <div class="all-order-row">
        <div>
          <strong>No orders yet</strong>
          <span>Your full order history will appear here.</span>
        </div>
        <span class="order-status">-</span>
      </div>
    `;
    return;
  }

  container.innerHTML = orders
    .slice(0, 20)
    .map((order) => {
      const amount = Number(order.total_amount || order.amount || 0);
      const amountText = amount > 0 ? `Rs. ${Math.round(amount).toLocaleString()}` : 'Amount pending';
      const vendor = order.vendor_name || order.vendor || 'Unknown Vendor';
      const product = order.product_name || 'Order Item';
      const paymentStatus = normalizePaymentStatus(order.payment_status);
      const paymentAction = getOrderPaymentAction(order);
      return `
        <div class="all-order-row">
          <div>
            <strong>#${order.order_number}</strong>
            <span>${product} • ${vendor} • ${amountText} • ${paymentStatus}</span>
          </div>
          <div style="display:flex;gap:10px;align-items:center;justify-content:flex-end;flex-wrap:wrap;">
            <span class="order-status">${normalizeStatus(order.order_status)}</span>
            ${paymentAction}
          </div>
        </div>
      `;
    })
    .join('');
}

window.payOrderWithStripe = async function (orderId, buttonEl) {
  const safeOrderId = Number(orderId || 0);
  if (!safeOrderId || typeof createStripeCheckoutSession !== 'function') {
    window.alert('Unable to start payment right now.');
    return;
  }

  const btn = buttonEl && buttonEl.tagName ? buttonEl : null;
  const originalText = btn ? btn.textContent : '';
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Starting...';
  }

  try {
    const result = await createStripeCheckoutSession(safeOrderId);
    if (!result || !result.success || !result.data || !result.data.checkout_url) {
      const msg = (result && result.message) ? result.message : 'Unable to create Stripe checkout session.';
      window.alert(msg);
      return;
    }

    window.location.href = result.data.checkout_url;
  } catch (error) {
    console.error('Stripe payment start failed:', error);
    window.alert('Unable to start payment at this moment.');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = originalText || 'Pay with Stripe';
    }
  }
};

function renderRecommendedVendors(vendors) {
  const container = document.getElementById('recommendedVendorsList');
  if (!container) return;

  if (!vendors || vendors.length === 0) {
    container.innerHTML = '<div class="vendor-mini">No vendor recommendations yet</div>';
    return;
  }

  const emojis = ['🍵', '🥥', '👗', '📦'];
  container.innerHTML = vendors
    .slice(0, 4)
    .map((v, i) => {
      const rating = Number(v.avg_rating || 0);
      const ratingText = rating > 0 ? ` <span style="color:var(--customer);">${rating.toFixed(1)}★</span>` : '';
      return `<div class="vendor-mini"><span style="font-size:1.2rem;">${emojis[i % emojis.length]}</span> ${v.shop_name}${ratingText}</div>`;
    })
    .join('');
}

function updateCustomerSummary(customer, orders, vendors) {
  const nameEl = document.getElementById('customerName');
  if (nameEl && customer?.full_name) {
    nameEl.textContent = customer.full_name.split(' ')[0];
  }

  const totalOrders = orders.length;
  const inProgress = orders.filter((o) => ['pending', 'processing', 'shipped', 'out_for_delivery'].includes((o.order_status || '').toLowerCase())).length;
  const wishlistCount = Math.min(30, totalOrders * 2);
  const followedVendors = new Set(orders.map((o) => o.vendor_name).filter(Boolean)).size || Math.min(8, vendors.length);

  const statNumbers = document.querySelectorAll('.stat-number');
  const statDetails = document.querySelectorAll('.stat-detail');

  if (statNumbers[0]) statNumbers[0].dataset.target = String(totalOrders);
  if (statNumbers[1]) statNumbers[1].dataset.target = String(inProgress);
  if (statNumbers[2]) statNumbers[2].dataset.target = String(wishlistCount);
  if (statNumbers[3]) statNumbers[3].dataset.target = String(followedVendors);

  if (statDetails[0]) {
    statDetails[0].textContent = `Total spent: Rs. ${Math.round(Number(customer?.total_spent || 0)).toLocaleString()}`;
  }
  if (statDetails[1]) statDetails[1].textContent = 'Awaiting delivery';
  if (statDetails[2]) statDetails[2].textContent = 'items saved';
  if (statDetails[3]) statDetails[3].textContent = `${followedVendors} active now`;
}

async function loadCustomerDashboardData() {
  setCustomerLoadingStates();
  customerDashboardState.customerEmail = '';
  customerDashboardState.customerId = null;
  customerDashboardState.customerName = 'Customer';
  customerDashboardState.userId = null;
  applyCustomerChatLinks();

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

    const role = String(identity.user.role || '').toLowerCase().trim();

    if (role !== 'customer') {
      // If mismatch, go back to the router with the intended role
      // This will trigger the "Session Conflict" page in dashboard.php
      window.location.href = `../dashboard.php?role=customer`;
      return;
    }

    applyCustomerIdentity(identity);

    const [customers, orders, vendors] = await Promise.all([getCustomers(), getOrders(), getVendors()]);

    if (!customers || customers.length === 0) {
      updateCustomerSummary(
        { full_name: customerDashboardState.customerName, total_spent: 0 },
        [],
        []
      );
      renderRecentOrders([]);
      renderAllOrders([]);
      renderRecommendedVendors([]);
      await loadCustomerNotifications(customerDashboardState.customerEmail);
      return;
    }

    const selectedCustomer =
      customers.find((c) => customerDashboardState.userId && Number(c.user_id) === Number(customerDashboardState.userId)) ||
      customers.find((c) => (c.email || '').toLowerCase() === customerDashboardState.customerEmail) ||
      customers[0];

    customerDashboardState.customerEmail = (selectedCustomer.email || customerDashboardState.customerEmail || '').toLowerCase();
    customerDashboardState.customerId = selectedCustomer.customer_id ? Number(selectedCustomer.customer_id) : customerDashboardState.customerId;
    customerDashboardState.customerName = selectedCustomer.full_name || customerDashboardState.customerName;
    applyCustomerChatLinks();

    const customerOrders = (orders || []).filter(
      (o) => Number(o.customer_id || 0) === Number(selectedCustomer.customer_id || 0)
    );

    const safeOrders = customerOrders;
    const safeVendors = vendors || [];

    updateCustomerSummary(selectedCustomer, safeOrders, safeVendors);
    renderRecentOrders(safeOrders);
    renderAllOrders(safeOrders);
    renderRecommendedVendors(safeVendors);
    await loadCustomerNotifications(customerDashboardState.customerEmail);
  } catch (error) {
    console.error('Customer dashboard API load failed:', error);
    updateCustomerSummary(
      { full_name: customerDashboardState.customerName, total_spent: 0 },
      [],
      []
    );
    renderRecentOrders([]);
    renderAllOrders([]);
    renderRecommendedVendors([]);
    await loadCustomerNotifications(customerDashboardState.customerEmail);
  }
}

(function setPageFromUrl() {
  const path = window.location.pathname;
  const dashboardView = document.getElementById('dashboard-view');
  const profileView = document.getElementById('profile-view');
  if (!dashboardView || !profileView) return;

  if (path.includes('userprofile.html')) {
    dashboardView.style.display = 'none';
    profileView.style.display = 'block';
  } else {
    dashboardView.style.display = 'block';
    profileView.style.display = 'none';
  }
})();

window.showDashboard = function () {
  const dashboardView = document.getElementById('dashboard-view');
  const profileView = document.getElementById('profile-view');
  if (dashboardView) dashboardView.style.display = 'block';
  if (profileView) profileView.style.display = 'none';
};

window.showProfile = function () {
  const dashboardView = document.getElementById('dashboard-view');
  const profileView = document.getElementById('profile-view');
  if (dashboardView) dashboardView.style.display = 'none';
  if (profileView) profileView.style.display = 'block';
};

async function customerLogout() {
  try {
    if (typeof authLogout === 'function') {
      await authLogout();
    }
  } catch (error) {
    console.warn('Customer logout request failed, redirecting anyway:', error);
  }

  window.location.href = '../pages/index.html';
}

window.addEventListener('load', async () => {
  showStripePaymentNoticeFromUrl();
  await loadCustomerDashboardData();
  startCounterAnimation();
  console.log('BizLink Customer Portal - Live data mode');
});
