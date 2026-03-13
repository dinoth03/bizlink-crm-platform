# BizLink CRM - Complete Backend Setup Guide (PHP + MySQL)

**ONE FILE TO RULE THEM ALL** - Everything you need, nothing you don't.

**No remote repository is required for this setup.** You can run everything fully on your local machine with XAMPP.

---

## Table of Contents
1. [What is a Backend?](#what-is-a-backend)
2. [Why PHP + MySQL?](#why-php--mysql)
3. [Installation Steps](#installation-steps)
4. [File Structure](#file-structure)
5. [Creating API Files](#creating-api-files)
6. [Connecting Frontend to Backend](#connecting-frontend-to-backend)
7. [Testing](#testing)
8. [Troubleshooting](#troubleshooting)

---

## What is a Backend?

Your CRM has:
- **Frontend** = HTML/CSS/JS (what users see)
- **Backend** = PHP code that talks to database
- **Database** = MySQL (where data is stored)

```
User clicks button in HTML
    ↓
JavaScript sends request to PHP
    ↓
PHP connects to MySQL, gets data
    ↓
PHP sends data back as JSON
    ↓
JavaScript displays data in HTML
```

**The backend is the MIDDLEMAN between your webpage and database.**

---

## Why PHP + MySQL?

| Feature | PHP | Node.js |
|---------|-----|---------|
| **Setup Time** | 5 minutes ⚡ | 30 minutes |
| **Learning Curve** | Easy ✅ | Medium |
| **For Your Project** | PERFECT | Overkill |
| **Hosting** | Everywhere | Need special host |
| **Code Size** | Small files | Large setup |

**Choose: PHP** ✅

---

## Installation Steps

### Step 1: Install XAMPP (Apache + PHP + MySQL)

**Download:**
- Go to: https://www.apachefriends.org/
- Click "Download XAMPP for Windows"
- Download the latest version

**Install:**
1. Run the `.exe` file
2. Choose folder: `C:\xampp\`
3. Keep clicking "Next" → "Install"
4. Finish installation

**Start Services:**
1. Open: `C:\xampp\xampp-control-panel.exe`
2. Click "Start" next to **Apache** (turns green)
3. Click "Start" next to **MySQL** (turns green)

```
Output:
Apache Running on Port 80
MySQL Running on Port 3306
```

✅ **XAMPP is now running!**

---

### Step 2: Move Your Project to XAMPP

**Move your BizLink folder:**

```
FROM: f:\Plymouth\3rd year\final year project\bizlink-crm-platform\
TO:   C:\xampp\htdocs\bizlink-crm-platform\
```

**How:**
1. Open: `C:\xampp\htdocs\`
2. Create new folder: `bizlink-crm-platform`
3. Copy **all files** from your project folder into it

**Verify URL works:**
- Open browser: `http://localhost/bizlink-crm-platform/`
- You should see your home page

✅ **Your project is now accessible via web!**

---

### Step 3: Create Database

**Step 3.1: Open phpMyAdmin**
1. Open browser: `http://localhost/phpmyadmin`
2. Login with username: `root` (no password needed)

**Step 3.2: Import your database**
1. Click "Import" tab
2. Click "Choose File"
3. Select: `bizlink-crm-platform.sql` from your project folder
4. Click "Import" button
5. Wait for success message ✅

**Verify:**
- Left sidebar should show `bizlink_crm` database
- Click it to see all tables (users, orders, products, etc.)

✅ **Database is ready!**

---

## File Structure

Your project will look like this:

```
C:\xampp\htdocs\bizlink-crm-platform\
│
├── api/                                ← API FOLDER (NEW)
│   ├── config.php                      ← Database connection
│   ├── get_orders.php
│   ├── get_products.php
│   ├── get_vendors.php
│   ├── get_customers.php
│   ├── get_dashboard_stats.php
│   └── get_categories.php
│
├── pages/
│   ├── home.html
│   ├── index.html
│   ├── contact.html
│   ├── marketplace.html
│   └── ...
│
├── admin/
│   ├── dashboard.html
│   └── order.html
│
├── customer/
│   ├── dashboard.html
│   └── userprofile.html
│
├── vendor/
│   ├── vendorpanel.html
│   └── ...
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── bizlink-crm-platform.sql
└── README.md
```

---

## Creating API Files

### Step 1: Create the API Folder

1. Go to: `C:\xampp\htdocs\bizlink-crm-platform\`
2. Create new folder: `api`
3. Inside `api` folder, create these files (copy-paste the code below)

---

### File 1: `api/config.php`

**This connects PHP to your MySQL database**

```php
<?php
// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');              // XAMPP default is no password
define('DB_NAME', 'bizlink_crm');
define('DB_PORT', 3306);

// Create Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

// Check Connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set Character Set (important for Unicode)
$conn->set_charset("utf8mb4");

// ============================================
// CORS HEADERS (Allow Frontend to Call API)
// ============================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests from browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

?>
```

**Location:** `C:\xampp\htdocs\bizlink-crm-platform\api\config.php`

---

### File 2: `api/get_dashboard_stats.php`

**This gets numbers for your admin dashboard (total orders, revenue, customers, etc.)**

```php
<?php
require 'config.php';

// Collect all statistics
$stats = array();

// 1. Total Orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders");
$row = $result->fetch_assoc();
$stats['total_orders'] = (int)$row['count'];

// 2. Total Revenue (completed orders only)
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE order_status = 'completed'");
$row = $result->fetch_assoc();
$stats['total_revenue'] = (float)($row['total'] ?? 0);

// 3. Active Customers
$result = $conn->query("SELECT COUNT(*) as count FROM customers WHERE customer_status = 'active'");
$row = $result->fetch_assoc();
$stats['active_customers'] = (int)$row['count'];

// 4. Active Vendors
$result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE vendor_status = 'active'");
$row = $result->fetch_assoc();
$stats['active_vendors'] = (int)$row['count'];

// 5. Total Products
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE product_status = 'active'");
$row = $result->fetch_assoc();
$stats['total_products'] = (int)$row['count'];

// 6. Pending Orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'");
$row = $result->fetch_assoc();
$stats['pending_orders'] = (int)$row['count'];

// Send response
echo json_encode([
    'success' => true,
    'data' => $stats,
    'timestamp' => date('Y-m-d H:i:s')
]);

$conn->close();
?>
```

**Location:** `C:\xampp\htdocs\bizlink-crm-platform\api\get_dashboard_stats.php`

---

### File 3: `api/get_orders.php`

**Gets all orders from database**

```php
<?php
require 'config.php';

// Get orders with customer information
$query = "SELECT 
    o.order_id,
    o.order_number,
    o.customer_id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    u.email,
    o.total_amount,
    o.order_status,
    o.created_at
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
ORDER BY o.created_at DESC
LIMIT 50";

$result = $conn->query($query);

if ($result) {
    $orders = array();
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'count' => count($orders)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching orders: ' . $conn->error
    ]);
}

$conn->close();
?>
```

**Location:** `C:\xampp\htdocs\bizlink-crm-platform\api\get_orders.php`

---

### File 4: `api/get_products.php`

**Gets all products from database**

```php
<?php
require 'config.php';

$query = "SELECT 
    p.product_id,
    p.product_name,
    p.category_id,
    pc.category_name,
    p.vendor_id,
    v.shop_name,
    p.base_price,
    p.stock_quantity,
    p.product_status,
    p.created_at
FROM products p
LEFT JOIN product_categories pc ON p.category_id = pc.category_id
LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
ORDER BY p.created_at DESC
LIMIT 100";

$result = $conn->query($query);

if ($result) {
    $products = array();
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching products: ' . $conn->error
    ]);
}

$conn->close();
?>
```

**Location:** `C:\xampp\htdocs\bizlink-crm-platform\api\get_products.php`

---

### File 5: `api/get_vendors.php`

**Gets all vendors with their product count**

```php
<?php
require 'config.php';

$query = "SELECT 
    v.vendor_id,
    v.vendor_name,
    v.shop_name,
    u.email,
    v.commission_rate,
    v.vendor_status,
    v.created_at,
    COUNT(p.product_id) as total_products
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
LEFT JOIN products p ON v.vendor_id = p.vendor_id
GROUP BY v.vendor_id
ORDER BY v.created_at DESC
LIMIT 100";

$result = $conn->query($query);

if ($result) {
    $vendors = array();
    while($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $vendors,
        'count' => count($vendors)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching vendors: ' . $conn->error
    ]);
}

$conn->close();
?>
```

**Location:** `C:\xampp\htdocs\bizlink-crm-platform\api\get_vendors.php`

---

### File 6: `api/get_customers.php`

**Gets all customers with order information**

```php
<?php
require 'config.php';

$query = "SELECT 
    c.customer_id,
    c.first_name,
    c.last_name,
    u.email,
    c.phone,
    c.address,
    c.city,
    c.country,
    c.customer_status,
    COUNT(o.order_id) as total_orders,
    SUM(o.total_amount) as total_spent,
    c.created_at
FROM customers c
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN orders o ON c.customer_id = o.customer_id
GROUP BY c.customer_id
ORDER BY c.created_at DESC
LIMIT 100";

$result = $conn->query($query);

if ($result) {
    $customers = array();
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $customers,
        'count' => count($customers)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching customers: ' . $conn->error
    ]);
}

$conn->close();
?>
```

**Location:** `C:\xampp\htdocs\bizlink-crm-platform\api\get_customers.php`

---

### File 7: `api/get_categories.php`

**Gets all product categories**

```php
<?php
require 'config.php';

$query = "SELECT 
    category_id,
    category_name,
    category_slug,
    category_description,
    sort_order
FROM product_categories
ORDER BY sort_order ASC";

$result = $conn->query($query);

if ($result) {
    $categories = array();
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $categories,
        'count' => count($categories)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching categories: ' . $conn->error
    ]);
}

