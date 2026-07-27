<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: /sales-system/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            $stmt = getDB()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                getDB()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
                header('Location: /sales-system/index.php');
                exit;
            }

            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sales System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/sales-system/index.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center login-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-5 col-lg-4">
            <div class="card shadow-sm auth-card">
                <div class="card-body p-4">
                    <h3 class="mb-3 text-center">Sales System</h3>
                    <p class="text-muted text-center mb-4">Sign in to continue</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autofocus
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log In</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
