<?php

/**
 * Helper to check and notify users about price drops on items in their wishlists.
 * 
 * @param mysqli $conn
 * @param int $productId
 * @param float $oldPrice
 * @param float $newPrice
 */
function checkPriceDropAlerts($conn, $productId, $oldPrice, $newPrice) {
    if ($newPrice >= $oldPrice) {
        return; // No price drop
    }

    // Find all customers who have this product in their wishlist
    $stmt = $conn->prepare("
        SELECT w.customer_id, c.user_id, p.product_name 
        FROM wishlists w
        JOIN customers c ON w.customer_id = c.customer_id
        JOIN products p ON w.product_id = p.product_id
        WHERE w.product_id = ?
    ");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $userId = (int)$row['user_id'];
        $productName = $row['product_name'];
        
        $title = "Price Drop Alert! 📉";
        $message = "A product in your wishlist, '{$productName}', just dropped in price from LKR " . number_format($oldPrice, 2) . " to LKR " . number_format($newPrice, 2) . ". Grab it now!";
        $actionUrl = "../pages/marketplace.html?id={$productId}";

        $notifications[] = [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl
        ];
    }
    $stmt->close();

    if (empty($notifications)) {
        return;
    }

    // Insert notifications
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, notification_type, title, message, action_url, priority) VALUES (?, 'promotion', ?, ?, ?, 'high')");
    foreach ($notifications as $n) {
        $notifStmt->bind_param("isss", $n['user_id'], $n['title'], $n['message'], $n['action_url']);
        $notifStmt->execute();
    }
    $notifStmt->close();
}

/**
 * Helper to get wishlist items for a customer
 */
function getCustomerWishlist($conn, $customerId) {
    $stmt = $conn->prepare("
        SELECT w.wishlist_item_id, p.product_id, p.product_name, p.price, p.discount_price, p.primary_image_url, v.business_name as vendor_name, p.category
        FROM wishlists w
        JOIN products p ON w.product_id = p.product_id
        JOIN vendors v ON p.vendor_id = v.vendor_id
        WHERE w.customer_id = ?
        ORDER BY w.added_at DESC
    ");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}
