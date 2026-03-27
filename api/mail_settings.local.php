<?php
// Local SMTP settings for OTP email delivery.
// Keep this file private and do not commit real credentials.

return [
    'MAIL_DRIVER' => 'smtp',
    'MAIL_FROM_ADDRESS' => 'your-email@gmail.com',
    'MAIL_FROM_NAME' => 'BizLink CRM',

    'SMTP_HOST' => 'smtp.gmail.com',
    'SMTP_PORT' => '587',
    'SMTP_USERNAME' => 'your-email@gmail.com',
    'SMTP_PASSWORD' => 'your-gmail-app-password',
    'SMTP_ENCRYPTION' => 'tls',

    // Optional custom path. Leave default unless you move PHPMailer files.
    'PHPMAILER_SRC_PATH' => __DIR__ . '/phpmailer/src',
];
