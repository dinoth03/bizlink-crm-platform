/*
BizLink Premium Plans - Interactive Features
Handles role switching (customer/vendor), FAQ accordion, and pricing updates
 */

document.addEventListener('DOMContentLoaded', function() {
  initRoleTabs();
  applySelectionFromUrl();
  initBillingToggle();
  initFAQ();
  initNavbar();
});

/**
 * Role Switching - Switch between Customer and Vendor plan views
 */
function switchRole(role) {
  const validRoles = ['customer', 'vendor'];
  if (!validRoles.includes(role)) return;

  // Update tab active state
  document.querySelectorAll('.role-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  if (event && event.target) {
    event.target.closest('.role-tab').classList.add('active');
  }

  // Hide all plan sections, comparison tables, FAQ, and CTA
  document.getElementById('customer-plans').style.display = 'none';
  document.getElementById('vendor-plans').style.display = 'none';
  document.getElementById('comparison-customer').style.display = 'none';
  document.getElementById('comparison-vendor').style.display = 'none';
  document.getElementById('faq-customer').style.display = 'none';
  document.getElementById('faq-vendor').style.display = 'none';
  document.getElementById('cta-customer').style.display = 'none';
  document.getElementById('cta-vendor').style.display = 'none';

  // Show selected role's content
  if (role === 'customer') {
    document.getElementById('customer-plans').style.display = 'block';
    document.getElementById('comparison-customer').style.display = 'block';
    document.getElementById('faq-customer').style.display = 'block';
    document.getElementById('cta-customer').style.display = 'block';
    document.querySelectorAll('.role-tab')[0].classList.add('active');
  } else if (role === 'vendor') {
    document.getElementById('vendor-plans').style.display = 'block';
    document.getElementById('comparison-vendor').style.display = 'block';
    document.getElementById('faq-vendor').style.display = 'block';
    document.getElementById('cta-vendor').style.display = 'block';
    document.querySelectorAll('.role-tab')[1].classList.add('active');
  }

  // Store preference
  localStorage.setItem('bizlinkPlanRole', role);

  // Scroll to plans section
  const plansSection = document.querySelector('.pricing-section');
  if (plansSection) {
    plansSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function initRoleTabs() {
  // Check if user has a stored preference
  const savedRole = localStorage.getItem('bizlinkPlanRole') || 'customer';
  
  // Check URL parameter
  const params = new URLSearchParams(window.location.search);
  const urlRole = params.get('role');
  
  const roleToShow = urlRole || savedRole;
  
  // Set initial state
  const tabs = document.querySelectorAll('.role-tab');
  if (tabs.length > 0) {
    tabs.forEach((tab, index) => {
      const isCustomer = tab.textContent.includes('Customer');
      if ((roleToShow === 'customer' && isCustomer) || (roleToShow === 'vendor' && !isCustomer)) {
        tab.classList.add('active');
      } else {
        tab.classList.remove('active');
      }
    });

    // Trigger initial display
    switchRole(roleToShow);
  }
}

function applySelectionFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const selectedPlan = (params.get('plan') || '').toLowerCase();
  const selectedBilling = (params.get('billing') || '').toLowerCase();

  if (selectedBilling === 'annual') {
    const toggle = document.getElementById('billingToggle');
    if (toggle && !toggle.classList.contains('active')) {
      toggle.classList.add('active');
      const prices = document.querySelectorAll('.monthly-price');
      prices.forEach(priceEl => {
        const annualPrice = priceEl.dataset.annual;
        const billingNote = priceEl.nextElementSibling;
        priceEl.textContent = `Rs. ${parseInt(annualPrice).toLocaleString()}`;
        if (billingNote && billingNote.classList.contains('price-note')) {
          const monthlyCost = Math.round(parseInt(annualPrice) / 12);
          billingNote.textContent = `Billed annually (Rs. ${monthlyCost}/month)`;
        }
      });
    }
  }

  if (selectedPlan) {
    const selectedButton = document.querySelector(`.btn-plan[data-plan="${selectedPlan}"]`);
    const selectedCard = selectedButton ? selectedButton.closest('.pricing-card') : null;
    if (selectedCard) {
      selectedCard.classList.add('selected-plan');
      selectedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
}

function getSelectedPaymentContext() {
  let sessionPayment = null;
  try {
    const raw = sessionStorage.getItem('premiumSelectedPaymentMethod');
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') {
        sessionPayment = {
          hint: String(parsed.hint || '').trim(),
          last4: String(parsed.last4 || '').trim(),
          brand: String(parsed.brand || '').trim(),
          type: String(parsed.type || '').trim()
        };
      }
    }
  } catch (e) {
    console.warn('Failed to read selected payment method from sessionStorage.', e);
  }

  const pageParams = new URLSearchParams(window.location.search);
  const fallbackPayment = {
    hint: (pageParams.get('payment_method_hint') || '').trim(),
    last4: (pageParams.get('payment_last4') || '').trim(),
    brand: (pageParams.get('payment_brand') || '').trim(),
    type: (pageParams.get('payment_type') || '').trim()
  };

  if (sessionPayment && (sessionPayment.hint || sessionPayment.last4 || sessionPayment.brand || sessionPayment.type)) {
    return sessionPayment;
  }

  return fallbackPayment;
}

/**
 * Billing Toggle - Switch between monthly and annual pricing
 */
function initBillingToggle() {
  const toggle = document.getElementById('billingToggle');
  const prices = document.querySelectorAll('.monthly-price');
  let isMonthly = true;

  if (toggle) {
    toggle.addEventListener('click', function() {
      toggle.classList.toggle('active');
      isMonthly = !isMonthly;

      prices.forEach(priceEl => {
        const monthlyPrice = priceEl.dataset.monthly;
        const annualPrice = priceEl.dataset.annual;
        const newPrice = isMonthly ? monthlyPrice : annualPrice;
        const billingNote = priceEl.nextElementSibling;

        // Update price
        priceEl.textContent = `Rs. ${parseInt(newPrice).toLocaleString()}`;

        // Update billing note
        if (billingNote && billingNote.classList.contains('price-note')) {
          if (isMonthly) {
            billingNote.textContent = 'Billed monthly';
          } else {
            const monthlyVal = parseInt(monthlyPrice);
            const annualVal = parseInt(annualPrice);
            const monthlyCost = Math.round(annualVal / 12);
            billingNote.textContent = `Billed annually (Rs. ${monthlyCost}/month)`;
          }
        }
      });
    });
  }
}

/**
 * FAQ Accordion - Toggle FAQ item visibility
 */
function toggleFAQ(faqItem) {
  faqItem.classList.toggle('active');
  
  // Smooth max-height animation
  const answer = faqItem.querySelector('.faq-answer');
  if (faqItem.classList.contains('active')) {
    answer.style.maxHeight = answer.scrollHeight + 'px';
  } else {
    answer.style.maxHeight = '0';
  }
}

function initFAQ() {
  const faqItems = document.querySelectorAll('.faq-item');
  faqItems.forEach(item => {
    item.addEventListener('click', function(e) {
      // Prevent triggering on nested links
      if (e.target.tagName !== 'A') {
        toggleFAQ(this);
      }
    });
  });
}

/**
 * Navbar Interactivity
 */
function initNavbar() {
  const hamburger = document.getElementById('hamburger');
  const navbar = document.getElementById('navbar');

  if (hamburger) {
    hamburger.addEventListener('click', function() {
      const navLinks = navbar.querySelector('.nav-links');
      navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
    });
  }

  // Close menu when link is clicked
  const navLinks = document.querySelectorAll('.nav-links a');
  navLinks.forEach(link => {
    link.addEventListener('click', function() {
      const navLinksContainer = document.querySelector('.nav-links');
      navLinksContainer.style.display = 'none';
    });
  });
}

/**
 * Smooth scroll for anchor links
 */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});

