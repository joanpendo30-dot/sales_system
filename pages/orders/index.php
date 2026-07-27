<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/stock_helpers.php';
requireLogin();

$db   = getDB();
$user = currentUser();

// Handle delete — admin only
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    requireRole(['admin']);

    $delId = (int)$_GET['delete'];

    // Restore stock before deleting, if the order wasn't already cancelled
    $delStmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $delStmt->execute([$delId]);
    $delOrder = $delStmt->fetch();

    if ($delOrder && $delOrder['status'] !== 'cancelled') {
        adjustStockSold($db, (int)$delOrder['business_id'], $delOrder['product_name'], -(int)$delOrder['quantity']);
    }

    $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$delId]);
    header('Location: /pages/orders/index.php?deleted=1');
    exit;
}

// Business filter (admin can pick any business)
$bizId = isset($_GET['biz']) && is_numeric($_GET['biz']) ? (int)$_GET['biz'] : 0;

$params = [];
$where  = [];

if ($user['role'] === 'admin') {
    if ($bizId) {
        $where[]  = 'o.business_id = ?';
        $params[] = $bizId;
    }
} else {
    if ($user['business_id']) {
        $where[]  = 'o.business_id = ?';
        $params[] = $user['business_id'];
    } else {
        $where[] = '1 = 0';
    }
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT o.*, b.name AS business_name,
            COALESCE(o.created_by_name, u.full_name, '—') AS created_by_display
     FROM orders o
     LEFT JOIN businesses b ON b.id = o.business_id
     LEFT JOIN users u      ON u.id = o.created_by
     $whereSQL
     ORDER BY o.created_at DESC"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Businesses dropdown — admin only
$businesses = [];
if ($user['role'] === 'admin') {
    $businesses = $db->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Orders</h2>
        <p class="text-muted mb-0">
            <?= $user['role'] === 'admin'
                ? 'All orders' . ($bizId ? ' for selected business' : '') . '.'
                : 'Orders for your business.' ?>
        </p>
    </div>
    <a href="/pages/orders/create.php" class="btn btn-primary">+ New Order</a>
</div>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Order deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Order created successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Order updated successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- BUSINESS FILTER — admin only -->
<?php if ($user['role'] === 'admin'): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">Filter by Business</label>
                <select name="biz" class="form-select form-select-sm" style="min-width:200px">
                    <option value="">All Businesses</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"
                            <?= $bizId === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm px-4">Filter</button>
            </div>
            <?php if ($bizId): ?>
            <div class="col-auto">
                <a href="/pages/orders/index.php"
                   class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Business</th>
                        <th class="text-end">Total</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>By</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $i => $o): ?>
                    <?php
                    $badge = match($o['status']) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default     => 'secondary',
                    };
                    ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($o['customer_name']) ?></strong></td>
                        <td><?= htmlspecialchars($o['product_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($o['business_name'] ?? '—') ?></td>
                        <td class="text-end fw-semibold">
                            KES <?= number_format((float)$o['total_amount'], 2) ?>
                        </td>
                        <td><?= htmlspecialchars($o['order_date']) ?></td>
                        <td>
                            <span class="badge bg-<?= $badge ?>">
                                <?= htmlspecialchars($o['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($o['created_by_display']) ?></td>
                        <td class="text-center">
                            <a href="/pages/orders/edit.php?id=<?= (int)$o['id'] ?>"
                               class="btn btn-sm btn-outline-primary">Edit</a>

                            <?php if ($user['role'] === 'admin'): ?>
                            <a href="?delete=<?= (int)$o['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this order? This cannot be undone.')">
                               Delete
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>