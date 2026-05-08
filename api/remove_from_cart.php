<?php
/**
 * Remove Item from Shopping Cart
 * DELETE /api/remove_from_cart.php
 * 
 * Required: Customer role
 * Request body: { cart_item_id }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST');

require_once 'config.php';
require_once 'auth_middleware.php';

// Verify customer authentication
requireAuth(['customer']);

try {
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($data['cart_item_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required field: cart_item_id'
        ]);
        exit;
    }

    $cart_item_id = intval($data['cart_item_id']);
    $customer_id = $_SESSION['user_id'];

    // Verify cart item exists and belongs to current customer
    $cart_item_query = $conn->query(
        "SELECT cart_item_id, product_id, quantity FROM shopping_cart 
         WHERE cart_item_id = $cart_item_id AND customer_id = $customer_id"
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

    // Delete cart item
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

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Item removed from cart successfully',
        'removed_item_id' => $cart_item_id,
        'removed_product_id' => intval($cart_item['product_id']),
        'quantity_removed' => intval($cart_item['quantity']),
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
