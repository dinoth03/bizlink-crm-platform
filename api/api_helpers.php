<?php

/**
 * Shared API response and validation helpers.
 */

function apiSuccess(
    mixed $data = null,
    string $message = 'Request completed successfully.',
    string $code = 'OK',
    int $status = 200,
    array $meta = []
): never {
    http_response_code($status);
    echo json_encode([
        'success' => true,
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'errors' => [],
        'meta' => $meta,
        'timestamp' => date('c')
    ]);
    exit;
}

function apiError(
    string $code,
    string $message,
    int $status = 400,
    array $errors = [],
    mixed $data = null,
    array $meta = []
): never {
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'code' => $code,
        'message' => $message,
        'data' => $data,
        'errors' => $errors,
        'meta' => $meta,
        'timestamp' => date('c')
    ]);
    exit;
}

function readJsonPayload(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function validateRequired(array $payload, array $requiredFields): array {
    $errors = [];
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $payload) || trim((string)$payload[$field]) === '') {
            $errors[] = [
                'field' => $field,
                'message' => $field . ' is required.'
            ];
        }
    }
    return $errors;
}

function validateEmailStrict(string $email): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (strlen($email) > 254) {
        return false;
    }
    return true;
}

function validateRoleStrict(string $role): bool {
    return in_array($role, ['admin', 'vendor', 'customer'], true);
}

function sanitizeString(string $value, int $maxLength): string {
    $value = trim($value);
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function getPaginationParams(array $source, int $defaultPerPage = 20, int $maxPerPage = 100): array {
    $page = isset($source['page']) ? (int)$source['page'] : 1;
    $perPage = isset($source['per_page']) ? (int)$source['per_page'] : $defaultPerPage;

    if ($page < 1) {
        $page = 1;
    }
    if ($perPage < 1) {
        $perPage = $defaultPerPage;
    }
    if ($perPage > $maxPerPage) {
        $perPage = $maxPerPage;
    }

    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => $offset,
        'limit' => $perPage
    ];
}

function appendSqlCondition(string &$whereSql, array &$params, array &$types, string $condition, mixed $value, string $type): void {
    $whereSql .= ' AND ' . $condition;
    $params[] = $value;
    $types[] = $type;
}

function bindDynamicParams(mysqli_stmt $stmt, array $types, array $params): void {
    if (empty($params)) {
        return;
    }

    $typeString = implode('', $types);
    $bindParams = [$typeString];

    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}
