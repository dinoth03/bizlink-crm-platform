(async function () {
  const pageType = document.body.getAttribute('data-page') || '';
  let currentUserRole = 'customer';

  // --- AUTH GATE ---
  if (typeof authMe === 'function') {
    const identity = await authMe(true); // Redirect to login if not authenticated
    if (!identity || !identity.user) return;

    const role = String(identity.user.role || '').toLowerCase().trim();
    currentUserRole = role || 'customer';
    // Update basic vendor UI with profile info (email, premium badge)
    try {
      const profile = identity.profile || {};
      if (role === 'vendor') {
        const emailEl = document.getElementById('vendorProfileEmail');
        if (emailEl && identity.user.email) emailEl.textContent = identity.user.email;

        const badgeEl = document.getElementById('vendorPremiumBadge');
        const rawPremium = (profile.is_premium !== undefined) ? profile.is_premium : identity.user.is_premium;
        const isPremium = Number(rawPremium) === 1 || String(rawPremium).toLowerCase() === 'true';
        if (badgeEl) {
          if (isPremium) {
            badgeEl.style.display = 'block';
            const expiry = profile.premium_expiry_date || '';
            if (expiry) badgeEl.textContent = `💎 Premium Active — valid until ${expiry}`;
            else badgeEl.textContent = '💎 Premium Active';
          } else {
            badgeEl.style.display = 'none';
          }
        }
      }
    } catch (e) {
      console.warn('Unable to set vendor profile UI.', e);
    }
    const canAccessPaymentsAsVendor = pageType === 'payments' && role === 'vendor';
    if (role !== 'customer' && !canAccessPaymentsAsVendor) {
      // If mismatch, go back to the router with the intended role
      // This will trigger the "Session Conflict" page in dashboard.php
      window.location.href = `../dashboard.php?role=customer`;
      return;
    }
  }
  // -----------------

  function showDemo(message) {
    window.alert(message);
  }

  function initOrders() {
    const filterButtons = document.querySelectorAll('[data-filter-status]');
    const rows = document.querySelectorAll('[data-order-status]');

    filterButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const value = btn.getAttribute('data-filter-status');
        filterButtons.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        rows.forEach((row) => {
          const status = row.getAttribute('data-order-status');
          row.style.display = value === 'all' || value === status ? '' : 'none';
        });
      });
    });

    document.querySelectorAll('[data-action-track]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const orderId = btn.getAttribute('data-action-track');
        showDemo('Tracking timeline opened for order ' + orderId + '.');
      });
    });

    document.querySelectorAll('[data-action-cancel]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const orderId = btn.getAttribute('data-action-cancel');
        showDemo('Cancel request sent for order ' + orderId + ' (demo).');
      });
    });

    document.querySelectorAll('[data-action-return]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const orderId = btn.getAttribute('data-action-return');
        showDemo('Return request created for order ' + orderId + ' (demo).');
      });
    });

    document.querySelectorAll('[data-action-review]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const orderId = btn.getAttribute('data-action-review');
        showDemo('Review form opened for order ' + orderId + '.');
      });
    });
  }

  function initWishlist() {
    document.querySelectorAll('[data-action-cart]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const product = btn.getAttribute('data-action-cart');
        showDemo(product + ' added to cart (demo).');
      });
    });

    document.querySelectorAll('[data-action-remove]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const card = btn.closest('.item-card');
        if (card) card.remove();
      });
    });
  }

  function initVendors() {
    document.querySelectorAll('[data-action-unfollow]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const card = btn.closest('.item-card');
        if (!card) return;
        card.style.opacity = '0.45';
        btn.textContent = 'Unfollowed';
        btn.disabled = true;
      });
    });
  }

  function initReviews() {
    const filter = document.getElementById('reviewTypeFilter');
    const cards = document.querySelectorAll('[data-review-type]');
    if (!filter) return;

    filter.addEventListener('change', () => {
      const value = filter.value;
      cards.forEach((card) => {
        const type = card.getAttribute('data-review-type');
        card.style.display = value === 'all' || value === type ? '' : 'none';
      });
    });

    document.querySelectorAll('[data-review-edit]').forEach((btn) => {
      btn.addEventListener('click', () => showDemo('Edit review mode (demo).'));
    });
    document.querySelectorAll('[data-review-delete]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const card = btn.closest('.card');
        if (card) card.remove();
      });
    });
  }

  function initAddresses() {
    document.querySelectorAll('[data-set-default-address]').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.default-badge.address').forEach((el) => el.remove());
        const row = btn.closest('.card');
        const target = row ? row.querySelector('.address-name') : null;
        if (target) {
          const span = document.createElement('span');
          span.className = 'default-badge address';
          span.textContent = 'Default';
          target.appendChild(document.createTextNode(' '));
          target.appendChild(span);
        }
      });
    });
  }

  function initPayments() {
    const parseCardFromRow = (row) => {
      if (!row) return null;

      const labelCell = row.querySelector('.card-label');
      const typeCell = row.children[1];
      const labelText = (labelCell ? labelCell.textContent : '').replace(/\s+Default\s*$/i, '').trim();
      const typeText = (typeCell ? typeCell.textContent : '').trim();

      if (!labelText) return null;

      const last4Match = labelText.match(/(\d{4})(?!.*\d)/);
      const brandMatch = labelText.match(/(Visa|Mastercard|Amex)/i);
      const brand = brandMatch ? brandMatch[1].toLowerCase() : 'card';
      const last4 = last4Match ? last4Match[1] : '0000';
      const type = typeText ? typeText.toLowerCase() : 'unknown';

      return {
        hint: `${brand}_${last4}`,
        brand,
        last4,
        type
      };
    };

    const getSelectedCard = () => {
      const defaultBadge = document.querySelector('.default-badge.card');
      const defaultRow = defaultBadge ? defaultBadge.closest('tr') : null;
      if (defaultRow) {
        return parseCardFromRow(defaultRow);
      }

      const firstRow = document.querySelector('tbody tr');
      return parseCardFromRow(firstRow);
    };

    document.querySelectorAll('[data-set-default-card]').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.default-badge.card').forEach((el) => el.remove());
        const row = btn.closest('tr');
        const cell = row ? row.querySelector('.card-label') : null;
        if (cell) {
          const span = document.createElement('span');
          span.className = 'default-badge card';
          span.textContent = 'Default';
          cell.appendChild(document.createTextNode(' '));
          cell.appendChild(span);
        }
      });
    });

    document.querySelectorAll('[data-delete-card]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const row = btn.closest('tr');
        if (row) row.remove();
      });
    });

    document.querySelectorAll('[data-premium-plan-link]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();

        const selectedCard = getSelectedCard();
        const url = new URL(link.getAttribute('href'), window.location.href);

        if (selectedCard) {
          try {
            sessionStorage.setItem('premiumSelectedPaymentMethod', JSON.stringify({
              hint: selectedCard.hint,
              last4: selectedCard.last4,
              brand: selectedCard.brand,
              type: selectedCard.type,
              ts: Date.now()
            }));
          } catch (storageError) {
            console.warn('Unable to persist selected payment method in sessionStorage.', storageError);
          }

          url.searchParams.set('payment_method_hint', selectedCard.hint);
          url.searchParams.set('payment_last4', selectedCard.last4);
          url.searchParams.set('payment_brand', selectedCard.brand);
          url.searchParams.set('payment_type', selectedCard.type);
        }

        if (currentUserRole === 'vendor' || currentUserRole === 'customer') {
          url.searchParams.set('role', currentUserRole);
        }

        window.location.href = url.toString();
      });
    });
  }

  if (pageType === 'orders') initOrders();
  if (pageType === 'wishlist') initWishlist();
  if (pageType === 'followed-vendors') initVendors();
  if (pageType === 'reviews') initReviews();
  if (pageType === 'addresses') initAddresses();
  if (pageType === 'payments') initPayments();
})();
