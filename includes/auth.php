<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CSRF ─────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// ── AUTH ──────────────────────────────────────────────────
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'] ?? 'user';
}

function logoutUser(): void {
    session_unset();
    session_destroy();
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /sales-system/auth/login.php');
        exit;
    }
}                           // ← this closing brace was missing

function currentUser(): array {
    if (!isLoggedIn()) {
        return ['full_name' => 'Guest', 'id' => null, 'role' => null];
    }
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: ['full_name' => 'Guest', 'id' => null, 'role' => null];
    }
    return $user;
}

function requireRole(array $roles): void {
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'], $roles)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;text-align:center;padding:60px">
                <h2>Access Denied</h2>
                <p>You do not have permission to view this page.</p>
                <a href="/sales-system/pages/dashboard.php">Go to Dashboard</a>
             </div>');
    }
}