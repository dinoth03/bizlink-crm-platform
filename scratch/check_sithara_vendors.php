<?php
$c = new mysqli('localhost', 'root', '', 'bizlink_crm');
if($c->connect_error) die('err');

$r = $c->query("SELECT * FROM vendors WHERE business_name LIKE '%Sithara%' OR user_id IN (33, 41)");
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
