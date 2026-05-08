<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

requireAuth(['customer']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'order_create_by_ip', 40, 900);
requireRateLimit($conn, 'user:' . $userId, 'order_create_by_user', 20, 900);

function generateOrderNumber(): string {
    return 'BL-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function generateUniqueOrderNumber(mysqli $conn, int $attempts = 5): string {
    for ($i = 0; $i < $attempts; $i++) {
        $candidate = generateOrderNumber();
        $stmt = $conn->prepare('SELECT order_id FROM orders WHERE order_number = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $candidate;
        }
    }
    return '';
}

function buildAddressString(array $userRow, string $fallback = 'To be confirmed at checkout'): string {
    $parts = [];
    foreach (['address', 'city', 'province', 'country', 'postal_code'] as $field) {
        $value = trim((string)($userRow[$field] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }
    $parts = array_values(array_unique($parts));
    return !empty($parts) ? implode(', ', $parts) : $fallback;
}

$payload = readJsonPayload();
$paymentMethod = strtolower(sanitizeString((string)($payload['payment_method'] ?? 'cash_on_delivery'), 50));
$shippingMethod = sanitizeString((string)($payload['shipping_method'] ?? 'Standard Delivery'), 100);
$shippingAddressInput = sanitizeString((string)($payload['shipping_address'] ?? ''), 500);
$billingAddressInput = sanitizeString((string)($payload['billing_address'] ?? ''), 500);
$orderNotes = sanitizeString((string)($payload['order_notes'] ?? $payload['notes'] ?? ''), 1000);
$customerNotes = sanitizeString((string)($payload['customer_notes'] ?? ''), 1000);
$currency = 'LKR';
$taxRate = 0.08;
$allowedPaymentMethods = ['credit_card', 'debit_card', 'bank_transfer', 'digital_wallet', 'cash_on_delivery'];

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    apiError('VALIDATION_ERROR', 'Invalid payment method.', 422, [
        ['field' => 'payment_method', 'message' => 'Supported methods: credit_card, debit_card, bank_transfer, digital_wallet, cash_on_delivery.']
    ]);
}

$customerStmt = $conn->prepare(
    'SELECT c.customer_id, c.total_orders, c.total_spent, u.full_name, u.email, u.phone, u.address, u.city, u.province, u.country, u.postal_code
     FROM customers c
     INNER JOIN users u ON u.user_id = c.user_id
     WHERE c.user_id = ?
     LIMIT 1'
);
if (!$customerStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare customer lookup.', 500);
}
$customerStmt->bind_param('i', $userId);
$customerStmt->execute();
$customer = $customerStmt->get_result()->fetch_assoc();
$customerStmt->close();

$customerId = (int)($customer['customer_id'] ?? 0);
if ($customerId <= 0) {
    apiError('CUSTOMER_NOT_FOUND', 'Customer profile not found for this user.', 404);
}

$userAddress = buildAddressString($customer);
$shippingAddress = $shippingAddressInput !== '' ? $shippingAddressInput : $userAddress;
$billingAddress = $billingAddressInput !== '' ? $billingAddressInput : $shippingAddress;

$cartItems = [];
$payloadItems = isset($payload['cart_items']) && is_array($payload['cart_items']) ? $payload['cart_items'] : [];
$usePayloadCart = !empty($payloadItems);

if ($usePayloadCart) {
    foreach ($payloadItems as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $productId = (int)($entry['product_id'] ?? $entry['id'] ?? 0);
        $quantity = (int)($entry['quantity'] ?? $entry['qty'] ?? 0);
        $variantId = isset($entry['variant_id']) && $entry['variant_id'] !== '' ? (int)$entry['variant_id'] : null;
        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }
        $cartItems[] = [
            'source' => 'payload',
            'product_id' => $productId,
            'quantity' => $quantity,
            'variant_id' => $variantId,
            'notes' => sanitizeString((string)($entry['notes'] ?? ''), 500)
        ];
    }
} else {
    $cartStmt = $conn->prepare(
        'SELECT cart_item_id, product_id, variant_id, quantity, price_at_addition, discount_price_at_addition, notes
         FROM shopping_cart
         WHERE customer_id = ?
         ORDER BY added_at ASC, cart_item_id ASC'
    );
    if (!$cartStmt) {
        apiError('DB_QUERY_ERROR', 'Failed to prepare cart lookup.', 500);
    }
    $cartStmt->bind_param('i', $customerId);
    $cartStmt->execute();
    $result = $cartStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cartItems[] = [
            'source' => 'cart',
            'cart_item_id' => (int)$row['cart_item_id'],
            'product_id' => (int)$row['product_id'],
            'quantity' => (int)$row['quantity'],
            'variant_id' => $row['variant_id'] !== null ? (int)$row['variant_id'] : null,
            'price_at_addition' => $row['price_at_addition'] !== null ? (float)$row['price_at_addition'] : null,
            'discount_price_at_addition' => $row['discount_price_at_addition'] !== null ? (float)$row['discount_price_at_addition'] : null,
            'notes' => sanitizeString((string)($row['notes'] ?? ''), 500)
        ];
    }
    $cartStmt->close();
}

