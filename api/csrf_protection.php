<?php

require_once 'api_helpers.php';

function ensureCsrfTokensTable(mysqli $conn): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS csrf_tokens (
        csrf_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        session_id VARCHAR(128) NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token_hash (token_hash),
        INDEX idx_expires_at (expires_at),
        INDEX idx_session_id (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    $conn->query($sql);
    $ensured = true;
}

/**
 * CSRF Protection Utility
 * Provides cross-site request forgery token generation, validation, and management.
 */

/**
 * Generate a new CSRF token and store in database.
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID (null for anonymous tokens)
 * @param string $sessionId Session ID
 * @return string The generated CSRF token (plaintext for client-side use)
 */
function generateCsrfToken($conn, ?int $userId = null, string $sessionId = ''): string {
    ensureCsrfTokensTable($conn);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiryMinutes = 60; // 1 hour
    
    try {
        $stmt = $conn->prepare(
            'INSERT INTO csrf_tokens (user_id, session_id, token_hash, expires_at, created_at) 
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())'
        );
        $stmt->bind_param('issi', $userId, $sessionId, $tokenHash, $expiryMinutes);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('CSRF token generation failed: ' . $e->getMessage());
        return '';
    }
    
    return $token;
}

/**
 * Validate a CSRF token from request.
 * 
 * @param mysqli $conn Database connection
 * @param string $token Token to validate (from request)
 * @param int|null $userId User ID (if applicable)
 * @param string $sessionId Session ID
 * @return bool True if token is valid
 */
function validateCsrfToken($conn, string $token, ?int $userId = null, string $sessionId = ''): bool {
    ensureCsrfTokensTable($conn);

    if (empty($token)) {
        return false;
    }
    
    $tokenHash = hash('sha256', $token);
    
    try {
        $stmt = $conn->prepare(
            'SELECT csrf_id, used_at FROM csrf_tokens 
             WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL
             LIMIT 1'
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row) {
            return false;
        }
        
        // Mark token as used (one-time use)
        $markStmt = $conn->prepare('UPDATE csrf_tokens SET used_at = NOW() WHERE csrf_id = ?');
        $markStmt->bind_param('i', $row['csrf_id']);
        $markStmt->execute();
        $markStmt->close();
        
        return true;
    } catch (Throwable $e) {
        error_log('CSRF token validation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get CSRF token from request.
 * Looks in: POST data, query string, or X-CSRF-Token header.
 * 
 * @return string Token value or empty string if not found
 */
function getCsrfTokenFromRequest(): string {
    // Check POST data first
    if (isset($_POST['_csrf_token'])) {
        return (string)$_POST['_csrf_token'];
    }
    
    // Check query string
    if (isset($_GET['_csrf_token'])) {
        return (string)$_GET['_csrf_token'];
    }
    
    // Check custom header
    $headers = getallheaders();
    if (isset($headers['X-CSRF-Token'])) {
        return (string)$headers['X-CSRF-Token'];
    }
    
    // Check JSON body
    $payload = json_decode(file_get_contents('php://input'), true);
    if (is_array($payload) && isset($payload['_csrf_token'])) {
        return (string)$payload['_csrf_token'];
    }
    
    return '';
}

/**
 * Require CSRF validation for state-changing operations.
 * Call this at the start of endpoints that handle POST/PUT/DELETE.
 * 
 * @param mysqli $conn Database connection
 * @param int|null $userId User ID
 * @param bool $exitOnFailure Exit with error on failure (default true)
 * @return bool True if token is valid
 */
function requireCsrfToken($conn, ?int $userId = null, bool $exitOnFailure = true): bool {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        return true; // CSRF not required for GET
    }
    
    $sessionId = session_id() ?: '';
    $token = getCsrfTokenFromRequest();
    
    if (!validateCsrfToken($conn, $token, $userId, $sessionId)) {
        if ($exitOnFailure) {
            apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
        }
        return false;
    }
    
    return true;
}

/**
 * Clean up expired CSRF tokens (periodically run).
 * 
 * @param mysqli $conn Database connection
 * @return int Number of tokens deleted
 */
function cleanupExpiredCsrfTokens($conn): int {
    ensureCsrfTokensTable($conn);

    try {
        $result = $conn->query('DELETE FROM csrf_tokens WHERE expires_at < NOW()');
        return $conn->affected_rows;
    } catch (Throwable $e) {
        error_log('CSRF cleanup failed: ' . $e->getMessage());
        return 0;
    }
}
?>
