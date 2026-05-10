<?php
require_once __DIR__ . '/api_helpers.php';

function ensurePromotionalTables(mysqli $conn): void {
    // Coupons/Discount Codes table
    $conn->query("CREATE TABLE IF NOT EXISTS coupons (
        coupon_id INT PRIMARY KEY AUTO_INCREMENT,
        coupon_code VARCHAR(50) UNIQUE NOT NULL,
        vendor_id INT DEFAULT NULL,
        discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
        discount_value DECIMAL(10,2) NOT NULL,
        max_uses INT DEFAULT NULL,
        current_uses INT DEFAULT 0,
        min_order_amount DECIMAL(15,2) DEFAULT 0,
        max_discount_amount DECIMAL(15,2) DEFAULT NULL,
        valid_from DATETIME NOT NULL,
        valid_until DATETIME NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        description TEXT,
        terms_and_conditions TEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_coupon_code (coupon_code),
        INDEX idx_vendor (vendor_id),
        INDEX idx_active (is_active),
        INDEX idx_valid_dates (valid_from, valid_until),
        CONSTRAINT fk_coupon_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE SET NULL,
        CONSTRAINT fk_coupon_creator FOREIGN KEY (created_by) REFERENCES users(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Coupon Usage Tracking
    $conn->query("CREATE TABLE IF NOT EXISTS coupon_usage (
        coupon_usage_id INT PRIMARY KEY AUTO_INCREMENT,
        coupon_id INT NOT NULL,
        customer_id INT NOT NULL,
        order_id INT NULL,
        discount_applied DECIMAL(15,2) NOT NULL,
        used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_coupon (coupon_id),
        INDEX idx_customer (customer_id),
        INDEX idx_order (order_id),
        CONSTRAINT fk_coupon_usage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(coupon_id) ON DELETE CASCADE,
        CONSTRAINT fk_coupon_usage_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
        CONSTRAINT fk_coupon_usage_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bulk Discount Rules (volume-based pricing)
    $conn->query("CREATE TABLE IF NOT EXISTS bulk_discounts (
        bulk_discount_id INT PRIMARY KEY AUTO_INCREMENT,
        vendor_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        category VARCHAR(255) DEFAULT NULL,
        min_quantity INT NOT NULL,
        max_quantity INT DEFAULT NULL,
        discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
        discount_value DECIMAL(10,2) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_vendor (vendor_id),
        INDEX idx_product (product_id),
        INDEX idx_category (category),
        INDEX idx_active (is_active),
        CONSTRAINT fk_bulk_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE CASCADE,
        CONSTRAINT fk_bulk_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seasonal Sales / Time-based Promotions
    $conn->query("CREATE TABLE IF NOT EXISTS seasonal_sales (
        seasonal_sale_id INT PRIMARY KEY AUTO_INCREMENT,
        sale_name VARCHAR(255) NOT NULL,
        sale_description TEXT,
        vendor_id INT DEFAULT NULL,
        discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
        discount_value DECIMAL(10,2) NOT NULL,
        max_discount_per_item DECIMAL(15,2) DEFAULT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        banner_image_url VARCHAR(500),
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_vendor (vendor_id),
        INDEX idx_dates (starts_at, ends_at),
        INDEX idx_active (is_active),
        CONSTRAINT fk_seasonal_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id) ON DELETE SET NULL,
        CONSTRAINT fk_seasonal_creator FOREIGN KEY (created_by) REFERENCES users(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Products/Categories included in Seasonal Sales
    $conn->query("CREATE TABLE IF NOT EXISTS seasonal_sale_items (
        seasonal_sale_item_id INT PRIMARY KEY AUTO_INCREMENT,
        seasonal_sale_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        category VARCHAR(255) DEFAULT NULL,
        applies_to_type ENUM('product', 'category', 'vendor_all') DEFAULT 'product',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_seasonal_sale (seasonal_sale_id),
        INDEX idx_product (product_id),
        INDEX idx_category (category),
        CONSTRAINT fk_seasonal_item_sale FOREIGN KEY (seasonal_sale_id) REFERENCES seasonal_sales(seasonal_sale_id) ON DELETE CASCADE,
        CONSTRAINT fk_seasonal_item_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function validateCoupon(mysqli $conn, string $code, float $orderSubtotal, int $customerId = 0): array {
    $code = strtoupper(trim($code));
    
    $stmt = $conn->prepare('SELECT c.* FROM coupons c WHERE UPPER(c.coupon_code) = ? AND c.is_active = 1 AND c.valid_from <= NOW() AND c.valid_until >= NOW() LIMIT 1');
    if (!$stmt) {
        return ['valid' => false, 'error' => 'DB_ERROR'];
    }
    
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$coupon) {
        return ['valid' => false, 'error' => 'COUPON_NOT_FOUND'];
    }
    
    if ((float)$orderSubtotal < (float)$coupon['min_order_amount']) {
        return ['valid' => false, 'error' => 'MINIMUM_ORDER_NOT_MET', 'minimum' => (float)$coupon['min_order_amount']];
    }
    
    if ($coupon['max_uses'] !== null && (int)$coupon['current_uses'] >= (int)$coupon['max_uses']) {
        return ['valid' => false, 'error' => 'COUPON_EXPIRED'];
    }
    
    // Calculate discount
    $discountAmount = 0.0;
    if ($coupon['discount_type'] === 'percentage') {
        $discountAmount = ($orderSubtotal * (float)$coupon['discount_value']) / 100;
    } else {
        $discountAmount = (float)$coupon['discount_value'];
    }
    
    if ($coupon['max_discount_amount'] !== null) {
        $discountAmount = min($discountAmount, (float)$coupon['max_discount_amount']);
    }
    
    $discountAmount = round(max(0, min($discountAmount, $orderSubtotal)), 2);
    
    return [
        'valid' => true,
        'coupon_id' => (int)$coupon['coupon_id'],
        'coupon_code' => $coupon['coupon_code'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value'],
        'discount_amount' => $discountAmount,
        'min_order_amount' => (float)$coupon['min_order_amount'],
        'max_uses' => $coupon['max_uses'],
        'current_uses' => (int)$coupon['current_uses']
    ];
}

function calculateBulkDiscounts(mysqli $conn, int $vendorId, array $items): array {
    $discounts = [];
    
    if (empty($items)) {
        return $discounts;
    }
    
    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 1);
        $subtotal = (float)($item['subtotal'] ?? 0);
        
        if ($productId <= 0) continue;
        
        $stmt = $conn->prepare('SELECT bd.* FROM bulk_discounts bd WHERE bd.vendor_id = ? AND (bd.product_id = ? OR bd.product_id IS NULL OR bd.category = (SELECT category FROM products WHERE product_id = ?)) AND bd.is_active = 1 AND bd.min_quantity <= ? ORDER BY bd.min_quantity DESC LIMIT 1');
        if (!$stmt) continue;
        
        $stmt->bind_param('iiii', $vendorId, $productId, $productId, $quantity);
        $stmt->execute();
        $discount = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($discount) {
            $discountAmount = 0.0;
            if ($discount['discount_type'] === 'percentage') {
                $discountAmount = ($subtotal * (float)$discount['discount_value']) / 100;
            } else {
                $discountAmount = (float)$discount['discount_value'] * $quantity;
            }
            
            $discounts[] = [
                'product_id' => $productId,
                'bulk_discount_id' => (int)$discount['bulk_discount_id'],
                'min_quantity' => (int)$discount['min_quantity'],
                'discount_type' => $discount['discount_type'],
                'discount_value' => (float)$discount['discount_value'],
                'discount_amount' => round(max(0, $discountAmount), 2)
            ];
        }
    }
    
    return $discounts;
}

function getActiveSeasonalSales(mysqli $conn, int $vendorId = 0, array $productIds = []): array {
    $sales = [];
    
    $sql = 'SELECT ss.* FROM seasonal_sales ss WHERE ss.is_active = 1 AND ss.starts_at <= NOW() AND ss.ends_at >= NOW()';
    if ($vendorId > 0) {
        $sql .= ' AND (ss.vendor_id = ? OR ss.vendor_id IS NULL)';
    }
    $sql .= ' ORDER BY ss.discount_value DESC';
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    if ($vendorId > 0) {
        $stmt->bind_param('i', $vendorId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    $stmt->close();
    
    return $sales;
}

function canApplySeasonalSale(mysqli $conn, int $saleId, int $productId): bool {
    $stmt = $conn->prepare('SELECT ssi.seasonal_sale_item_id FROM seasonal_sale_items ssi WHERE ssi.seasonal_sale_id = ? AND (ssi.product_id = ? OR ssi.applies_to_type = "vendor_all" OR (ssi.category = (SELECT category FROM products WHERE product_id = ?))) LIMIT 1');
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('iii', $saleId, $productId, $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $found = $result->fetch_assoc();
    $stmt->close();
    
    return $found !== null;
}
?>