/**
 * Animation on scroll
 */
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('in-view');
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

document.querySelectorAll('.pricing-card, .faq-item, .comparison-table').forEach(el => {
  observer.observe(el);
});

/**
 * Track plan selection
 */
document.querySelectorAll('.btn-plan').forEach(btn => {
  btn.addEventListener('click', async function(e) {
    // If it's the Enterprise plan, don't handle with Stripe (custom quote)
    if (this.classList.contains('enterprise')) return;

    e.preventDefault();

    const planName = (this.dataset.plan || this.closest('.pricing-card').querySelector('.plan-name').textContent).toLowerCase();
    const billingToggle = document.getElementById('billingToggle');
    const billing = billingToggle.classList.contains('active') ? 'annual' : 'monthly';
    const selectedPayment = getSelectedPaymentContext();
    const paymentMethodHint = selectedPayment.hint;
    const paymentLast4 = selectedPayment.last4;
    const paymentBrand = selectedPayment.brand;
    const paymentType = selectedPayment.type;

    const redirectParams = new URLSearchParams({ plan: planName, billing: billing });
    if (paymentMethodHint) redirectParams.set('payment_method_hint', paymentMethodHint);
    if (paymentLast4) redirectParams.set('payment_last4', paymentLast4);
    if (paymentBrand) redirectParams.set('payment_brand', paymentBrand);
    if (paymentType) redirectParams.set('payment_type', paymentType);

    const redirectTarget = `premiumplans.html?${redirectParams.toString()}`;
    const originalText = this.textContent;
    
    // Check if logged in as vendor
    try {
      const response = await fetch('../api/auth_me.php');
      const auth = await response.json();
      
      if (!auth.success || auth.user.role !== 'vendor') {
        window.location.href = `index.html?redirect=${encodeURIComponent(redirectTarget)}`;
        return;
      }

      // Start checkout
      this.textContent = 'Connecting...';
      this.style.opacity = '0.7';
      this.style.pointerEvents = 'none';

      const checkoutPayload = { plan: planName, billing: billing };
      if (paymentMethodHint) checkoutPayload.payment_method_hint = paymentMethodHint;
      if (paymentLast4) checkoutPayload.payment_last4 = paymentLast4;
      if (paymentBrand) checkoutPayload.payment_brand = paymentBrand;
      if (paymentType) checkoutPayload.payment_type = paymentType;

      const checkoutRes = await fetch('../api/create_premium_checkout_session.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(checkoutPayload)
      });
      const result = await checkoutRes.json();
      
      if (result.success && result.data.checkout_url) {
        window.location.href = result.data.checkout_url;
      } else {
        alert(result.message || 'Payment setup failed. Please try again.');
        this.textContent = originalText;
        this.style.opacity = '1';
        this.style.pointerEvents = 'auto';
      }
    } catch (err) {
      console.error(err);
      window.location.href = `index.html?redirect=${encodeURIComponent(redirectTarget)}`;
    }
  });
});
