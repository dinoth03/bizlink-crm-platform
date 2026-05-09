/* BIZLINK – ADMIN DASHBOARD JS */

let adminState = {
  currentPage: 'dashboard',
  currentVendorStatus: 'all',
  currentProductStatus: 'all',
  currentDisputeStatus: 'all'
};

// NAVIGATION
function adminNavigate(page, el) {
  if (el) {
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
  }
  
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('page-' + page);
  if (target) {
    target.classList.add('active');
    adminState.currentPage = page;
    adminLoadPageData(page);
  }
}

function adminLoadPageData(page) {
  if (page === 'dashboard') {
    loadAdminAnalytics();
  } else if (page === 'vendors') {
    adminLoadVendors(1, adminState.currentVendorStatus);
  } else if (page === 'products') {
    adminLoadProducts(1, adminState.currentProductStatus);
  } else if (page === 'disputes') {
    adminLoadDisputes(1, adminState.currentDisputeStatus);
  }
}

// ANALYTICS
async function loadAdminAnalytics() {
  try {
    const response = await fetch('../api/admin_analytics.php');
    if (!response.ok) throw new Error('Failed to fetch analytics');
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Analytics failed');
    
    const data = result.data || {};
    
    // Update stat cards
    document.getElementById('totalOrders').textContent = (data.total_orders || 0).toLocaleString();
    document.getElementById('totalRevenue').textContent = 'Rs. ' + Math.round(data.total_revenue || 0).toLocaleString();
    document.getElementById('totalCustomers').textContent = (data.total_customers || 0).toLocaleString();
    document.getElementById('activeVendors').textContent = (data.active_vendors || 0).toLocaleString();
    document.getElementById('pendingVendors').textContent = (data.pending_vendors || 0).toLocaleString();
    document.getElementById('totalProducts').textContent = (data.total_products || 0).toLocaleString();
    document.getElementById('pendingProducts').textContent = (data.pending_products || 0).toLocaleString();
    document.getElementById('pendingDisputes').textContent = (data.pending_disputes || 0).toLocaleString();
    
    window.adminAnalytics = data;
    
  } catch (error) {
    console.error('Error loading admin analytics:', error);
  }
}

// VENDOR MANAGEMENT
async function adminLoadVendors(page, status) {
  try {
    const vendorsList = document.getElementById('vendorsList');
    vendorsList.innerHTML = '<div class="loading">Loading vendors...</div>';
    
    const query = new URLSearchParams({
      page: page,
      status: status
    });
    
    const response = await fetch(`../api/admin_vendors.php?${query}`);
    if (!response.ok) throw new Error('Failed to fetch vendors');
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Failed');
    
    const vendors = result.data?.vendors || [];
    
    if (vendors.length === 0) {
      vendorsList.innerHTML = '<div class="loading">No vendors found</div>';
      return;
    }
    
    let html = '<table><thead><tr><th>Business Name</th><th>Contact</th><th>Email</th><th>Status</th><th>Products</th><th>Sales</th><th>Actions</th></tr></thead><tbody>';
    
    vendors.forEach(v => {
      const statusBadge = v.approval_status === 'approved' ? '<span class="badge badge-success">Approved</span>' :
                         v.approval_status === 'rejected' ? '<span class="badge badge-danger">Rejected</span>' :
                         '<span class="badge badge-warning">Pending</span>';
      
      html += `
        <tr>
          <td><strong>${v.business_name}</strong></td>
          <td>${v.contact_person}</td>
          <td>${v.email}</td>
          <td>${statusBadge}</td>
          <td>${v.product_count}</td>
          <td>Rs. ${Math.round(v.total_revenue).toLocaleString()}</td>
          <td>
            <div class="action-buttons">
              <button onclick="adminOpenVendorModal(${v.vendor_id}, '${v.business_name}', '${v.email}')" class="btn btn-admin">Review</button>
            </div>
          </td>
        </tr>
      `;
    });
    
    html += '</tbody></table>';
    vendorsList.innerHTML = html;
    
  } catch (error) {
    console.error('Error loading vendors:', error);
    document.getElementById('vendorsList').innerHTML = `<div class="error">Error loading vendors: ${error.message}</div>`;
  }
}

