<?php
/**
 * Update Cart Item Quantity
 * PUT /api/update_cart_quantity.php
 * 
 * Required: Customer role
 * Request body: { cart_item_id, quantity }
 * Quantity: 0 removes item, >0 updates quantity
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST');

require_once 'config.php';
require_once 'auth_middleware.php';

// Verify customer authentication
requireAuth(['customer']);

try {
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($data['cart_item_id']) || !isset($data['quantity'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: cart_item_id, quantity'
        ]);
        exit;
    }

    $cart_item_id = intval($data['cart_item_id']);
    $new_quantity = intval($data['quantity']);
    $customer_id = $_SESSION['user_id'];

    // Validate quantity
    if ($new_quantity < 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Quantity cannot be negative (use 0 to remove item)'
        ]);
        exit;
    }

    // Verify cart item exists and belongs to current customer
    $cart_item_query = $conn->query(
        "SELECT c.cart_item_id, c.product_id, c.quantity FROM shopping_cart c
         WHERE c.cart_item_id = $cart_item_id AND c.customer_id = $customer_id"
    );

    if ($cart_item_query->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Cart item not found'
        ]);
        exit;
    }

    $cart_item = $cart_item_query->fetch_assoc();
    $product_id = intval($cart_item['product_id']);
    $old_quantity = intval($cart_item['quantity']);

    // If quantity is 0, delete the item
    if ($new_quantity === 0) {
        $delete_query = "DELETE FROM shopping_cart WHERE cart_item_id = $cart_item_id";

        if (!$conn->query($delete_query)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error removing item from cart: ' . $conn->error
            ]);
            exit;
        }

        // Get updated cart totals
        $cart_totals = $conn->query(
            "SELECT 
                COUNT(*) as total_items, 
                SUM(quantity) as total_quantity,
                SUM(
                    CASE 
                        WHEN discount_price_at_addition IS NOT NULL 
                        THEN (discount_price_at_addition * quantity)
                        ELSE (price_at_addition * quantity)
                    END
                ) as total_price
             FROM shopping_cart 
             WHERE customer_id = $customer_id"
        );

        $totals = $cart_totals->fetch_assoc();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart (quantity set to 0)',
            'action' => 'item_deleted',
            'cart_item_id' => $cart_item_id,
            'quantity_removed' => $old_quantity,
            'cart_summary' => [
                'total_items' => intval($totals['total_items']),
                'total_quantity' => intval($totals['total_quantity']),
                'total_price' => floatval($totals['total_price']),
                'currency' => 'LKR'
            ]
        ]);
        exit;
    }

    // Get product stock information
    $product_query = $conn->query(
        "SELECT quantity_in_stock FROM products WHERE product_id = $product_id"
    );

    if ($product_query->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
        exit;
    }

    $product = $product_query->fetch_assoc();
    $available_stock = intval($product['quantity_in_stock']);

    // Check stock availability
    if ($new_quantity > $available_stock) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Quantity exceeds available stock. Max available: $available_stock",
            'available_quantity' => $available_stock,
            'requested_quantity' => $new_quantity
        ]);
        exit;
    }

    // Update cart item quantity
    $update_query = "UPDATE shopping_cart 
                    SET quantity = $new_quantity, updated_at = NOW()
                    WHERE cart_item_id = $cart_item_id";

    if (!$conn->query($update_query)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error updating cart item: ' . $conn->error
        ]);
        exit;
    }

    // Get updated cart item details
    $updated_item_query = $conn->query(
        "SELECT 
            c.cart_item_id,
            c.quantity,
            c.price_at_addition,
            c.discount_price_at_addition,
            CASE 
                WHEN c.discount_price_at_addition IS NOT NULL 
                THEN (c.discount_price_at_addition * c.quantity)
                ELSE (c.price_at_addition * c.quantity)
            END as line_total
         FROM shopping_cart c
         WHERE c.cart_item_id = $cart_item_id"
    );

    $updated_item = $updated_item_query->fetch_assoc();

    // Get updated cart totals
    $cart_totals = $conn->query(
        "SELECT 
            COUNT(*) as total_items, 
            SUM(quantity) as total_quantity,
            SUM(
                CASE 
                    WHEN discount_price_at_addition IS NOT NULL 
                    THEN (discount_price_at_addition * quantity)
                    ELSE (price_at_addition * quantity)
                END
            ) as total_price
         FROM shopping_cart 
         WHERE customer_id = $customer_id"
    );

    $totals = $cart_totals->fetch_assoc();

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Cart item quantity updated successfully',
        'cart_item_id' => $cart_item_id,
        'old_quantity' => $old_quantity,
        'new_quantity' => $new_quantity,
        'quantity_changed' => $new_quantity - $old_quantity,
        'item_details' => [
            'price' => floatval($updated_item['price_at_addition']),
            'discount_price' => $updated_item['discount_price_at_addition'] ? floatval($updated_item['discount_price_at_addition']) : null,
            'effective_price' => $updated_item['discount_price_at_addition'] ? floatval($updated_item['discount_price_at_addition']) : floatval($updated_item['price_at_addition']),
            'line_total' => floatval($updated_item['line_total'])
        ],
        'cart_summary' => [
            'total_items' => intval($totals['total_items']),
            'total_quantity' => intval($totals['total_quantity']),
            'total_price' => floatval($totals['total_price']),
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
