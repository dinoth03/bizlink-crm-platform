// ============================================
// API HELPER - Call all your backend API files
// ============================================

const API_BASE = (() => {
    // Allow explicit override from HTML: window.API_BASE = 'https://.../api/'
    if (window.API_BASE && typeof window.API_BASE === 'string') {
        return window.API_BASE;
    }

    const origin = window.location.origin;
    const host = window.location.hostname;

    // Local XAMPP path keeps project under /bizlink-crm-platform
    if (host === 'localhost' || host === '127.0.0.1') {
        return origin + '/bizlink-crm-platform/api/';
    }

    // Production (Cloud Run / custom domain) serves from root
    return origin + '/api/';
})();

let csrfTokenCache = null;

function isStateChangingMethod(method) {
    const m = String(method || 'GET').toUpperCase();
    return m === 'POST' || m === 'PUT' || m === 'PATCH' || m === 'DELETE';
}

async function fetchCsrfToken() {
    const response = await fetch(API_BASE + 'csrf_token.php', { method: 'GET' });
    const payload = await parseJsonSafely(response);
    if (payload && payload.success && payload.data && payload.data.token) {
        csrfTokenCache = payload.data.token;
        return csrfTokenCache;
    }
    return null;
}

async function ensureCsrfToken() {
    if (csrfTokenCache) {
        return csrfTokenCache;
    }
    return fetchCsrfToken();
}

function getLoginPageUrl(reason = 'session_expired') {
    const origin = window.location.origin;
    const host = window.location.hostname;
    const params = new URLSearchParams({ reason });

    if (host === 'localhost' || host === '127.0.0.1') {
        return origin + '/bizlink-crm-platform/pages/index.html?' + params.toString();
    }

    return origin + '/pages/index.html?' + params.toString();
}

function handleAuthFailure(status, payload = {}) {
    let reason = 'session_expired';

    if (status === 429 || payload.code === 'rate_limited') {
        reason = 'too_many_requests';
    }

    if (payload.code === 'unauthorized') {
        reason = 'unauthorized';
    }

    window.location.href = getLoginPageUrl(reason);
}

async function parseJsonSafely(response) {
    const text = await response.text();
    if (!text) return {};
    try {
        return JSON.parse(text);
    } catch (error) {
        return {
            success: false,
            message: 'Invalid JSON response from server.'
        };
    }
}

async function apiRequest(path, options = {}, shouldRedirectOnAuthFailure = true) {
    const requestOptions = {
        method: (options.method || 'GET').toUpperCase(),
        ...options
    };

    if (!requestOptions.headers) {
        requestOptions.headers = {};
    }

    if (isStateChangingMethod(requestOptions.method)) {
        const csrfToken = await ensureCsrfToken();
        if (csrfToken) {
            requestOptions.headers['X-CSRF-Token'] = csrfToken;
        }
    }

    const response = await fetch(API_BASE + path, requestOptions);
    const data = await parseJsonSafely(response);

    if (response.status === 403 && data && data.code === 'CSRF_VALIDATION_FAILED' && isStateChangingMethod(requestOptions.method)) {
        csrfTokenCache = null;
        const retryToken = await ensureCsrfToken();
        if (retryToken) {
            requestOptions.headers['X-CSRF-Token'] = retryToken;
            const retryResponse = await fetch(API_BASE + path, requestOptions);
            const retryData = await parseJsonSafely(retryResponse);
            return {
                ok: retryResponse.ok,
                status: retryResponse.status,
                data: retryData
            };
        }
    }

    if ((response.status === 401 || response.status === 429) && shouldRedirectOnAuthFailure) {
        handleAuthFailure(response.status, data || {});
        return null;
    }

    return {
        ok: response.ok,
        status: response.status,
        data
    };
}

