(function () {
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
  }

  const pageType = document.body.getAttribute('data-page') || '';
  if (pageType === 'orders') initOrders();
  if (pageType === 'wishlist') initWishlist();
  if (pageType === 'followed-vendors') initVendors();
  if (pageType === 'reviews') initReviews();
  if (pageType === 'addresses') initAddresses();
  if (pageType === 'payments') initPayments();
})();