if (empty($cartItems)) {
    apiError('CART_EMPTY', 'Your cart is empty.', 404);
}

$productStmt = $conn->prepare(
    'SELECT p.product_id, p.product_name, p.product_type, p.price, p.discount_price, p.quantity_in_stock, p.is_active, p.vendor_id, p.total_sold, v.business_name, v.commission_rate
     FROM products p
     INNER JOIN vendors v ON v.vendor_id = p.vendor_id
     WHERE p.product_id = ?
     LIMIT 1'
);
if (!$productStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare product lookup.', 500);
}

$variantStmt = $conn->prepare(
    'SELECT variant_id, variant_price, quantity_in_stock, is_available
     FROM product_variants
     WHERE variant_id = ? AND product_id = ?
     LIMIT 1'
);
if (!$variantStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare variant lookup.', 500);
}

$resolvedItems = [];
foreach ($cartItems as $item) {
    $productId = (int)$item['product_id'];
    $quantity = (int)$item['quantity'];
    $variantId = isset($item['variant_id']) && $item['variant_id'] !== null ? (int)$item['variant_id'] : null;

    $productStmt->bind_param('i', $productId);
    $productStmt->execute();
    $product = $productStmt->get_result()->fetch_assoc();

    if (!$product) {
        apiError('PRODUCT_NOT_FOUND', 'One of the selected products was not found.', 404);
    }
    if ((int)$product['is_active'] !== 1) {
        apiError('PRODUCT_UNAVAILABLE', 'One of the selected products is inactive.', 409);
    }

    $productType = strtolower((string)($product['product_type'] ?? 'physical'));
    $basePrice = (float)($product['discount_price'] ?? 0);
    if ($basePrice <= 0) {
        $basePrice = (float)$product['price'];
    }

    $productStock = (int)$product['quantity_in_stock'];
    $variantStock = null;

    if ($variantId !== null) {
        $variantStmt->bind_param('ii', $variantId, $productId);
        $variantStmt->execute();
        $variant = $variantStmt->get_result()->fetch_assoc();

        if (!$variant || (int)$variant['is_available'] !== 1) {
            apiError('VARIANT_NOT_FOUND', 'One of the selected variants was not found or is unavailable.', 404);
        }

        $variantStock = (int)$variant['quantity_in_stock'];
        if ($variantStock > 0 && $quantity > $variantStock && $productType !== 'service') {
            apiError('INSUFFICIENT_STOCK', 'Requested quantity exceeds variant stock.', 409);
        }

        $variantPrice = (float)($variant['variant_price'] ?? 0);
        if ($variantPrice > 0) {
            $basePrice = $variantPrice;
        }
    }

    if ($productType !== 'service' && $variantId === null && $productStock > 0 && $quantity > $productStock) {
        apiError('INSUFFICIENT_STOCK', 'Requested quantity exceeds product stock.', 409);
    }

    $unitPrice = $basePrice;
    if (($item['source'] ?? '') === 'cart') {
        $snapshotDiscount = $item['discount_price_at_addition'] ?? null;
        $snapshotPrice = $item['price_at_addition'] ?? null;
        if ($snapshotDiscount !== null && $snapshotDiscount > 0) {
            $unitPrice = (float)$snapshotDiscount;
        } elseif ($snapshotPrice !== null && $snapshotPrice > 0) {
            $unitPrice = (float)$snapshotPrice;
        }
    }

    $lineSubtotal = round($unitPrice * $quantity, 2);
    $snapshotPrice = (float)($item['price_at_addition'] ?? $unitPrice);
    $discountUnit = max(0.0, $snapshotPrice - $unitPrice);
    $lineDiscount = round($discountUnit * $quantity, 2);
    $lineTax = round($lineSubtotal * $taxRate, 2);
    $lineTotal = round($lineSubtotal + $lineTax, 2);

    $resolvedItems[] = [
        'source' => (string)($item['source'] ?? 'payload'),
        'cart_item_id' => (int)($item['cart_item_id'] ?? 0),
        'product_id' => $productId,
        'product_name' => (string)$product['product_name'],
        'product_type' => $productType,
        'vendor_id' => (int)$product['vendor_id'],
        'vendor_name' => (string)$product['business_name'],
        'commission_rate' => (float)$product['commission_rate'],
        'quantity' => $quantity,
        'variant_id' => $variantId,
        'product_stock' => $productStock,
        'variant_stock' => $variantStock,
        'unit_price' => $unitPrice,
        'line_subtotal' => $lineSubtotal,
        'line_discount' => $lineDiscount,
        'line_tax' => $lineTax,
        'line_total' => $lineTotal,
        'notes' => (string)($item['notes'] ?? '')
    ];
}

