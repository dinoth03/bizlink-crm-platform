<?php
require 'config.php';
require_once 'api_helpers.php';
require_once 'auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

// Enforce admin-only access
try {
    requireAuth($conn, 'admin');
} catch (Throwable $e) {
    apiError('AUTH_REQUIRED', $e->getMessage(), 401);
}

// Get query parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

$status = strtolower(trim((string)($_GET['status'] ?? '')));
$role = strtolower(trim((string)($_GET['role'] ?? '')));
$search = trim((string)($_GET['search'] ?? ''));

$allowedStatuses = ['new', 'in_progress', 'resolved', 'closed'];
$allowedRoles = ['admin', 'vendor', 'customer'];

// Build WHERE clause
$whereConditions = [];
$bindParams = [];
$bindTypes = '';

if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $whereConditions[] = 'inquiry_status = ?';
    $bindParams[] = $status;
    $bindTypes .= 's';
}

if ($role !== '' && in_array($role, $allowedRoles, true)) {
    $whereConditions[] = 'target_role = ?';
    $bindParams[] = $role;
    $bindTypes .= 's';
}

if ($search !== '') {
    $searchWildcard = '%' . $search . '%';
    $whereConditions[] = '(full_name LIKE ? OR email LIKE ? OR message LIKE ?)';
    $bindParams[] = $searchWildcard;
    $bindParams[] = $searchWildcard;
    $bindParams[] = $searchWildcard;
    $bindTypes .= 'sss';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count
$countQuery = 'SELECT COUNT(*) as total FROM contact_inquiries ' . $whereClause;
$countStmt = $conn->prepare($countQuery);
if (!empty($bindParams)) {
    $countStmt->bind_param($bindTypes, ...$bindParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();
$totalCount = (int)($countRow['total'] ?? 0);
$countStmt->close();

// Get paginated inquiries
$query = 'SELECT inquiry_id, full_name, email, target_role, message, inquiry_status, admin_notes, 
                 source_page, ip_address, created_at, updated_at 
          FROM contact_inquiries 
          ' . $whereClause . ' 
          ORDER BY created_at DESC 
          LIMIT ? OFFSET ?';

$stmt = $conn->prepare($query);
$bindParams[] = $limit;
$bindParams[] = $offset;
$bindTypes .= 'ii';
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$result = $stmt->get_result();

$inquiries = [];
while ($row = $result->fetch_assoc()) {
    $inquiries[] = [
        'inquiry_id' => (int)$row['inquiry_id'],
        'full_name' => $row['full_name'],
        'email' => $row['email'],
        'target_role' => $row['target_role'],
        'message' => $row['message'],
        'status' => $row['inquiry_status'],
        'admin_notes' => $row['admin_notes'],
        'source_page' => $row['source_page'],
        'ip_address' => $row['ip_address'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$stmt->close();

apiSuccess([
    'inquiries' => $inquiries,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $totalCount,
        'total_pages' => ceil($totalCount / $limit)
    ]
], 'Inquiries retrieved successfully.', 'INQUIRIES_RETRIEVED', 200);

$conn->close();
?>
