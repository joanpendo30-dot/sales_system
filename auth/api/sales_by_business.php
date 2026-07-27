<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

try {
    $db   = getDB();
    $user = currentUser();

    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to']   ?? date('Y-m-d');
    $biz  = $_GET['biz']  ?? 'all';

    $params = [$from, $to];
    $where  = ['o.order_date BETWEEN ? AND ?'];

    if ($user['role'] === 'admin') {
        if ($biz !== 'all' && is_numeric($biz)) {
            $where[]  = 'b.id = ?';
            $params[] = (int)$biz;
        }
    } else {
        if ($user['business_id']) {
            $where[]  = 'b.id = ?';
            $params[] = $user['business_id'];
        } else {
            $where[] = '1 = 0';
        }
    }

    $whereSQL = implode(' AND ', $where);

    $sql = "
        SELECT
            b.id,
            b.name AS business_name,
            COUNT(o.id) AS total_orders,
            COALESCE(SUM(o.total_amount), 0) AS total_revenue,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) AS completed_revenue,
            SUM(CASE WHEN o.status = 'pending'   THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
        FROM businesses b
        LEFT JOIN orders o
            ON o.business_id = b.id
           AND $whereSQL
        GROUP BY b.id, b.name
        ORDER BY total_revenue DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $row) {
        $data[] = [
            'id'        => $row['id'],
            'name'      => $row['business_name'],
            'orders'    => (int)$row['total_orders'],
            'revenue'   => (float)$row['total_revenue'],
            'completed' => (float)$row['completed_revenue'],
            'pending'   => (int)$row['pending_count'],
            'cancelled' => (int)$row['cancelled_count'],
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}