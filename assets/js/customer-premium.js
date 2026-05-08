document.addEventListener('DOMContentLoaded', function() {
  const pageRole = String(document.body.getAttribute('data-role') || '').toLowerCase();

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

  async function startPremiumCheckout(plan, billing = 'monthly') {
    const selectedPayment = getSelectedPaymentContext();
    const checkoutPayload = {
      plan,
      billing,
      role: pageRole || 'customer'
    };

    if (selectedPayment.hint) checkoutPayload.payment_method_hint = selectedPayment.hint;
    if (selectedPayment.last4) checkoutPayload.payment_last4 = selectedPayment.last4;
    if (selectedPayment.brand) checkoutPayload.payment_brand = selectedPayment.brand;
    if (selectedPayment.type) checkoutPayload.payment_type = selectedPayment.type;

    const response = await apiRequest('create_premium_checkout_session.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(checkoutPayload)
    }, false);

    if (response && response.ok && response.data && response.data.success && response.data.data && response.data.data.checkout_url) {
      const result = response.data;
      window.location.href = result.data.checkout_url;
      return true;
    }

    const errorMessage = response && response.data && response.data.message
      ? response.data.message
      : 'Payment setup failed. Please try again.';
    window.alert(errorMessage);
    return false;
  }

  // wire up choose buttons on payment-methods page
  const chooseButtons = document.querySelectorAll('[data-choose-plan]');
  const confirmBox = document.getElementById('premium-selection-confirm');

  if (chooseButtons && chooseButtons.length) {
    chooseButtons.forEach(btn => {
      btn.addEventListener('click', function(e) {
        const plan = this.getAttribute('data-choose-plan');
        if (!plan) return;

        // Save selection to sessionStorage so payment flow can read it
        sessionStorage.setItem('premiumSelectedPlan', JSON.stringify({ plan }));

        // For vendor or customer payment pages, open real Stripe checkout for paid plans.
        const freePlans = ['starter', 'basic-shopper'];
        if ((pageRole === 'vendor' || pageRole === 'customer') && !freePlans.includes(plan)) {
          e.preventDefault();
          const billing = 'monthly';
          this.disabled = true;
          const originalText = this.textContent;
          this.textContent = 'Connecting...';
          this.style.opacity = '0.7';

          startPremiumCheckout(plan, billing).finally(() => {
            this.disabled = false;
            this.textContent = originalText;
            this.style.opacity = '1';
          });
          return;
        }

        // Show a small confirmation and show link to Manage Payment Methods (current page)
        if (confirmBox) {
          confirmBox.textContent = pageRole === 'vendor'
            ? `Selected plan: ${formatPlanName(plan)} — use saved card or add a new one to complete vendor upgrade.`
            : `Selected plan: ${formatPlanName(plan)} — use saved card or add a new one to complete upgrade.`;
          confirmBox.style.display = 'block';
          // hide after 6s
          setTimeout(() => { confirmBox.style.display = 'none'; }, 6000);
        }

        // Optionally highlight the selected button briefly
        this.classList.add('active');
        setTimeout(() => this.classList.remove('active'), 900);

        // Scroll to add card section to encourage user to add/save a card if none
        const addCardEl = document.querySelector('.form-grid');
        if (addCardEl) addCardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });
  }

  // If user navigated from premiumplans with a preselected plan, highlight it
  const params = new URLSearchParams(window.location.search);
  const planParam = params.get('plan');
  if (planParam) {
    // try to find button for that plan and simulate a confirmation
    const targetBtn = document.querySelector(`[data-choose-plan*="${planParam}"]`);
    if (targetBtn) targetBtn.click();
  }

  function formatPlanName(key) {
    return key.replace(/-/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
  }
});
