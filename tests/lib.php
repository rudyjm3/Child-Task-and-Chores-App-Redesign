<?php
// tests/lib.php - Minimal CLI test helpers for the Child Task and Chore App.
// Each test file runs as its own PHP process (see tests/run.php) so the
// includes/functions.php schema bootstrap can be exercised freshly per test.
//
// Conventions:
//   require __DIR__ . '/lib.php';
//   t_fresh_db();                 // drop + recreate the app database (BEFORE app_boot)
//   t_load_sql('fixtures/x.sql'); // load a fixture into the app database
//   app_boot();                   // include functions.php (connects + bootstraps schema)
//   t_assert(...); t_assert_eq(...);
//   t_done();                     // print summary, exit non-zero on failure

const T_DB_HOST = 'localhost';
const T_DB_USER = 'root';
const T_DB_PASS = '';
const T_DB_NAME = 'child_chore_app';

// Match the app timezone (includes/db_connect.php) BEFORE any date()/NOW()
// usage, and pin every test connection's session time_zone to it. Otherwise
// NOW()-relative fixture rows are written in server time (UTC) while the app
// computes dates in America/New_York, and tests fail near midnight UTC.
date_default_timezone_set('America/New_York');

function t_apply_session_tz(PDO $pdo): PDO {
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}

$GLOBALS['__t_pass'] = 0;
$GLOBALS['__t_fail'] = 0;

function t_server_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . T_DB_HOST, T_DB_USER, T_DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        t_apply_session_tz($pdo);
    }
    return $pdo;
}

// Drop and recreate the app database. Must be called BEFORE app_boot().
function t_fresh_db(): void {
    $pdo = t_server_pdo();
    $pdo->exec('DROP DATABASE IF EXISTS `' . T_DB_NAME . '`');
    $pdo->exec('CREATE DATABASE `' . T_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
}

// Load a .sql file (relative to tests/) into the app database.
function t_load_sql(string $relPath): void {
    $path = __DIR__ . '/' . $relPath;
    if (!is_file($path)) {
        fwrite(STDERR, "Fixture not found: $path\n");
        exit(2);
    }
    $sql = file_get_contents($path);
    $pdo = new PDO('mysql:host=' . T_DB_HOST . ';dbname=' . T_DB_NAME, T_DB_USER, T_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    t_apply_session_tz($pdo);
    $pdo->exec($sql);
}

// Include the app (connects to DB and runs the schema bootstrap). Returns the PDO handle.
function app_boot(): PDO {
    // Silence the bootstrap's error_log noise and PHP 8.4 deprecation chatter.
    ini_set('error_log', '/dev/null');
    error_reporting(E_ALL & ~E_DEPRECATED);
    // Declared BEFORE the include so the top-level $db/$pdo assignments inside
    // db_connect.php bind to the real globals (we are inside a function scope).
    global $db, $pdo;
    require_once dirname(__DIR__) . '/includes/functions.php';
    return $db;
}

// Direct PDO to the app DB without booting the app (for schema inspection).
function t_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . T_DB_HOST . ';dbname=' . T_DB_NAME, T_DB_USER, T_DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        t_apply_session_tz($pdo);
    }
    return $pdo;
}

function t_table_exists(string $table): bool {
    $stmt = t_db()->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([T_DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function t_column_exists(string $table, string $column): bool {
    $stmt = t_db()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([T_DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function t_scalar(string $sql, array $params = []) {
    $stmt = t_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function t_rows(string $sql, array $params = []): array {
    $stmt = t_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function t_assert(bool $cond, string $label): void {
    if ($cond) {
        $GLOBALS['__t_pass']++;
        echo "  ok: $label\n";
    } else {
        $GLOBALS['__t_fail']++;
        echo "  FAIL: $label\n";
    }
}

function t_assert_eq($expected, $actual, string $label): void {
    if ($expected == $actual) {
        $GLOBALS['__t_pass']++;
        echo "  ok: $label\n";
    } else {
        $GLOBALS['__t_fail']++;
        echo "  FAIL: $label\n    expected: " . var_export($expected, true)
           . "\n    actual:   " . var_export($actual, true) . "\n";
    }
}

function t_done(): void {
    $p = $GLOBALS['__t_pass'];
    $f = $GLOBALS['__t_fail'];
    echo ($f === 0 ? "PASS" : "FAIL") . " ($p passed, $f failed)\n";
    exit($f === 0 ? 0 : 1);
}
