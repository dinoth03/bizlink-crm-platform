<?php
$c = new mysqli('localhost', 'root', '', 'bizlink_crm');
if($c->connect_error) die('err');

echo "--- USER 41 DATA ---\n";
$r = $c->query("SELECT user_id, email, full_name, role FROM users WHERE user_id=41");
print_r($r->fetch_assoc());

echo "\n--- USER 33 DATA ---\n";
$r = $c->query("SELECT user_id, email, full_name, role FROM users WHERE user_id=33");
print_r($r->fetch_assoc());
?>