function adminFilterVendors(status, el) {
  document.querySelectorAll('#page-vendors .filter-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  adminState.currentVendorStatus = status;
  adminLoadVendors(1, status);
}

function adminSearchVendors() {
  const search = document.getElementById('vendorSearch').value;
  // Search implementation would go here
}

function adminOpenVendorModal(vendorId, vendorName, vendorEmail) {
  document.getElementById('vendorName').value = vendorName;
  document.getElementById('vendorEmail').value = vendorEmail;
  document.getElementById('vendorAction').value = 'approved';
  document.getElementById('vendorNotes').value = '';
  
  document.getElementById('vendorActionForm').onsubmit = async (e) => {
    e.preventDefault();
    await adminSubmitVendorAction(vendorId);
  };
  
  document.getElementById('vendorModal').classList.add('active');
}

async function adminSubmitVendorAction(vendorId) {
  try {
    const action = document.getElementById('vendorAction').value;
    const notes = document.getElementById('vendorNotes').value;
    
    const response = await fetch('../api/admin_update_vendor.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        vendor_id: vendorId,
        status: action,
        notes: notes
      })
    });
    
    const result = await response.json();
    if (result.success) {
      alert('Vendor ' + action + ' successfully');
      adminCloseModal('vendorModal');
      adminLoadVendors(1, adminState.currentVendorStatus);
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error: ' + error.message);
  }
}

// PRODUCT MODERATION
async function adminLoadProducts(page, status) {
  try {
    const productsList = document.getElementById('productsList');
    productsList.innerHTML = '<div class="loading">Loading products...</div>';
    
    const query = new URLSearchParams({
      page: page,
      status: status
    });
    
    const response = await fetch(`../api/admin_products.php?${query}`);
    if (!response.ok) throw new Error('Failed to fetch products');
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Failed');
    
    const products = result.data?.products || [];
    
    if (products.length === 0) {
      productsList.innerHTML = '<div class="loading">No products found</div>';
      return;
    }
    
    let html = '<table><thead><tr><th>Product Name</th><th>Vendor</th><th>Category</th><th>Price</th><th>Status</th><th>Sales</th><th>Actions</th></tr></thead><tbody>';
    
    products.forEach(p => {
      const statusBadge = p.moderation_status === 'approved' ? '<span class="badge badge-success">Approved</span>' :
                         p.moderation_status === 'flagged' ? '<span class="badge badge-warning">Flagged</span>' :
                         p.moderation_status === 'rejected' ? '<span class="badge badge-danger">Rejected</span>' :
                         '<span class="badge badge-info">Pending</span>';
      
      html += `
        <tr>
          <td><strong>${p.product_name}</strong></td>
          <td>${p.business_name}</td>
          <td>${p.category}</td>
          <td>Rs. ${p.price.toLocaleString()}</td>
          <td>${statusBadge}</td>
          <td>${p.sales_count}</td>
          <td>
            <div class="action-buttons">
              <button onclick="adminOpenProductModal(${p.product_id}, '${p.product_name.replace(/'/g, "\\'")}', '${p.business_name.replace(/'/g, "\\'")}')" class="btn btn-admin">Review</button>
            </div>
          </td>
        </tr>
      `;
    });
    
    html += '</tbody></table>';
    productsList.innerHTML = html;
    
  } catch (error) {
    console.error('Error loading products:', error);
    document.getElementById('productsList').innerHTML = `<div class="error">Error loading products: ${error.message}</div>`;
  }
}

function adminFilterProducts(status, el) {
  document.querySelectorAll('#page-products .filter-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  adminState.currentProductStatus = status;
  adminLoadProducts(1, status);
}

function adminSearchProducts() {
  const search = document.getElementById('productSearch').value;
  // Search implementation would go here
}

function adminOpenProductModal(productId, productName, vendorName) {
  document.getElementById('productName').value = productName;
  document.getElementById('productVendor').value = vendorName;
  document.getElementById('productAction').value = 'approved';
  document.getElementById('productReason').value = '';
  
  document.getElementById('productActionForm').onsubmit = async (e) => {
    e.preventDefault();
    await adminSubmitProductAction(productId);
  };
  
  document.getElementById('productModal').classList.add('active');
}

async function adminSubmitProductAction(productId) {
  try {
    const action = document.getElementById('productAction').value;
    const reason = document.getElementById('productReason').value;
    
    const response = await fetch('../api/admin_update_product.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        product_id: productId,
        status: action,
        reason: reason
      })
    });
    
    const result = await response.json();
    if (result.success) {
      alert('Product ' + action + ' successfully');
      adminCloseModal('productModal');
      adminLoadProducts(1, adminState.currentProductStatus);
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error: ' + error.message);
  }
}

