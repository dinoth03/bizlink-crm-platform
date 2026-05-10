<?php
require_once __DIR__ . '/api_helpers.php';

function bizlinkShippingZones(): array {
    return [
        [
            'zone_code' => 'western',
            'zone_name' => 'Western Province',
            'provinces' => ['western', 'west', 'colombo', 'gampaha', 'kalutara'],
            'base_cost' => 250.0,
            'per_kg' => 35.0,
            'per_item' => 10.0,
            'delivery_days_min' => 1,
            'delivery_days_max' => 2,
            'same_day' => true
        ],
        [
            'zone_code' => 'central',
            'zone_name' => 'Central Province',
            'provinces' => ['central', 'kandy', 'matale', 'nuwara eliya'],
            'base_cost' => 320.0,
            'per_kg' => 45.0,
            'per_item' => 12.0,
            'delivery_days_min' => 2,
            'delivery_days_max' => 3,
            'same_day' => false
        ],
        [
            'zone_code' => 'southern',
            'zone_name' => 'Southern Province',
            'provinces' => ['southern', 'galle', 'matara', 'hambantota'],
            'base_cost' => 340.0,
            'per_kg' => 45.0,
            'per_item' => 12.0,
            'delivery_days_min' => 2,
            'delivery_days_max' => 3,
            'same_day' => false
        ],
        [
            'zone_code' => 'northern',
            'zone_name' => 'Northern Province',
            'provinces' => ['northern', 'jaffna', 'kilinochchi', 'mannar', 'mullaitivu', 'vavuniya'],
            'base_cost' => 420.0,
            'per_kg' => 55.0,
            'per_item' => 15.0,
            'delivery_days_min' => 3,
            'delivery_days_max' => 5,
            'same_day' => false
        ],
        [
            'zone_code' => 'eastern',
            'zone_name' => 'Eastern Province',
            'provinces' => ['eastern', 'trincomalee', 'batticaloa', 'ampara'],
            'base_cost' => 420.0,
            'per_kg' => 55.0,
            'per_item' => 15.0,
            'delivery_days_min' => 3,
            'delivery_days_max' => 5,
            'same_day' => false
        ],
        [
            'zone_code' => 'north_central',
            'zone_name' => 'North Central Province',
            'provinces' => ['north central', 'anuradhapura', 'polonnaruwa'],
            'base_cost' => 380.0,
            'per_kg' => 50.0,
            'per_item' => 14.0,
            'delivery_days_min' => 2,
            'delivery_days_max' => 4,
            'same_day' => false
        ],
        [
            'zone_code' => 'north_western',
            'zone_name' => 'North Western Province',
            'provinces' => ['north western', 'kurunegala', 'puttalam'],
            'base_cost' => 330.0,
            'per_kg' => 45.0,
            'per_item' => 12.0,
            'delivery_days_min' => 2,
            'delivery_days_max' => 4,
            'same_day' => false
        ],
        [
            'zone_code' => 'uva',
            'zone_name' => 'Uva Province',
            'provinces' => ['uva', 'badulla', 'monaragala'],
            'base_cost' => 390.0,
            'per_kg' => 50.0,
            'per_item' => 14.0,
            'delivery_days_min' => 3,
            'delivery_days_max' => 5,
            'same_day' => false
        ],
        [
            'zone_code' => 'sabaragamuwa',
            'zone_name' => 'Sabaragamuwa Province',
            'provinces' => ['sabaragamuwa', 'ratnapura', 'kegalle'],
            'base_cost' => 340.0,
            'per_kg' => 45.0,
            'per_item' => 12.0,
            'delivery_days_min' => 2,
            'delivery_days_max' => 4,
            'same_day' => false
        ],
        [
            'zone_code' => 'default',
            'zone_name' => 'Island Wide',
            'provinces' => ['default'],
            'base_cost' => 400.0,
            'per_kg' => 55.0,
            'per_item' => 15.0,
            'delivery_days_min' => 3,
            'delivery_days_max' => 6,
            'same_day' => false
        ]
    ];
}

function bizlinkNormalizeLocation(?string $value): string {
    $normalized = strtolower(trim((string)$value));
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    return $normalized ?: '';
}

