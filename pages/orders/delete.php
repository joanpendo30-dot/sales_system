<?php
/**
 * Deletes an order by id.
 * Triggered from a confirm() dialog on index.php; for a production system
 * consider converting this to a POST-only action with its own CSRF token.
 */
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = getDB()->prepare('DELETE FROM orders WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: index.php');
exit;
