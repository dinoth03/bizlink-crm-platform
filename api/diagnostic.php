<?php
/**
 * DIAGNOSTIC SCRIPT FOR MARKETPLACE DATABASE ISSUES
 * 
 * Visit: http://localhost/bizlink-crm-platform/api/diagnostic.php
 * 
 * This script will test:
 * 1. Database connection
 * 2. Database existence
 * 3. Products table existence
 * 4. Products data count
 * 5. Sample product data
 * 6. Error log contents
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Don't output JSON, output HTML for readability
header('Content-Type: text/html; charset=utf-8');

echo "<html><head><title>BizLink CRM - Database Diagnostic</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    .info { color: #17a2b8; font-weight: bold; }
    pre { background: #f8f9fa; padding: 10px; overflow-x: auto; border-radius: 3px; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #f8f9fa; }
    .log-section { background: #fff3cd; padding: 10px; border-radius: 3px; margin: 10px 0; }
</style></head><body>";

echo "<h1>🔧 BizLink CRM Database Diagnostic Tool</h1>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// TEST 1: Database Connection
echo "<div class='section'>";
echo "<h2>1. Database Connection Test</h2>";

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'bizlink_crm');
define('DB_PORT', 3306);

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, null, DB_PORT);

if ($conn) {
    echo "<p><span class='success'>✓ Connection successful to " . DB_HOST . ":" . DB_PORT . "</span></p>";
} else {
    echo "<p><span class='error'>✗ Connection failed: " . mysqli_connect_error() . "</span></p>";
    echo "<p>Make sure:</p>";
    echo "<ul>";
    echo "<li>MySQL is running (XAMPP MySQL should be started)</li>";
    echo "<li>Host is correct: <strong>" . DB_HOST . "</strong></li>";
    echo "<li>Port is correct: <strong>" . DB_PORT . "</strong></li>";
    echo "<li>Username is correct: <strong>" . DB_USER . "</strong></li>";
    echo "<li>Password is correct: <strong>" . (empty(DB_PASSWORD) ? '(empty)' : '*****') . "</strong></li>";
    echo "</ul>";
    echo "</div>";
    goto show_logs;
}

// TEST 2: Select Database
echo "<h3>2. Database Selection Test</h3>";
$db_select = mysqli_select_db($conn, DB_NAME);

if ($db_select) {
    echo "<p><span class='success'>✓ Database selected: " . DB_NAME . "</span></p>";
} else {
    echo "<p><span class='error'>✗ Cannot select database: " . mysqli_error($conn) . "</span></p>";
    echo "<p>The database <strong>" . DB_NAME . "</strong> does not exist.</p>";
    echo "<p>To create it, open <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a> and create a database named <strong>" . DB_NAME . "</strong></p>";
    mysqli_close($conn);
    echo "</div>";
    goto show_logs;
}

// TEST 3: Check Tables
echo "<h3>3. Database Tables Check</h3>";
$tables_query = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='" . DB_NAME . "'";
$tables_result = mysqli_query($conn, $tables_query);

if ($tables_result) {
    $tables = [];
    while ($row = mysqli_fetch_assoc($tables_result)) {
        $tables[] = $row['TABLE_NAME'];
    }
    
    echo "<p>Found <strong>" . count($tables) . "</strong> tables:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li><code>" . htmlspecialchars($table) . "</code></li>";
    }
    echo "</ul>";
    
    if (!in_array('products', $tables)) {
        echo "<p><span class='error'>✗ CRITICAL: 'products' table not found!</span></p>";
        echo "<p>You need to import the database schema. Check if <strong>bizlink-crm-platform.sql</strong> exists and import it via phpMyAdmin.</p>";
        mysqli_close($conn);
        echo "</div>";
        goto show_logs;
    } else {
        echo "<p><span class='success'>✓ 'products' table exists</span></p>";
    }
} else {
    echo "<p><span class='error'>✗ Cannot query tables: " . mysqli_error($conn) . "</span></p>";
    echo "</div>";
    goto show_logs;
}

// TEST 4: Products Table Structure
echo "<h3>4. Products Table Structure</h3>";
$structure_query = "DESCRIBE products";
$structure_result = mysqli_query($conn, $structure_query);

if ($structure_result && mysqli_num_rows($structure_result) > 0) {
    echo "<table>";
    echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = mysqli_fetch_assoc($structure_result)) {
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($col['Field']) . "</code></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? '(null)') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><span class='error'>✗ Cannot describe table: " . mysqli_error($conn) . "</span></p>";
}

// TEST 5: Product Count
echo "<h3>5. Products Data Count</h3>";
$count_query = "SELECT COUNT(*) as total FROM products";
$count_result = mysqli_query($conn, $count_query);

if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $product_count = $count_row['total'];
    
    if ($product_count > 0) {
        echo "<p><span class='success'>✓ Found " . $product_count . " products in database</span></p>";
    } else {
        echo "<p><span class='warning'>⚠ No products found in database (count = 0)</span></p>";
        echo "<p>This could be why the marketplace shows no items. You need to import sample data or insert products.</p>";
    }
} else {
    echo "<p><span class='error'>✗ Cannot count products: " . mysqli_error($conn) . "</span></p>";
}

// TEST 6: Sample Product Data
echo "<h3>6. Sample Product Data (First 5)</h3>";
$sample_query = "SELECT product_id, product_name, category, price, is_active 
                 FROM products LIMIT 5";
$sample_result = mysqli_query($conn, $sample_query);

if ($sample_result && mysqli_num_rows($sample_result) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Active</th></tr>";
    while ($product = mysqli_fetch_assoc($sample_result)) {
        echo "<tr>";
        echo "<td>" . $product['product_id'] . "</td>";
        echo "<td>" . htmlspecialchars($product['product_name']) . "</td>";
        echo "<td>" . htmlspecialchars($product['category']) . "</td>";
        echo "<td>Rs. " . number_format($product['price'], 2) . "</td>";
        echo "<td>" . ($product['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else if ($product_count > 0) {
    echo "<p><span class='warning'>⚠ Cannot fetch sample data: " . mysqli_error($conn) . "</span></p>";
}

// TEST 7: API Endpoint Test
echo "<h3>7. API Endpoint Test</h3>";
echo "<p>Try visiting this URL directly in your browser:</p>";
$api_url = "http://localhost/bizlink-crm-platform/api/get_products.php?page=1&per_page=24";
echo "<pre><a href='" . htmlspecialchars($api_url) . "' target='_blank'>" . htmlspecialchars($api_url) . "</a></pre>";
echo "<p>The response should be JSON with a 'success' field and array of products in the 'data' field.</p>";

// TEST 8: Vendors Table (for JOIN)
echo "<h3>8. Vendors Table Check</h3>";
$vendors_query = "SELECT COUNT(*) as total FROM vendors";
$vendors_result = mysqli_query($conn, $vendors_query);

if ($vendors_result) {
    $vendors_row = mysqli_fetch_assoc($vendors_result);
    $vendor_count = $vendors_row['total'];
    echo "<p>Vendors count: <strong>" . $vendor_count . "</strong></p>";
    
    if ($vendor_count === 0) {
        echo "<p><span class='warning'>⚠ No vendors in database. Product queries use a LEFT JOIN to vendors.</span></p>";
    }
} else {
    echo "<p><span class='warning'>⚠ Cannot count vendors: " . mysqli_error($conn) . "</span></p>";
}

echo "</div>";

mysqli_close($conn);

// SHOW ERROR LOGS
show_logs:
echo "<div class='section'>";
echo "<h2>9. Error Log Contents</h2>";

$error_log_file = dirname(__FILE__) . '/../logs/api_errors.log';

if (file_exists($error_log_file)) {
    $log_content = file_get_contents($error_log_file);
    $log_lines = explode("\n", trim($log_content));
    
    // Show last 30 lines
    $lines_to_show = array_slice($log_lines, -30);
    
    echo "<p>Last 30 log entries:</p>";
    echo "<div class='log-section'>";
    echo "<pre>" . htmlspecialchars(implode("\n", $lines_to_show)) . "</pre>";
    echo "</div>";
} else {
    echo "<p><span class='info'>ℹ No error log file found yet. Logs will appear here after API requests.</span></p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>📋 Troubleshooting Checklist</h2>";
echo "<ul>";
echo "<li>✓ MySQL is running in XAMPP</li>";
echo "<li>✓ Database 'bizlink_crm' exists</li>";
echo "<li>✓ 'products' table exists</li>";
echo "<li>✓ 'products' table has data (count > 0)</li>";
echo "<li>✓ 'vendors' table exists (for JOIN queries)</li>";
echo "<li>✓ No errors in error log</li>";
echo "<li>✓ API endpoint returns valid JSON</li>";
echo "</ul>";
echo "<p><strong>If marketplace still shows no products:</strong></p>";
echo "<ol>";
echo "<li>Open browser DevTools (F12) → Console tab</li>";
echo "<li>Check for API error messages starting with '[Marketplace]'</li>";
echo "<li>Look for the API response with product count</li>";
echo "<li>If API returns empty [], check the products table has active products (is_active = 1)</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
