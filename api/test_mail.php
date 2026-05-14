<?php
require_once 'mail_service.php';

$to = 'dinoth08@gmail.com'; // Testing with your own email
$subject = 'BizLink SMTP Test';
$body = '<h1>SMTP Connection Test</h1><p>If you received this, the SMTP connection is working correctly with the new settings.</p>';

echo "Attempting to send test email to $to...\n";
$result = sendMail($to, $subject, $body);

echo "Result:\n";
print_r($result);
?>
