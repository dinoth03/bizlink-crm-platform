<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

requireAuth(['vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'vendor_add_product_by_ip', 40, 900);
requireRateLimit($conn, 'vendor:' . $userId, 'vendor_add_product_by_user', 30, 900);

$payload = readJsonPayload();

$productName = sanitizeString((string)($payload['product_name'] ?? ''), 255);
$category = sanitizeString((string)($payload['category'] ?? ''), 100);
$description = sanitizeString((string)($payload['product_description'] ?? ''), 5000);
$price = isset($payload['price']) ? (float)$payload['price'] : 0;
$stock = isset($payload['quantity_in_stock']) ? (int)$payload['quantity_in_stock'] : 0;
$status = strtolower(trim((string)($payload['status'] ?? 'active')));
$skuInput = sanitizeString((string)($payload['sku'] ?? ''), 100);
$imageUrl = sanitizeString((string)($payload['primary_image_url'] ?? ''), 500);
$discountPercentage = isset($payload['discount_percentage']) ? (float)$payload['discount_percentage'] : 0;

$errors = [];
if ($productName === '') {
    $errors[] = ['field' => 'product_name', 'message' => 'Product name is required.'];
}
if ($category === '') {
    $errors[] = ['field' => 'category', 'message' => 'Category is required.'];
}
if ($price <= 0) {
    $errors[] = ['field' => 'price', 'message' => 'Price must be greater than 0.'];
}
if ($stock < 0) {
    $errors[] = ['field' => 'quantity_in_stock', 'message' => 'Stock cannot be negative.'];
}
if (!in_array($status, ['active', 'draft'], true)) {
    $errors[] = ['field' => 'status', 'message' => 'Status must be active or draft.'];
}
if ($discountPercentage < 0 || $discountPercentage > 100) {
    $errors[] = ['field' => 'discount_percentage', 'message' => 'Discount must be between 0 and 100.'];
}

if (!empty($errors)) {
    apiError('VALIDATION_ERROR', 'Invalid product details.', 422, $errors);
}

$vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
if (!$vendorStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendor query.', 500);
}
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendor = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

$vendorId = (int)($vendor['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found for this user.', 404);
}

function createSlug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string)$text, '-');
    return $text !== '' ? $text : 'product';
}

function createUniqueProductSlug(mysqli $conn, string $productName): string {
    $base = createSlug($productName);
    $candidate = $base;
    $counter = 1;

    while ($counter <= 1000) {
        $stmt = $conn->prepare('SELECT product_id FROM products WHERE product_slug = ? LIMIT 1');
        if (!$stmt) {
            apiError('DB_QUERY_ERROR', 'Failed to prepare slug check query.', 500);
        }
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            return $candidate;
        }

        $counter++;
        $candidate = $base . '-' . $counter;
    }

    return $base . '-' . time();
}

function createUniqueSku(mysqli $conn, int $vendorId, string $inputSku, string $productName): string {
    $baseSku = $inputSku !== '' ? strtoupper($inputSku) : strtoupper(substr(createSlug($productName), 0, 12));
    $baseSku = preg_replace('/[^A-Z0-9-]/', '', $baseSku);
    if ($baseSku === '') {
        $baseSku = 'PRD';
    }

    $candidate = $baseSku;
    $counter = 1;

    while ($counter <= 1000) {
        $stmt = $conn->prepare('SELECT product_id FROM products WHERE sku = ? LIMIT 1');
        if (!$stmt) {
            apiError('DB_QUERY_ERROR', 'Failed to prepare SKU check query.', 500);
        }
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            return $candidate;
        }

        $counter++;
        $candidate = $baseSku . '-' . $vendorId . '-' . $counter;
    }

    return $baseSku . '-' . $vendorId . '-' . time();
}

$productSlug = createUniqueProductSlug($conn, $productName);
$sku = createUniqueSku($conn, $vendorId, $skuInput, $productName);
$isActive = $status === 'active' ? 1 : 0;
$discountPrice = null;
if ($discountPercentage > 0) {
    $discountPrice = round($price * (1 - ($discountPercentage / 100)), 2);
}

$insertStmt = $conn->prepare(
    'INSERT INTO products (
        vendor_id,
        product_name,
        product_slug,
        product_description,
        category,
        price,
        discount_price,
        discount_percentage,
        currency,
        sku,
        quantity_in_stock,
        primary_image_url,
        is_active,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
);

if (!$insertStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare product insert query.', 500);
}

$currency = 'LKR';
$insertStmt->bind_param(
    'issssdddssisi',
    $vendorId,
    $productName,
    $productSlug,
    $description,
    $category,
    $price,
    $discountPrice,
    $discountPercentage,
    $currency,
    $sku,
    $stock,
    $imageUrl,
    $isActive
);

if (!$insertStmt->execute()) {
    $error = $insertStmt->error;
    $insertStmt->close();
    apiError('DB_WRITE_ERROR', 'Failed to add product: ' . $error, 500);
}

$productId = (int)$insertStmt->insert_id;
$insertStmt->close();

apiSuccess([
    'product_id' => $productId,
    'product_name' => $productName,
    'product_slug' => $productSlug,
    'sku' => $sku,
    'status' => $status,
    'is_active' => (bool)$isActive
], 'Product added successfully.', 'PRODUCT_ADDED', 201);

$conn->close();
