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

const FALLBACK_CUSTOMER = {
  full_name: 'Dilani Perera',
  total_spent: 148750
};

const FALLBACK_ORDERS = [
  { order_number: 'BL-4201', product_name: 'Ceylon Tea Gift Pack', order_status: 'delivered', vendor_name: 'Ceylon Tea House' },
  { order_number: 'BL-4202', product_name: 'Handloom Cotton Saree', order_status: 'shipped', vendor_name: 'Serendib Style House' },
  { order_number: 'BL-4203', product_name: 'Organic Samba Rice 5kg', order_status: 'processing', vendor_name: 'Lanka Fresh Mart' },
  { order_number: 'BL-4204', product_name: 'Ayurvedic Body Oil', order_status: 'out_for_delivery', vendor_name: 'Suwasetha Wellness' },
  { order_number: 'BL-4205', product_name: 'Batik Office Shirt', order_status: 'pending', vendor_name: 'Colombo Batik Studio' },
  { order_number: 'BL-4206', product_name: 'Ceylon Cinnamon Pack', order_status: 'delivered', vendor_name: 'Matara Spice House' },
  { order_number: 'BL-4207', product_name: 'Kithul Jaggery Bundle', order_status: 'processing', vendor_name: 'Kithul Naturals' },
  { order_number: 'BL-4208', product_name: 'Laptop Wireless Mouse', order_status: 'shipped', vendor_name: 'Ceylon Tech Hub' },
  { order_number: 'BL-4209', product_name: 'Coconut Oil 1L', order_status: 'delivered', vendor_name: 'Pure Coconut Lanka' }
];

const FALLBACK_VENDORS = [
  { shop_name: 'Ceylon Tea House', avg_rating: 4.8 },
  { shop_name: 'Lanka Fresh Mart', avg_rating: 4.6 },
  { shop_name: 'Serendib Style House', avg_rating: 4.9 },
  { shop_name: 'Ceylon Tech Hub', avg_rating: 4.7 },
  { shop_name: 'Matara Spice House', avg_rating: 4.5 },
  { shop_name: 'Kithul Naturals', avg_rating: 4.6 },
  { shop_name: 'Suwasetha Wellness', avg_rating: 4.4 },
  { shop_name: 'Colombo Batik Studio', avg_rating: 4.7 },
  { shop_name: 'Pure Coconut Lanka', avg_rating: 4.5 }
];

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
  try {
    const [customers, orders, vendors] = await Promise.all([getCustomers(), getOrders(), getVendors()]);

    if (!customers || customers.length === 0) {
      updateCustomerSummary(FALLBACK_CUSTOMER, FALLBACK_ORDERS, FALLBACK_VENDORS);
      renderRecentOrders(FALLBACK_ORDERS);
      renderRecommendedVendors(FALLBACK_VENDORS);
      return;
    }

    const selectedCustomer =
      customers.find((c) => (c.full_name || '').toLowerCase().includes('dilani')) || customers[0];

    const customerOrders = (orders || []).filter(
      (o) => (o.customer_name || '').toLowerCase() === (selectedCustomer.full_name || '').toLowerCase()
    );

    const safeOrders = customerOrders.length > 0 ? customerOrders : FALLBACK_ORDERS;
    const safeVendors = vendors && vendors.length > 0 ? vendors : FALLBACK_VENDORS;

    updateCustomerSummary(selectedCustomer, safeOrders, safeVendors);
    renderRecentOrders(safeOrders);
    renderRecommendedVendors(safeVendors);
  } catch (error) {
    console.error('Customer dashboard API load failed:', error);
    updateCustomerSummary(FALLBACK_CUSTOMER, FALLBACK_ORDERS, FALLBACK_VENDORS);
    renderRecentOrders(FALLBACK_ORDERS);
    renderRecommendedVendors(FALLBACK_VENDORS);
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

window.addEventListener('load', async () => {
  await loadCustomerDashboardData();
  startCounterAnimation();
  console.log('BizLink Customer Portal - Live data mode');
});
