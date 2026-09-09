<?php
// Build a throwaway DB with a DELIBERATE hole in the rate card, so the fallback path can be
// tested end-to-end over HTTP. Production is read only.
$TEST_DB = 'dentinno_gapapi';
$src     = 'dentinno_crm';
$root = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec("DROP DATABASE IF EXISTS `$TEST_DB`");
$root->exec("CREATE DATABASE `$TEST_DB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
foreach ($root->query("SHOW TABLES FROM `$src`")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $root->exec("CREATE TABLE `$TEST_DB`.`$t` LIKE `$src`.`$t`");
    $root->exec("INSERT INTO `$TEST_DB`.`$t` SELECT * FROM `$src`.`$t`");
}
$d = new PDO("mysql:host=localhost;dbname=$TEST_DB;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// One weight method whose only rule covers 0-0.99 kg. Anything heavier matches nothing.
$d->exec("DELETE FROM shipping_rules");
$d->exec("DELETE FROM shipping_methods");
$mid = 0;
$d->exec("INSERT INTO shipping_methods (id,name,type,base_cost,is_active,sort_order) VALUES (900,'Gappy Weight','weight',0,1,0)");
$d->exec("INSERT INTO shipping_rules (method_id,rule_type,min_value,max_value,cost,is_free,is_active) VALUES (900,'weight',0,0.99,60,0,1)");

// The old dangerous setting: free above Rs.1,000.
$d->exec("UPDATE site_settings SET svalue='{\"freeThreshold\":1000,\"flatRate\":99}' WHERE skey='shippingConfig'");

echo "seeded `$TEST_DB`: one method, rule covers 0-0.99 kg only; shippingConfig free above Rs.1000\n";