// DISPUTE RESOLUTION
async function adminLoadDisputes(page, status) {
  try {
    const disputesList = document.getElementById('disputesList');
    disputesList.innerHTML = '<div class="loading">Loading disputes...</div>';
    
    const query = new URLSearchParams({
      page: page,
      status: status
    });
    
    const response = await fetch(`../api/admin_disputes.php?${query}`);
    if (!response.ok) throw new Error('Failed to fetch disputes');
    
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Failed');
    
    const disputes = result.data?.disputes || [];
    
    if (disputes.length === 0) {
      disputesList.innerHTML = '<div class="loading">No disputes found</div>';
      return;
    }
    
    let html = '<table><thead><tr><th>Order</th><th>Customer</th><th>Vendor</th><th>Amount</th><th>Status</th><th>Days Open</th><th>Actions</th></tr></thead><tbody>';
    
    disputes.forEach(d => {
      const statusBadge = d.status === 'open' ? '<span class="badge badge-danger">Open</span>' :
                         d.status === 'refunded' ? '<span class="badge badge-success">Refunded</span>' :
                         d.status === 'replaced' ? '<span class="badge badge-success">Replaced</span>' :
                         '<span class="badge badge-info">Resolved</span>';
      
      html += `
        <tr>
          <td><strong>${d.order_number}</strong></td>
          <td>${d.customer_name}<br><small>${d.customer_email}</small></td>
          <td>${d.business_name}</td>
          <td>Rs. ${Math.round(d.total_amount).toLocaleString()}</td>
          <td>${statusBadge}</td>
          <td>${d.days_open}</td>
          <td>
            <div class="action-buttons">
              <button onclick="adminOpenDisputeModal(${d.dispute_id}, '${d.order_number}', '${d.issue_description.replace(/'/g, "\\'")}')" class="btn btn-admin">Review</button>
            </div>
          </td>
        </tr>
      `;
    });
    
    html += '</tbody></table>';
    disputesList.innerHTML = html;
    
  } catch (error) {
    console.error('Error loading disputes:', error);
    document.getElementById('disputesList').innerHTML = `<div class="error">Error loading disputes: ${error.message}</div>`;
  }
}

function adminFilterDisputes(status, el) {
  document.querySelectorAll('#page-disputes .filter-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  adminState.currentDisputeStatus = status;
  adminLoadDisputes(1, status);
}

function adminSearchDisputes() {
  const search = document.getElementById('disputeSearch').value;
  // Search implementation would go here
}

function adminOpenDisputeModal(disputeId, orderNumber, issue) {
  document.getElementById('disputeOrder').value = orderNumber;
  document.getElementById('disputeIssue').value = issue;
  document.getElementById('disputeResolution').value = 'resolved';
  document.getElementById('disputeNotes').value = '';
  
  document.getElementById('disputeActionForm').onsubmit = async (e) => {
    e.preventDefault();
    await adminSubmitDisputeResolution(disputeId);
  };
  
  document.getElementById('disputeModal').classList.add('active');
}

async function adminSubmitDisputeResolution(disputeId) {
  try {
    const resolution = document.getElementById('disputeResolution').value;
    const notes = document.getElementById('disputeNotes').value;
    
    const response = await fetch('../api/admin_resolve_dispute.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        dispute_id: disputeId,
        resolution: resolution,
        notes: notes
      })
    });
    
    const result = await response.json();
    if (result.success) {
      alert('Dispute resolved: ' + resolution);
      adminCloseModal('disputeModal');
      adminLoadDisputes(1, adminState.currentDisputeStatus);
    } else {
      alert('Error: ' + result.message);
    }
  } catch (error) {
    alert('Error: ' + error.message);
  }
}

// MODALS
function adminCloseModal(modalId) {
  document.getElementById(modalId).classList.remove('active');
}

window.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal')) {
    e.target.classList.remove('active');
  }
});

// LOGOUT
async function adminLogout() {
  try {
    if (typeof authLogout === 'function') {
      await authLogout();
    }
  } catch (error) {
    console.warn('Logout failed:', error);
  }
  window.location.href = '../pages/index.html';
}

// INIT
window.addEventListener('load', async () => {
  await loadAdminAnalytics();
  console.log('BizLink Admin Dashboard - Live data mode');
});
