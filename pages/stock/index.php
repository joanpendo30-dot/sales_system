<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

$db = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM stock WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: /pages/stock/index.php?deleted=1');
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
        "SELECT s.*, b.name AS business_name, u.full_name AS added_by_name
         FROM stock s
         LEFT JOIN businesses b ON b.id = s.business_id
         LEFT JOIN users u      ON u.id = s.added_by
         WHERE s.business_id = ?
         ORDER BY b.name, s.product_name"
    );
    $stmt->execute([$filterBusinessId]);
    $stocks = $stmt->fetchAll();
} else {
    $stocks = $db->query(
        "SELECT s.*, b.name AS business_name, u.full_name AS added_by_name
         FROM stock s
         LEFT JOIN businesses b ON b.id = s.business_id
         LEFT JOIN users u      ON u.id = s.added_by
         ORDER BY b.name, s.product_name"
    )->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Stock Management</h2>
        <p class="text-muted mb-0">Manage product stock for all businesses.</p>
    </div>
    <a href="/pages/stock/add.php" class="btn btn-primary">+ Add Stock</a>
</div>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Stock item deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        Stock saved successfully.
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
                <a href="/pages/stock/index.php" class="btn btn-sm btn-outline-secondary">
                    Clear Filter
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Business</th>
                        <th>Product</th>
                        <th class="text-end">Added</th>
                        <th class="text-end">Sold</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total Value</th>
                        <th>Added By</th>
                        <th>Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stocks)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">No stock found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php
                    $grandTotalValue = 0;
                    foreach ($stocks as $i => $s):
                        $added     = (int)$s['quantity'];
                        $sold      = (int)$s['quantity_sold'];
                        $remaining = max(0, $added - $sold);
                        $lineValue = $remaining * (float)$s['unit_price'];
                        $grandTotalValue += $lineValue;
                    ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($s['business_name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['product_name']) ?></td>
                        <td class="text-end"><?= $added ?></td>
                        <td class="text-end">
                            <?php if ($sold > 0): ?>
                                <span class="badge bg-secondary">-<?= $sold ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($remaining <= 5): ?>
                                <span class="badge bg-danger"><?= $remaining ?></span>
                            <?php elseif ($remaining <= 20): ?>
                                <span class="badge bg-warning text-dark"><?= $remaining ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $remaining ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">KES <?= number_format((float)$s['unit_price'], 2) ?></td>
                        <td class="text-end fw-semibold">
                            KES <?= number_format($lineValue, 2) ?>
                        </td>
                        <td><?= htmlspecialchars($s['added_by_name'] ?? '—') ?></td>
                        <td><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                        <td class="text-center">
                            <a href="/pages/stock/add.php?edit=<?= (int)$s['id'] ?>"
                               class="btn btn-sm btn-outline-primary">Edit</a>
                            <a href="?delete=<?= (int)$s['id'] ?>&business_id=<?= $filterBusinessId ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this stock item?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($stocks)): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="7" class="fw-semibold">Total Stock Value (Remaining)</td>
                        <td class="text-end fw-bold text-success">
                            KES <?= number_format($grandTotalValue, 2) ?>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>