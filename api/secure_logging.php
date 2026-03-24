<?php

/**
 * Secure Logging Utility
 * Logs security events while redacting sensitive personal data.
 */

/**
 * Redact sensitive data from logs.
 * Masks emails, phone numbers, IPs, and partial credit card numbers.
 * 
 * @param string $data Data to redact
 * @return string Redacted data
 */
function redactSensitiveData(string $data): string {
    // Redact email addresses
    $data = preg_replace_callback(
        '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i',
        function($matches) {
            $local = substr($matches[1], 0, 2);
            return $local . '***@' . $matches[2];
        },
        $data
    );
    
    // Redact phone numbers (various formats)
    $data = preg_replace(
        [
            '/\+?1?\s?[\(\-]?([0-9]{3})[\)\-]?\s?([0-9]{3})[\-]?([0-9]{4})/',
            '/\+[0-9]{1,3}[0-9\s\-\(\)]{6,}[0-9]{2}/'
        ],
        ['$1***$3', '+***'],
        $data
    );
    
    // Redact IP addresses (show first octet only)
    $data = preg_replace_callback(
        '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/',
        function($matches) {
            $parts = explode('.', $matches[0]);
            return $parts[0] . '.***.***.***';
        },
        $data
    );
    
    // Redact full names (keep first letter and last letter)
    $data = preg_replace_callback(
        '/\b([A-Z][a-z]+)\s+([A-Z][a-z]+)\b/',
        function($matches) {
            $first = $matches[1][0] . str_repeat('*', strlen($matches[1]) - 2) . $matches[1][strlen($matches[1]) - 1];
            $last = $matches[2][0] . str_repeat('*', strlen($matches[2]) - 2) . $matches[2][strlen($matches[2]) - 1];
            return $first . ' ' . $last;
        },
        $data
    );
    
    // Redact credit card numbers (show last 4 digits only)
    $data = preg_replace(
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?(\d{4})\b/',
        '****-****-****-$1',
        $data
    );
    
    // Redact SSN/ID numbers
    $data = preg_replace(
        '/\b\d{3}[\s\-]?\d{2}[\s\-]?\d{4}\b/',
        '***-**-****',
        $data
    );
    
    return $data;
}

/**
 * Log a security event with automatic redaction.
 * 
 * @param string $event Event type (e.g., "login_failed", "account_created")
 * @param array $context Additional context (will be redacted)
 * @param string $severity Log severity (info, warning, error, critical)
 */
function logSecurityEvent(string $event, array $context = [], string $severity = 'info'): void {
    $sanitizedContext = [];
    foreach ($context as $key => $value) {
        $sanitizedContext[$key] = redactSensitiveData((string)$value);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'severity' => strtoupper($severity),
        'event' => $event,
        'ip' => isset($context['ip']) ? redactSensitiveData($context['ip']) : getClientIpAddress(),
        'user_id' => $context['user_id'] ?? null,
        'context' => $sanitizedContext
    ];
    
    error_log('[SECURITY] ' . json_encode($logEntry));
}

/**
 * Log authentication success.
 * 
 * @param int $userId User ID
 * @param string $role User role
 * @param string $email Email (will be redacted in log)
 */
function logAuthSuccess(int $userId, string $role, string $email): void {
    logSecurityEvent('auth_success', [
        'user_id' => $userId,
        'role' => $role,
        'email' => $email,
        'ip' => getClientIpAddress()
    ], 'info');
}

/**
 * Log authentication failure.
 * 
 * @param string $email Email (will be redacted in log)
 * @param string $role Role attempted
 * @param string $reason Failure reason
 * @param string $ip IP address
 */
function logAuthFailure(string $email, string $role, string $reason, string $ip = ''): void {
    logSecurityEvent('auth_failure', [
        'email' => $email,
        'role' => $role,
        'reason' => $reason,
        'ip' => $ip ?: getClientIpAddress()
    ], 'warning');
}

/**
 * Log rate limit exceeded.
 * 
 * @param string $identifier Identifier that exceeded limit
 * @param string $endpoint Endpoint that was hit
 * @param string $ip IP address
 */
function logRateLimitExceeded(string $identifier, string $endpoint, string $ip = ''): void {
    logSecurityEvent('rate_limit_exceeded', [
        'identifier' => $identifier,
        'endpoint' => $endpoint,
        'ip' => $ip ?: getClientIpAddress()
    ], 'warning');
}

/**
 * Log suspicious activity.
 * 
 * @param string $reason Reason for suspension
 * @param array $details Additional details
 */
function logSuspiciousActivity(string $reason, array $details = []): void {
    logSecurityEvent('suspicious_activity', array_merge([
        'reason' => $reason,
        'ip' => getClientIpAddress()
    ], $details), 'critical');
}

/**
 * Log CSRF token validation failure.
 * 
 * @param string $ip IP address
 * @param string $endpoint Endpoint that failed validation
 */
function logCsrfFailure(string $endpoint, string $ip = ''): void {
    logSecurityEvent('csrf_validation_failed', [
        'endpoint' => $endpoint,
        'ip' => $ip ?: getClientIpAddress()
    ], 'warning');
}

/**
 * Log API error without exposing sensitive details.
 * 
 * @param string $endpoint Endpoint that errored
 * @param int $statusCode HTTP status code
 * @param string $errorMessage Error message
 */
function logApiError(string $endpoint, int $statusCode, string $errorMessage): void {
    $sanitized = redactSensitiveData($errorMessage);
    error_log("[API ERROR] {$endpoint} - Status {$statusCode}: {$sanitized}");
}

/**
 * Log account-related changes.
 * 
 * @param int $userId User ID
 * @param string $action Action performed (created, suspended, verified, etc.)
 * @param string $email Email address (will be redacted)
 * @param array $details Additional details
 */
function logAccountChange(int $userId, string $action, string $email = '', array $details = []): void {
    logSecurityEvent('account_' . $action, array_merge([
        'user_id' => $userId,
        'email' => $email,
        'ip' => getClientIpAddress()
    ], $details), 'info');
}

/**
 * Clean up old security logs (optional - depends on your logging infrastructure).
 * For file-based logging, monitor log size.
 * For database logging, create appropriate retention policies.
 * 
 * @return void
 */
function rotateSecurityLogs(): void {
    // This is a placeholder for log rotation logic
    // Implementation depends on your logging backend (file, database, syslog, etc.)
    // For file-based logging, consider using logrotate on Linux/Unix systems
    // For database logging, implement a cron job to archive/delete old records
    error_log('[SECURITY] Log rotation check performed at ' . date('Y-m-d H:i:s'));
}
?>
