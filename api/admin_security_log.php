<?php
/**
 * Admin Security Logging
 * Logs all admin-related security events: registration, email verification, login attempts, etc.
 */

define('ADMIN_SECURITY_LOG_TABLE', 'admin_security_log');

/**
 * Log admin security event
 */
function logAdminSecurityEvent(
    mysqli $conn,
    ?int $adminUserId,
    string $eventType,
    string $status,
    string $description,
    ?string $ipAddress = null,
    ?string $userAgent = null,
    ?array $metadata = null
): bool {
    try {
        // Ensure table exists
        ensureAdminSecurityLogTable($conn);

        $ipAddress = $ipAddress ?? substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $userAgent = $userAgent ?? substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $stmt = $conn->prepare(
            'INSERT INTO ' . ADMIN_SECURITY_LOG_TABLE . ' 
             (admin_user_id, event_type, status, description, ip_address, user_agent, metadata, logged_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        if (!$stmt) {
            error_log("[Admin Security Log] Prepare failed: " . $conn->error);
            return false;
        }

        $stmt->bind_param(
            'issssss',
            $adminUserId,
            $eventType,
            $status,
            $description,
            $ipAddress,
            $userAgent,
            $metadataJson
        );

        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            error_log("[Admin Security Log] Event logged: {$eventType} - {$status}");
        } else {
            error_log("[Admin Security Log] Execute failed: " . $conn->error);
        }

        return $success;
    } catch (Throwable $e) {
        error_log("[Admin Security Log] Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Create admin security log table if it doesn't exist
 */
function ensureAdminSecurityLogTable(mysqli $conn): bool {
    try {
        $checkSql = "SHOW TABLES LIKE '" . ADMIN_SECURITY_LOG_TABLE . "'";
        $result = $conn->query($checkSql);

        if ($result && $result->num_rows > 0) {
            return true; // Table exists
        }

        $createSql = "
            CREATE TABLE IF NOT EXISTS " . ADMIN_SECURITY_LOG_TABLE . " (
                log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
                admin_user_id INT NULL,
                event_type VARCHAR(100) NOT NULL,
                status VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                ip_address VARCHAR(45),
                user_agent VARCHAR(500),
                metadata JSON,
                logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_admin_security_log_user FOREIGN KEY (admin_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
                INDEX idx_admin_user (admin_user_id),
                INDEX idx_event_type (event_type),
                INDEX idx_status (status),
                INDEX idx_logged_at (logged_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        if ($conn->query($createSql)) {
            return true;
        } else {
            error_log("[Admin Security Log] Table creation failed: " . $conn->error);
            return false;
        }
    } catch (Throwable $e) {
        error_log("[Admin Security Log] Table creation exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Get admin security logs with filtering
 */
function getAdminSecurityLogs(
    mysqli $conn,
    ?int $adminUserId = null,
    ?string $eventType = null,
    ?string $status = null,
    int $limit = 100,
    int $offset = 0
): array {
    try {
        $sql = "SELECT * FROM " . ADMIN_SECURITY_LOG_TABLE . " WHERE 1=1";
        $types = '';
        $params = [];

        if ($adminUserId !== null) {
            $sql .= " AND admin_user_id = ?";
            $types .= 'i';
            $params[] = $adminUserId;
        }

        if ($eventType !== null) {
            $sql .= " AND event_type = ?";
            $types .= 's';
            $params[] = $eventType;
        }

        if ($status !== null) {
            $sql .= " AND status = ?";
            $types .= 's';
            $params[] = $status;
        }

        $sql .= " ORDER BY logged_at DESC LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];

        while ($row = $result->fetch_assoc()) {
            if ($row['metadata']) {
                $row['metadata'] = json_decode($row['metadata'], true);
            }
            $logs[] = $row;
        }

        $stmt->close();
        return $logs;
    } catch (Throwable $e) {
        error_log("[Admin Security Log] Query failed: " . $e->getMessage());
        return [];
    }
}

?>
