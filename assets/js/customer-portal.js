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

  const customerOrdersState = {
    orders: [],
    filteredOrders: [],
    activeStatus: 'all',
    searchTerm: '',
    activeOrder: null,
    searchTimer: null
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatCurrency(value) {
    const amount = Number(value || 0);
    return 'LKR ' + amount.toLocaleString('en-LK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function formatDateValue(value) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return String(value);
    }
    return parsed.toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    });
  }

  function formatDateTimeValue(value) {
    if (!value) return '-';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return String(value);
    }
    return parsed.toLocaleString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function getOrderStatusMeta(orderStatus) {
    const normalized = String(orderStatus || 'pending').toLowerCase();
    const labelMap = {
      pending: 'Pending',
      processing: 'Processing',
      shipped: 'Shipped',
      out_for_delivery: 'Out for delivery',
      delivered: 'Delivered',
      completed: 'Completed',
      cancelled: 'Cancelled',
      returned: 'Returned'
    };

    return {
      value: normalized,
      label: labelMap[normalized] || normalized.replace(/_/g, ' '),
      className: 'is-' + normalized
    };
  }

  function getPaymentStatusMeta(paymentStatus) {
    const normalized = String(paymentStatus || 'unpaid').toLowerCase();
    const labelMap = {
      unpaid: 'Unpaid',
      paid: 'Paid',
      partially_paid: 'Part-paid',
      failed: 'Failed',
      refunded: 'Refunded'
    };

    return {
      value: normalized,
      label: labelMap[normalized] || normalized.replace(/_/g, ' '),
      className: 'is-' + normalized
    };
  }

  function getStatusRank(orderStatus) {
    const normalized = String(orderStatus || 'pending').toLowerCase();
    const rankMap = {
      pending: 0,
      processing: 1,
      shipped: 2,
      out_for_delivery: 3,
      delivered: 4,
      completed: 4,
      cancelled: -1,
      returned: -1
    };

    return rankMap[normalized] ?? 0;
  }

  function buildTrackingTimeline(order) {
    const orderStatus = String(order?.order_status || 'pending').toLowerCase();
    const shipment = order?.shipment || {};
    const orderDate = order?.order_date || order?.created_at || null;
    const trackingDate = shipment.shipment_date || order?.updated_at || orderDate;
    const deliveredDate = shipment.actual_delivery_date || order?.actual_delivery_date || order?.expected_delivery_date || null;
    const rank = getStatusRank(orderStatus);
    const isCancelled = orderStatus === 'cancelled' || orderStatus === 'returned';

    const steps = [
      {
        key: 'placed',
        label: 'Order placed',
        date: orderDate,
        note: 'We received your order.',
        complete: true
      },
      {
        key: 'payment',
        label: 'Payment confirmed',
        date: order?.payment?.payment_status === 'paid' || order?.payment?.payment_status === 'partially_paid' ? orderDate : null,
        note: order?.payment?.payment_status ? 'Payment status: ' + getPaymentStatusMeta(order.payment.payment_status).label : 'Awaiting payment confirmation.',
        complete: ['paid', 'partially_paid', 'refunded'].includes(String(order?.payment?.payment_status || '').toLowerCase())
      },
      {
        key: 'processing',
        label: 'Processing',
        date: rank >= 1 ? order?.updated_at || orderDate : null,
        note: rank >= 1 ? 'Vendor is preparing your items.' : 'Preparing for fulfillment.',
        complete: rank >= 1
      },
      {
        key: 'shipped',
        label: 'Shipped',
        date: rank >= 2 ? trackingDate : null,
        note: shipment.carrier_name || order?.carrier_name ? 'Carrier: ' + (shipment.carrier_name || order.carrier_name) : 'Shipment not created yet.',
        complete: rank >= 2
      },
      {
        key: 'delivery',
        label: 'Out for delivery',
        date: rank >= 3 ? deliveredDate || trackingDate : null,
        note: deliveredDate ? 'Expected or actual delivery date available.' : 'Waiting for courier dispatch.',
        complete: rank >= 3
      },
      {
        key: 'delivered',
        label: isCancelled ? 'Cancelled / Returned' : 'Delivered',
        date: rank >= 4 ? deliveredDate : null,
        note: isCancelled ? 'This order was closed before delivery.' : 'Completed successfully.',
        complete: rank >= 4 || isCancelled
      }
    ];

    return steps;
  }

  function buildOrderSearchString(order) {
    const pieces = [
      order?.order_number,
      order?.vendor_name,
      order?.tracking_number,
      order?.carrier_name,
      order?.payment_status,
      order?.order_status
    ];
    return pieces.filter(Boolean).join(' ').toLowerCase();
  }

  function renderOrderRow(order) {
    const statusMeta = getOrderStatusMeta(order.order_status);
    const paymentMeta = getPaymentStatusMeta(order.payment_status);
    const trackingText = order.tracking_number
      ? `${order.carrier_name || 'Carrier'} · ${order.tracking_number}`
      : order.shipment && order.shipment.shipment_status
        ? `Shipment ${String(order.shipment.shipment_status).replace(/_/g, ' ')}`
        : 'Waiting for shipment';
    const actionLabel = order.tracking_number || ['shipped', 'out_for_delivery', 'delivered', 'completed'].includes(String(order.order_status || '').toLowerCase())
      ? 'Track shipment'
      : 'View details';

    return `
      <tr data-order-id="${escapeHtml(order.order_id)}" data-order-status="${escapeHtml(statusMeta.value)}">
        <td>
          <div class="order-meta">
            <span class="order-number">${escapeHtml(order.order_number || ('#' + order.order_id))}</span>
            <span class="order-vendor">${escapeHtml(order.item_count || 0)} item${Number(order.item_count || 0) === 1 ? '' : 's'}</span>
          </div>
        </td>
        <td>
          <div class="order-meta">
            <span class="order-number">${escapeHtml(order.vendor_name || 'Vendor')}</span>
            <span class="order-vendor">${escapeHtml(order.vendor_category || 'Marketplace vendor')}</span>
          </div>
        </td>
        <td><span class="status-pill ${escapeHtml(statusMeta.className)}">${escapeHtml(statusMeta.label)}</span></td>
        <td><span class="payment-pill ${escapeHtml(paymentMeta.className)}">${escapeHtml(paymentMeta.label)}</span></td>
        <td>
          <div class="tracking-mini">
            <strong>${escapeHtml(trackingText)}</strong>
            <span>${escapeHtml(order.expected_delivery_date ? 'ETA ' + formatDateValue(order.expected_delivery_date) : 'Tracking updates appear here')}</span>
          </div>
        </td>
        <td><strong>${escapeHtml(formatCurrency(order.total_amount))}</strong></td>
        <td class="order-date">${escapeHtml(formatDateValue(order.created_at || order.order_date))}</td>
        <td>
          <div class="order-actions">
            <button type="button" class="order-action-btn primary" data-order-open="${escapeHtml(order.order_id)}">${escapeHtml(actionLabel)}</button>
          </div>
        </td>
      </tr>
    `;
  }

  function renderOrderDetails(order) {
    const statusMeta = getOrderStatusMeta(order.order_status);
    const paymentMeta = getPaymentStatusMeta(order.payment_status);
    const shipment = order.shipment || {};
    const items = Array.isArray(order.items) ? order.items : [];
    const timeline = buildTrackingTimeline(order);
    const trackingNumber = order.tracking_number || shipment.tracking_number || '-';
    const carrierName = order.carrier_name || shipment.carrier_name || '-';
    const shipmentStatus = shipment.shipment_status || (order.order_status === 'delivered' || order.order_status === 'completed' ? 'delivered' : 'pending');

    return `
      <section class="order-modal-hero">
        <div>
          <h4>${escapeHtml(order.order_number || ('Order #' + order.order_id))}</h4>
          <p>${escapeHtml(order.vendor_name || 'Vendor')} · ${escapeHtml(order.vendor_category || 'Marketplace vendor')}</p>
        </div>
        <div class="order-modal-footer-actions">
          <span class="status-pill ${escapeHtml(statusMeta.className)}">${escapeHtml(statusMeta.label)}</span>
          <span class="payment-pill ${escapeHtml(paymentMeta.className)}">${escapeHtml(paymentMeta.label)}</span>
        </div>
      </section>

      <section class="order-modal-grid">
        <div class="detail-card">
          <div class="row">
            <div>
              <h4>Shipment tracking</h4>
              <div class="detail-copy">Latest delivery and courier information.</div>
            </div>
          </div>
          <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Tracking number</span><span class="detail-value">${escapeHtml(trackingNumber)}</span></div>
            <div class="detail-row"><span class="detail-label">Carrier</span><span class="detail-value">${escapeHtml(carrierName)}</span></div>
            <div class="detail-row"><span class="detail-label">Shipment status</span><span class="detail-value">${escapeHtml(String(shipmentStatus).replace(/_/g, ' '))}</span></div>
            <div class="detail-row"><span class="detail-label">Estimated delivery</span><span class="detail-value">${escapeHtml(formatDateValue(order.expected_delivery_date || shipment.estimated_delivery))}</span></div>
            <div class="detail-row"><span class="detail-label">Actual delivery</span><span class="detail-value">${escapeHtml(formatDateValue(order.actual_delivery_date || shipment.actual_delivery_date))}</span></div>
            <div class="detail-row"><span class="detail-label">Placed on</span><span class="detail-value">${escapeHtml(formatDateTimeValue(order.created_at || order.order_date))}</span></div>
          </div>

          <div>
            <h4>Tracking timeline</h4>
            <div class="timeline">
              ${timeline.map((step, index) => `
                <div class="timeline-step ${step.complete ? 'is-complete' : ''} ${step.complete || index === 0 ? 'is-active' : ''}">
                  <div class="timeline-marker"></div>
                  <div class="timeline-body">
                    <strong>${escapeHtml(step.label)}</strong>
                    <span class="timeline-note">${escapeHtml(step.note)}${step.date ? ' · ' + escapeHtml(formatDateValue(step.date)) : ''}</span>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        </div>

        <div class="detail-card">
          <div>
            <h4>Order summary</h4>
            <div class="detail-copy">Items, totals, and delivery information.</div>
          </div>
          <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Items</span><span class="detail-value">${escapeHtml(order.total_quantity || items.length || 0)} unit${Number(order.total_quantity || items.length || 0) === 1 ? '' : 's'}</span></div>
            <div class="detail-row"><span class="detail-label">Subtotal</span><span class="detail-value">${escapeHtml(formatCurrency(order.subtotal))}</span></div>
            <div class="detail-row"><span class="detail-label">Discount</span><span class="detail-value">-${escapeHtml(formatCurrency(order.discount_amount))}</span></div>
            <div class="detail-row"><span class="detail-label">Shipping</span><span class="detail-value">${escapeHtml(formatCurrency(order.shipping_cost))}</span></div>
            <div class="detail-row"><span class="detail-label">Tax</span><span class="detail-value">${escapeHtml(formatCurrency(order.tax_amount))}</span></div>
            <div class="detail-row"><span class="detail-label">Total</span><span class="detail-value">${escapeHtml(formatCurrency(order.total_amount))}</span></div>
          </div>

          <div>
            <h4>Delivery address</h4>
            <div class="detail-copy">${escapeHtml(order.shipping_address || order.billing_address || 'No delivery address stored')}</div>
          </div>

          <div>
            <h4>Payment</h4>
            <div class="detail-list">
              <div class="detail-row"><span class="detail-label">Method</span><span class="detail-value">${escapeHtml(order.payment?.payment_method || '-')}</span></div>
              <div class="detail-row"><span class="detail-label">Transaction</span><span class="detail-value">${escapeHtml(order.payment?.transaction_reference || order.payment?.transaction_id || '-')}</span></div>
              <div class="detail-row"><span class="detail-label">Receipt</span><span class="detail-value">${order.payment?.receipt_url ? '<a href="' + escapeHtml(order.payment.receipt_url) + '" target="_blank" rel="noopener">View receipt</a>' : '-'}</span></div>
            </div>
          </div>
        </div>
      </section>

      <section class="detail-card">
        <div>
          <h4>Order items</h4>
          <div class="detail-copy">Products included in this purchase.</div>
        </div>
        <div class="order-items">
          ${items.length > 0 ? items.map((item) => `
            <div class="order-item-row">
              <div>
                <strong>${escapeHtml(item.product_name || 'Product')}</strong>
                <div class="order-item-meta">${escapeHtml(item.variant_name || item.category || 'Standard item')} · Qty ${escapeHtml(item.quantity || 1)}</div>
              </div>
              <div class="order-item-price">${escapeHtml(formatCurrency(item.total_amount || item.subtotal || 0))}</div>
            </div>
          `).join('') : '<div class="orders-empty-state" style="padding:18px 0;"><h3 style="margin:0;">No line items found</h3></div>'}
        </div>
      </section>
    `;
  }

  async function loadCustomerOrders() {
    const body = document.getElementById('customerOrdersTableBody');
    const resultCount = document.getElementById('ordersResultCount');
    const subtitle = document.getElementById('ordersTableSubtitle');
    const emptyState = document.getElementById('ordersEmptyState');

    if (!body) return;

    body.innerHTML = '<tr><td colspan="8"><div class="orders-loading-state">Loading order history...</div></td></tr>';
    if (subtitle) subtitle.textContent = 'Loading your purchase history...';

    const params = new URLSearchParams();
    params.set('per_page', '100');
    params.set('page', '1');
    if (customerOrdersState.activeStatus && customerOrdersState.activeStatus !== 'all') {
      params.set('status', customerOrdersState.activeStatus);
    }
    if (customerOrdersState.searchTerm) {
      params.set('search', customerOrdersState.searchTerm);
    }

    const response = typeof apiRequest === 'function'
      ? await apiRequest('get_customer_orders.php?' + params.toString())
      : await fetch((window.API_BASE || '../api/') + 'get_customer_orders.php?' + params.toString(), { credentials: 'same-origin' }).then(async (fetchResponse) => ({
          ok: fetchResponse.ok,
          status: fetchResponse.status,
          data: await fetchResponse.json().catch(() => ({}))
        }));

    if (!response) {
      body.innerHTML = '<tr><td colspan="8"><div class="orders-empty-state"><h3>Unable to load orders</h3><p>Please refresh the page and try again.</p></div></td></tr>';
      return;
    }

    const payload = response.data || {};
    const data = payload.data && typeof payload.data === 'object' ? payload.data : payload;
    const orders = Array.isArray(data.orders) ? data.orders : [];

    customerOrdersState.orders = orders;
    customerOrdersState.filteredOrders = orders;

    const totalOrders = orders.length;
    const deliveredOrders = orders.filter((order) => ['delivered', 'completed'].includes(String(order.order_status || '').toLowerCase())).length;
    const transitOrders = orders.filter((order) => ['pending', 'processing', 'shipped', 'out_for_delivery'].includes(String(order.order_status || '').toLowerCase())).length;
    const totalSpent = orders.reduce((sum, order) => sum + Number(order.total_amount || 0), 0);

    const tbodyHtml = orders.map(renderOrderRow).join('');
    body.innerHTML = tbodyHtml || '<tr><td colspan="8"><div class="orders-empty-state"><h3>No orders found</h3><p>Your purchases will appear here once you place your first order.</p></div></td></tr>';

    if (resultCount) resultCount.textContent = totalOrders + ' order' + (totalOrders === 1 ? '' : 's');
    if (subtitle) subtitle.textContent = totalOrders > 0 ? 'Showing your most recent orders with live shipment details.' : 'No order history is available yet.';
    if (emptyState) emptyState.hidden = totalOrders > 0;

    const totalCountEl = document.getElementById('ordersTotalCount');
    const transitCountEl = document.getElementById('ordersTransitCount');
    const deliveredCountEl = document.getElementById('ordersDeliveredCount');
    const spentCountEl = document.getElementById('ordersSpentCount');
    if (totalCountEl) totalCountEl.textContent = String(totalOrders);
    if (transitCountEl) transitCountEl.textContent = String(transitOrders);
    if (deliveredCountEl) deliveredCountEl.textContent = String(deliveredOrders);
    if (spentCountEl) spentCountEl.textContent = formatCurrency(totalSpent);

    if (!tbodyHtml) {
      body.innerHTML = '<tr><td colspan="8"><div class="orders-empty-state"><h3>No orders found</h3><p>Your purchases will appear here once you place your first order.</p></div></td></tr>';
      return;
    }

    body.querySelectorAll('[data-order-open]').forEach((button) => {
      button.addEventListener('click', () => {
        openCustomerOrderModal(button.getAttribute('data-order-open'));
      });
    });
  }

  function openCustomerOrderModal(orderId) {
    const modalBackdrop = document.getElementById('customerOrderModalBackdrop');
    const modalTitle = document.getElementById('customerOrderModalTitle');
    const modalSubtitle = document.getElementById('customerOrderModalSubtitle');
    const modalBody = document.getElementById('customerOrderModalBody');
    const order = customerOrdersState.orders.find((entry) => String(entry.order_id) === String(orderId));

    if (!modalBackdrop || !modalTitle || !modalSubtitle || !modalBody || !order) return;

    customerOrdersState.activeOrder = order;
    modalTitle.textContent = order.order_number || ('Order #' + order.order_id);
    modalSubtitle.textContent = (order.vendor_name || 'Vendor') + ' · ' + formatDateValue(order.created_at || order.order_date);
    modalBody.innerHTML = renderOrderDetails(order);
    modalBackdrop.hidden = false;
    modalBackdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeCustomerOrderModal() {
    const modalBackdrop = document.getElementById('customerOrderModalBackdrop');
    if (!modalBackdrop) return;
    modalBackdrop.classList.remove('open');
    modalBackdrop.hidden = true;
    document.body.style.overflow = '';
    customerOrdersState.activeOrder = null;
  }

  function initOrders() {
    const filterButtons = document.querySelectorAll('[data-order-filter]');
    const searchInput = document.getElementById('ordersSearch');
    const modalBackdrop = document.getElementById('customerOrderModalBackdrop');
    const modalCloseButton = document.getElementById('customerOrderModalClose');

    filterButtons.forEach((button) => {
      button.addEventListener('click', async () => {
        customerOrdersState.activeStatus = button.getAttribute('data-order-filter') || 'all';
        filterButtons.forEach((entry) => entry.classList.remove('active'));
        button.classList.add('active');
        await loadCustomerOrders();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        window.clearTimeout(customerOrdersState.searchTimer);
        customerOrdersState.searchTimer = window.setTimeout(() => {
          customerOrdersState.searchTerm = searchInput.value.trim();
          loadCustomerOrders();
        }, 250);
      });
    }

    if (modalCloseButton) {
      modalCloseButton.addEventListener('click', closeCustomerOrderModal);
    }

    if (modalBackdrop) {
      modalBackdrop.addEventListener('click', (event) => {
        if (event.target === modalBackdrop) {
          closeCustomerOrderModal();
        }
      });
    }

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modalBackdrop && !modalBackdrop.hidden) {
        closeCustomerOrderModal();
      }
    });

    loadCustomerOrders();
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
