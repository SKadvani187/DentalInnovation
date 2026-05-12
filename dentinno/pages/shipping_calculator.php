<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Shipping Calculator';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data   = json_decode(file_get_contents('php://input'), true);
    $price  = (float)($data['price'] ?? 0);
    $weight = (float)($data['weight'] ?? 0);
    $qty    = (int)($data['qty'] ?? 1);
    $zone   = (int)($data['zone_id'] ?? 0);

    $results = [];
    $methods = db()->fetchAll("SELECT * FROM shipping_methods WHERE is_active=1 ORDER BY sort_order");

    foreach ($methods as $method) {
        $cost = null;
        $is_free = false;
        $applicable_rule = null;

        if ($method['type'] === 'flat') {
            $cost = $method['base_cost'];
        } elseif ($method['type'] === 'free') {
            $cost = 0; $is_free = true;
        } else {
            // Find best matching rule
            $rules = db()->fetchAll(
                "SELECT * FROM shipping_rules WHERE method_id=? AND is_active=1 AND (zone_id IS NULL OR zone_id=?) ORDER BY min_value ASC",
                [$method['id'], $zone ?: 0]
            );
            foreach ($rules as $rule) {
                $val = match($rule['rule_type']) {
                    'weight'   => $weight,
                    'price'    => $price,
                    'quantity' => $qty,
                    default    => 0,
                };
                $max = $rule['max_value'] ?? PHP_FLOAT_MAX;
                if ($val >= $rule['min_value'] && $val <= $max) {
                    $cost = $rule['is_free'] ? 0 : $rule['cost'];
                    $is_free = (bool)$rule['is_free'];
                    $applicable_rule = $rule;
                    break;
                }
            }
        }

        if ($cost !== null) {
            $results[] = [
                'method_id'   => $method['id'],
                'name'        => $method['name'],
                'description' => $method['description'],
                'type'        => $method['type'],
                'cost'        => $cost,
                'is_free'     => $is_free,
                'rule_type'   => $applicable_rule['rule_type'] ?? null,
                'formatted'   => $is_free ? 'FREE' : '₹' . number_format($cost, 2),
            ];
        }
    }

    // Sort: free first, then cheapest
    usort($results, fn($a,$b) => $b['is_free'] <=> $a['is_free'] ?: $a['cost'] <=> $b['cost']);

    echo json_encode(['success'=>true,'results'=>$results,'inputs'=>['price'=>$price,'weight'=>$weight,'qty'=>$qty]]);
    exit;
}

$zones    = db()->fetchAll("SELECT * FROM shipping_zones WHERE is_active=1 ORDER BY name");
$products = db()->fetchAll("SELECT id,name,price,weight_kg FROM products WHERE is_active=1 ORDER BY name LIMIT 200");

include __DIR__ . '/../includes/header.php';
?>
<style>
.calc-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:24px;}
.result-item{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;background:var(--bg-elevated);border-radius:var(--radius);margin-bottom:8px;border:1px solid var(--border-color);transition:.2s;}
.result-item:hover{border-color:var(--border-active);}
.result-item.cheapest{border-color:var(--gold-primary);background:rgba(201,168,76,.06);}
.result-item.free-ship{border-color:var(--success);background:rgba(46,204,113,.06);}
.type-icon{width:36px;height:36px;border-radius:9px;display:grid;place-items:center;font-size:.9rem;flex-shrink:0;}
</style>

<div class="page-header fade-in">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-calculator" style="color:var(--gold-primary);margin-right:10px;"></i>Shipping Calculator</h1>
    <p>Calculate applicable shipping costs for any order combination</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;" class="fade-in">

  <!-- Inputs -->
  <div class="calc-card">
    <h3 style="font-family:'Playfair Display',serif;margin-bottom:18px;">Order Details</h3>

    <!-- Quick fill from product -->
    <div class="form-group">
      <label class="form-label">Quick Fill from Product</label>
      <select class="form-control" id="quickProduct" onchange="quickFill(this)">
        <option value="">— Select a product to auto-fill —</option>
        <?php foreach($products as $p): ?>
        <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" data-weight="<?= $p['weight_kg'] ?>"><?= htmlspecialchars($p['name']) ?> — <?= formatCurrency($p['price']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="height:1px;background:var(--border-color);margin:16px 0;"></div>

    <div class="form-group">
      <label class="form-label">Order Total (₹)</label>
      <input type="number" class="form-control" id="c_price" placeholder="e.g. 12500" oninput="calcDebounce()">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Total Weight (kg)</label>
        <input type="number" step="0.1" class="form-control" id="c_weight" placeholder="e.g. 2.5" oninput="calcDebounce()">
      </div>
      <div class="form-group">
        <label class="form-label">Qty / Items</label>
        <input type="number" class="form-control" id="c_qty" value="1" min="1" oninput="calcDebounce()">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Destination Zone</label>
      <select class="form-control" id="c_zone" onchange="calcDebounce()">
        <option value="">All Zones (Default)</option>
        <?php foreach($zones as $z): ?><option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-gold" style="width:100%;" onclick="calculate()">
      <i class="fa-solid fa-calculator"></i> Calculate Shipping
    </button>
  </div>

  <!-- Results -->
  <div class="calc-card">
    <h3 style="font-family:'Playfair Display',serif;margin-bottom:18px;">Available Shipping Options</h3>
    <div id="calcResults">
      <div style="text-align:center;padding:40px 0;color:var(--text-muted);">
        <i class="fa-solid fa-truck" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:12px;"></i>
        Enter order details to see applicable shipping methods
      </div>
    </div>
  </div>