$vendors = [];
foreach ($resolvedItems as $item) {
    $vendorId = (int)$item['vendor_id'];
    if (!isset($vendors[$vendorId])) {
        $vendors[$vendorId] = [
            'vendor_id' => $vendorId,
            'vendor_name' => (string)$item['vendor_name'],
            'commission_rate' => (float)$item['commission_rate'],
            'items' => []
        ];
    }
    $vendors[$vendorId]['items'][] = $item;
}

if (empty($vendors)) {
    apiError('CART_EMPTY', 'No valid cart items were found.', 404);
}

$orderNumberCheck = generateUniqueOrderNumber($conn, 5);
if ($orderNumberCheck === '') {
    apiError('ORDER_NUMBER_GENERATION_FAILED', 'Unable to generate order numbers.', 500);
}

$conn->begin_transaction();

try {
    $insertOrderStmt = $conn->prepare(
        'INSERT INTO orders (
            order_number,
            customer_id,
            vendor_id,
            order_status,
            payment_status,
            order_date,
            shipping_address,
            billing_address,
            subtotal,
            discount_amount,
            tax_amount,
            shipping_cost,
            commission_amount,
            total_amount,
            currency,
            shipping_method,
            notes,
            customer_notes,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $insertItemWithVariantStmt = $conn->prepare(
        'INSERT INTO order_items (
            order_id,
            product_id,
            variant_id,
            product_name,
            price_at_purchase,
            quantity,
            discount_applied,
            subtotal,
            tax_amount,
            total_amount,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $insertItemNoVariantStmt = $conn->prepare(
        'INSERT INTO order_items (
            order_id,
            product_id,
            variant_id,
            product_name,
            price_at_purchase,
            quantity,
            discount_applied,
            subtotal,
            tax_amount,
            total_amount,
            created_at
        ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $updateProductStmt = $conn->prepare('UPDATE products SET quantity_in_stock = quantity_in_stock - ?, total_sold = total_sold + ? WHERE product_id = ? AND quantity_in_stock >= ?');
    $updateVariantStmt = $conn->prepare('UPDATE product_variants SET quantity_in_stock = quantity_in_stock - ? WHERE variant_id = ? AND quantity_in_stock >= ?');
    $updateVendorStmt = $conn->prepare('UPDATE vendors SET total_orders = total_orders + 1 WHERE vendor_id = ?');
    $updateCustomerStmt = $conn->prepare('UPDATE customers SET total_orders = total_orders + ?, total_spent = total_spent + ? WHERE customer_id = ?');

    if (!$insertOrderStmt || !$insertItemWithVariantStmt || !$insertItemNoVariantStmt || !$updateProductStmt || !$updateVariantStmt || !$updateVendorStmt || !$updateCustomerStmt) {
        throw new Exception('Failed to prepare one or more checkout statements.');
    }

    $createdOrders = [];
    $orderCount = 0;
    $spentTotal = 0.0;
    $cartItemIdsToClear = [];

    foreach ($vendors as $vendorGroup) {
        $vendorItems = $vendorGroup['items'];
        $vendorId = (int)$vendorGroup['vendor_id'];
        $vendorSubtotal = 0.0;
        $vendorDiscount = 0.0;
        $vendorTax = 0.0;
        $vendorCommission = 0.0;

        foreach ($vendorItems as $vendorItem) {
            if ($vendorItem['product_type'] !== 'service') {
                $availableStock = $vendorItem['variant_id'] !== null
                    ? (int)($vendorItem['variant_stock'] ?? 0)
                    : (int)$vendorItem['product_stock'];
                if ($availableStock > 0 && $vendorItem['quantity'] > $availableStock) {
                    throw new Exception('Insufficient stock for ' . $vendorItem['product_name']);
                }
            }

            $vendorSubtotal += (float)$vendorItem['line_subtotal'];
            $vendorDiscount += (float)$vendorItem['line_discount'];
            $vendorTax += (float)$vendorItem['line_tax'];
        }

        $vendorCommission = round($vendorSubtotal * ((float)$vendorGroup['commission_rate'] / 100), 2);
        $shippingCost = 0.0;
        $grandTotal = round($vendorSubtotal + $vendorTax + $shippingCost, 2);
        $orderNumber = generateUniqueOrderNumber($conn, 5);
        if ($orderNumber === '') {
            throw new Exception('Unable to generate a unique order number.');
        }

        $orderStatus = 'pending';
        $paymentStatus = 'unpaid';
        $insertOrderStmt->bind_param(
            'siissssddddddssss',
            $orderNumber,
            $customerId,
            $vendorId,
            $orderStatus,
            $paymentStatus,
            $shippingAddress,
            $billingAddress,
            $vendorSubtotal,
            $vendorDiscount,
            $vendorTax,
            $shippingCost,
            $vendorCommission,
            $grandTotal,
            $currency,
            $shippingMethod,
            $orderNotes,
            $customerNotes
        );
        if (!$insertOrderStmt->execute()) {
            throw new Exception('Failed to create order for vendor ' . $vendorGroup['vendor_name']);
        }
        $orderId = (int)$insertOrderStmt->insert_id;

        foreach ($vendorItems as $vendorItem) {
            $productId = (int)$vendorItem['product_id'];
            $variantId = $vendorItem['variant_id'] !== null ? (int)$vendorItem['variant_id'] : null;
            $quantity = (int)$vendorItem['quantity'];
            $productName = (string)$vendorItem['product_name'];
            $unitPrice = round((float)$vendorItem['unit_price'], 2);
            $discountApplied = round((float)$vendorItem['line_discount'], 2);
            $lineSubtotal = round((float)$vendorItem['line_subtotal'], 2);
            $lineTax = round((float)$vendorItem['line_tax'], 2);
            $lineTotal = round((float)$vendorItem['line_total'], 2);

            if ($variantId !== null) {
                $insertItemWithVariantStmt->bind_param(
                    'iiisdidddd',
                    $orderId,
                    $productId,
                    $variantId,
                    $productName,
                    $unitPrice,
                    $quantity,
                    $discountApplied,
                    $lineSubtotal,
                    $lineTax,
                    $lineTotal
                );
                if (!$insertItemWithVariantStmt->execute()) {
                    throw new Exception('Failed to create order item for ' . $productName);
                }
            } else {
                $insertItemNoVariantStmt->bind_param(
                    'iisdidddd',
                    $orderId,
                    $productId,
                    $productName,
                    $unitPrice,
                    $quantity,
                    $discountApplied,
                    $lineSubtotal,
                    $lineTax,
                    $lineTotal
                );
                if (!$insertItemNoVariantStmt->execute()) {
                    throw new Exception('Failed to create order item for ' . $productName);
                }
            }

            if ($vendorItem['product_type'] !== 'service') {
                $updateProductStmt->bind_param('iiii', $quantity, $quantity, $productId, $quantity);
                if (!$updateProductStmt->execute() || $updateProductStmt->affected_rows < 1) {
                    throw new Exception('Insufficient stock while reserving ' . $productName);
                }

                if ($variantId !== null) {
                    $updateVariantStmt->bind_param('iii', $quantity, $variantId, $quantity);
                    if (!$updateVariantStmt->execute() || $updateVariantStmt->affected_rows < 1) {
                        throw new Exception('Insufficient variant stock while reserving ' . $productName);
                    }
                }
            }

            if (($vendorItem['source'] ?? '') === 'cart' && (int)($vendorItem['cart_item_id'] ?? 0) > 0) {
                $cartItemIdsToClear[] = (int)$vendorItem['cart_item_id'];
            }
        }

        $updateVendorStmt->bind_param('i', $vendorId);
        $updateVendorStmt->execute();

        $createdOrders[] = [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'vendor_id' => $vendorId,
            'vendor_name' => (string)$vendorGroup['vendor_name'],
            'subtotal' => round($vendorSubtotal, 2),
            'discount_amount' => round($vendorDiscount, 2),
            'tax_amount' => round($vendorTax, 2),
            'commission_amount' => round($vendorCommission, 2),
            'total_amount' => round($grandTotal, 2),
            'items_count' => count($vendorItems)
        ];

        $orderCount++;
        $spentTotal += $grandTotal;
    }

    $updateCustomerStmt->bind_param('idi', $orderCount, $spentTotal, $customerId);
    if (!$updateCustomerStmt->execute()) {
        throw new Exception('Failed to update customer order statistics.');
    }

    if (!$usePayloadCart && !empty($cartItemIdsToClear)) {
        $cartItemIdsToClear = array_values(array_unique($cartItemIdsToClear));
        $placeholders = implode(',', array_fill(0, count($cartItemIdsToClear), '?'));
        $deleteStmt = $conn->prepare('DELETE FROM shopping_cart WHERE customer_id = ? AND cart_item_id IN (' . $placeholders . ')');
        if ($deleteStmt) {
            $deleteTypes = 'i' . str_repeat('i', count($cartItemIdsToClear));
            $deleteParams = [$customerId];
            foreach ($cartItemIdsToClear as $cartItemId) {
                $deleteParams[] = $cartItemId;
            }
            $bindArgs = [$deleteTypes];
            foreach ($deleteParams as $index => $value) {
                $bindArgs[] = &$deleteParams[$index];
            }
            call_user_func_array([$deleteStmt, 'bind_param'], $bindArgs);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
    }

    $conn->commit();

    apiSuccess([
        'orders_created' => $createdOrders,
        'orders_count' => count($createdOrders),
        'shipping_address' => $shippingAddress,
        'billing_address' => $billingAddress,
        'payment_method' => $paymentMethod,
        'shipping_method' => $shippingMethod,
        'cart_source' => $usePayloadCart ? 'payload' : 'shopping_cart'
    ], 'Checkout completed successfully.', 'ORDER_CREATED', 201);
} catch (Throwable $exception) {
    $conn->rollback();
    apiError('ORDER_CREATE_FAILED', $exception->getMessage(), 500);
}

$conn->close();
?>
