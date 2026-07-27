<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin']);

$db = getDB();

// Validate business id
$bizId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$bizId) {
    header('Location: /sales-system/pages/dashboard.php');
    exit;
}

// Get business info
$bizStmt = $db->prepare("SELECT * FROM businesses WHERE id = ? LIMIT 1");
$bizStmt->execute([$bizId]);
$business = $bizStmt->fetch();
if (!$business) {
    header('Location: /sales-system/pages/dashboard.php');
    exit;
}

// Date filters
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// KPI summary for this business
$kpiStmt = $db->prepare(
    "SELECT
        COUNT(*)                                                                      AS total_orders,
        COALESCE(SUM(total_amount), 0)                                                AS total_revenue,
        COALESCE(SUM(CASE WHEN status='completed' THEN total_amount ELSE 0 END), 0)   AS completed_revenue,
        SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END)                           AS pending_count,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END)                           AS cancelled_count,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END)                           AS completed_count
     FROM orders
     WHERE business_id = ?
       AND order_date BETWEEN ? AND ?"
);
$kpiStmt->execute([$bizId, $from, $to]);
$kpi = $kpiStmt->fetch();

// All orders for this business in date range
$ordersStmt = $db->prepare(
    "SELECT o.*, COALESCE(o.created_by_name, u.full_name, '—') AS created_by_display
     FROM orders o
     LEFT JOIN users u ON u.id = o.created_by
     WHERE o.business_id = ?
       AND o.order_date BETWEEN ? AND ?
     ORDER BY o.order_date DESC, o.created_at DESC"
);
$ordersStmt->execute([$bizId, $from, $to]);
$orders = $ordersStmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<!-- PAGE HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/sales-system/pages/dashboard.php"
           class="text-muted small text-decoration-none">← Back to Dashboard</a>
        <h2 class="mb-0 mt-1">🏢 <?= htmlspecialchars($business['name']) ?></h2>
        <p class="text-muted mb-0">Sales report and orders for this business.</p>
    </div>
    <button class="btn btn-outline-secondary btn-sm" onclick="exportPDF()">
        ⬇ Export PDF
    </button>
</div>

<!-- DATE FILTERS -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="id" value="<?= $bizId ?>">
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($to) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm px-4">Filter</button>
            </div>
            <div class="col-auto">
                <a href="?id=<?= $bizId ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Total Orders</div>
            <div class="fs-4 fw-bold"><?= (int)$kpi['total_orders'] ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Total Revenue</div>
            <div class="fs-5 fw-bold">KES <?= number_format((float)$kpi['total_revenue'], 2) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Completed Revenue</div>
            <div class="fs-5 fw-bold text-success">
                KES <?= number_format((float)$kpi['completed_revenue'], 2) ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Completed</div>
            <div class="fs-4 fw-bold text-success"><?= (int)$kpi['completed_count'] ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Pending</div>
            <div class="fs-4 fw-bold text-secondary"><?= (int)$kpi['pending_count'] ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Cancelled</div>
            <div class="fs-4 fw-bold text-danger"><?= (int)$kpi['cancelled_count'] ?></div>
        </div>
    </div>
</div>

<!-- ORDERS TABLE -->
<div class="card shadow-sm" id="ordersCard">
    <div class="card-body">
        <h5 class="fw-semibold mb-3">
            Orders
            <span class="badge bg-primary ms-2"><?= count($orders) ?></span>
        </h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="ordersTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No orders found for this date range.
                            </td>
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
                        <td class="text-end"><?= (int)($o['quantity'] ?? 1) ?></td>
                        <td class="text-end">KES <?= number_format((float)($o['unit_price'] ?? 0), 2) ?></td>
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
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($orders)): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="fw-semibold">Total</td>
                        <td class="text-end fw-bold text-success">
                            KES <?= number_format((float)$kpi['total_revenue'], 2) ?>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- PDF EXPORT SCRIPT -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script>
function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('Business Sales Report', 14,