</div>

<!-- Summary -->
<div class="card fade-in" style="margin-top:20px;padding:20px;" id="summaryCard" style="display:none;">
  <h3 style="font-family:'Playfair Display',serif;margin-bottom:14px;">Shipping Cost Summary</h3>
  <div id="summaryContent"></div>
</div>

<script>
const typeIcons = {flat:'truck',free:'gift',weight:'weight-scale',price:'tag',product:'box',flexible:'sliders'};
const typeColors= {flat:'#3498DB',free:'#2ECC71',weight:'#9B59B6',price:'#F39C12',product:'#C9A84C',flexible:'#E74C3C'};

function quickFill(sel){
  const opt=sel.selectedOptions[0];
  if(!opt.value)return;
  document.getElementById('c_price').value=opt.dataset.price||'';
  document.getElementById('c_weight').value=opt.dataset.weight||'';
  document.getElementById('c_qty').value=1;
  calculate();
}

let debounceTimer;
function calcDebounce(){ clearTimeout(debounceTimer); debounceTimer=setTimeout(calculate, 500); }

async function calculate(){
  const price=document.getElementById('c_price').value;
  const weight=document.getElementById('c_weight').value;
  if(!price&&!weight){return;}
  const payload={price:parseFloat(price)||0,weight:parseFloat(weight)||0,qty:parseInt(document.getElementById('c_qty').value)||1,zone_id:document.getElementById('c_zone').value||0};
  const res=await fetch('shipping_calculator.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  const data=await res.json();
  if(!data.success)return;
  renderResults(data.results, data.inputs);
}

function renderResults(results, inputs){
  const div=document.getElementById('calcResults');
  if(!results.length){
    div.innerHTML='<div style="text-align:center;padding:30px;color:var(--text-muted);">No shipping methods match these order details</div>';
    return;
  }
  div.innerHTML=results.map((r,i)=>`
    <div class="result-item ${r.is_free?'free-ship':i===0&&!r.is_free?'cheapest':''}">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="type-icon" style="background:${typeColors[r.type]||'#666'}20;">
          <i class="fa-solid fa-${typeIcons[r.type]||'truck'}" style="color:${typeColors[r.type]||'#666'};"></i>
        </div>
        <div>
          <div style="font-weight:600;font-size:.9rem;">${r.name}</div>
          ${r.description?`<div style="font-size:.75rem;color:var(--text-muted);">${r.description}</div>`:''}
          <div style="display:flex;gap:5px;margin-top:3px;">
            <span class="ship-type-badge type-${r.type}" style="font-size:.65rem;">${r.type}</span>
            ${r.rule_type?`<span style="font-size:.65rem;color:var(--text-muted);">via ${r.rule_type} rule</span>`:''}
          </div>
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:1.1rem;font-weight:700;color:${r.is_free?'var(--success)':'var(--gold-primary)'};">${r.formatted}</div>
        ${i===0&&!r.is_free?'<div style="font-size:.68rem;color:var(--gold-primary);font-weight:600;">BEST RATE</div>':''}
        ${r.is_free?'<div style="font-size:.68rem;color:var(--success);font-weight:600;">FREE</div>':''}
      </div>
    </div>`).join('');

  // Summary
  const cheapest=results[0];
  document.getElementById('summaryCard').style.display='block';
  document.getElementById('summaryContent').innerHTML=`
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
      <div style="background:var(--bg-elevated);padding:14px;border-radius:var(--radius);"><div class="stat-label">Order Value</div><div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);">₹${Number(inputs.price).toLocaleString('en-IN')}</div></div>
      <div style="background:var(--bg-elevated);padding:14px;border-radius:var(--radius);"><div class="stat-label">Total Weight</div><div style="font-size:1.1rem;font-weight:700;">${inputs.weight} kg</div></div>
      <div style="background:var(--bg-elevated);padding:14px;border-radius:var(--radius);"><div class="stat-label">Methods Available</div><div style="font-size:1.1rem;font-weight:700;color:var(--gold-primary);">${results.length}</div></div>
      <div style="background:var(--bg-elevated);padding:14px;border-radius:var(--radius);"><div class="stat-label">Cheapest Option</div><div style="font-size:1.1rem;font-weight:700;color:${cheapest.is_free?'var(--success)':'var(--gold-primary)'};">${cheapest.formatted}</div></div>
    </div>`;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
