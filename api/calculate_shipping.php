<?php
require_once 'config.php';
require_once 'api_helpers.php';
require_once 'shipping_helpers.php';

function readShippingPayload(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_GET ?: [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ($_POST ?: []);
}

$payload = readShippingPayload();
$estimate = bizlinkEstimateShipping([
    'province' => $payload['province'] ?? $payload['shipping_province'] ?? '',
    'city' => $payload['city'] ?? $payload['shipping_city'] ?? '',
    'subtotal' => $payload['subtotal'] ?? $payload['amount'] ?? 0,
    'weight_kg' => $payload['weight_kg'] ?? $payload['weight'] ?? 0,
    'item_count' => $payload['item_count'] ?? $payload['quantity'] ?? 1,
    'shipping_method' => $payload['shipping_method'] ?? 'standard',
    'is_service' => !empty($payload['is_service'])
]);

apiSuccess($estimate, 'Shipping estimate calculated.', 'SHIPPING_ESTIMATE_CALCULATED');

$conn->close();
?>