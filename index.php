<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

header('Location: /sales-system/pages/dashboard.php');
exit;
