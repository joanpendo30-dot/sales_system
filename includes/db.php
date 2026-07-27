<?php
// includes/db.php — PostgreSQL version (Render)

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $databaseUrl = getenv('DATABASE_URL');

        if (!$databaseUrl) {
            die('DATABASE_URL environment variable is not set.');
        }

        $parts = parse_url($databaseUrl);

        $host = $parts['host'];
        $port = $parts['port'] ?? 5432;
        $dbname = ltrim($parts['path'], '/');
        $user = $parts['user'];
        $pass = $parts['pass'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

        $pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}