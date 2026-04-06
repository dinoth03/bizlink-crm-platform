<?php
/**
 * BIZLINK CRM - PRODUCT SETUP SCRIPT
 * This script directly inserts 48 sample products into the database
 * 
 * USAGE:
 * 1. Visit: http://localhost/bizlink-crm-platform/setup_products.php
 * 2. Click "Insert 48 Products"
 * 3. All products will be added to database
 * 4. Success message will show
 * 5. Delete this file after (you don't need it anymore)
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bizlink_crm';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert') {
    
    // Note: We don't delete old products - they may have orders linked to them
    // Just INSERT the new products with new IDs (starting from 100+)
    
    // SQL INSERT statements
    $sql = "INSERT INTO products (product_name, category, vendor_id, price, quantity_in_stock, is_active, created_at) VALUES
('Galaxy Pro Laptop 15\"', 'electronics', 1, 189000, 15, 1, NOW()),
('Wireless Noise-Cancel Headphones', 'electronics', 1, 14500, 45, 1, NOW()),
('Smart CCTV 4-Camera Kit', 'electronics', 1, 32000, 8, 1, NOW()),
('Power Backup UPS 2000VA', 'electronics', 1, 21500, 20, 1, NOW()),
('POS System Touch Terminal', 'electronics', 1, 55000, 5, 1, NOW()),
('Handloom Cotton Saree', 'fashion', 2, 4800, 50, 1, NOW()),
('Men''s Linen Business Shirt', 'fashion', 2, 2800, 100, 1, NOW()),
('Batik Casual Dress', 'fashion', 2, 3500, 75, 1, NOW()),
('Leather Sandals – Handmade', 'fashion', 2, 1950, 60, 1, NOW()),
('Teak Wood Coffee Table', 'home', 1, 28500, 10, 1, NOW()),
('Coconut Shell Decor Set', 'home', 1, 1200, 120, 1, NOW()),
('Solar LED Garden Lights (Set 6)', 'home', 1, 5400, 35, 1, NOW()),
('Ceylon Black Tea – 1kg Premium', 'grocery', 3, 1850, 200, 1, NOW()),
('Cold-Pressed Coconut Oil 1L', 'grocery', 3, 950, 150, 1, NOW()),
('Organic Jaggery – 500g', 'grocery', 3, 380, 300, 1, NOW()),
('Spice Mix Bundle – 6 Varieties', 'grocery', 3, 1200, 180, 1, NOW()),
('Organic Vegetable Seeds Pack', 'agriculture', 4, 650, 250, 1, NOW()),
('Drip Irrigation Starter Kit', 'agriculture', 4, 8500, 12, 1, NOW()),
('Organic Fertilizer 25kg Bag', 'agriculture', 4, 2200, 80, 1, NOW()),
('Rubber Tapper''s Tool Set', 'agriculture', 4, 3400, 25, 1, NOW()),
('Cement – 50kg Premium Bag', 'construction', 5, 1750, 500, 1, NOW()),
('Steel Rebar – 12mm (per bundle)', 'construction', 5, 42000, 100, 1, NOW()),
('Roof Sheet Aluminum – 10ft', 'construction', 5, 3200, 200, 1, NOW()),
('Herbal Ayurvedic Oil – 100ml', 'health', 1, 780, 400, 1, NOW()),
('Digital Blood Pressure Monitor', 'health', 1, 6500, 30, 1, NOW()),
('Moringa Leaf Powder – 250g', 'health', 1, 890, 150, 1, NOW()),
('Ergonomic Office Chair', 'office', 1, 18500, 20, 1, NOW()),
('A4 Printing Paper – 5 Ream Box', 'office', 1, 4200, 500, 1, NOW()),
('Brother Printer Ink Set (4-colour)', 'office', 1, 3800, 200, 1, NOW()),
('Industrial Safety Gloves (12 pairs)', 'industrial', 5, 1800, 300, 1, NOW()),
('Electric Power Drill 800W', 'industrial', 5, 14500, 18, 1, NOW()),
('Industrial Fan 36\" Heavy Duty', 'industrial', 5, 22000, 8, 1, NOW()),
('Kraft Paper Bags (100 pcs)', 'packaging', 3, 1600, 250, 1, NOW()),
('Bubble Wrap Roll 50m', 'packaging', 3, 2400, 180, 1, NOW()),
('Website Design & Development', 'it', 1, 45000, 10, 1, NOW()),
('Cloud Hosting – 1 Year Business Plan', 'it', 1, 18000, 50, 1, NOW()),
('Social Media Management – Monthly', 'marketing', 2, 25000, 20, 1, NOW()),
('Branded Flyer Design (10 designs)', 'marketing', 2, 8500, 100, 1, NOW()),
('Monthly Bookkeeping Service', 'accounting', 3, 12000, 30, 1, NOW()),
('Annual Tax Filing & Compliance', 'accounting', 3, 35000, 15, 1, NOW()),
('Island-Wide Courier – Per Shipment', 'logistics', 4, 350, 1000, 1, NOW()),
('Cold Chain Logistics – Perishables', 'logistics', 4, 1800, 50, 1, NOW()),
('Paddy Thresher Machine – Small', 'agriculture', 4, 95000, 3, 1, NOW()),
('Coir Rope – 100m Roll', 'agriculture', 4, 1100, 200, 1, NOW()),
('Floor Tiles – Marble Finish 60x60cm', 'construction', 5, 480, 1000, 1, NOW()),
('Casio Scientific Calculator', 'office', 1, 2200, 100, 1, NOW()),
('Stainless Steel Water Bottle 1L', 'health', 1, 1450, 200, 1, NOW()),
('Jumbo Cardboard Box (10 pcs)', 'packaging', 3, 1900, 400, 1, NOW())";

    if ($conn->query($sql)) {
        $success = true;
        $message = "✅ SUCCESS! 48 new products have been added to your database!<br><small>(Now you'll have 6 original + 48 new = 54 total products)</small>";
    } else {
        $success = false;
        $message = "❌ Error inserting products: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BizLink CRM - Product Setup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 30px;
            text-align: left;
            border-radius: 5px;
        }
        
        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .info-box ul {
            list-style: none;
            font-size: 13px;
            color: #555;
        }
        
        .info-box li {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .info-box li:before {
            content: "✓";
            color: #667eea;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        button {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .product-count {
            font-size: 13px;
            color: #666;
            margin-top: 10px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .next-steps {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            text-align: left;
        }
        
        .next-steps h4 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .next-steps ol {
            margin-left: 20px;
            font-size: 13px;
            color: #856404;
        }
        
        .next-steps li {
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛒 BizLink Product Setup</h1>
        <p class="subtitle">Add 48 sample products to your marketplace</p>
        
        <div class="info-box">
            <h3>What will be added:</h3>
            <ul>
                <li>48 new products (adding to your 6 existing)</li>
                <li>14 different categories</li>
                <li>Realistic prices and stock</li>
                <li>5 vendor assignments</li>
            </ul>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
            
            <?php if ($success): ?>
                <div class="next-steps">
                    <h4>✓ What's Next:</h4>
                    <ol>
                        <li>Open your marketplace: <code>http://localhost/bizlink-crm-platform/pages/marketplace.html</code></li>
                        <li>Press <strong>Ctrl+F5</strong> to hard refresh</li>
                        <li>You should now see <strong>54 products total</strong> (6 original + 48 new)</li>
                        <li>Delete this setup file when done</li>
                    </ol>
                </div>
                
                <div class="button-group" style="margin-top: 30px;">
                    <button class="btn-primary" onclick="window.location.href='http://localhost/bizlink-crm-platform/pages/marketplace.html'">
                        🛒 View Marketplace
                    </button>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="insert">
                
                <button type="submit" class="btn-primary">
                    ✨ Insert 48 Products Now
                </button>
            </form>
            
            <p class="product-count">This will take about 2-3 seconds</p>
            
            <div class="next-steps">
                <h4>⚠️ Important:</h4>
                <ol>
                    <li>Make sure MySQL is running</li>
                    <li>Database <strong>bizlink_crm</strong> must exist</li>
                    <li>Your 6 existing products will NOT be deleted</li>
                    <li>Click the button above to add 48 new products</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
