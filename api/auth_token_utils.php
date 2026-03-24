<?php

function generateSecureToken(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

function hashAuthToken(string $token): string {
    return hash('sha256', $token);
}

function isLocalHostEnvironment(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    return str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
}

function buildPublicUrl(string $relativePath, array $query = []): string {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $path = '/' . ltrim($relativePath, '/');
    $queryString = !empty($query) ? ('?' . http_build_query($query)) : '';

    return $scheme . '://' . $host . $path . $queryString;
}
