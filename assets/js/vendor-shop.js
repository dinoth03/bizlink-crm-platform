/* ============================================
   VENDOR SHOP PAGE - JavaScript
   ============================================ */

let vendorData = null;
let currentTab = 'products';
let filteredProducts = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const vendorId = urlParams.get('id') || urlParams.get('vendor_id');

    if (!vendorId || vendorId <= 0) {
        showError('Missing Vendor', 'No vendor ID provided. Please select a vendor from the marketplace.');
        return;
    }

    await loadVendorProfile(vendorId);
});

async function loadVendorProfile(vendorId) {
    try {
        const response = await fetch(`../api/get_vendor_profile.php?vendor_id=${vendorId}`);
        const payload = await response.json();

        if (!payload || !payload.success) {
            showError('Vendor Not Found', payload?.message || 'Could not load vendor information.');
            return;
        }

        vendorData = payload.data;
        renderVendorProfile();
        renderProducts();
        renderReviews();
        renderAbout();

    } catch (error) {
        console.error('Failed to load vendor profile:', error);
        showError('Loading Error', 'Could not load vendor information. Please try again.');
    }
}

function renderVendorProfile() {
    const vendor = vendorData.vendor;

    // Banner
    if (vendor.business_banner_url) {
        document.getElementById('vendorBanner').style.backgroundImage = `url('${vendor.business_banner_url}')`;
        document.getElementById('vendorBanner').style.backgroundSize = 'cover';
        document.getElementById('vendorBanner').style.backgroundPosition = 'center';
    }

    // Logo
    if (vendor.business_logo_url) {
        document.getElementById('vendorLogo').src = vendor.business_logo_url;
    } else {
        document.getElementById('vendorLogo').src = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect fill=%22%23333%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%22 y=%2260%22 text-anchor=%22middle%22 fill=%22%23999%22 font-size=%2240%22 font-weight=%22bold%22%3E%E2%9C%93%3C/text%3E%3C/svg%3E';
    }

    // Basic info
    document.getElementById('vendorName').textContent = vendor.business_name || 'Unnamed Vendor';
    document.getElementById('vendorCategory').textContent = vendor.business_category || 'General';
    document.getElementById('vendorDescription').textContent = vendor.business_description || 'No description available.';

    // Rating
    const rating = parseFloat(vendor.avg_rating || 0).toFixed(1);
    const reviewCount = parseInt(vendor.total_reviews || 0);
    const stars = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));
    document.getElementById('vendorRating').innerHTML = `
        <span class="stars">${stars}</span>
        <span class="rating-text">${rating} (${reviewCount} reviews)</span>
    `;

    // Status
    const statusBadge = document.getElementById('vendorStatus');
    const isVerified = vendor.verification_status === 'verified';
    statusBadge.textContent = isVerified ? '✓ Verified' : '⏳ Pending Verification';
    statusBadge.classList.toggle('verified', isVerified);

    // Contact links
    if (vendor.business_email) {
        document.getElementById('vendorEmail').href = `mailto:${vendor.business_email}`;
        document.getElementById('vendorEmail').textContent = '📧 Email';
    }

    if (vendor.business_phone) {
        document.getElementById('vendorPhone').href = `tel:${vendor.business_phone}`;
        document.getElementById('vendorPhone').textContent = '📞 Phone';
    }

    if (vendor.business_website) {
        document.getElementById('vendorWebsite').href = vendor.business_website;
        document.getElementById('vendorWebsite').textContent = '🌐 Website';
    }

    // Hide error state
    document.getElementById('errorState').classList.add('hidden');
}

function renderProducts() {
    const products = vendorData.products || [];
    filteredProducts = [...products];
    renderProductsGrid(filteredProducts);

    // Setup search
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            filteredProducts = products.filter(p =>
                p.product_name.toLowerCase().includes(query) ||
                (p.product_description && p.product_description.toLowerCase().includes(query))
            );
            renderProductsGrid(filteredProducts);
        });
    }

    // Setup category filter
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        const categories = [...new Set(products.map(p => p.category).filter(Boolean))];
        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            option.textContent = cat;
            categoryFilter.appendChild(option);
        });

        categoryFilter.addEventListener('change', (e) => {
            const selectedCategory = e.target.value;
            filteredProducts = selectedCategory
                ? products.filter(p => p.category === selectedCategory)
                : [...products];
            renderProductsGrid(filteredProducts);
        });
    }
}

function renderProductsGrid(products) {
    const grid = document.getElementById('productsGrid');

    if (!products || products.length === 0) {
        grid.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-secondary); grid-column: 1/-1;">No products found</div>';
        return;
    }

    grid.innerHTML = products.map(product => {
        const price = parseFloat(product.price || 0).toFixed(2);
        const discount = product.discount_price ? parseFloat(product.discount_price).toFixed(2) : null;
        const rating = parseFloat(product.avg_rating || 0).toFixed(1);
        const stars = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));

        return `
            <div class="product-card" onclick="viewProductDetail('${product.product_id}')">
                <img class="product-image" src="${product.primary_image_url || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22%3E%3Crect fill=%22%23333%22 width=%22200%22 height=%22150%22/%3E%3Ctext x=%22100%22 y=%2280%22 text-anchor=%22middle%22 fill=%22%23666%22 font-size=%2220%22%3EProduct Image%3C/text%3E%3C/svg%3E'}" alt="${product.product_name}"/>
                <div class="product-info">
                    <div class="product-name">${product.product_name}</div>
                    <div class="product-price">
                        ${discount ? `<del>Rs. ${price}</del> Rs. ${discount}` : `Rs. ${price}`}
                    </div>
                    <div class="product-rating">${stars} (${product.total_reviews || 0})</div>
                </div>
            </div>
        `;
    }).join('');
}

