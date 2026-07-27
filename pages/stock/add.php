<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

$db     = getDB();
$user   = currentUser();
$errors = [];

$businesses = $db->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();

$editId   = isset($_GET['edit']) && is_numeric($_GET['edit']) ? (int)$_GET['edit'] : null;
$editItem = null;
if ($editId) {
    $s = $db->prepare("SELECT * FROM stock WHERE id = ?");
    $s->execute([$editId]);
    $editItem = $s->fetch();
    if (!$editItem) {
        header('Location: /sales-system/pages/stock/index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_id  = (int)($_POST['business_id']  ?? 0);
    $product_name = trim($_POST['product_name']  ?? '');
    $quantity     = (int)($_POST['quantity']      ?? 0);
    $unit_price   = (float)($_POST['unit_price']  ?? 0);

    if (!$business_id)        $errors[] = 'Please select a business.';
    if ($product_name === '')  $errors[] = 'Product name is required.';
    if ($quantity < 0)         $errors[] = 'Quantity cannot be negative.';
    if ($unit_price < 0)       $errors[] = 'Unit price cannot be negative.';

    if (empty($errors)) {
        if ($editId) {
            $db->prepare(
                "UPDATE stock SET business_id=?, product_name=?, quantity=?, unit_price=? WHERE id=?"
            )->execute([$business_id, $product_name, $quantity, $unit_price, $editId]);
        } else {
            $db->prepare(
                "INSERT INTO stock (business_id, product_name, quantity, unit_price, added_by)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$business_id, $product_name, $quantity, $unit_price, $user['id']]);
        }
        header('Location: /sales-system/pages/stock/index.php?saved=1');
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><?= $editId ? 'Edit Stock' : 'Add Stock' ?></h2>
        <p class="text-muted mb-0"><?= $editId ? 'Update stock details.' : 'Add new stock for a business.' ?></p>
    </div>
    <a href="/sales-system/pages/stock/index.php" class="btn btn-outline-secondary">← Back</a>
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
                <label class="form-label fw-semibold">Business <span class="text-danger">*</span></label>
                <select name="business_id" class="form-select" required>
                    <option value="">— Select Business —</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"
                            <?= ($editItem['business_id'] ?? $_POST['business_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                <input type="text" name="product_name" class="form-control"
                       value="<?= htmlspecialchars($editItem['product_name'] ?? $_POST['product_name'] ?? '') ?>"
                       required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" class="form-control" min="0"
                           value="<?= htmlspecialchars($editItem['quantity'] ?? $_POST['quantity'] ?? '0') ?>"
                           required>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Unit Price (KES) <span class="text-danger">*</span></label>
                    <input type="number" name="unit_price" class="form-control" min="0" step="0.01"
                           value="<?= htmlspecialchars($editItem['unit_price'] ?? $_POST['unit_price'] ?? '0.00') ?>"
                           required>
                </div>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <?= $editId ? 'Update Stock' : 'Add Stock' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>