<?php
// Throwaway DB where Express is GENUINELY faster than Standard, to prove the collapsing rule
// keeps a real speed choice while dropping same-day duplicates. Production is read only.
$TEST_DB = 'dentinno_twodate';
$src     = 'dentinno_crm';
$root = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec("DROP DATABASE IF EXISTS `$TEST_DB`");
$root->exec("CREATE DATABASE `$TEST_DB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
foreach ($root->query("SHOW TABLES FROM `$src`")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $root->exec("CREATE TABLE `$TEST_DB`.`$t` LIKE `$src`.`$t`");
    $root->exec("INSERT INTO `$TEST_DB`.`$t` SELECT * FROM `$src`.`$t`");
}
$d = new PDO("mysql:host=localhost;dbname=$TEST_DB;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Gujarat base transit becomes 3 days, so an Express promise of 1 day is a real upgrade.
$d->exec("UPDATE delivery_pincodes SET delivery_days=3 WHERE pincode_prefix IN ('36','37','38','39')");
// Standard options inherit the pincode (3 days); Express keeps its own 1 day.
$d->exec("UPDATE shipping_methods SET delivery_days=NULL WHERE id IN (15,16)");
$d->exec("UPDATE shipping_methods SET delivery_days=1   WHERE id=17");

echo "seeded `$TEST_DB`: Gujarat base 3 days, Express 1 day\n";
foreach ($d->query("SELECT id,name,base_cost,delivery_days FROM shipping_methods WHERE is_active=1 ORDER BY id") as $m)
    printf("  #%s %-24s Rs.%-8s days=%s\n", $m['id'], $m['name'], $m['base_cost'], $m['delivery_days'] ?? '(pincode)');