function flattenEnvelope(envelope) {
    if (!envelope || typeof envelope !== 'object') {
        return envelope;
    }

    const flattened = {
        success: !!envelope.success,
        code: envelope.code || '',
        message: envelope.message || '',
        errors: Array.isArray(envelope.errors) ? envelope.errors : [],
        meta: envelope.meta || {}
    };

    if (envelope.data !== undefined) {
        flattened.data = envelope.data;
        if (envelope.data && typeof envelope.data === 'object' && !Array.isArray(envelope.data)) {
            Object.assign(flattened, envelope.data);
        }
    }

    return flattened;
}

// Auth Signup
async function authSignup(payload) {
    try {
        const result = await apiRequest('auth_signup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload || {})
        }, false);

        return result ? flattenEnvelope(result.data || { success: false, message: 'Signup failed.' }) : { success: false, message: 'Signup failed.' };
    } catch (error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: 'Unable to connect to signup service.'
        };
    }
}

// Auth Login
async function authLogin(payload) {
    try {
        const result = await apiRequest('auth_login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload || {})
        }, false);

        return result ? flattenEnvelope(result.data || { success: false, message: 'Login failed.' }) : { success: false, message: 'Login failed.' };
    } catch (error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: 'Unable to connect to login service.'
        };
    }
}

async function authLogout() {
    try {
        const result = await apiRequest('auth_logout.php', {
            method: 'POST'
        }, false);

        return result ? flattenEnvelope(result.data || { success: false, message: 'Logout failed.' }) : { success: false, message: 'Logout failed.' };
    } catch (error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: 'Unable to connect to logout service.'
        };
    }
}

async function authForgotPassword(payload) {
    try {
        const result = await apiRequest('auth_forgot_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload || {})
        }, false);

        return result ? flattenEnvelope(result.data || { success: false, message: 'Request failed.' }) : { success: false, message: 'Request failed.' };
    } catch (error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: 'Unable to connect to password reset service.'
        };
    }
}

async function authResetPassword(payload) {
    try {
        const result = await apiRequest('auth_reset_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload || {})
        }, false);

        return result ? flattenEnvelope(result.data || { success: false, message: 'Reset failed.' }) : { success: false, message: 'Reset failed.' };
    } catch (error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: 'Unable to connect to reset service.'
        };
    }
}

async function authVerifyEmail(payload) {
    try {
        const token = (payload && payload.token) ? String(payload.token) : '';
        const query = new URLSearchParams({ verify_token: token }).toString();
        const result = await apiRequest('auth_verify_email.php?' + query, {
            method: 'GET'
        }, false);

        return result ? flattenEnvelope(result.data || { success: false, message: 'Verification failed.' }) : { success: false, message: 'Verification failed.' };
    } catch (error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: 'Unable to connect to verification service.'
        };
    }
}

async function authResendVerification(payload) {
    try {
        const result = await apiRequest('auth_resend_verification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload || {})
        }, false);

        return result ? flattenEnvelope(result.data || { success: false, message: 'Resend failed.' }) : { success: false, message: 'Resend failed.' };
    } catch (error) {
        console.error('API Error:', error);
        return {
            success: false,
            message: 'Unable to connect to verification service.'
        };
    }
}

// Get Dashboard Stats
async function getDashboardStats() {
    try {
        const result = await apiRequest('get_dashboard_stats.php');
        if (!result) return null;
        const data = result.data || {};
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
        const result = await apiRequest('get_orders.php');
        if (!result) return null;
        const data = result.data || {};
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
        const result = await apiRequest('get_products.php');
        if (!result) return null;
        const data = result.data || {};
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
        const result = await apiRequest('get_vendors.php');
        if (!result) return null;
        const data = result.data || {};
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
        const result = await apiRequest('get_customers.php');
        if (!result) return null;
        const data = result.data || {};
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
        const result = await apiRequest('get_categories.php');
        if (!result) return null;
        const data = result.data || {};
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

        const result = await apiRequest('get_notifications.php?' + query.toString());
        if (!result) return null;
        const data = result.data || {};
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
        const result = await apiRequest('mark_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!result) return null;
        const data = result.data || {};
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
