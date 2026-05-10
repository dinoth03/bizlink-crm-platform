<?php
require_once 'config.php';
require_once 'api_helpers.php';
require_once 'shipping_helpers.php';

$zones = bizlinkShippingZones();
apiSuccess([
    'zones' => $zones,
    'matrix' => $zones
], 'Shipping zones fetched.', 'SHIPPING_ZONES_FETCHED');

$conn->close();
?>