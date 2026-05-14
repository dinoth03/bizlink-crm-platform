<?php

require __DIR__ . '/mail_service.php';

if ($argc < 2) {
    fwrite(STDERR, "Missing payload file.\n");
    exit(1);
}

$payloadFile = $argv[1];
if (!is_file($payloadFile)) {
    fwrite(STDERR, "Payload file not found.\n");
    exit(1);
}

$payload = json_decode((string)file_get_contents($payloadFile), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Invalid payload.\n");
    exit(1);
}

$result = sendMail(
    (string)($payload['to'] ?? ''),
    (string)($payload['subject'] ?? ''),
    (string)($payload['htmlBody'] ?? ''),
    isset($payload['textBody']) && $payload['textBody'] !== null ? (string)$payload['textBody'] : null
);

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);