function renderReviews() {
    const reviews = vendorData.reviews || [];

    // Calculate average ratings
    const deliveryRatings = reviews.filter(r => r.delivery_speed_rating).map(r => r.delivery_speed_rating);
    const qualityRatings = reviews.filter(r => r.product_quality_rating).map(r => r.product_quality_rating);
    const commRatings = reviews.filter(r => r.communication_rating).map(r => r.communication_rating);

    const avgDelivery = deliveryRatings.length > 0 ? (deliveryRatings.reduce((a, b) => a + b) / deliveryRatings.length).toFixed(1) : 'N/A';
    const avgQuality = qualityRatings.length > 0 ? (qualityRatings.reduce((a, b) => a + b) / qualityRatings.length).toFixed(1) : 'N/A';
    const avgComm = commRatings.length > 0 ? (commRatings.reduce((a, b) => a + b) / commRatings.length).toFixed(1) : 'N/A';

    document.getElementById('overallRating').textContent = (vendorData.vendor.avg_rating || 0).toFixed(1);
    document.getElementById('qualityRating').textContent = avgQuality;
    document.getElementById('deliveryRating').textContent = avgDelivery;
    document.getElementById('communicationRating').textContent = avgComm;

    // Render review list
    const reviewsList = document.getElementById('reviewsList');

    if (!reviews || reviews.length === 0) {
        reviewsList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-secondary);">No reviews yet</div>';
        return;
    }

    reviewsList.innerHTML = reviews.map(review => {
        const stars = '★'.repeat(Math.round(review.rating)) + '☆'.repeat(5 - Math.round(review.rating));
        const date = new Date(review.created_at).toLocaleDateString();

        return `
            <div class="review-item">
                <div class="review-header">
                    <div>
                        <div class="review-author">${review.reviewer_name || 'Anonymous Customer'}</div>
                        <div class="review-date">${date}</div>
                    </div>
                    <div class="review-rating">${stars}</div>
                </div>
                <div class="review-content">${review.review_content || 'No comment provided'}</div>
            </div>
        `;
    }).join('');
}

function renderAbout() {
    const vendor = vendorData.vendor;

    const aboutContent = document.getElementById('aboutContent');
    aboutContent.innerHTML = `
        <p><strong>Business Name:</strong> ${vendor.business_name}</p>
        <p><strong>Category:</strong> ${vendor.business_category || 'N/A'}</p>
        <p><strong>Description:</strong></p>
        <p>${vendor.business_description || 'No description available'}</p>
        <p><strong>Contact Email:</strong> ${vendor.business_email || 'Not provided'}</p>
        <p><strong>Contact Phone:</strong> ${vendor.business_phone || 'Not provided'}</p>
        ${vendor.business_website ? `<p><strong>Website:</strong> <a href="${vendor.business_website}" target="_blank">${vendor.business_website}</a></p>` : ''}
        <p><strong>Member Since:</strong> ${new Date(vendor.created_at).toLocaleDateString()}</p>
    `;

    document.getElementById('totalProducts').textContent = vendor.total_products || 0;
    document.getElementById('totalReviews').textContent = vendor.total_reviews || 0;
    document.getElementById('joinDate').textContent = new Date(vendor.created_at).getFullYear();
}

function switchTab(tabName) {
    currentTab = tabName;

    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach((btn, idx) => {
        btn.classList.remove('active');
        if ((idx === 0 && tabName === 'products') ||
            (idx === 1 && tabName === 'reviews') ||
            (idx === 2 && tabName === 'info')) {
            btn.classList.add('active');
        }
    });

    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    const tabMap = {
        'products': 'productsTab',
        'reviews': 'reviewsTab',
        'info': 'infoTab'
    };

    const tabElement = document.getElementById(tabMap[tabName]);
    if (tabElement) {
        tabElement.classList.remove('hidden');
    }
}

function viewProductDetail(productId) {
    // Open product detail page
    window.location.href = `marketplace.html?product=${productId}`;
}

async function startVendorChat() {
    if (!vendorData) return;

    const vendorUserId = vendorData.vendor.user_id;
    const vendorName = vendorData.vendor.business_name;

    // Check if user is authenticated
    const identity = await authMe(false);
    if (!identity || !identity.user) {
        showToast('Please sign in to message vendors', 'info');
        window.location.href = 'index.html?reason=login_required';
        return;
    }

    // Open chat with vendor
    window.location.href = `chat.html?targetUserId=${vendorUserId}`;
}

function showError(title, message) {
    const errorState = document.getElementById('errorState');
    const vendorHeader = document.getElementById('vendorHeader');
    const vendorTabs = document.querySelector('.vendor-tabs');
    const tabContent = document.querySelector('.tab-content');

    if (vendorHeader) vendorHeader.style.display = 'none';
    if (vendorTabs) vendorTabs.style.display = 'none';
    if (tabContent) tabContent.style.display = 'none';

    document.getElementById('errorTitle').textContent = title;
    document.getElementById('errorMessage').textContent = message;
    errorState.classList.remove('hidden');
}

function showToast(message, type = 'info') {
    const stack = document.getElementById('toastStack');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    stack.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('out');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
