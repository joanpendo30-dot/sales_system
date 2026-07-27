<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/stock_helpers.php';
requireLogin();

$db     = getDB();
$user   = currentUser();
$errors = [];

$orderId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$orderId) {
    header('Location: /pages/orders/index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    header('Location: /pages/orders/index.php');
    exit;
}

// Restrict regular users to only edit orders from their own business
if ($user['role'] !== 'admin' && (int)$order['business_id'] !== (int)$user['business_id']) {
    header('Location: /pages/orders/index.php');
    exit;
}

// Businesses dropdown
if ($user['role'] === 'admin') {
    $businesses = $db->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();
} else {
    $businesses = [];
    if ($user['business_id']) {
        $bstmt = $db->prepare("SELECT id, name FROM businesses WHERE id = ?");
        $bstmt->execute([$user['business_id']]);
        $businesses = $bstmt->fetchAll();
    }
}

// All stock rows, for the JS product picker
$allStockJs = $db->query(
    "SELECT id, business_id, product_name, unit_price, quantity, quantity_sold FROM stock"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $product_name  = trim($_POST['product_name']  ?? '');
    $quantity      = (int)($_POST['quantity']      ?? 1);
    $unit_price    = (float)($_POST['unit_price']  ?? 0);
    $order_date    = $_POST['order_date']          ?? date('Y-m-d');
    $status        = $_POST['status']              ?? 'pending';
    $business_id   = (int)($_POST['business_id']   ?? 0);

    if ($user['role'] !== 'admin') {
        $business_id = (int)($user['business_id'] ?? 0);
    }

    $total_amount = $quantity * $unit_price;

    if ($customer_name === '') $errors[] = 'Customer name is required.';
    if ($product_name === '')  $errors[] = 'Product name is required.';
    if ($quantity <= 0)        $errors[] = 'Quantity must be at least 1.';
    if ($unit_price < 0)       $errors[] = 'Unit price cannot be negative.';
    if (!$business_id)         $errors[] = 'Business is required.';
    if (!in_array($status, ['pending', 'completed', 'cancelled'])) {
        $errors[] = 'Invalid status.';
    }

    if (empty($errors)) {
        // Figure out old vs new stock contribution BEFORE overwriting the order
        $oldContribution = ($order['status'] !== 'cancelled') ? (int)$order['quantity'] : 0;
        $newContribution = ($status !== 'cancelled') ? $quantity : 0;

        $db->prepare(
            "UPDATE orders
             SET customer_name=?, product_name=?, quantity=?, unit_price=?, total_amount=?,
                 order_date=?, status=?, business_id=?
             WHERE id=?"
        )->execute([
            $customer_name, $product_name, $quantity, $unit_price, $total_amount,
            $order_date, $status, $business_id, $orderId
        ]);

        // Restore the old contribution from the OLD business/product...
        if ($oldContribution > 0) {
            adjustStockSold($db, (int)$order['business_id'], $order['product_name'], -$oldContribution);
        }
        // ...then apply the new contribution to the NEW business/product.
        if ($newContribution > 0) {
            adjustStockSold($db, $business_id, $product_name, $newContribution);
        }

        header('Location: /pages/orders/index.php?updated=1');
        exit;
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">Edit Order</h2>
        <p class="text-muted mb-0">Update order #<?= (int)$order['id'] ?>.</p>
    </div>
    <a href="/pages/orders/index.php" class="btn btn-outline-secondary">← Back</a>
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

<div class="card shadow-sm" style="max-width:650px;">
    <div class="card-body p-4">
        <form method="POST">

            <div class="mb-3">
                <label class="form-label fw-semibold">Business <span class="text-danger">*</span></label>
                <?php if ($user['role'] === 'admin'): ?>
                    <select name="business_id" class="form-select" required>
                        <option value="">— Select Business —</option>
                        <?php foreach ($businesses as $b): ?>
                            <?php
                            $selected = isset($_POST['business_id'])
                                ? $_POST['business_id'] == $b['id']
                                : $order['business_id'] == $b['id'];
                            ?>
                            <option value="<?= (int)$b['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" class="form-control"
                           value="<?= htmlspecialchars($businesses[0]['name'] ?? '—') ?>" disabled>
                    <input type="hidden" name="business_id" value="<?= (int)($businesses[0]['id'] ?? 0) ?>">
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Customer Name <span class="text-danger">*</span></label>
                <input type="text" name="customer_name" class="form-control"
                       value="<?= htmlspecialchars($_POST['customer_name'] ?? $order['customer_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                <select id="productPicker" class="form-select mb-2" onchange="pickProduct(this)">
                    <option value="">— Pick from stock (optional) —</option>
                </select>
                <input type="text" name="product_name" id="product_name" class="form-control"
                       value="<?= htmlspecialchars($_POST['product_name'] ?? $order['product_name']) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-4">
                    <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" class="form-control" min="1"
                           value="<?= htmlspecialchars($_POST['quantity'] ?? $order['quantity']) ?>" required>
                </div>
                <div class="col-4">
                    <label class="form-label fw-semibold">Unit Price <span class="text-danger">*</span></label>
                    <input type="number" name="unit_price" class="form-control" min="0" step="0.01"
                           value="<?= htmlspecialchars($_POST['unit_price'] ?? $order['unit_price']) ?>" required>
                </div>
                <div class="col-4">
                    <label class="form-label fw-semibold">Order Date <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control"
                           value="<?= htmlspecialchars($_POST['order_date'] ?? $order['order_date']) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select">
                    <?php $statusVal = $_POST['status'] ?? $order['status']; ?>
                    <option value="pending"   <?= $statusVal === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="completed" <?= $statusVal === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $statusVal === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Update Order</button>
            </div>

        </form>
    </div>
</div>

<script>
const allStock = <?= json_encode($allStockJs) ?>;

function refreshProductPicker() {
    const bizField = document.querySelector('[name="business_id"]');
    const bizId = bizField ? parseInt(bizField.value || '0', 10) : 0;
    const picker = document.getElementById('productPicker');
    picker.innerHTML = '<option value="">— Pick from stock (optional) —</option>';

    allStock
        .filter(s => parseInt(s.business_id, 10) === bizId)
        .forEach(s => {
            const remaining = Math.max(0, s.quantity - s.quantity_sold);
            const opt = document.createElement('option');
            opt.value = s.product_name;
            opt.dataset.price = s.unit_price;
            opt.textContent = s.product_name + ' (remaining: ' + remaining + ') — KES ' + parseFloat(s.unit_price).toFixed(2);
            picker.appendChild(opt);
        });
}

function pickProduct(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt.value) return;
    document.getElementById('product_name').value = opt.value;
    const priceField = document.querySelector('[name="unit_price"]');
    if (priceField && opt.dataset.price) {
        priceField.value = parseFloat(opt.dataset.price).toFixed(2);
    }
}

const bizFieldEl = document.querySelector('[name="business_id"]');
if (bizFieldEl && bizFieldEl.tagName === 'SELECT') {
    bizFieldEl.addEventListener('change', refreshProductPicker);
}
refreshProductPicker();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>