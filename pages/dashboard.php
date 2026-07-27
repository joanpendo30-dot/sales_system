<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db   = getDB();
$user = currentUser();

// Determine business scoping: admin sees everything, others see only their business
$isAdmin    = $user['role'] === 'admin';
$businessId = $user['business_id'] ?? null;

// --- KPI summary ---
if ($isAdmin) {
    $kpi = $db->query(
        "SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_revenue,
            SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
         FROM orders"
    )->fetch();
} elseif ($businessId) {
    $kpiStmt = $db->prepare(
        "SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_revenue,
            SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
         FROM orders
         WHERE business_id = ?"
    );
    $kpiStmt->execute([$businessId]);
    $kpi = $kpiStmt->fetch();
} else {
    $kpi = [
        'total_orders'     => 0,
        'total_revenue'    => 0,
        'pending_orders'   => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
    ];
}

// --- Revenue this month ---
if ($isAdmin) {
    $monthRevenueStmt = $db->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) AS month_revenue
         FROM orders
         WHERE order_date >= DATE_TRUNC('month', CURRENT_DATE)"
    );
    $monthRevenueStmt->execute();
} elseif ($businessId) {
    $monthRevenueStmt = $db->prepare(
        "SELECT COALESCE(SUM(total_amount), 0) AS month_revenue
         FROM orders
         WHERE order_date >= DATE_TRUNC('month', CURRENT_DATE)
           AND business_id = ?"
    );
    $monthRevenueStmt->execute([$businessId]);
} else {
    $monthRevenueStmt = null;
}
$monthRevenue = $monthRevenueStmt ? (float)$monthRevenueStmt->fetch()['month_revenue'] : 0;

// --- Revenue trend last 14 days ---
if ($isAdmin) {
    $trendStmt = $db->prepare(
        "SELECT order_date, COALESCE(SUM(total_amount), 0) AS day_total
         FROM orders
         WHERE order_date >= CURRENT_DATE - INTERVAL '13 days'
         GROUP BY order_date
         ORDER BY order_date ASC"
    );
    $trendStmt->execute();
} elseif ($businessId) {
    $trendStmt = $db->prepare(
        "SELECT order_date, COALESCE(SUM(total_amount), 0) AS day_total
         FROM orders
         WHERE order_date >= CURRENT_DATE - INTERVAL '13 days'
           AND business_id = ?
         GROUP BY order_date
         ORDER BY order_date ASC"
    );
    $trendStmt->execute([$businessId]);
} else {
    $trendStmt = null;
}
$trendRows = $trendStmt ? $trendStmt->fetchAll() : [];

$trendMap = [];
foreach ($trendRows as $row) {
    $trendMap[$row['order_date']] = (float)$row['day_total'];
}
$labels = [];
$values = [];
for ($i = 13; $i >= 0; $i--) {
    $d        = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('M j', strtotime($d));
    $values[] = $trendMap[$d] ?? 0;
}

// --- Business cards (cards shown at top) ---
if ($isAdmin) {
    $bizStmt = $db->query(
        "SELECT
            b.id,
            b.name,
            COUNT(o.id)                      AS total_orders,
            COALESCE(SUM(o.total_amount), 0) AS total_revenue
         FROM businesses b
         LEFT JOIN orders o ON o.business_id = b.id
         GROUP BY b.id, b.name
         ORDER BY total_revenue DESC"
    );
    $businesses = $bizStmt->fetchAll();
} else {
    if ($businessId) {
        $bizStmt = $db->prepare(
            "SELECT
                b.id,
                b.name,
                COUNT(o.id)                      AS total_orders,
                COALESCE(SUM(o.total_amount), 0) AS total_revenue
             FROM businesses b
             LEFT JOIN orders o ON o.business_id = b.id
             WHERE b.id = ?
             GROUP BY b.id, b.name"
        );
        $bizStmt->execute([$businessId]);
        $businesses = $bizStmt->fetchAll();
    } else {
        $businesses = [];
    }
}

// --- Business performance (detailed breakdown) — admin only ---
$performance = [];
if ($isAdmin) {
    $perfStmt = $db->query(
        "SELECT
            b.id,
            b.name,
            COUNT(o.id)                                                                AS total_orders,
            COALESCE(SUM(o.total_amount), 0)                                           AS total_revenue,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) AS completed_revenue,
            SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END)                    AS completed_count,
            SUM(CASE WHEN o.status = 'pending'   THEN 1 ELSE 0 END)                    AS pending_count,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END)                    AS cancelled_count
         FROM businesses b
         LEFT JOIN orders o ON o.business_id = b.id
         GROUP BY b.id, b.name
         ORDER BY total_revenue DESC"
    );
    $performance = $perfStmt->fetchAll();
}