function bizlinkResolveShippingZone(?string $province = '', ?string $city = ''): array {
    $provinceNorm = bizlinkNormalizeLocation($province);
    $cityNorm = bizlinkNormalizeLocation($city);
    foreach (bizlinkShippingZones() as $zone) {
        foreach ($zone['provinces'] as $needle) {
            $needleNorm = bizlinkNormalizeLocation($needle);
            if ($needleNorm !== '' && (
                $provinceNorm === $needleNorm ||
                str_contains($provinceNorm, $needleNorm) ||
                str_contains($cityNorm, $needleNorm)
            )) {
                return $zone;
            }
        }
    }
    return bizlinkShippingZones()[count(bizlinkShippingZones()) - 1];
}

function bizlinkEstimateShipping(array $input): array {
    $province = (string)($input['province'] ?? '');
    $city = (string)($input['city'] ?? '');
    $subtotal = max(0.0, (float)($input['subtotal'] ?? 0));
    $weight = max(0.0, (float)($input['weight_kg'] ?? 0));
    $itemCount = max(1, (int)($input['item_count'] ?? 1));
    $isService = !empty($input['is_service']);
    $shippingMethod = strtolower(trim((string)($input['shipping_method'] ?? 'standard')));

    if ($isService) {
        $zone = [
            'zone_code' => 'service',
            'zone_name' => 'Service Delivery',
            'base_cost' => 0.0,
            'per_kg' => 0.0,
            'per_item' => 0.0,
            'delivery_days_min' => 0,
            'delivery_days_max' => 7,
            'same_day' => false
        ];
        $cost = 0.0;
        $minDays = 0;
        $maxDays = 7;
    } else {
        $zone = bizlinkResolveShippingZone($province, $city);
        $base = (float)$zone['base_cost'];
        $perKg = (float)$zone['per_kg'];
        $perItem = (float)$zone['per_item'];
        $cost = $base + ($weight * $perKg) + (max(0, $itemCount - 1) * $perItem) + ($subtotal * 0.015);
        if ($shippingMethod === 'express' || !empty($zone['same_day'])) {
            $cost += 180.0;
        }
        $minDays = (int)$zone['delivery_days_min'];
        $maxDays = (int)$zone['delivery_days_max'];
        if ($shippingMethod === 'express') {
            $minDays = max(0, $minDays - 1);
            $maxDays = max($minDays, $maxDays - 1);
        }
    }

    $cost = round(max(0.0, $cost), 2);
    $now = new DateTimeImmutable('now');
    $etaStart = $now->modify('+' . $minDays . ' days')->format('Y-m-d');
    $etaEnd = $now->modify('+' . $maxDays . ' days')->format('Y-m-d');

    return [
        'zone_code' => $zone['zone_code'],
        'zone_name' => $zone['zone_name'],
        'shipping_cost' => $cost,
        'estimated_delivery_start' => $etaStart,
        'estimated_delivery_end' => $etaEnd,
        'delivery_days_min' => $minDays,
        'delivery_days_max' => $maxDays,
        'same_day' => !empty($zone['same_day']),
        'subtotal' => $subtotal,
        'weight_kg' => $weight,
        'item_count' => $itemCount,
        'shipping_method' => $shippingMethod,
        'province' => $province,
        'city' => $city
    ];
}

function bizlinkGenerateTrackingNumber(int $seed = 0): string {
    $random = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $prefix = 'TRK';
    if ($seed > 0) {
        $prefix = 'BL' . str_pad((string)($seed % 1000), 3, '0', STR_PAD_LEFT);
    }
    return $prefix . '-' . date('YmdHis') . '-' . $random;
}

