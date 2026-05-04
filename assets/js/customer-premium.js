document.addEventListener('DOMContentLoaded', function() {
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

        // Show a small confirmation and show link to Manage Payment Methods (current page)
        if (confirmBox) {
          confirmBox.textContent = `Selected plan: ${formatPlanName(plan)} — use saved card or add a new one to complete upgrade.`;
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
