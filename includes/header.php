<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
requireLogin();
$currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 240px;
        }
        body {
            overflow-x: hidden;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: #1a1f2e;
            z-index: 1040;
            overflow-y: auto;
            transition: margin-left .25s ease;
        }
        .sidebar-brand {
            padding: 1.25rem 1.25rem;
            font-weight: 600;
            font-size: 1.15rem;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-nav {
            padding: .75rem .75rem;
        }
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,.6);
            padding: .6rem .9rem;
            border-radius: .4rem;
            margin-bottom: .15rem;
            font-size: .95rem;
        }
        .sidebar-nav .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,.06);
        }
        .sidebar-nav .nav-link.active {
            color: #fff;
            background: #2563eb;
        }
        .sidebar-user {
            position: sticky;
            bottom: 0;
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,.08);
            background: #1a1f2e;
        }
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left .25s ease;
        }
        .topbar {
            display: none;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: .75rem 1rem;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            z-index: 1035;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            .sidebar.show {
                margin-left: 0;
            }
            .main-wrapper {
                margin-left: 0;
            }
            .topbar {
                display: flex;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }

        /* ===== Business Card Colour Variants ===== */
        .biz-tile {
            border-width: 1px !important;
            border-style: solid !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .biz-tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.1);
        }

        .biz-amber  { background: linear-gradient(180deg,#fff8ec 0%,#ffffff 100%); border-color:#f5c98a !important; }
        .biz-blue   { background: linear-gradient(180deg,#eefaff 0%,#ffffff 100%); border-color:#93d9ee !important; }
        .biz-pink   { background: linear-gradient(180deg,#fdf2f8 0%,#ffffff 100%); border-color:#f3aed1 !important; }
        .biz-green  { background: linear-gradient(180deg,#f0fdf4 0%,#ffffff 100%); border-color:#a7e3b8 !important; }
        .biz-purple { background: linear-gradient(180deg,#f5f3ff 0%,#ffffff 100%); border-color:#c9b8f5 !important; }
        .biz-teal   { background: linear-gradient(180deg,#effcfa 0%,#ffffff 100%); border-color:#94dfd1 !important; }

        .biz-amber  .biz-tile-icon { color:#d97706; }
        .biz-blue   .biz-tile-icon { color:#0891b2; }
        .biz-pink   .biz-tile-icon { color:#c026d3; }
        .biz-green  .biz-tile-icon { color:#16a34a; }
        .biz-purple .biz-tile-icon { color:#7c3aed; }
        .biz-teal   .biz-tile-icon { color:#0d9488; }

        .biz-amber  .btn-biz { background:#d97706; border-color:#d97706; color:#fff; }
        .biz-blue   .btn-biz { background:#0891b2; border-color:#0891b2; color:#fff; }
        .biz-pink   .btn-biz { background:#c026d3; border-color:#c026d3; color:#fff; }
        .biz-green  .btn-biz { background:#16a34a; border-color:#16a34a; color:#fff; }
        .biz-purple .btn-biz { background:#7c3aed; border-color:#7c3aed; color:#fff; }
        .biz-teal   .btn-biz { background:#0d9488; border-color:#0d9488; color:#fff; }

        .biz-amber  .btn-biz:hover { background:#b45309; border-color:#b45309; }
        .biz-blue   .btn-biz:hover { background:#0e7490; border-color:#0e7490; }
        .biz-pink   .btn-biz:hover { background:#a21caf; border-color:#a21caf; }
        .biz-green  .btn-biz:hover { background:#15803d; border-color:#15803d; }
        .biz-purple .btn-biz:hover { background:#6d28d9; border-color:#6d28d9; }
        .biz-teal   .btn-biz:hover { background:#0f766e; border-color:#0f766e; }

        .biz-amber  .btn-biz, .biz-blue .btn-biz, .biz-pink .btn-biz,
        .biz-green  .btn-biz, .biz-purple .btn-biz, .biz-teal .btn-biz {
            border-radius: 0.6rem;
        }

        /* ===== Business Performance progress bar & rank badge colours ===== */
        .progress-amber  { background-color: #d97706 !important; }
        .progress-blue   { background-color: #0891b2 !important; }
        .progress-pink   { background-color: #c026d3 !important; }
        .progress-green  { background-color: #16a34a !important; }
        .progress-purple { background-color: #7c3aed !important; }
        .progress-teal   { background-color: #0d9488 !important; }

        .rank-amber  { background-color: #d97706 !important; }
        .rank-blue   { background-color: #0891b2 !important; }
        .rank-pink   { background-color: #c026d3 !important; }
        .rank-green  { background-color: #16a34a !important; }
        .rank-purple { background-color: #7c3aed !important; }
        .rank-teal   { background-color: #0d9488 !important; }
    </style>
</head>
<body class="bg-light">

<?php
// helper to mark active link
function navActive($needle) {
    return str_contains($_SERVER['REQUEST_URI'], $needle) ? 'active' : '';
}
?>

<!-- SIDEBAR -->
<nav class="sidebar d-flex flex-column" id="sidebar">
    <div class="sidebar-brand">Sales System</div>

    <div class="sidebar-nav flex-grow-1">
        <a class="nav-link <?= navActive('dashboard') ?>" href="/sales-system/pages/dashboard.php">
            Dashboard
        </a>
        <a class="nav-link <?= navActive('orders') ?>" href="/sales-system/pages/orders/index.php">
            Orders
        </a>
        <a class="nav-link <?= navActive('report') ?>" href="/sales-system/pages/orders/report.php">
            Reports
        </a>

        <?php if ($currentUser['role'] === 'admin'): ?>
        <a class="nav-link <?= navActive('stock') ?>" href="/sales-system/pages/stock/index.php">
            Stock
        </a>
        <a class="nav-link <?= navActive('users') ?>" href="/sales-system/pages/users/index.php">
            Users
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-user">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-white-50 small text-truncate">
                <?= htmlspecialchars($currentUser['full_name']) ?>
            </span>
            <span class="badge bg-secondary">
                <?= htmlspecialchars($currentUser['role']) ?>
            </span>
        </div>
        <a href="/sales-system/auth/logout.php" class="btn btn-outline-light btn-sm w-100">Logout</a>
    </div>
</nav>

<!-- OVERLAY (mobile only) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN WRAPPER -->
<div class="main-wrapper" id="mainWrapper">

    <!-- TOPBAR (mobile only, holds the toggle button) -->
    <div class="topbar">
        <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle" type="button">
            &#9776;
        </button>
        <span class="fw-semibold">Sales System</span>
        <span></span>
    </div>

    <!-- PAGE WRAPPER -->
    <div class="container-fluid px-4 py-4">