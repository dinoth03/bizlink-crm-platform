<?php
/**
 * Get Shopping Cart for Current Customer
 * GET /api/get_cart.php
 * 
 * Required: Customer role
 * Returns: All cart items with product details and totals
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'config.php';
require_once 'auth_middleware.php';

// Verify customer authentication
requireAuth(['customer']);

try {
    $customer_id = $_SESSION['user_id'];

    // Get cart items with product details
    $cart_query = $conn->query(
        "SELECT 
            c.cart_item_id,
            c.product_id,
            c.variant_id,
            c.quantity,
            c.price_at_addition,
            c.discount_price_at_addition,
            c.notes,
            c.added_at,
            c.updated_at,
            p.product_name,
            p.product_slug,
            p.primary_image_url,
            p.quantity_in_stock as product_stock,
            p.category,
            v.variant_id as has_variant,
            v.variant_name,
            v.quantity_in_stock as variant_stock,
            CASE 
                WHEN c.discount_price_at_addition IS NOT NULL 
                THEN (c.discount_price_at_addition * c.quantity)
                ELSE (c.price_at_addition * c.quantity)
            END as line_total
         FROM shopping_cart c
         JOIN products p ON c.product_id = p.product_id
         LEFT JOIN product_variants v ON c.variant_id = v.variant_id
         WHERE c.customer_id = $customer_id
         ORDER BY c.added_at DESC"
    );

    if ($cart_query === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching cart: ' . $conn->error
        ]);
        exit;
    }

    $cart_items = [];
    $subtotal = 0;
    $total_quantity = 0;

    while ($item = $cart_query->fetch_assoc()) {
        $line_total = floatval($item['line_total']);
        $subtotal += $line_total;
        $total_quantity += intval($item['quantity']);

        $cart_items[] = [
            'cart_item_id' => intval($item['cart_item_id']),
            'product_id' => intval($item['product_id']),
            'product_name' => $item['product_name'],
            'product_slug' => $item['product_slug'],
            'primary_image_url' => $item['primary_image_url'],
            'category' => $item['category'],
            'variant_id' => $item['variant_id'] ? intval($item['variant_id']) : null,
            'variant_name' => $item['variant_name'],
            'quantity' => intval($item['quantity']),
            'price' => floatval($item['price_at_addition']),
            'discount_price' => $item['discount_price_at_addition'] ? floatval($item['discount_price_at_addition']) : null,
            'effective_price' => $item['discount_price_at_addition'] ? floatval($item['discount_price_at_addition']) : floatval($item['price_at_addition']),
            'line_total' => $line_total,
            'product_stock' => intval($item['product_stock']),
            'variant_stock' => $item['variant_stock'] ? intval($item['variant_stock']) : null,
            'notes' => $item['notes'],
            'added_at' => $item['added_at'],
            'updated_at' => $item['updated_at']
        ];
    }

    // Calculate cart totals
    $tax_rate = 0.08; // 8% VAT
    $tax_amount = $subtotal * $tax_rate;
    $total_amount = $subtotal + $tax_amount;

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Cart retrieved successfully',
        'cart_items' => $cart_items,
        'cart_summary' => [
            'total_items_in_cart' => count($cart_items),
            'total_quantity' => $total_quantity,
            'subtotal' => round($subtotal, 2),
            'tax_rate' => $tax_rate * 100,
            'tax_amount' => round($tax_amount, 2),
            'shipping_cost' => 0, // Will be calculated at checkout
            'total_amount' => round($total_amount, 2),
            'currency' => 'LKR'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