$conn->close();
?>
```

**Location:** `C:\xampp\htdocs\bizlink-crm-platform\api\get_categories.php`

---

## Connecting Frontend to Backend

Now update your HTML files to get REAL DATA from the database instead of fake/hardcoded data.

### Step 1: Add API JavaScript Helper

Create file: `C:\xampp\htdocs\bizlink-crm-platform\assets\js\api.js`

```javascript
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
```

**Include this in your HTML:**
```html
<script src="../assets/js/api.js"></script>
```

---

### Step 2: Update Admin Dashboard

**Find:** `C:\xampp\htdocs\bizlink-crm-platform\admin\dashboard.html`

**Add this before closing `</body>` tag:**

```html
<script src="../assets/js/api.js"></script>
<script>
    // Load dashboard when page loads
    document.addEventListener('DOMContentLoaded', async function() {
        
        // Get stats from database
        const stats = await getDashboardStats();
        
        if (stats) {
            // Update KPI Cards
            // Find the elements with data-count and update their text
            
            // Total Revenue
            const revenueElement = document.querySelector('[class="kpi-value"][data-count="4820000"]');
            if (revenueElement) {
                revenueElement.textContent = formatCurrency(stats.total_revenue);
            }
            
            // Active Customers
            const customersElement = document.querySelector('[class="kpi-value"][data-count="12480"]');
            if (customersElement) {
                customersElement.textContent = formatNumber(stats.active_customers);
            }
            
            // Total Orders
            const ordersElement = document.querySelector('[class="kpi-value"][data-count="3847"]');
            if (ordersElement) {
                ordersElement.textContent = formatNumber(stats.total_orders);
            }
            
            // Active Vendors
            const vendorsElement = document.querySelector('[class="kpi-value"][data-count="320"]');
            if (vendorsElement) {
                vendorsElement.textContent = formatNumber(stats.active_vendors);
            }
        }
    });
