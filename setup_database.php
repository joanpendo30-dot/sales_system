<?php
// setup_database.php
// ONE-TIME USE: creates tables and seeds initial data on the Postgres database.

require_once __DIR__ . '/includes/db.php';

$expectedKey = getenv('SETUP_SECRET');
$providedKey = $_GET['key'] ?? '';

if (!$expectedKey) {
    die('SETUP_SECRET environment variable is not set. Add one in Render before using this script.');
}

if (!hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    die('Forbidden: missing or incorrect key.');
}

$db = getDB();

$stmt = $db->query("
    SELECT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public' AND table_name = 'users'
    ) AS users_table_exists
");
$exists = $stmt->fetch()['users_table_exists'];

if ($exists) {
    die('Setup already ran -- the "users" table already exists. Nothing to do. (Delete this file now.)');
}

$sql = <<<SQL
CREATE OR REPLACE FUNCTION trigger_set_timestamp()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          VARCHAR(10)  NOT NULL DEFAULT 'user'
                  CHECK (role IN ('admin','user')),
    is_active     SMALLINT     NOT NULL DEFAULT 1,
    last_login    TIMESTAMP    NULL DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER set_timestamp_users
BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp();

CREATE TABLE IF NOT EXISTS orders (
    id            SERIAL PRIMARY KEY,
    customer_name VARCHAR(150)   NOT NULL,
    product_name  VARCHAR(150)   NOT NULL,
    quantity      INT            NOT NULL DEFAULT 1,
    unit_price    NUMERIC(10,2)  NOT NULL DEFAULT 0.00,
    total_amount  NUMERIC(10,2)  NOT NULL DEFAULT 0.00,
    order_date    DATE           NOT NULL,
    status        VARCHAR(10)    NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending','completed','cancelled')),
    created_by    INT            NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_orders_date ON orders(order_date);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_name);

CREATE TRIGGER set_timestamp_orders
BEFORE UPDATE ON orders
FOR EACH ROW EXECUTE FUNCTION trigger_set_timestamp();

INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES (
    'admin',
    'admin@example.com',
    '$2b$10$6PuKTgO2uzE0BpSI7IHekOBgOynUpayKCg55Q7BVTviomvtB/95P6',
    'System Administrator',
    'admin',
    1
);

INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES (
    'jdoe',
    'jdoe@example.com',
    '$2b$10$7rJFwhzF0ZJsBESrYNaHQOvI.YJDCQhfHvfaG2SARO2pkOizXCAiS',
    'Jane Doe',
    'user',
    1
);
SQL;

try {
    $db->exec($sql);
    echo "<h2>Setup complete</h2>";
    echo "<p>Tables created and seeded successfully.</p>";
    echo "<p><strong>Now delete this file (setup_database.php) from your project and redeploy.</strong></p>";
} catch (Exception $e) {
    http_response_code(500);
    echo "<h2>Setup failed</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}