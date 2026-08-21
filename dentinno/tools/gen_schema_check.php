<?php
// One-off generator: emits prod_schema_check.sql from the current (reference) DB schema.
// Run: php tools/gen_schema_check.php   (writes ../prod_schema_check.sql)
require __DIR__ . '/../includes/config.php';
$db = db();
$dbname = DB_NAME;

$tables = $db->fetchAll("SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME", [$dbname]);
$cols   = $db->fetchAll("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME, ORDINAL_POSITION", [$dbname]);
$nt = count($tables); $nc = count($cols);
$q = fn($s) => "'" . str_replace("'", "''", $s) . "'";

$tblSql = '';
foreach ($tables as $i => $r) {
    $tblSql .= '  SELECT ' . $q($r['t']) . ($i === 0 ? ' AS tbl' : '') . ($i < $nt - 1 ? ' UNION ALL' : '') . "\n";
}
$colSql = '';
foreach ($cols as $i => $r) {
    $colSql .= '  SELECT ' . $q($r['t']) . ($i === 0 ? ' AS tbl' : '') . ', ' . $q($r['c']) . ($i === 0 ? ' AS col' : '') . ($i < $nc - 1 ? ' UNION ALL' : '') . "\n";
}

$out = "-- =============================================================================
-- PRODUCTION SCHEMA DRIFT CHECK  (read-only -- changes no data)
--
-- Reports any TABLE or COLUMN the application expects (present in the known-good
-- reference schema) but MISSING on this server. Run BEFORE importing the catalogue
-- so you don't hit errors like the missing hsn_code column.
--
-- Usage:   mysql -u USER -p PROD_DB < prod_schema_check.sql
-- Uses DATABASE() so it checks whichever DB you connect to (no hardcoded name).
--
-- Generated from the reference schema: $nt tables, $nc columns.
-- If BOTH result sets are empty, production matches the reference -- safe to import.
-- =============================================================================

-- ---- 1) MISSING TABLES ------------------------------------------------------
SELECT '*** MISSING TABLE ***' AS issue, expected.tbl AS name
FROM (
$tblSql) AS expected
LEFT JOIN information_schema.TABLES it
  ON it.TABLE_SCHEMA = DATABASE() AND it.TABLE_NAME = expected.tbl
WHERE it.TABLE_NAME IS NULL
ORDER BY expected.tbl;

-- ---- 2) MISSING COLUMNS (only for tables that exist) ------------------------
SELECT '*** MISSING COLUMN ***' AS issue, expected.tbl AS table_name, expected.col AS column_name
FROM (
$colSql) AS expected
JOIN information_schema.TABLES it
  ON it.TABLE_SCHEMA = DATABASE() AND it.TABLE_NAME = expected.tbl
LEFT JOIN information_schema.COLUMNS ic
  ON ic.TABLE_SCHEMA = DATABASE() AND ic.TABLE_NAME = expected.tbl AND ic.COLUMN_NAME = expected.col
WHERE ic.COLUMN_NAME IS NULL
ORDER BY expected.tbl, expected.col;
";

file_put_contents(__DIR__ . '/../prod_schema_check.sql', $out);
echo "Wrote prod_schema_check.sql ($nt tables, $nc columns, " . strlen($out) . " bytes)\n";
