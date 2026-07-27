<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

$db = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $me       = currentUser();
    if ($deleteId === (int)$me['id']) {
        header('Location: /pages/users/index.php?error=self');
        exit;
    }
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$deleteId]);
    header('Location: /pages/users/index.php?deleted=1');
    exit;
}

// Businesses for filter dropdown
$businesses = $db->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();

// Selected filter (0 / empty = all businesses)
$filterBusinessId = isset($_GET['business_id']) && is_numeric($_GET['business_id'])
    ? (int)$_GET['business_id']
    : 0;

if ($filterBusinessId) {
    $stmt = $db->prepare(
        "SELECT u.*, b.name AS business_name
         FROM users u
         LEFT JOIN businesses b ON b.id = u.business_id
         WHERE u.business_id = ?
         ORDER BY u.full_name"
    );
    $stmt->execute([$filterBusinessId]);
    $users = $stmt->fetchAll();
} else {
    $users = $db->query(
        "SELECT u.*, b.name AS business_name
         FROM users u
         LEFT JOIN businesses b ON b.id = u.business_id
         ORDER BY u.full_name"
    )->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Users</h2>
    <a href="/pages/users/create.php" class="btn btn-primary">+ New User</a>
</div>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        User deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        User saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'self'): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        You cannot delete your own account.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- BUSINESS FILTER -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <label class="form-label fw-semibold mb-0 me-2">Filter by Business:</label>
            <select name="business_id" class="form-select form-select-sm" style="max-width:250px;"
                    onchange="this.form.submit()">
                <option value="0" <?= $filterBusinessId === 0 ? 'selected' : '' ?>>All Businesses</option>
                <?php foreach ($businesses as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"
                        <?= $filterBusinessId === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterBusinessId): ?>
                <a href="/pages/users/index.php" class="btn btn-sm btn-outline-secondary">
                    Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-responsive bg-white rounded shadow-sm">
    <table class="table table-striped align-middle mb-0">
        <thead>
            <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Business</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                <td><?= htmlspecialchars($u['business_name'] ?? '—') ?></td>
                <td>
                    <span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : 'secondary' ?>">
                        <?= htmlspecialchars($u['role']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Inactive</span>
                    <?php endif; ?>
                </td>
                <td><?= $u['last_login'] ? htmlspecialchars($u['last_login']) : '—' ?></td>
                <td>
                    <a href="/pages/users/edit.php?id=<?= (int)$u['id'] ?>"
                       class="btn btn-sm btn-outline-primary">Edit</a>
                    <a href="?delete=<?= (int)$u['id'] ?>&business_id=<?= $filterBusinessId ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete user <?= htmlspecialchars(addslashes($u['full_name'])) ?>?')">
                       Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>s