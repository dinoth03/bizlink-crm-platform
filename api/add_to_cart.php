<?php
/**
 * Add Item to Shopping Cart
 * POST /api/add_to_cart.php
 * 
 * Required: Customer role
 * Request body: { product_id, quantity, variant_id (optional) }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once 'config.php';
require_once 'auth_middleware.php';

// Verify customer authentication
requireAuth(['customer']);

try {
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($data['product_id']) || !isset($data['quantity'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: product_id, quantity'
        ]);
        exit;
    }

    $product_id = intval($data['product_id']);
    $quantity = intval($data['quantity']);
    $variant_id = isset($data['variant_id']) ? intval($data['variant_id']) : null;
    $notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';

    // Validate quantity
    if ($quantity <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Quantity must be greater than 0'
        ]);
        exit;
    }

    // Get current user (customer)
    $customer_id = $_SESSION['user_id'];

    // Verify product exists and get price
    $product_query = $conn->query(
        "SELECT product_id, price, discount_price, quantity_in_stock 
         FROM products 
         WHERE product_id = $product_id AND is_active = 1"
    );

    if ($product_query->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Product not found or is inactive'
        ]);
        exit;
    }

    $product = $product_query->fetch_assoc();

    // Check stock availability
    if ($product['quantity_in_stock'] < $quantity) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Only {$product['quantity_in_stock']} items available in stock",
            'available_quantity' => $product['quantity_in_stock']
        ]);
        exit;
    }

    // If variant specified, verify it exists and has sufficient stock
    if ($variant_id !== null) {
        $variant_query = $conn->query(
            "SELECT variant_id, quantity_in_stock, variant_price 
             FROM product_variants 
             WHERE variant_id = $variant_id AND product_id = $product_id"
        );

        if ($variant_query->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Variant not found'
            ]);
            exit;
        }

        $variant = $variant_query->fetch_assoc();
        if ($variant['quantity_in_stock'] < $quantity) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Only {$variant['quantity_in_stock']} of this variant available",
                'available_quantity' => $variant['quantity_in_stock']
            ]);
            exit;
        }

        // Use variant price if available
        $price = $variant['variant_price'] ?? $product['price'];
        $discount_price = null;
    } else {
        // Use product price
        $price = $product['price'];
        $discount_price = $product['discount_price'] ?? null;
    }

    // Check if item already in cart
    $variant_condition = $variant_id !== null ? "AND variant_id = $variant_id" : "AND variant_id IS NULL";
    $existing_query = $conn->query(
        "SELECT cart_item_id, quantity FROM shopping_cart 
         WHERE customer_id = $customer_id AND product_id = $product_id $variant_condition"
    );

    if ($existing_query->num_rows > 0) {
        // Update existing cart item
        $existing_item = $existing_query->fetch_assoc();
        $new_quantity = $existing_item['quantity'] + $quantity;

        // Verify total quantity doesn't exceed stock
        if ($new_quantity > $product['quantity_in_stock']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Total quantity exceeds available stock. Max available: {$product['quantity_in_stock']}",
                'current_in_cart' => $existing_item['quantity'],
                'available_quantity' => $product['quantity_in_stock']
            ]);
            exit;
        }

        $update_query = "UPDATE shopping_cart 
                        SET quantity = $new_quantity, 
                            updated_at = NOW()
                        WHERE cart_item_id = {$existing_item['cart_item_id']}";

        if (!$conn->query($update_query)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error updating cart item: ' . $conn->error
            ]);
            exit;
        }

        $cart_item_id = $existing_item['cart_item_id'];
    } else {
        // Insert new cart item
        $variant_id_value = $variant_id !== null ? $variant_id : 'NULL';
        $discount_price_value = $discount_price !== null ? $discount_price : 'NULL';

        $insert_query = "INSERT INTO shopping_cart 
                        (customer_id, product_id, variant_id, quantity, price_at_addition, discount_price_at_addition, notes) 
                        VALUES ($customer_id, $product_id, $variant_id_value, $quantity, $price, $discount_price_value, '$notes')";

        if (!$conn->query($insert_query)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error adding item to cart: ' . $conn->error
            ]);
            exit;
        }

        $cart_item_id = $conn->insert_id;
    }

    // Get cart totals
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
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart successfully',
        'cart_item_id' => $cart_item_id,
        'quantity_added' => $quantity,
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
