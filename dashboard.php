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

// If URL specifies a role different from session, handle the mismatch gracefully
if ($requestedRole && $requestedRole !== $sessionRole) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Session Conflict - BizLink CRM</title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary: #50C878;
                --bg: #0a0c10;
                --card: #161b22;
                --text: #f0f6fc;
                --text-dim: #8b949e;
            }
            body {
                background: var(--bg);
                color: var(--text);
                font-family: 'DM Sans', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
                padding: 20px;
                text-align: center;
            }
            .card {
                background: var(--card);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 50px rgba(0,0,0,0.5);
                max-width: 450px;
                width: 100%;
                border: 1px solid rgba(255,255,255,0.1);
            }
            h1 { font-size: 24px; margin-bottom: 15px; color: #ffab70; }
            p { color: var(--text-dim); line-height: 1.6; margin-bottom: 30px; }
            .badge {
                background: rgba(80, 200, 120, 0.1);
                color: var(--primary);
                padding: 4px 12px;
                border-radius: 12px;
                font-weight: 600;
                text-transform: capitalize;
            }
            .btn-group { display: flex; flex-direction: column; gap: 12px; }
            .btn {
                padding: 14px;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                transition: 0.3s;
                border: none;
                cursor: pointer;
            }
            .btn-primary {
                background: var(--primary);
                color: white;
            }
            .btn-secondary {
                background: rgba(255,255,255,0.05);
                color: var(--text);
                border: 1px solid rgba(255,255,255,0.1);
            }
            .btn:hover { transform: translateY(-2px); opacity: 0.9; }
        </style>
    </head>
    <body>
        <div class="card">
            <div style="font-size: 50px; margin-bottom: 20px;">🔄</div>
            <h1>Session Conflict</h1>
            <p>
                You are currently logged in as a <span class="badge"><?php echo htmlspecialchars($sessionRole); ?></span>.<br>
                This tab was intended for the <span class="badge"><?php echo htmlspecialchars($requestedRole); ?></span> dashboard.
            </p>
            <div class="btn-group">
                <a href="dashboard.php?role=<?php echo $sessionRole; ?>" class="btn btn-primary">
                    Go to <?php echo ucfirst($sessionRole); ?> Dashboard
                </a>
                <a href="api/auth_logout.php" class="btn btn-secondary">
                    Sign out to switch account
                </a>
                <button onclick="window.location.reload()" class="btn btn-secondary">
                    Refresh Page
                </button>
            </div>
        </div>
    </body>
    </html>
    <?php
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
