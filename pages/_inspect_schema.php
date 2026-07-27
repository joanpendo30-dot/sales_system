<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDB();

$tablesToCheck = ['stock', 'orders', 'businesses', 'order_items', 'products'];

echo "<h2>Database Schema Inspector</h2>";

// First, list ALL tables that actually exist
echo "<h3>All tables in database:</h3><pre>";
$stmt = $db->query("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public'
");
$allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($allTables);
echo "</pre><hr>";

foreach ($tablesToCheck as $table) {
    echo "<h3>Table: $table</h3>";
    try {
        // Postgres has no "SHOW CREATE TABLE" — list columns/types instead
        $stmt = $db->prepare("
            SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = :table
            ORDER BY ordinal_position
        ");
        $stmt->execute(['table' => $table]);
        $columns = $stmt->fetchAll();

        if (!$columns) {
            echo "<p style='color:red;'>Not found</p>";
            echo "<hr>";
            continue;
        }

        echo "<pre>" . htmlspecialchars(print_r($columns, true)) . "</pre>";

        // Also show a sample row so I can see real data shape
        $sample = $db->query("SELECT * FROM \"$table\" LIMIT 2")->fetchAll();
        echo "<strong>Sample rows:</strong><pre>";
        print_r($sample);
        echo "</pre>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Not found or error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "<hr>";
}