// --- Low stock alerts ---
$lowStockThreshold = 5;
$lowStockItems = [];
if ($isAdmin) {
    $lowStockStmt = $db->prepare(
        "SELECT s.*, b.name AS business_name
         FROM stock s
         LEFT JOIN businesses b ON b.id = s.business_id
         WHERE (s.quantity - s.quantity_sold) <= ?
         ORDER BY (s.quantity - s.quantity_sold) ASC"
    );
    $lowStockStmt->execute([$lowStockThreshold]);
    $lowStockItems = $lowStockStmt->fetchAll();
} elseif ($businessId) {
    $lowStockStmt = $db->prepare(
        "SELECT s.*, b.name AS business_name
         FROM stock s
         LEFT JOIN businesses b ON b.id = s.business_id
         WHERE (s.quantity - s.quantity_sold) <= ? AND s.business_id = ?
         ORDER BY (s.quantity - s.quantity_sold) ASC"
    );
    $lowStockStmt->execute([$lowStockThreshold, $businessId]);
    $lowStockItems = $lowStockStmt->fetchAll();
}

// Pick a relevant icon class based on keywords in the business name
function businessIcon($name) {
    $name = strtolower($name);
    return match(true) {
        str_contains($name, 'hardware')                                  => 'bi-tools',
        str_contains($name, 'cyber') || str_contains($name, 'computer')   => 'bi-laptop',
        str_contains($name, 'bouquet') || str_contains($name, 'flower')   => 'bi-flower1',
        str_contains($name, 'pharmacy') || str_contains($name, 'chemist') => 'bi-capsule',
        str_contains($name, 'electronic')                                 => 'bi-cpu',
        str_contains($name, 'grocery') || str_contains($name, 'super')    => 'bi-basket',
        str_contains($name, 'clothing') || str_contains($name, 'fashion') => 'bi-bag',
        str_contains($name, 'restaurant') || str_contains($name, 'food')  => 'bi-cup-hot',
        default                                                           => 'bi-shop',
    };
}

// Pick a colour KEY based on keywords in the business name, else rotate a palette
function businessColorKey($name, $index = 0) {
    $name = strtolower($name);
    return match(true) {
        str_contains($name, 'hardware')                                  => 'amber',
        str_contains($name, 'cyber') || str_contains($name, 'computer')   => 'blue',
        str_contains($name, 'boutique') || str_contains($name, 'fashion') => 'pink',
        str_contains($name, 'pharmacy') || str_contains($name, 'chemist') => 'green',
        str_contains($name, 'electronic')                                 => 'purple',
        str_contains($name, 'grocery') || str_contains($name, 'super')    => 'teal',
        default => ['amber','blue','pink','green','purple','teal'][$index % 6],
    };
}

require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- PAGE HEADER -->
<div class="mb-3">
    <h4 class="mb-0">Dashboard</h4>
    <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($user['full_name']) ?>.</p>
</div>

<!-- BUSINESS SALES CARDS -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <h6 class="fw-semibold mb-3">
            🏢 <?= $isAdmin ? 'Business Sales' : 'Your Business' ?>
        </h6>

        <?php if (empty($businesses)): ?>
            <p class="text-muted text-center small py-2 mb-0">
                <?= $isAdmin
                    ? 'No businesses found.'
                    : 'You are not assigned to a business yet. Contact your administrator.' ?>
            </p>
        <?php else: ?>

        <div class="row g-2">
            <?php foreach ($businesses as $i => $b): ?>
            <?php $colorKey = businessColorKey($b['name'], $i); ?>
            <div class="col-md-4 col-lg-3">
                <div class="card biz-tile biz-<?= $colorKey ?> h-100">
                    <div class="card-body text-center py-3">
                        <div class="mb-2 biz-tile-icon">
                            <i class="bi <?= businessIcon($b['name']) ?>" style="font-size:28px;"></i>
                        </div>
                        <h6 class="fw-semibold mb-2 small"><?= htmlspecialchars($b['name']) ?></h6>
                        <?php if ($isAdmin): ?>
                            <a href="/pages/business/view.php?id=<?= (int)$b['id'] ?>"
                               class="btn btn-sm btn-biz w-100 py-1">View Sales →</a>
                        <?php else: ?>
                            <a href="/pages/orders/report.php"
                               class="btn btn-sm btn-biz w-100 py-1">View Report →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
</div>

