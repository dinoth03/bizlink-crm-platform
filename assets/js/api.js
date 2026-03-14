// ============================================
// API HELPER - Call all your backend API files
// ============================================

const API_BASE = 'http://localhost/bizlink-crm-platform/api/';

// Get Dashboard Stats
async function getDashboardStats() {
    try {
        const response = await fetch(API_BASE + 'get_dashboard_stats.php');
        const data = await response.json();
        if (data.success) {
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Get All Orders
async function getOrders() {
    try {
        const response = await fetch(API_BASE + 'get_orders.php');
        const data = await response.json();
        if (data.success) {
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Get All Products
async function getProducts() {
    try {
        const response = await fetch(API_BASE + 'get_products.php');
        const data = await response.json();
        if (data.success) {
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Get All Vendors
async function getVendors() {
    try {
        const response = await fetch(API_BASE + 'get_vendors.php');
        const data = await response.json();
        if (data.success) {
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Get All Customers
async function getCustomers() {
    try {
        const response = await fetch(API_BASE + 'get_customers.php');
        const data = await response.json();
        if (data.success) {
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Get Categories
async function getCategories() {
    try {
        const response = await fetch(API_BASE + 'get_categories.php');
        const data = await response.json();
        if (data.success) {
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Get Notifications for a user
async function getNotifications(params = {}) {
    try {
        const query = new URLSearchParams();
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                query.set(key, value);
            }
        });

        const response = await fetch(API_BASE + 'get_notifications.php?' + query.toString());
        const data = await response.json();
        if (data.success) {
            return data;
        }

        console.error('Error:', data.message);
        return null;
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Mark one or all notifications as read
async function markNotificationsRead(payload = {}) {
    try {
        const response = await fetch(API_BASE + 'mark_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (data.success) {
            return data;
        }

        console.error('Error:', data.message);
        return null;
    } catch (error) {
        console.error('API Error:', error);
        return null;
    }
}

// Helper: Format Number (1000000 → 1,000,000)
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

// Helper: Format Currency (1000 → Rs. 1,000)
function formatCurrency(num) {
    return 'Rs. ' + formatNumber(Math.round(num));
}

// Helper: Format Date
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}
