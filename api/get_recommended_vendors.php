<?php
/**
 * ============================================
 * GET RECOMMENDED VENDORS FOR CUSTOMER
 * ============================================
 * 
 * Recommends vendors based on:
 * 1. Purchase history (categories customer has bought from)
 * 2. Vendor ratings (high-rated vendors with 4+ stars)
 * 3. Variety (vendors customer hasn't recently purchased from)
 * 
 * Returns: JSON array of recommended vendors with details
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

// Enable error logging
ini_set('display_errors', 0);

try {
  // ============================================
  // VERIFY CUSTOMER LOGIN
  // ============================================
  if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }

  $customerId = $_SESSION['user_id'];

  // Create connection
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
  if ($conn->connect_error) {
    throw new Exception('Database connection failed: ' . $conn->connect_error);
  }

  $conn->set_charset('utf8mb4');

  // ============================================
  // STEP 1: GET CUSTOMER'S PURCHASE HISTORY
  // ============================================
  $historyQuery = "
    SELECT DISTINCT v.business_category
    FROM orders o
    JOIN vendors v ON o.vendor_id = v.vendor_id
    WHERE o.customer_id = (SELECT customer_id FROM customers WHERE user_id = ?)
    AND o.order_status IN ('delivered', 'shipped', 'processing')
    LIMIT 5
  ";

  $stmt = $conn->prepare($historyQuery);
  if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error);
  }

  $stmt->bind_param('i', $customerId);
  $stmt->execute();
  $categoryResult = $stmt->get_result();

  $categories = [];
  while ($row = $categoryResult->fetch_assoc()) {
    if ($row['business_category']) {
      $categories[] = $row['business_category'];
    }
  }
  $stmt->close();

  // ============================================
  // STEP 2: GET VENDORS ALREADY PURCHASED FROM
  // ============================================
  $purchasedQuery = "
    SELECT DISTINCT o.vendor_id
    FROM orders o
    WHERE o.customer_id = (SELECT customer_id FROM customers WHERE user_id = ?)
  ";

  $stmt = $conn->prepare($purchasedQuery);
  if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error);
  }

  $stmt->bind_param('i', $customerId);
  $stmt->execute();
  $purchasedResult = $stmt->get_result();

  $purchasedVendorIds = [];
  while ($row = $purchasedResult->fetch_assoc()) {
    $purchasedVendorIds[] = $row['vendor_id'];
  }
  $stmt->close();

  // ============================================
  // STEP 3: RECOMMEND VENDORS
  // ============================================
  // Strategy:
  // 1. If customer has purchase history, recommend vendors in similar categories with high ratings
  // 2. If no history, recommend top-rated verified vendors across all categories
  // 3. Exclude vendors already purchased from (to show variety)

  $recommendedVendors = [];

  if (!empty($categories)) {
    // CASE 1: Customer has purchase history - recommend similar categories
    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    $purchasedPlaceholders = !empty($purchasedVendorIds) ? implode(',', array_fill(0, count($purchasedVendorIds), '?')) : '';

    $query = "
      SELECT 
        v.vendor_id,
        u.full_name,
        v.business_name,
        v.business_category,
        v.avg_rating,
        v.total_reviews,
        v.total_products,
        v.business_logo_url
      FROM vendors v
      JOIN users u ON v.user_id = u.user_id
      WHERE v.business_category IN ($placeholders)
      AND v.verification_status = 'verified'
      AND v.avg_rating >= 4.0
      AND v.total_products > 0
    ";

    // Exclude already purchased vendors
    if (!empty($purchasedVendorIds)) {
      $query .= " AND v.vendor_id NOT IN ($purchasedPlaceholders)";
    }

    $query .= " ORDER BY v.avg_rating DESC, v.total_reviews DESC LIMIT 8";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }

    $bindParams = array_merge($categories, $purchasedVendorIds);
    $types = str_repeat('s', count($categories)) . str_repeat('i', count($purchasedVendorIds));

    if (!empty($bindParams)) {
      $stmt->bind_param($types, ...$bindParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $recommendedVendors[] = [
        'vendor_id' => (int)$row['vendor_id'],
        'name' => $row['business_name'],
        'category' => $row['business_category'] ?? 'General',
        'rating' => (float)$row['avg_rating'],
        'reviews' => (int)$row['total_reviews'],
        'products' => (int)$row['total_products'],
        'logo' => $row['business_logo_url'] ?? 'https://via.placeholder.com/100?text=' . urlencode(substr($row['business_name'], 0, 2))
      ];
    }
    $stmt->close();
  }

  // If no results from similar categories, get top-rated vendors
  if (count($recommendedVendors) < 4) {
    $purchasedPlaceholders = !empty($purchasedVendorIds) ? implode(',', array_fill(0, count($purchasedVendorIds), '?')) : '';

    $query = "
      SELECT 
        v.vendor_id,
        u.full_name,
        v.business_name,
        v.business_category,
        v.avg_rating,
        v.total_reviews,
        v.total_products,
        v.business_logo_url
      FROM vendors v
      JOIN users u ON v.user_id = u.user_id
      WHERE v.verification_status = 'verified'
      AND v.avg_rating >= 3.8
      AND v.total_products > 0
    ";

    if (!empty($purchasedVendorIds)) {
      $query .= " AND v.vendor_id NOT IN ($purchasedPlaceholders)";
    }

    $query .= " ORDER BY v.avg_rating DESC, v.total_reviews DESC LIMIT 8";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }

    if (!empty($purchasedVendorIds)) {
      $types = str_repeat('i', count($purchasedVendorIds));
      $stmt->bind_param($types, ...$purchasedVendorIds);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $topRatedVendors = [];
    while ($row = $result->fetch_assoc()) {
      $topRatedVendors[] = [
        'vendor_id' => (int)$row['vendor_id'],
        'name' => $row['business_name'],
        'category' => $row['business_category'] ?? 'General',
        'rating' => (float)$row['avg_rating'],
        'reviews' => (int)$row['total_reviews'],
        'products' => (int)$row['total_products'],
        'logo' => $row['business_logo_url'] ?? 'https://via.placeholder.com/100?text=' . urlencode(substr($row['business_name'], 0, 2))
      ];
    }
    $stmt->close();

    // Merge and deduplicate
    foreach ($topRatedVendors as $vendor) {
      if (count($recommendedVendors) < 8) {
        $alreadyExists = false;
        foreach ($recommendedVendors as $existing) {
          if ($existing['vendor_id'] === $vendor['vendor_id']) {
            $alreadyExists = true;
            break;
          }
        }
        if (!$alreadyExists) {
          $recommendedVendors[] = $vendor;
        }
      }
    }
  }

  // Limit to 4 for dashboard display
  $recommendedVendors = array_slice($recommendedVendors, 0, 4);

  $conn->close();

  // ============================================
  // RETURN RESPONSE
  // ============================================
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'vendors' => $recommendedVendors,
    'count' => count($recommendedVendors)
  ]);

} catch (Exception $e) {
  error_log('Error in get_recommended_vendors.php: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'error' => 'Failed to fetch recommended vendors',
    'details' => $e->getMessage()
  ]);
}
?>
