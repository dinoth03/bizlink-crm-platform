<?php
$c = new mysqli('localhost', 'root', '', 'bizlink_crm');
if($c->connect_error) die('err');
$r = $c->query("SELECT u.user_id, v.vendor_id, v.business_name FROM users u JOIN vendors v ON u.user_id = v.user_id WHERE u.email = 'niroshan.perera@ceylontech.lk'");
$vendor = $r->fetch_assoc();
print_r($vendor);

$r = $c->query("SELECT product_id, product_name, vendor_id, is_active FROM products WHERE vendor_id = " . $vendor['vendor_id']);
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
