<?php
$c = new mysqli('localhost', 'root', '', 'bizlink_crm');
if($c->connect_error) die('err');

echo "--- RICE COOKER DATA ---\n";
$r = $c->query("SELECT p.product_id, p.product_name, p.vendor_id, v.user_id as owner_user_id, v.business_name FROM products p LEFT JOIN vendors v ON p.vendor_id = v.vendor_id WHERE p.product_name LIKE '%Rice Cooker%'");
while($row = $r->fetch_assoc()) {
    print_r($row);
}

echo "\n--- CURRENT VENDOR (sithara) DATA ---\n";
$r = $c->query("SELECT u.user_id, u.email, v.vendor_id, v.business_name FROM users u LEFT JOIN vendors v ON u.user_id = v.user_id WHERE u.email = 'sithara@gmail.com'");
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
