<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

$db     = getDB();
$me     = currentUser();
$errors = [];

// Validate user id
$userId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$userId) {
    header('Location: /sales-system/pages/users/index.php');
    exit;
}

// Fetch user to edit
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$editUser = $stmt->fetch();
if (!$editUser) {
    header('Location: /sales-system/pages/users/index.php');
    exit;
}

// Businesses dropdown
$businesses = $db->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name']   ?? '');
    $username    = trim($_POST['username']    ?? '');
    $email       = trim($_POST['email']       ?? '');
    $password    = $_POST['password']         ?? '';
    $role        = $_POST['role']             ?? 'user';
    $business_id = (int)($_POST['business_id'] ?? 0);
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($username === '')  $errors[] = 'Username is required.';
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    // Check username unique (excluding current user)
    $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->execute([$username, $userId]);
    if ($check->fetch()) $errors[] = 'Username already taken by another user.';

    // Prevent admin removing their own admin role/active status accidentally
    if ($userId === (int)$me['id'] && $role !== 'admin') {
        $errors[] = 'You cannot remove your own admin role.';
    }
    if ($userId === (int)$me['id'] && !$is_active) {
        $errors[] = 'You cannot deactivate your own account.';
    }

    if (empty($errors)) {
        if ($password !== '') {
            // Update with new password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare(
                "UPDATE users
                 SET full_name=?, username=?, email=?, password_hash=?, role=?, business_id=?, is_active=?
                 WHERE id=?"
            )->execute([
                $full_name, $username, $email, $hash,
                $role, $business_id ?: null, $is_active, $userId
            ]);
        } else {
            // Update without changing password
            $db->prepare(
                "UPDATE users
                 SET full_name=?, username=?, email=?, role=?, business_id=?, is_active=?
                 WHERE id=?"
            )->execute([
                $full_name, $username, $email,
                $role, $business_id ?: null, $is_active, $userId
            ]);
        }
        header('Location: /sales-system/pages/users/index.php?saved=1');
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Edit User</h2>
        <p class="text-muted mb-0">Update user details and business assignment.</p>
    </div>
    <a href="/sales-system/pages/users/index.php" class="btn btn-outline-secondary">← Back</a>
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
                       value="<?= htmlspecialchars($_POST['full_name'] ?? $editUser['full_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control"
                       value="<?= htmlspecialchars($_POST['username'] ?? $editUser['username']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? $editUser['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Leave blank to keep current password">
                <div class="form-text">Only fill this in if you want to change the password.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Business</label>
                <select name="business_id" class="form-select">
                    <option value="">— No Business —</option>
                    <?php foreach ($businesses as $b): ?>
                        <?php
                        $selected = isset($_POST['business_id'])
                            ? $_POST['business_id'] == $b['id']
                            : $editUser['business_id'] == $b['id'];
                        ?>
                        <option value="<?= (int)$b['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Assign this user to the business they work in.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                <select name="role" class="form-select">
                    <?php $roleVal = $_POST['role'] ?? $editUser['role']; ?>
                    <option value="user"  <?= $roleVal === 'user'  ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $roleVal === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <?php if ($userId === (int)$me['id']): ?>
                    <div class="form-text text-warning">⚠ You are editing your own account.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3 form-check">
                <?php $activeVal = isset($_POST['is_active']) || (!isset($_POST['full_name']) && $editUser['is_active']); ?>
                <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                       <?= $activeVal ? 'checked' : '' ?>>
                <label class="form-check-label" for="isActive">Active</label>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Update User</button>
            </div>

        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>