</script>
```

---

### Step 3: Update Orders Page

**Find:** `C:\xampp\htdocs\bizlink-crm-platform\admin\order.html`

**Add before closing `</body>` tag:**

```html
<script src="../assets/js/api.js"></script>
<script>
    // Load orders table
    document.addEventListener('DOMContentLoaded', async function() {
        const orders = await getOrders();
        
        if (orders && orders.length > 0) {
            let tableHTML = '';
            
            orders.forEach(order => {
                tableHTML += `
                    <tr>
                        <td>${order.order_number}</td>
                        <td>${order.customer_name}</td>
                        <td>${order.email}</td>
                        <td>${formatCurrency(order.total_amount)}</td>
                        <td>
                            <span class="status-badge status-${order.order_status}">
                                ${order.order_status}
                            </span>
                        </td>
                        <td>${formatDate(order.created_at)}</td>
                        <td>
                            <button class="action-btn">View</button>
                        </td>
                    </tr>
                `;
            });
            
            // Insert into table
            const tbody = document.querySelector('table tbody');
            if (tbody) {
                tbody.innerHTML = tableHTML;
            }
        }
    });
</script>
```

---

### Step 4: Update Marketplace

**Find:** `C:\xampp\htdocs\bizlink-crm-platform\pages\marketplace.html`

**Add before closing `</body>` tag:**

```html
<script src="../assets/js/api.js"></script>
<script>
    // Load products
    document.addEventListener('DOMContentLoaded', async function() {
        const products = await getProducts();
        
        if (products && products.length > 0) {
            let html = '';
            
            products.forEach(product => {
                html += `
                    <div class="product-card">
                        <h3>${product.product_name}</h3>
                        <p class="category">${product.category_name}</p>
                        <p class="vendor">By: ${product.shop_name}</p>
                        <p class="price">${formatCurrency(product.base_price)}</p>
                        <p class="stock">Stock: ${product.stock_quantity}</p>
                        <button class="btn-primary">Add to Cart</button>
                    </div>
                `;
            });
            
            // Insert into container (adjust selector based on your HTML)
            const container = document.querySelector('.products-container');
            if (container) {
                container.innerHTML = html;
            }
        }
    });
