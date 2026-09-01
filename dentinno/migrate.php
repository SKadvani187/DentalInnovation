<?php
/**
 * SQL migration runner for Dentinno.
 *
 *   php migrate.php                      # run every pending migration, in version order
 *   php migrate.php --status             # list applied / pending
 *   php migrate.php --new add_foo_table  # create the next migration file (stamps the version)
 *   php migrate.php --force <version>    # re-run one migration (ignores history)
 *   php migrate.php --baseline           # mark all CURRENT files applied WITHOUT running them
 *                                        # (only for a DB that was already set up by hand)
 *
 * Ordering
 * --------
 * Migrations live in migrations/ and are named  <version>_<name>.sql  where <version> is a
 * UTC timestamp, YYYYMMDDHHMMSS. They run in ascending version order, and `schema_migrations`
 * records the VERSION, not the filename — so a file can be renamed later without the runner
 * thinking it is a new migration. Never edit the version of a migration that has shipped.
 *
 * Migrations must not contain `USE <db>` or `CREATE DATABASE`: the runner connects with the
 * DB_NAME from config.php (which production overrides via env), and a hardcoded USE would send
 * the schema into the wrong database. The runner rejects any file that carries one.
 */

require_once __DIR__ . '/includes/config.php';

if (php_sapi_name() !== 'cli') {
    // Migrations alter the schema — they must NEVER be triggerable over HTTP.
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die("Forbidden. Run migrations from the command line: php migrate.php\n");
}

const MIG_DIR = __DIR__ . '/migrations';

/**
 * Versions of the pre-versioning migrations, keyed by their old filename. Used ONCE per database
 * to convert a legacy `schema_migrations` (keyed by filename) to the version-keyed table. Every
 * entry mirrors a file in migrations/ — never edit these, or an upgraded database will re-run
 * migrations it has already applied.
 */
