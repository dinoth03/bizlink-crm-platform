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
