<?php
require 'config.php';
require_once 'mail_service.php';

$to = 'dinoth08@gmail.com';
$result = sendMail($to, 'BizLink HTTP SMTP Test', '<p>BizLink HTTP test</p>', 'BizLink HTTP test');

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