function bizlinkBuildPrintableLabel(array $order, array $shipment, array $estimated): string {
    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $recipient = htmlspecialchars(trim((string)($order['shipping_address'] ?? '')), ENT_QUOTES, 'UTF-8');
    $carrier = htmlspecialchars((string)($shipment['carrier_name'] ?? 'BizLink Courier'), ENT_QUOTES, 'UTF-8');
    $tracking = htmlspecialchars((string)($shipment['tracking_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $zone = htmlspecialchars((string)($estimated['zone_name'] ?? 'Island Wide'), ENT_QUOTES, 'UTF-8');
    $eta = htmlspecialchars((string)($estimated['estimated_delivery_end'] ?? ''), ENT_QUOTES, 'UTF-8');

    return '<div style="font-family:Arial,sans-serif;border:2px dashed #222;padding:18px;max-width:420px;background:#fff;color:#111">'
        . '<h2 style="margin:0 0 10px;">BizLink Shipping Label</h2>'
        . '<p><strong>Order:</strong> ' . $orderNumber . '</p>'
        . '<p><strong>Carrier:</strong> ' . $carrier . '</p>'
        . '<p><strong>Tracking:</strong> ' . $tracking . '</p>'
        . '<p><strong>Zone:</strong> ' . $zone . '</p>'
        . '<p><strong>ETA:</strong> ' . $eta . '</p>'
        . '<p style="margin-bottom:0;"><strong>Ship To:</strong><br>' . nl2br($recipient) . '</p>'
        . '</div>';
}

function bizlinkEnsureLogisticsTables(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS shipping_zones (
        shipping_zone_id INT PRIMARY KEY AUTO_INCREMENT,
        zone_code VARCHAR(50) UNIQUE NOT NULL,
        zone_name VARCHAR(255) NOT NULL,
        provinces_json JSON,
        base_cost DECIMAL(15,2) DEFAULT 0,
        per_kg_cost DECIMAL(15,2) DEFAULT 0,
        per_item_cost DECIMAL(15,2) DEFAULT 0,
        delivery_days_min INT DEFAULT 1,
        delivery_days_max INT DEFAULT 3,
        same_day BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS shipping_labels (
        shipping_label_id INT PRIMARY KEY AUTO_INCREMENT,
        order_id INT NOT NULL,
        shipment_id INT NULL,
        tracking_number VARCHAR(255) UNIQUE NOT NULL,
        carrier_name VARCHAR(100) NOT NULL,
        carrier_url VARCHAR(500),
        label_payload_json LONGTEXT,
        print_status ENUM('pending', 'printed', 'void') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_shipping_labels_order (order_id),
        INDEX idx_shipping_labels_shipment (shipment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS return_requests (
        return_request_id INT PRIMARY KEY AUTO_INCREMENT,
        order_id INT NOT NULL,
        customer_id INT NOT NULL,
        vendor_id INT NOT NULL,
        rma_number VARCHAR(100) UNIQUE NOT NULL,
        reason VARCHAR(255) NOT NULL,
        reason_details TEXT,
        requested_amount DECIMAL(15,2) DEFAULT 0,
        return_status ENUM('requested', 'approved', 'rejected', 'in_transit', 'received', 'refunded', 'closed') DEFAULT 'requested',
        return_tracking_number VARCHAR(255),
        carrier_name VARCHAR(100),
        return_shipping_cost DECIMAL(15,2) DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_return_requests_order (order_id),
        INDEX idx_return_requests_customer (customer_id),
        INDEX idx_return_requests_vendor (vendor_id),
        INDEX idx_return_requests_status (return_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function bizlinkSeedShippingZones(mysqli $conn): void {
    bizlinkEnsureLogisticsTables($conn);
    $zones = bizlinkShippingZones();
    $stmt = $conn->prepare('INSERT INTO shipping_zones (zone_code, zone_name, provinces_json, base_cost, per_kg_cost, per_item_cost, delivery_days_min, delivery_days_max, same_day, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE zone_name = VALUES(zone_name), provinces_json = VALUES(provinces_json), base_cost = VALUES(base_cost), per_kg_cost = VALUES(per_kg_cost), per_item_cost = VALUES(per_item_cost), delivery_days_min = VALUES(delivery_days_min), delivery_days_max = VALUES(delivery_days_max), same_day = VALUES(same_day), is_active = 1');
    if (!$stmt) {
        return;
    }
    foreach ($zones as $zone) {
        $json = json_encode($zone['provinces']);
        $stmt->bind_param(
            'sssdddiiii',
            $zone['zone_code'],
            $zone['zone_name'],
            $json,
            $zone['base_cost'],
            $zone['per_kg'],
            $zone['per_item'],
            $zone['delivery_days_min'],
            $zone['delivery_days_max'],
            $zone['same_day'],
            $dummy = 1
        );
        $stmt->execute();
    }
    $stmt->close();
}

function bizlinkOrderDeliveryEstimateFromOrder(array $orderRow, array $extra = []): array {
    $province = (string)($extra['province'] ?? ($orderRow['province'] ?? ''));
    $city = (string)($extra['city'] ?? ($orderRow['city'] ?? ''));
    $subtotal = (float)($orderRow['subtotal'] ?? 0);
    $weight = (float)($extra['weight_kg'] ?? 0);
    $items = (int)($extra['item_count'] ?? 1);
    $shippingMethod = (string)($orderRow['shipping_method'] ?? 'standard');
    return bizlinkEstimateShipping([
        'province' => $province,
        'city' => $city,
        'subtotal' => $subtotal,
        'weight_kg' => $weight,
        'item_count' => $items,
        'shipping_method' => $shippingMethod,
        'is_service' => ($extra['is_service'] ?? false)
    ]);
}
