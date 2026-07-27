<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();

$tablesToCheck = ['stock', 'orders', 'businesses', 'order_items', 'products'];

echo "<h2>Database Schema Inspector</h2>";

// First, list ALL tables that actually exist
echo "<h3>All tables in database:</h3><pre>";
$allTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($allTables);
echo "</pre><hr>";

foreach ($tablesToCheck as $table) {
    echo "<h3>Table: $table</h3>";
    try {
        $stmt = $db->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch();
        echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";

        // Also show a sample row so I can see real data shape
        $sample = $db->query("SELECT * FROM `$table` LIMIT 2")->fetchAll();
        echo "<strong>Sample rows:</strong><pre>";
        print_r($sample);
        echo "</pre>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Not found or error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "<hr>";
}