const LEGACY_VERSIONS = [
    'database_additions.sql'                             => 20260512172733,
    'database_auth_orders.sql'                           => 20260604184959,
    'database_otp.sql'                                   => 20260604185000,
    'database_storefront.sql'                            => 20260604185001,
    'database_bestsellers_fix.sql'                       => 20260606050540,
    'database_initial_data_insert.sql'                   => 20260606050541,
    'database_razorpay.sql'                              => 20260606050542,
    'database_whatsapp.sql'                              => 20260606050543,
    'database_delivery_pincodes.sql'                     => 20260606161049,
    'database_product_questions.sql'                     => 20260606161050,
    'database_footerconfig.sql'                          => 20260608191046,
    'database_navmenu.sql'                               => 20260608191047,
    'database_storefront_chrome.sql'                     => 20260608191048,
    'database_shipping_class.sql'                        => 20260609015011,
    'database_order_statuses.sql'                        => 20260609184425,
    'database_product_shipping_method.sql'               => 20260609184426,
    'database_refunds.sql'                               => 20260609184427,
    'database_shipping_simple.sql'                       => 20260609184428,
    'database_zone_pincodes.sql'                         => 20260609184429,
    'database_zone_rates.sql'                            => 20260609184430,
    'database_cart.sql'                                  => 20260610080310,
    'database_shipping_demo.sql'                         => 20260610080311,
    'database_bulk_quotes.sql'                           => 20260610184506,
    'database_drop_fbtitems.sql'                         => 20260610184507,
    'database_drop_freegifts.sql'                        => 20260610184508,
    'database_product_catalogue.sql'                     => 20260610184509,
    'database_product_content_fields.sql'                => 20260610184510,
    'database_product_fbt.sql'                           => 20260610184511,
    'database_product_gifts.sql'                         => 20260610184512,
    'database_about.sql'                                 => 20260610194634,
    'database_branding.sql'                              => 20260610194635,
    'database_combos_stock_items.sql'                    => 20260610194636,
    'database_contact_messages.sql'                      => 20260610194637,
    'database_contactus.sql'                             => 20260610194638,
    'database_storefront_offers_social.sql'              => 20260610194639,
    'database_testimonials_rating_product.sql'           => 20260610194640,
    'database_order_items_offer.sql'                     => 20260610232631,
    'database_storefront_offers_relational.sql'          => 20260610232632,
    'database_storefront_offers_relational_backfill.sql' => 20260610232633,
    'database_order_effects_reversal.sql'                => 20260611174218,
    'database_category_seo.sql'                          => 20260613153357,
    'database_combo_seo.sql'                             => 20260613153358,
    'database_combo_soft_delete.sql'                     => 20260613153359,
    'database_coupon_redemptions.sql'                    => 20260613153400,
    'database_coupon_soft_delete.sql'                    => 20260613153401,
    'database_coupon_start_date.sql'                     => 20260613153402,
    'database_customer_soft_delete.sql'                  => 20260613153403,
    'database_login_attempts.sql'                        => 20260613153404,
    'database_offer_soft_delete.sql'                     => 20260613153405,
    'database_order_status_history.sql'                  => 20260613153406,
    'database_product_seo.sql'                           => 20260613153407,
    'database_product_soft_delete.sql'                   => 20260613153408,
    'database_refund_processing_status.sql'              => 20260613153409,
    'database_admin_audit_log.sql'                       => 20260613172108,
    'database_bulkquote_soft_delete.sql'                 => 20260613172109,
    'database_course_soft_delete.sql'                    => 20260613172110,
    'database_event_soft_delete.sql'                     => 20260613172111,
    'database_message_soft_delete.sql'                   => 20260613172112,
    'database_question_soft_delete.sql'                  => 20260613172113,
    'database_refund_actioned_by.sql'                    => 20260613172114,
    'database_review_soft_delete.sql'                    => 20260613172115,
    'database_activity_log.sql'                          => 20260614225609,
    'database_inventory_movements.sql'                   => 20260614225610,
    'database_notification_reads.sql'                    => 20260614225611,
    'database_product_cost_price.sql'                    => 20260614225612,
    'database_order_mail_log.sql'                        => 20260615080237,
    'database_rbac.sql'                                  => 20260615080238,
    'database_rbac2.sql'                                 => 20260615080239,
    'database_remove_shipping_calculator.sql'            => 20260615113218,
    'database_restore_nav_inventory_activity.sql'        => 20260615122155,
    'database_customer_provisional.sql'                  => 20260618102619,
    'database_cancelled_payment_unpaid.sql'              => 20260618122549,
    'database_order_payment_failed_at.sql'               => 20260618122550,
    'database_customer_email_null_fix.sql'               => 20260618182519,
    'database_gst_invoice.sql'                           => 20260618182520,
    'database_maintenance_mode.sql'                      => 20260619152421,
    'database_product_bulk_offers.sql'                   => 20260620205632,
    'database_home_product_links_fix.sql'                => 20260621003201,
    'database_home_curation.sql'                         => 20260621004936,
    'database_combo_detail_fields.sql'                   => 20260622181325,
    'database_events_icon_fix.sql'                       => 20260622181326,
    'database_product_keyspec_html.sql'                  => 20260622181327,
    'database_product_youtube.sql'                       => 20260622181328,
    'database_customer_password.sql'                     => 20260624005427,
];

/** The base schema, which older installs imported by hand and never recorded. */
const BASE_SCHEMA_VERSION = 20260512172732;

/**
 * Migrations get their OWN connection, not the app's.
 *
 * The app connects with ATTR_EMULATE_PREPARES => false, and a native-prepared statement cannot
 * carry the multi-statement scripts these files are. Emulation lets one query() run the whole
 * file, and nextRowset() then surfaces an error from ANY statement in it — the app's connection
 * settings stay untouched.
 */
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ]
);

function out(string $msg = ''): void { echo $msg . PHP_EOL; }
function fail(string $msg): never { out($msg); exit(1); }

/** All migrations on disk: version => ['version','file','path','name']. Ascending. */
function loadMigrations(): array {
    $list = [];
    foreach (glob(MIG_DIR . '/*.sql') as $path) {
        $file = basename($path);
        if (!preg_match('/^(\d{14})_(.+)\.sql$/', $file, $m)) {
            fail("Bad migration filename: $file\nExpected <14-digit version>_<name>.sql — create new ones with: php migrate.php --new <name>");
        }
        $v = (int)$m[1];
        if (isset($list[$v])) {
            fail("Duplicate version $v: {$list[$v]['file']} and $file\nBump one of them by a second.");
        }
        $list[$v] = ['version' => $v, 'file' => $file, 'path' => $path, 'name' => $m[2]];
    }
    ksort($list);
    return $list;
}

