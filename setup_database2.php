<?php
// setup_database2.php
// ONE-TIME USE: adds missing businesses/stock tables + business_id columns.

require_once __DIR__ . '/includes/db.php';

$expectedKey = getenv('SETUP_SECRET');
$providedKey = $_GET['key'] ?? '';

if (!$expectedKey) {
    die('SETUP_SECRET environment variable is not set.');
}

if (!hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    die('Forbidden: missing or incorrect key.');
}

$db = getDB();

$stmt = $db->query("
    SELECT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'businesses'
    ) AS businesses_table_exists
");
$exists = $stmt->fetch()['businesses_table_exists'];

if ($exists) {
    die('Setup already ran -- the "businesses" table already exists. Nothing to do. (Delete this file now.)');
}

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS businesses (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER set_timestamp_businesses
BEFORE UPDATE ON businesses
FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp();

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS business_id INT NULL REFERENCES businesses(id) ON DELETE SET NULL;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS business_id INT NULL REFERENCES businesses(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS created_by_name VARCHAR(100) NULL;

CREATE TABLE IF NOT EXISTS stock (
    id             SERIAL PRIMARY KEY,
    business_id    INT NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
    product_name   VARCHAR(150) NOT NULL,
    quantity       INT NOT NULL DEFAULT 0,
    quantity_sold  INT NOT NULL DEFAULT 0,
    unit_price     NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    added_by       INT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_stock_business ON stock(business_id);

CREATE TRIGGER set_timestamp_stock
BEFORE UPDATE ON stock
FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp();

INSERT INTO businesses (name) VALUES ('Sample Business');
SQL;

try {
    $db->exec($sql);
    echo "<h2>Setup complete</h2>";
    echo "<p>businesses/stock tables created, business_id columns added.</p>";
    echo "<p><strong>Now delete this file (setup_database2.php) from your project and redeploy.</strong></p>";
} catch (Exception $e) {
    http_response_code(500);
    echo "<h2>Setup failed</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}