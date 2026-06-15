<?php
// Verify dashboard revenue calcs reconcile after the fixes. CLI only.
if (php_sapi_name() !== 'cli') { http_response_code(403); die("CLI only\n"); }
require __DIR__ . '/../includes/auth.php';

$s = getDashboardStats();
$pass = 0; $fail = 0;
function check($l,$cond){ global $pass,$fail; $cond?$pass++:$fail++; echo '  ['.($cond?'PASS':'FAIL')."] $l\n"; }

echo "Total revenue (all-time paid): " . $s['total_revenue'] . "\n";
echo "Monthly revenue (this month, paid): " . $s['monthly_revenue'] . "\n\n";

echo "Revenue chart (paid, last 6 months, zero-filled):\n";
foreach ($s['revenue_chart'] as $r) printf("  %-9s revenue=%-12s orders=%s\n", $r['month'], $r['revenue'], $r['orders']);

check('chart has exactly 6 months', count($s['revenue_chart']) === 6);

// chronological + labels present
$labels = array_column($s['revenue_chart'], 'month');
check('all months labelled', count(array_filter($labels)) === 6);
check('last chart month == current month label', end($labels) === date('M Y'));

// KEY reconciliation: chart's current-month revenue must equal the Monthly Revenue card.
$lastRev = (float) end($s['revenue_chart'])['revenue'];
check('chart current-month revenue == monthly_revenue card', abs($lastRev - (float)$s['monthly_revenue']) < 0.005);

// 6-month paid revenue must be <= all-time paid revenue.
$sum6 = array_sum(array_column($s['revenue_chart'], 'revenue'));
check('sum(6-month paid) <= total paid revenue', $sum6 <= (float)$s['total_revenue'] + 0.005);
echo "  (6-month paid total = $sum6)\n";

echo "\n=== $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
