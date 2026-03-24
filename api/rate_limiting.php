<?php

require_once 'api_helpers.php';

/**
 * Rate Limiting Service
 * Enforces per-IP and per-account rate limits on sensitive endpoints.
 */

define('RATE_LIMIT_ENABLED', (bool)(getenv('RATE_LIMIT_ENABLED') ?? true));
define('RATE_LIMIT_DEFAULT_WINDOW_SECONDS', (int)(getenv('RATE_LIMIT_WINDOW_SECONDS') ?: 900)); // 15 minutes
define('RATE_LIMIT_DEFAULT_MAX_ATTEMPTS', (int)(getenv('RATE_LIMIT_MAX_ATTEMPTS') ?: 10));

/**
 * Represents a rate limit check result.
 */
class RateLimitResult {
    public bool $allowed;
    public int $remaining;
    public int $retryAfterSeconds;
    public string $key;
    
    public function __construct(bool $allowed, int $remaining, int $retryAfterSeconds, string $key) {
        $this->allowed = $allowed;
        $this->remaining = max(0, $remaining);
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->key = $key;
    }
}

/**
 * Check if a request should be rate limited.
 * 
 * @param mysqli $conn Database connection
 * @param string $identifier Unique identifier (email, IP, user_id, etc.)
 * @param string $endpoint The API endpoint being hit (e.g., "login", "signup")
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @return RateLimitResult Rate limit status
 */
function checkRateLimit(
    $conn,
    string $identifier,
    string $endpoint,
    int $maxAttempts = null,
    int $windowSeconds = null
): RateLimitResult {
    if (!RATE_LIMIT_ENABLED) {
        return new RateLimitResult(true, $maxAttempts ?? RATE_LIMIT_DEFAULT_MAX_ATTEMPTS, 0, '');
    }
    
    $maxAttempts = $maxAttempts ?? RATE_LIMIT_DEFAULT_MAX_ATTEMPTS;
    $windowSeconds = $windowSeconds ?? RATE_LIMIT_DEFAULT_WINDOW_SECONDS;
    
    $key = hash('sha256', $endpoint . ':' . $identifier);
    $now = time();
    $windowStart = $now - $windowSeconds;
    
    try {
        // Get current attempt count
        $stmt = $conn->prepare(
            'SELECT COUNT(*) as attempt_count, MIN(timestamp) as oldest_timestamp
             FROM rate_limit_log
             WHERE limit_key = ? AND timestamp > ?'
        );
        $stmt->bind_param('si', $key, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $attemptCount = (int)($row['attempt_count'] ?? 0);
        $oldestTimestamp = (int)($row['oldest_timestamp'] ?? $now);
        
        // Calculate retry-after seconds
        $retryAfterSeconds = max(0, ($oldestTimestamp + $windowSeconds) - $now);
        
        // Check if limit exceeded
        $allowed = $attemptCount < $maxAttempts;
        $remaining = max(0, $maxAttempts - $attemptCount - 1);
        
        // Log this attempt
        if ($allowed) {
            $logStmt = $conn->prepare(
                'INSERT INTO rate_limit_log (limit_key, endpoint, identifier, timestamp) VALUES (?, ?, ?, ?)'
            );
            $logStmt->bind_param('sssi', $key, $endpoint, $identifier, $now);
            $logStmt->execute();
            $logStmt->close();
        }
        
        return new RateLimitResult($allowed, $remaining, $retryAfterSeconds, $key);
    } catch (Throwable $e) {
        error_log('Rate limit check failed: ' . $e->getMessage());
        // Fail open - allow request if rate limit service is down
        return new RateLimitResult(true, $maxAttempts, 0, $key);
    }
}

/**
 * Check rate limit and exit with 429 if exceeded.
 * 
 * @param mysqli $conn Database connection
 * @param string $identifier Unique identifier
 * @param string $endpoint API endpoint name
 * @param int $maxAttempts Maximum attempts
 * @param int $windowSeconds Time window in seconds
 * @return RateLimitResult Always returns result if check passes
 */
function requireRateLimit(
    $conn,
    string $identifier,
    string $endpoint,
    int $maxAttempts = null,
    int $windowSeconds = null
): RateLimitResult {
    $result = checkRateLimit($conn, $identifier, $endpoint, $maxAttempts, $windowSeconds);
    
    if (!$result->allowed) {
        header('Retry-After: ' . $result->retryAfterSeconds);
        apiError(
            'RATE_LIMITED',
            'Too many requests. Please try again later.',
            429,
            [],
            null,
            ['retry_after' => $result->retryAfterSeconds]
        );
    }
    
    return $result;
}

/**
 * Get current request IP address (handles proxies).
 * 
 * @return string Client IP address
 */
function getClientIpAddress(): string {
    $ip = '';
    
    // Check for IP from shared internet
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    // Check for IP from proxy
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Handle multiple IPs (take the first one, which is the real client)
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    // Check for direct connection
    elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Clean up old rate limit records (older than 1 day).
 * Call this periodically from a cron job.
 * 
 * @param mysqli $conn Database connection
 * @return int Number of records deleted
 */
function cleanupOldRateLimitLogs($conn): int {
    try {
        $result = $conn->query(
            'DELETE FROM rate_limit_log WHERE timestamp < ' . (time() - 86400)
        );
        return $conn->affected_rows;
    } catch (Throwable $e) {
        error_log('Rate limit cleanup failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get rate limit info for a specific identifier.
 * Useful for debugging or displaying remaining attempts to user.
 * 
 * @param mysqli $conn Database connection
 * @param string $identifier Unique identifier
 * @param string $endpoint API endpoint name
 * @param int $windowSeconds Time window in seconds
 * @return array Status information
 */
function getRateLimitInfo($conn, string $identifier, string $endpoint, int $windowSeconds = null): array {
    $windowSeconds = $windowSeconds ?? RATE_LIMIT_DEFAULT_WINDOW_SECONDS;
    $key = hash('sha256', $endpoint . ':' . $identifier);
    $windowStart = time() - $windowSeconds;
    
    try {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) as attempt_count FROM rate_limit_log
             WHERE limit_key = ? AND timestamp > ?'
        );
        $stmt->bind_param('si', $key, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $attemptCount = (int)($row['attempt_count'] ?? 0);
        
        return [
            'endpoint' => $endpoint,
            'identifier' => redactSensitiveData($identifier),
            'attempts' => $attemptCount,
            'max_attempts' => RATE_LIMIT_DEFAULT_MAX_ATTEMPTS,
            'remaining' => max(0, RATE_LIMIT_DEFAULT_MAX_ATTEMPTS - $attemptCount),
            'window_seconds' => $windowSeconds
        ];
    } catch (Throwable $e) {
        error_log('Failed to get rate limit info: ' . $e->getMessage());
        return [];
    }
}
?>
