<?php
/**
 * Simple SQL migration runner for Dentinno.
 *
 *   php migrate.php           # run all pending database_*.sql files (in name order)
 *   php migrate.php --status  # list which files have run / are pending
 *   php migrate.php --force <file.sql>   # re-run one specific file (ignores history)
 *   php migrate.php --baseline           # mark ALL files as applied WITHOUT running
 *                                        # (use on an existing DB that was set up by hand)
 *
 * - Files live in this directory and match database_*.sql.
 * - Each applied file is recorded in `schema_migrations` so re-running is safe.
 * - Most files already use CREATE TABLE IF NOT EXISTS / INSERT IGNORE, so a missed
 *   record won't corrupt data; the history table just avoids needless re-runs.
 */

require_once __DIR__ . '/includes/config.php';

if (php_sapi_name() !== 'cli') {
    // Migrations alter the schema — they must NEVER be triggerable over HTTP.
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die("Forbidden. Run migrations from the command line: php migrate.php\n");
}

$pdo = db()->getConnection();
$dir = __DIR__;

// Ensure the history table exists.
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(190) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$applied = [];
foreach ($pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) as $f) {
    $applied[$f] = true;
}

// All migration files, sorted by name (so prefixes can order them if needed).
$files = glob($dir . '/database_*.sql');
sort($files);

// Exclude destructive maintenance scripts (database_purge_*.sql). These DELETE data
// and are run manually/intentionally, not as schema migrations — never auto-apply them
// via `php migrate.php`. (They can still be run by hand, or with --force if truly needed.)
$files = array_values(array_filter($files, fn($p) => !preg_match('/^database_purge_/', basename($p))));

$args = array_slice($argv ?? [], 1);
$mode = $args[0] ?? 'run';

function out($msg) { echo $msg . PHP_EOL; }

// --- status ---
if ($mode === '--status') {
    out("Migration status:");
    foreach ($files as $path) {
        $name = basename($path);
        out(sprintf("  [%s] %s", isset($applied[$name]) ? 'x' : ' ', $name));
    }
    out("\nTotal: " . count($files) . " files, " . count($applied) . " applied.");
    exit;
}

// --- baseline: record every file as applied without executing it ---
if ($mode === '--baseline') {
    $n = 0;
    $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)
                           ON DUPLICATE KEY UPDATE applied_at=applied_at");
    foreach ($files as $path) {
        $name = basename($path);
        if (isset($applied[$name])) continue;
        $stmt->execute([$name]);
        out("BASELINED $name");
        $n++;
    }
    out($n ? "Marked $n file(s) as applied (not executed)." : "Already baselined.");
    exit;
}

// --- force re-run a single file ---
if ($mode === '--force') {
    $target = $args[1] ?? '';
    if ($target === '') { out("Usage: php migrate.php --force <file.sql>"); exit(1); }
    $path = $dir . '/' . basename($target);
    if (!is_file($path)) { out("Not found: $target"); exit(1); }
    runFile($pdo, $path, true);
    exit;
}

// --- normal run: apply all pending ---
$ran = 0;
foreach ($files as $path) {
    $name = basename($path);
    if (isset($applied[$name])) continue;
    runFile($pdo, $path, false);
    $ran++;
}
out($ran ? "Done. Applied $ran migration(s)." : "Nothing to do — all migrations already applied.");

/** Execute one .sql file (whole-file exec, then record it). */
function runFile(PDO $pdo, string $path, bool $force): void {
    $name = basename($path);
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') { out("SKIP (empty): $name"); return; }
    try {
        $pdo->exec($sql);                       // PDO runs multi-statement scripts on MySQL
        $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)
                               ON DUPLICATE KEY UPDATE applied_at=CURRENT_TIMESTAMP");
        $stmt->execute([$name]);
        out(($force ? "RE-RAN " : "APPLIED ") . $name);
    } catch (Throwable $e) {
        out("ERROR in $name: " . $e->getMessage());
        out("Stopping. Fix the file and re-run.");
        exit(1);
    }
}