function checksum(string $path): string { return substr(hash('sha256', file_get_contents($path)), 0, 32); }

/** Create the history table, and convert a legacy filename-keyed one on first run. */
function ensureHistory(PDO $pdo): void {
    $exists = $pdo->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
    if (!$exists) {
        $pdo->exec("CREATE TABLE schema_migrations (
            version    BIGINT       NOT NULL PRIMARY KEY,
            filename   VARCHAR(190) NOT NULL,
            checksum   CHAR(32)     NULL,
            applied_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )");
        return;
    }

    $cols = $pdo->query("SHOW COLUMNS FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('version', $cols, true)) return;   // already converted

    // --- one-time upgrade of a legacy (filename-keyed) history table ---
    out("Legacy schema_migrations detected — converting to version-keyed history.");
    $rows = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);

    $unknown = array_values(array_filter($rows, fn($f) => !isset(LEGACY_VERSIONS[$f])));
    if ($unknown) {
        fail("These applied migrations have no known version:\n  - " . implode("\n  - ", $unknown)
           . "\nAdd them to LEGACY_VERSIONS in migrate.php (matching their file in migrations/) and re-run.");
    }

    $pdo->exec("ALTER TABLE schema_migrations ADD COLUMN version BIGINT NULL AFTER filename");
    if (!in_array('checksum', $cols, true)) {
        $pdo->exec("ALTER TABLE schema_migrations ADD COLUMN checksum CHAR(32) NULL AFTER version");
    }
    $upd = $pdo->prepare("UPDATE schema_migrations SET version=? WHERE filename=?");
    foreach ($rows as $f) $upd->execute([LEGACY_VERSIONS[$f], $f]);

    // The base schema predates the runner: an existing DB has it, but never recorded it.
    $ins = $pdo->prepare("INSERT IGNORE INTO schema_migrations (filename, version) VALUES (?,?)");
    $ins->execute(['20260512172732_base_schema.sql', BASE_SCHEMA_VERSION]);

    $pdo->exec("ALTER TABLE schema_migrations MODIFY version BIGINT NOT NULL");
    $pdo->exec("ALTER TABLE schema_migrations DROP PRIMARY KEY, ADD PRIMARY KEY (version)");

    // Stamp today's file contents as each row's checksum. It isn't proof of what this database
    // actually ran (that history predates checksums) — it's the baseline that makes drift
    // detection work from here on.
    $cs = $pdo->prepare("UPDATE schema_migrations SET checksum=?, filename=? WHERE version=?");
    foreach (loadMigrations() as $v => $m) $cs->execute([checksum($m['path']), $m['file'], $v]);

    out("Converted " . (count($rows) + 1) . " history rows.");
}

/** version => ['filename','checksum'] for everything already applied. */
function appliedMap(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT version, filename, checksum FROM schema_migrations")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int)$r['version']] = ['filename' => $r['filename'], 'checksum' => $r['checksum']];
    }
    return $out;
}

/** Execute one migration file and record it. */
function runFile(PDO $pdo, array $m, bool $force): void {
    $sql = file_get_contents($m['path']);
    // A UTF-8 BOM would sit in front of the first statement — it breaks the SQL and it would hide
    // a leading USE from the check below. Drop it before doing anything else.
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    if (trim($sql) === '') { out("SKIP (empty): {$m['file']}"); return; }

    // A hardcoded USE/CREATE DATABASE would silently redirect the schema at a different database
    // than the one the runner connected to. Refuse rather than corrupt the wrong DB.
    if (preg_match('/^[ \t]*(USE[ \t]+\S|CREATE[ \t]+DATABASE)/im', $sql)) {
        fail("{$m['file']} contains USE / CREATE DATABASE. Remove it — migrations run inside the database the runner connected to (DB_NAME).");
    }

    try {
        $st = $pdo->query($sql);
        // Drain every result set: without this, an error in a LATER statement of a multi-statement
        // script is never raised and the migration is wrongly recorded as successful.
        while ($st && $st->nextRowset()) { /* keep draining */ }

        $pdo->prepare("INSERT INTO schema_migrations (version, filename, checksum) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE filename=VALUES(filename), checksum=VALUES(checksum), applied_at=CURRENT_TIMESTAMP")
            ->execute([$m['version'], $m['file'], checksum($m['path'])]);
        out(($force ? 'RE-RAN  ' : 'APPLIED ') . $m['file']);
    } catch (Throwable $e) {
        out("ERROR in {$m['file']}: " . $e->getMessage());
        out("Stopping. MySQL auto-commits DDL, so this file may be HALF-APPLIED —");
        out("check the database, make the file safe to re-run, then run migrate.php again.");
        exit(1);
    }
}

