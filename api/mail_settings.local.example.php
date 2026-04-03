<?php
return [
    // Mail driver: smtp, php, dev
    'MAIL_DRIVER' => 'smtp',

    // Sender identity
    'MAIL_FROM_ADDRESS' => 'your-email@gmail.com',
    'MAIL_FROM_NAME' => 'BizLink CRM',

    // SMTP (Gmail example)
    'SMTP_HOST' => 'smtp.gmail.com',
    'SMTP_PORT' => '587',
    'SMTP_USERNAME' => 'your-email@gmail.com',
    'SMTP_PASSWORD' => 'your-gmail-app-password',
    'SMTP_ENCRYPTION' => 'tls',

    // Optional PHPMailer source path
    'PHPMAILER_SRC_PATH' => __DIR__ . '/phpmailer/src'
];
