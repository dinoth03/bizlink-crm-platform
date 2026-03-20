<?php
/**
 * Dashboard Router - Centralized entry point for authenticated pages
 * Usage: dashboard.php?role=admin or dashboard.php (uses session to determine role)
 */

session_start();
require 'api/config.php';

// Determine requested role from URL or session
$requestedRole = strtolower(trim($_GET['role'] ?? ''));
$sessionRole = $_SESSION['role'] ?? null;

// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: pages/index.html');
    exit;
}

// If URL specifies a role different from session, deny access
if ($requestedRole && $requestedRole !== $sessionRole) {
    http_response_code(403);
    echo "Access Denied. You don't have permission to access this dashboard.";
    exit;
}

// Use session role
$userRole = $sessionRole;
$userId = $_SESSION['user_id'];

// Map roles to their dashboard files
$dashboardMap = [
    'admin' => 'admin/dashboard.html',
    'vendor' => 'vendor/vendorpanel.html',
    'customer' => 'customer/dashboard.html'
];

// Get the appropriate dashboard file
$dashboardFile = $dashboardMap[$userRole] ?? null;

if (!$dashboardFile || !file_exists($dashboardFile)) {
    http_response_code(404);
    echo "Dashboard not found for role: $userRole";
    exit;
}

// Serve the dashboard HTML
include $dashboardFile;
?>
