<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_COOKIE'] = '';
chdir(__DIR__ . '/api');
require 'admin_reply_contact_inquiry.php';
