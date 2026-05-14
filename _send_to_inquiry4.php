<?php
chdir(__DIR__ . '/api');
require 'mail_service.php';
$result = sendMail('sadanu9@gamil.com', 'BizLink SMTP Test', '<p>BizLink test</p>', 'BizLink test');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