</script>
```

---

## Testing

### Test 1: Check if API files work

**Open browser:**
```
http://localhost/bizlink-crm-platform/api/get_dashboard_stats.php
```

**You should see JSON like:**
```json
{
  "success": true,
  "data": {
    "total_orders": 0,
    "total_revenue": 0,
    "active_customers": 0,
    "active_vendors": 0,
    "total_products": 0,
    "pending_orders": 0
  },
  "timestamp": "2026-03-11 10:30:45"
}
```

✅ **If you see this, API is working!**

---

### Test 2: Check if dashboard loads data

**Open browser:**
```
http://localhost/bizlink-crm-platform/admin/dashboard.html
```

**Check:**
- Does it show numbers instead of 0?
- Open DevTools (F12 → Console)
- Look for any red errors

✅ **If you see real numbers and no errors, you're done!**

---

### Test 3: Check if orders table loads

**Open browser:**
```
http://localhost/bizlink-crm-platform/admin/order.html
```

**Check:**
- Are there rows in the table?
- Do they show real data from database?

✅ **If yes, backend is connected!**

---

## Troubleshooting

### Problem: "Connection failed"

**Solution:**
1. Make sure MySQL is running in XAMPP Control Panel
2. Check that database `bizlink_crm` exists
   - Open: `http://localhost/phpmyadmin`
   - Look in left sidebar for `bizlink_crm`

---

### Problem: API URL returns "404 Error"

**Solution:**
1. Check that folder path is correct:
   ```
    C:\xampp\htdocs\bizlink-crm-platform\api\
   ```

2. Check that API file names are correct:
   ```
   config.php
   get_dashboard_stats.php
   get_orders.php
   ```

3. Restart Apache in XAMPP Control Panel

---

### Problem: "CORS error" in browser console

**Solution:**
- Make sure `config.php` has these lines:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

---

### Problem: Table shows no data

**Solution:**
1. Check browser console (F12)
2. Look for red error messages
3. Open: `http://localhost/phpmyadmin`
4. Click on tables to see if they have data
5. If tables are empty, import `bizlink-crm-platform.sql`

---

### Problem: "Undefined variable" errors

**Solution:**
1. Open file in VS Code
2. Check spelling of variable names
3. Make sure `require 'config.php';` is at top of file
4. Restart Apache

---

## Summary

### What You Did:
✅ Installed XAMPP (Apache + PHP + MySQL)
✅ Moved project to `C:\xampp\htdocs\bizlink-crm-platform\`
✅ Created `api/` folder with 7 PHP files
✅ Connected PHP to MySQL database
✅ Created `api.js` helper with fetch functions
✅ Updated HTML files to call APIs
✅ Tested everything

### How It Works:
1. User opens your webpage in browser
2. JavaScript calls PHP file (e.g., `get_orders.php`)
3. PHP connects to MySQL and gets data
4. PHP sends data back as JSON
5. JavaScript displays data in HTML

### Next Steps:
1. Update ALL your HTML pages with fetch code
2. Customize API queries for your needs
3. Add more PHP files for other features
4. Test thoroughly before submitting project

---

## Files Reference

| File | Purpose | Location |
|------|---------|----------|
| `config.php` | Database connection | `api/config.php` |
| `get_dashboard_stats.php` | Dashboard numbers | `api/get_dashboard_stats.php` |
| `get_orders.php` | All orders | `api/get_orders.php` |
| `get_products.php` | All products | `api/get_products.php` |
| `get_vendors.php` | All vendors | `api/get_vendors.php` |
| `get_customers.php` | All customers | `api/get_customers.php` |
| `get_categories.php` | Product categories | `api/get_categories.php` |
| `api.js` | Frontend helper | `assets/js/api.js` |

---

## Quick Checklist

- [ ] XAMPP installed and running
- [ ] Project moved to `C:\xampp\htdocs\bizlink-crm-platform\`
- [ ] Database imported in phpMyAdmin
- [ ] Created `api/` folder
- [ ] Created all 7 PHP files
- [ ] Created `api.js` helper
- [ ] Updated at least one HTML file
- [ ] Tested API in browser
- [ ] Tested dashboard page
- [ ] No red errors in console

**DONE! Your backend is now connected! 🎉**

