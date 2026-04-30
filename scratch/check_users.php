<?php
$c = new mysqli('localhost', 'root', '', 'bizlink_crm');
if($c->connect_error) die('err');
$r = $c->query('SELECT email, role FROM users LIMIT 5');
while($row = $r->fetch_assoc()) {
    echo $row['email'] . ' | ' . $row['role'] . "\n";
}
?>
