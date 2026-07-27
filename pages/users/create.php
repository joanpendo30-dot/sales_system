<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

$db         = getDB();
$errors     = [];
$businesses = $db->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name']   ?? '');
    $username    = trim($_POST['username']    ?? '');
    $email       = trim($_POST['email']       ?? '');
    $password    = $_POST['password']         ?? '';
    $role        = $_POST['role']             ?? 'user';
    $business_id = (int)($_POST['business_id'] ?? 0);
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if ($full_name === '')  $errors[] = 'Full name is required.';
    if ($username === '')   $errors[] = 'Username is required.';
    if ($password === '')   $errors[] = 'Password is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Check username unique
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) $errors[] = 'Username already exists.';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare(
            "INSERT INTO users (full_name, username, email, password_hash, role, business_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $full_name, $username, $email, $hash,
            $role, $business_id ?: null, $is_active
        ]);
        header('Location: /pages/users/index.php?saved=1');
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Create User</h2>
        <p class="text-muted mb-0">Add a new user and assign them to a business.</p>
    </div>
    <a href="/pages/users/index.php" class="btn btn-outline-secondary">← Back</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width:600px;">
    <div class="card-body p-4">
        <form method="POST">

            <div class="mb-3">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control"
                       placeholder="Minimum 6 characters" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Business</label>
                <select name="business_id" class="form-select">
                    <option value="">— No Business —</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"
                            <?= ($_POST['business_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                <select name="role" class="form-select">
                    <option value="user"  <?= ($_POST['role'] ?? '') === 'user'  ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                       <?= isset($_POST['is_active']) || !isset($_POST['full_name']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="isActive">Active</label>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Create User</button>
            </div>

        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>