<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$user       = currentUser();
$db         = getDB();
$businesses = $db->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<h4 class="mb-4 fw-semibold">Sales Report</h4>

<div class="row g-2 align-items-end mb-4">
    <div class="col-auto">
        <label class="form-label small text-muted mb-1">From</label>
        <input type="date" id="fromDate" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small text-muted mb-1">To</label>
        <input type="date" id="toDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
    </div>
    <?php if ($user['role'] === 'admin'): ?>
    <div class="col-auto">
        <label class="form-label small text-muted mb-1">Business</label>
        <select id="bizFilter" class="form-select form-select-sm" style="min-width:180px">
            <option value="all">All Businesses</option>
            <?php foreach ($businesses as $b): ?>
                <option value="<?= htmlspecialchars($b['id']) ?>">
                    <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php else: ?>
        <input type="hidden" id="bizFilter" value="all">
    <?php endif; ?>
    <div class="col-auto">
        <button class="btn btn-primary btn-sm px-4" onclick="loadReport()">Filter</button>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-secondary btn-sm px-3" onclick="exportPDF()">⬇ Export PDF</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Total Orders</div>
            <div class="fs-4 fw-semibold" id="mOrders">—</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Total Revenue</div>
            <div class="fs-5 fw-semibold" id="mRevenue">—</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Completed Revenue</div>
            <div class="fs-5 fw-semibold text-success" id="mCompleted">—</div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Pending / Cancelled</div>
            <div class="fs-5 fw-semibold">
                <span class="text-secondary" id="mPending">—</span> /
                <span class="text-danger"    id="mCancelled">—</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <div class="card border-0 bg-light text-center py-3">
            <div class="text-muted small">Active Businesses</div>
            <div class="fs-4 fw-semibold" id="mBiz">—</div>
        </div>
    </div>
</div>

<?php if ($user['role'] === 'admin'): ?>
<div class="card border shadow-none">
    <div class="card-body">
        <h6 class="fw-semibold mb-3">Sales by Business</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Business</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Total Revenue</th>
                        <th class="text-end">Completed</th>
                        <th class="text-end">Pending</th>
                        <th class="text-end">Cancelled</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody id="bizTableBody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="errorBox" class="alert alert-danger mt-3" style="display:none;"></div>

<script>
    const IS_ADMIN = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;
</script>
<script src="/auth/js/reports.js"></script>
<script>
    loadReport();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>