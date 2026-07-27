<?php
require_once __DIR__ . '/../includes/auth.php';

logoutUser();

header('Location: /sales-system/auth/login.php');
exit;