// ---------------------------------------------------------------------------

$args = array_slice($argv ?? [], 1);
$mode = $args[0] ?? 'run';

// --- --new: create the next migration file --------------------------------
if ($mode === '--new') {
    $name = trim((string)($args[1] ?? ''));
    if ($name === '') fail("Usage: php migrate.php --new <name>   e.g. --new add_product_barcode");
    $name = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
    $name = trim($name, '_');
    if ($name === '') fail("Name must contain letters or digits.");

    $existing = loadMigrations();
    // Stamp UTC so the order is the same everywhere; step forward on a collision.
    $v = (int)gmdate('YmdHis');
    while (isset($existing[$v])) $v++;
    $path = MIG_DIR . '/' . $v . '_' . $name . '.sql';
    file_put_contents($path, "-- $name\n-- Keep this idempotent (IF NOT EXISTS / INSERT IGNORE): MySQL auto-commits DDL,\n-- so a half-applied file has to be safe to re-run. No USE / CREATE DATABASE.\n\n");
    out("Created migrations/" . basename($path));
    exit;
}

ensureHistory($pdo);
$migrations = loadMigrations();
$applied    = appliedMap($pdo);

// --- --status --------------------------------------------------------------
if ($mode === '--status') {
    out("Migration status:");
    $drift = [];
    foreach ($migrations as $v => $m) {
        $isApplied = isset($applied[$v]);
        out(sprintf("  [%s] %d  %s", $isApplied ? 'x' : ' ', $v, $m['file']));
        if ($isApplied && $applied[$v]['checksum'] && $applied[$v]['checksum'] !== checksum($m['path'])) {
            $drift[] = $m['file'];
        }
    }
    $pending = count($migrations) - count(array_intersect_key($applied, $migrations));
    out("\nTotal: " . count($migrations) . " files, " . count($applied) . " applied, $pending pending.");

    // Applied rows with no file left on disk — usually a deleted or re-versioned migration.
    if ($orphans = array_diff_key($applied, $migrations)) {
        out("\nApplied but missing from migrations/:");
        foreach ($orphans as $v => $r) out("  $v  {$r['filename']}");
    }
    if ($drift) {
        out("\nCHANGED SINCE IT WAS APPLIED (this database did NOT get the new content):");
        foreach ($drift as $f) out("  $f");
    }
    exit;
}

// --- --baseline: record everything as applied without running it -----------
if ($mode === '--baseline') {
    $ins = $pdo->prepare("INSERT INTO schema_migrations (version, filename, checksum) VALUES (?,?,?)
                          ON DUPLICATE KEY UPDATE applied_at=applied_at");
    $n = 0;
    foreach ($migrations as $v => $m) {
        if (isset($applied[$v])) continue;
        $ins->execute([$v, $m['file'], checksum($m['path'])]);
        out("BASELINED {$m['file']}");
        $n++;
    }
    out($n ? "Marked $n file(s) as applied (not executed)." : "Already baselined.");
    exit;
}

// --- --force <version|filename> -------------------------------------------
if ($mode === '--force') {
    $target = trim((string)($args[1] ?? ''));
    if ($target === '') fail("Usage: php migrate.php --force <version>");
    $hit = null;
    foreach ($migrations as $v => $m) {
        if ((string)$v === $target || $m['file'] === $target || $m['file'] === basename($target)) { $hit = $m; break; }
    }
    if (!$hit) fail("Not found in migrations/: $target");
    runFile($pdo, $hit, true);
    exit;
}

// --- normal run ------------------------------------------------------------
$ran = 0;
foreach ($migrations as $v => $m) {
    if (isset($applied[$v])) continue;
    runFile($pdo, $m, false);
    $ran++;
}
out($ran ? "Done. Applied $ran migration(s)." : "Nothing to do — all migrations already applied.");
