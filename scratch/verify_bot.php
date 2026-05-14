<?php
include 'api/config.php';
$res = $conn->query("SELECT user_id, role, account_status FROM users WHERE user_id = 999");
$row = $res->fetch_assoc();
if ($row) {
    echo "USER_999: Role=" . $row['role'] . " Status=" . $row['account_status'] . "\n";
} else {
    echo "USER_999_NOT_FOUND\n";
}
?>