<!-- LOW STOCK ALERTS -->
<?php if (!empty($lowStockItems)): ?>
<div class="card shadow-sm mb-3 border-danger">
    <div class="card-body py-3">
        <h6 class="fw-semibold mb-3 text-danger">⚠️ Low Stock Alerts</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Business</th>
                        <th>Product</th>
                        <th class="text-end">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockItems as $item): ?>
                    <?php $remaining = max(0, (int)$item['quantity'] - (int)$item['quantity_sold']); ?>
                    <tr>
                        <td><?= htmlspecialchars($item['business_name']) ?></td>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="text-end">
                            <span class="badge bg-danger"><?= $remaining ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="/pages/stock/index.php" class="btn btn-sm btn-outline-danger mt-2">
            Manage Stock →
        </a>
    </div>
</div>
<?php endif; ?>

<!-- BUSINESS PERFORMANCE — admin only -->
<?php if ($isAdmin): ?>
<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <h6 class="fw-semibold mb-3">📊 Business Performance</h6>

        <?php if (empty($performance)): ?>
            <p class="text-muted text-center small py-2 mb-0">No business performance data yet.</p>
        <?php else: ?>

        <?php $maxRev = (float)($performance[0]['total_revenue'] ?? 1); ?>

        <div class="d-flex flex-column gap-3">
            <?php foreach ($performance as $i => $p): ?>
            <?php
            $pct = $maxRev > 0 ? round(($p['total_revenue'] / $maxRev) * 100) : 0;
            $totalForRate = (int)$p['completed_count'] + (int)$p['pending_count'] + (int)$p['cancelled_count'];
            $completionRate = $totalForRate > 0
                ? round(((int)$p['completed_count'] / $totalForRate) * 100)
                : 0;
            $completionBadge = match(true) {
                $completionRate >= 70 => 'bg-success',
                $completionRate >= 40 => 'bg-warning',
                default               => 'bg-danger',
            };
            $colorKey = businessColorKey($p['name'], $i);
            ?>
            <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rank-<?= $colorKey ?>" style="font-size:10px;">#<?= $i + 1 ?></span>
                        <span class="fw-semibold small"><?= htmlspecialchars($p['name']) ?></span>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold text-success small">
                            KES <?= number_format((float)$p['total_revenue'], 2) ?>
                        </span>
                    </div>
                </div>

                <div class="progress mb-1" style="height:6px;">
                    <div class="progress-bar progress-<?= $colorKey ?>" style="width:<?= $pct ?>%"></div>
                </div>

                <div class="d-flex flex-wrap gap-3 small text-muted">
                    <span>📦 <?= (int)$p['total_orders'] ?> orders</span>
                    <span class="text-success">✓ <?= (int)$p['completed_count'] ?> completed</span>
                    <span class="text-secondary">⏳ <?= (int)$p['pending_count'] ?> pending</span>
                    <span class="text-danger">✕ <?= (int)$p['cancelled_count'] ?> cancelled</span>
                    <span>
                        Completion rate:
                        <span class="badge <?= $completionBadge ?>"><?= $completionRate ?>%</span>
                    </span>
                </div>
            </div>
            <?php if ($i < count($performance) - 1): ?>
                <hr class="my-1">
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- KPI CARDS -->
<div class="row g-2 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Total Orders</div>
                <div class="fs-5 fw-bold"><?= (int)$kpi['total_orders'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Total Revenue</div>
                <div class="fs-6 fw-bold">
                    KES <?= number_format((float)$kpi['total_revenue'], 2) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Revenue This Month</div>
                <div class="fs-6 fw-bold text-success">
                    KES <?= number_format($monthRevenue, 2) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="text-muted" style="font-size:11px;">Pending Orders</div>
                <div class="fs-5 fw-bold text-secondary">
                    <?= (int)$kpi['pending_orders'] ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REVENUE CHART + ORDER STATUS -->
<div class="row g-2 mb-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body py-3">
                <h6 class="card-title small fw-semibold mb-2">Revenue - Last 14 Days</h6>
                <canvas id="revenueChart" height="130"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body py-3">
                <h6 class="card-title small fw-semibold mb-2">Order Status Breakdown</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between py-2 px-2">
                        <span class="small">Completed</span>
                        <span class="badge bg-success">
                            <?= (int)$kpi['completed_orders'] ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-2">
                        <span class="small">Pending</span>
                        <span class="badge bg-secondary">
                            <?= (int)$kpi['pending_orders'] ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-2">
                        <span class="small">Cancelled</span>
                        <span class="badge bg-danger">
                            <?= (int)$kpi['cancelled_orders'] ?>
                        </span>
                    </li>
                </ul>
                <a href="/pages/orders/report.php"
                   class="btn btn-outline-primary btn-sm w-100 mt-2">
                   View Full Report
                </a>
            </div>
        </div>
    </div>
</div>

<!-- CHART SCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode($values) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.1)',
            tension: 0.3,
            fill: true,
            pointRadius: 2,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales:  { y: { beginAtZero: true } }
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>