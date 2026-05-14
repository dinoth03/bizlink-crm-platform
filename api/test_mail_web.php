<?php
require_once 'mail_service.php';
$to = 'dinoth08@gmail.com';
$subject = 'Web Context SMTP Test';
$body = 'Testing from Apache.';

$result = sendMail($to, $subject, $body);

header('Content-Type: application/json');
echo json_encode(['result' => $result, 'last_error' => $GLOBALS['LAST_MAIL_ERROR'] ?? null], JSON_PRETTY_PRINT);
?>
