<?php
include 'api/config.php';
$res = $conn->query("SELECT user_id, full_name, role FROM users WHERE role = 'bot' LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo "BOT_FOUND: ID=" . $row['user_id'] . " Name=" . $row['full_name'] . "\n";
} else {
    echo "BOT_NOT_FOUND\n";
    // Let's create it
    $email = 'ai-bot@bizlink-crm.local';
    $fullName = 'AI Assistant';
    $role = 'bot';
    $passwordHash = password_hash('secure-bot-password-' . time(), PASSWORD_BCRYPT);
    $status = 'active';

    $insertSql = "INSERT INTO users (email, full_name, role, password_hash, account_status, email_verified, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param('sssss', $email, $fullName, $role, $passwordHash, $status);
    if ($stmt->execute()) {
        echo "BOT_CREATED: ID=" . $stmt->insert_id . "\n";
    } else {
        echo "BOT_CREATE_FAILED: " . $stmt->error . "\n";
    }
}
?>
