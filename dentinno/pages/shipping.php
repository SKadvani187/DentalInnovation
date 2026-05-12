<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Shipping';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'save_method') {
        $d = $data;
        if (!empty($d['id'])) {
            db()->execute("UPDATE shipping_methods SET name=?,description=?,type=?,base_cost=?,is_active=? WHERE id=?",
                [$d['name'],$d['description'],$d['type'],$d['base_cost'],$d['is_active'],$d['id']]);
            echo json_encode(['success'=>true,'message'=>'Shipping method updated']);
        } else {
            db()->insert("INSERT INTO shipping_methods (name,description,type,base_cost,is_active) VALUES (?,?,?,?,?)",
                [$d['name'],$d['description'],$d['type'],$d['base_cost'],$d['is_active']??1]);
            echo json_encode(['success'=>true,'message'=>'Shipping method created']);
        }
    } elseif ($action === 'delete_method') {
        db()->execute("DELETE FROM shipping_methods WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true,'message'=>'Method deleted']);
    } elseif ($action === 'toggle_method') {
        db()->execute("UPDATE shipping_methods SET is_active=NOT is_active WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true]);
    } elseif ($action === 'save_zone') {
        $d = $data;
        $states_json = json_encode(array_filter(array_map('trim', explode(',', $d['states']))));
        if (!empty($d['id'])) {
            db()->execute("UPDATE shipping_zones SET name=?,states=?,is_active=? WHERE id=?",[$d['name'],$states_json,$d['is_active'],$d['id']]);
        } else {
            db()->insert("INSERT INTO shipping_zones (name,states,is_active) VALUES (?,?,?)",[$d['name'],$states_json,1]);
        }
        echo json_encode(['success'=>true,'message'=>'Zone saved']);
    } elseif ($action === 'delete_zone') {
        db()->execute("DELETE FROM shipping_zones WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true]);
    } elseif ($action === 'save_rule') {
        $d = $data;
        if (!empty($d['id'])) {
            db()->execute("UPDATE shipping_rules SET method_id=?,zone_id=?,rule_type=?,min_value=?,max_value=?,cost=?,is_free=?,is_active=? WHERE id=?",
                [$d['method_id'],$d['zone_id']?:null,$d['rule_type'],$d['min_value'],$d['max_value']?:null,$d['cost'],$d['is_free']??0,$d['is_active']??1,$d['id']]);
        } else {
            db()->insert("INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,is_active) VALUES (?,?,?,?,?,?,?,?)",
                [$d['method_id'],$d['zone_id']?:null,$d['rule_type'],$d['min_value'],$d['max_value']?:null,$d['cost'],$d['is_free']??0,1]);
        }
        echo json_encode(['success'=>true,'message'=>'Rule saved']);
    } elseif ($action === 'delete_rule') {
        db()->execute("DELETE FROM shipping_rules WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true]);
    }
    exit;
}

$methods = db()->fetchAll("SELECT * FROM shipping_methods ORDER BY sort_order,id");
$zones   = db()->fetchAll("SELECT * FROM shipping_zones ORDER BY id");
$rules   = db()->fetchAll("SELECT r.*,m.name as method_name,z.name as zone_name FROM shipping_rules r LEFT JOIN shipping_methods m ON r.method_id=m.id LEFT JOIN shipping_zones z ON r.zone_id=z.id ORDER BY r.method_id,r.rule_type,r.min_value");

include __DIR__ . '/../includes/header.php';
?>
<style>
.ship-type-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
.type-flat{background:rgba(52,152,219,.15);color:#3498DB;}
.type-weight{background:rgba(155,89,182,.15);color:#9B59B6;}
.type-price{background:rgba(46,204,113,.15);color:#2ECC71;}
.type-product{background:rgba(241,196,15,.15);color:#F1C40F;}
.type-free{background:rgba(201,168,76,.15);color:var(--gold-primary);}
.type-flexible{background:rgba(231,76,60,.15);color:#E74C3C;}
.ship-tabs{display:flex;gap:0;margin-bottom:24px;background:var(--bg-elevated);border-radius:12px;padding:4px;}
.ship-tab{flex:1;padding:10px;text-align:center;background:none;border:none;color:var(--text-secondary);font-size:.85rem;font-weight:500;cursor:pointer;border-radius:8px;transition:.2s;font-family:inherit;}
.ship-tab.active{background:var(--bg-card);color:var(--gold-primary);box-shadow:0 2px 8px rgba(0,0,0,.3);}
.ship-section{display:none;} .ship-section.active{display:block;}
.rule-type-icon{width:32px;height:32px;border-radius:8px;display:grid;place-items:center;font-size:.85rem;}
</style>

<div class="page-header fade-in">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-truck" style="color:var(--gold-primary);margin-right:10px;"></i>Shipping Management</h1>
    <p>Configure methods, zones, weight-based, price-based and product-specific rules</p>
  </div>
</div>

<!-- Shipping Tabs -->
<div class="ship-tabs fade-in">
  <button class="ship-tab active" onclick="showShipTab('methods',this)"><i class="fa-solid fa-list-check" style="margin-right:6px;"></i>Methods</button>
  <button class="ship-tab" onclick="showShipTab('rules',this)"><i class="fa-solid fa-sliders" style="margin-right:6px;"></i>Rules</button>
  <button class="ship-tab" onclick="showShipTab('zones',this)"><i class="fa-solid fa-map-location-dot" style="margin-right:6px;"></i>Zones</button>
  <button class="ship-tab" onclick="showShipTab('calculator',this)"><i class="fa-solid fa-calculator" style="margin-right:6px;"></i>Calculator</button>
</div>

<!-- METHODS -->
<div id="ship-methods" class="ship-section active fade-in">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-family:'Playfair Display',serif;">Shipping Methods</h3>
    <button class="btn btn-gold btn-sm" onclick="openMethodModal()"><i class="fa-solid fa-plus"></i> Add Method</button>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
    <?php foreach($methods as $m): ?>
    <div class="card" style="padding:18px;" id="method-<?= $m['id'] ?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
        <div>
          <div style="font-weight:600;font-size:.95rem;margin-bottom:4px;"><?= htmlspecialchars($m['name']) ?></div>
          <span class="ship-type-badge type-<?= $m['type'] ?>"><i class="fa-solid fa-<?= ['flat'=>'truck','free'=>'gift','product'=>'box','weight'=>'weight-scale','price'=>'tag','flexible'=>'sliders'][$m['type']]??'truck' ?>"></i> <?= ucfirst($m['type']) ?></span>
        </div>
        <div style="display:flex;gap:6px;">
          <button class="btn btn-ghost btn-sm btn-icon" onclick='openMethodModal(<?= json_encode($m) ?>)'><i class="fa-solid fa-pen"></i></button>
          <button class="btn btn-ghost btn-sm btn-icon" onclick="toggleMethod(<?= $m['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $m['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
          <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteMethod(<?= $m['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
        </div>
      </div>
      <?php if($m['description']): ?><p style="color:var(--text-secondary);font-size:.82rem;margin-bottom:10px;"><?= htmlspecialchars($m['description']) ?></p><?php endif; ?>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="color:var(--gold-primary);font-weight:700;">Base: <?= $m['base_cost'] > 0 ? formatCurrency($m['base_cost']) : 'Calculated' ?></span>
        <span class="badge badge-<?= $m['is_active']?'success':'secondary' ?>"><?= $m['is_active']?'Active':'Inactive' ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($methods)): ?><div class="card" style="padding:24px;text-align:center;color:var(--text-muted);">No shipping methods yet. <a href="#" onclick="openMethodModal()" style="color:var(--gold-primary);">Add one</a></div><?php endif; ?>
  </div>
</div>

<!-- RULES -->
<div id="ship-rules" class="ship-section fade-in">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-family:'Playfair Display',serif;">Shipping Rules</h3>
    <button class="btn btn-gold btn-sm" onclick="openRuleModal()"><i class="fa-solid fa-plus"></i> Add Rule</button>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead><tr><th>Method</th><th>Zone</th><th>Rule Type</th><th>Range</th><th>Cost</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($rules as $r): ?>
          <tr>
            <td class="font-bold" style="font-size:.85rem;"><?= htmlspecialchars($r['method_name']) ?></td>
            <td><?= $r['zone_name'] ? htmlspecialchars($r['zone_name']) : '<span class="badge badge-secondary">All Zones</span>' ?></td>
            <td>
              <span class="ship-type-badge type-<?= ['weight'=>'weight','price'=>'price','quantity'=>'price','product'=>'product'][$r['rule_type']]??'flat' ?>">
                <i class="fa-solid fa-<?= ['weight'=>'weight-scale','price'=>'tag','quantity'=>'hashtag','product'=>'box'][$r['rule_type']]??'circle-dot' ?>"></i>
                <?= ucfirst($r['rule_type']) ?>
              </span>
            </td>
            <td>
              <?php
              $unit = ['weight'=>'kg','price'=>'₹','quantity'=>'units','product'=>'item'][$r['rule_type']] ?? '';
              $min = $r['rule_type']==='price' ? '₹'.number_format($r['min_value']) : $r['min_value'].' '.$unit;
              $max = $r['max_value'] ? ($r['rule_type']==='price'?'₹'.number_format($r['max_value']):$r['max_value'].' '.$unit) : 'above';
              echo "<span style='font-size:.82rem;'>{$min} – {$max}</span>";
              ?>
            </td>
            <td>
              <?php if($r['is_free']): ?>
              <span class="badge badge-success"><i class="fa-solid fa-gift"></i> FREE</span>
              <?php else: ?>
              <span class="font-bold" style="color:var(--gold-primary);"><?= formatCurrency($r['cost']) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $r['is_active']?'success':'secondary' ?>"><?= $r['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <div style="display:flex;gap:5px;">
                <button class="btn btn-ghost btn-sm btn-icon" onclick='openRuleModal(<?= json_encode($r) ?>)'><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteRule(<?= $r['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($rules)): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-sliders"></i><p>No rules configured yet</p></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ZONES -->
<div id="ship-zones" class="ship-section fade-in">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-family:'Playfair Display',serif;">Shipping Zones</h3>
    <button class="btn btn-gold btn-sm" onclick="openZoneModal()"><i class="fa-solid fa-plus"></i> Add Zone</button>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach($zones as $z):
      $states = $z['states'] ? json_decode($z['states'],true) : [];
    ?>
    <div class="card" style="padding:18px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div style="font-weight:600;"><i class="fa-solid fa-map-location-dot" style="color:var(--gold-primary);margin-right:7px;"></i><?= htmlspecialchars($z['name']) ?></div>
        <div style="display:flex;gap:5px;">
          <button class="btn btn-ghost btn-sm btn-icon" onclick='openZoneModal(<?= json_encode($z) ?>)'><i class="fa-solid fa-pen"></i></button>
          <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteZone(<?= $z['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
        </div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:5px;">
        <?php foreach(array_slice($states,0,6) as $s): ?><span class="badge badge-secondary" style="font-size:.72rem;"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
        <?php if(count($states)>6): ?><span class="text-muted" style="font-size:.75rem;">+<?= count($states)-6 ?> more</span><?php endif; ?>
        <?php if(empty($states)): ?><span class="text-muted" style="font-size:.8rem;">All states</span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- CALCULATOR -->
<div id="ship-calculator" class="ship-section fade-in">
  <h3 style="font-family:'Playfair Display',serif;margin-bottom:20px;">Shipping Cost Calculator</h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:800px;">
    <div class="card" style="padding:24px;">
      <h4 style="margin-bottom:16px;color:var(--gold-primary);">Order Details</h4>
      <div class="form-group"><label class="form-label">Order Total (₹)</label><input type="number" class="form-control" id="calc_price" placeholder="e.g. 15000" oninput="calcShipping()"></div>
      <div class="form-group"><label class="form-label">Total Weight (kg)</label><input type="number" step="0.1" class="form-control" id="calc_weight" placeholder="e.g. 3.5" oninput="calcShipping()"></div>
      <div class="form-group"><label class="form-label">Destination Zone</label>
        <select class="form-control" id="calc_zone" onchange="calcShipping()">
          <option value="">All Zones</option>
          <?php foreach($zones as $z): ?><option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="card" style="padding:24px;" id="calcResults">
      <h4 style="margin-bottom:16px;color:var(--gold-primary);">Applicable Methods</h4>
      <div id="calcOutput" style="color:var(--text-muted);text-align:center;padding:20px 0;"><i class="fa-solid fa-calculator" style="font-size:2rem;opacity:.3;"></i><br>Enter order details</div>
    </div>
  </div>
</div>

<!-- METHOD MODAL -->
<div class="modal-overlay" id="methodModal" style="display:none;" onclick="if(event.target===this)closeModal('methodModal')">
  <div class="modal-box" style="max-width:520px;">
    <div class="modal-head"><h2 id="methodModalTitle">Add Shipping Method</h2><button class="close-btn" onclick="closeModal('methodModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <input type="hidden" id="method_id">
      <div class="form-group"><label class="form-label">Method Name *</label><input type="text" class="form-control" id="method_name" placeholder="e.g. Express Delivery"></div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" id="method_desc" rows="2" placeholder="Brief description..."></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Type *</label>
          <select class="form-control" id="method_type">
            <option value="flat">Flat Rate</option><option value="free">Free Shipping</option>
            <option value="weight">Weight-Based</option><option value="price">Price-Based</option>
            <option value="product">Product-Based</option><option value="flexible">Flexible</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Base Cost (₹)</label><input type="number" class="form-control" id="method_cost" placeholder="0"></div>
      </div>
      <div class="form-group"><label class="form-label">Status</label><select class="form-control" id="method_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('methodModal')">Cancel</button>
      <button class="btn btn-gold" onclick="saveMethod()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
    </div>
  </div>
</div>

<!-- RULE MODAL -->
<div class="modal-overlay" id="ruleModal" style="display:none;" onclick="if(event.target===this)closeModal('ruleModal')">
  <div class="modal-box" style="max-width:560px;">
    <div class="modal-head"><h2 id="ruleModalTitle">Add Shipping Rule</h2><button class="close-btn" onclick="closeModal('ruleModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <input type="hidden" id="rule_id">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Shipping Method *</label>
          <select class="form-control" id="rule_method">
            <option value="">— Select Method —</option>
            <?php foreach($methods as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Zone (optional)</label>
          <select class="form-control" id="rule_zone">
            <option value="">All Zones</option>
            <?php foreach($zones as $z): ?><option value="<?= $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Rule Type *</label>
        <select class="form-control" id="rule_type">
          <option value="weight">Weight-Based (kg)</option><option value="price">Price-Based (₹)</option>
          <option value="quantity">Quantity-Based</option><option value="product">Product-Specific</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Min Value *</label><input type="number" step="0.01" class="form-control" id="rule_min" placeholder="0"></div>
        <div class="form-group"><label class="form-label">Max Value <small class="text-muted">(blank = unlimited)</small></label><input type="number" step="0.01" class="form-control" id="rule_max" placeholder="unlimited"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Shipping Cost (₹) *</label><input type="number" class="form-control" id="rule_cost" placeholder="0"></div>
        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="rule_free" style="width:16px;height:16px;accent-color:var(--gold-primary);" onchange="document.getElementById('rule_cost').disabled=this.checked;if(this.checked)document.getElementById('rule_cost').value=0;"><span class="form-label" style="margin:0;">Free Shipping</span></label>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('ruleModal')">Cancel</button>
      <button class="btn btn-gold" onclick="saveRule()"><i class="fa-solid fa-floppy-disk"></i> Save Rule</button>
    </div>
  </div>
</div>

<!-- ZONE MODAL -->
<div class="modal-overlay" id="zoneModal" style="display:none;" onclick="if(event.target===this)closeModal('zoneModal')">
  <div class="modal-box" style="max-width:480px;">
    <div class="modal-head"><h2>Shipping Zone</h2><button class="close-btn" onclick="closeModal('zoneModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
      <input type="hidden" id="zone_id">
      <div class="form-group"><label class="form-label">Zone Name *</label><input type="text" class="form-control" id="zone_name" placeholder="e.g. North India"></div>
      <div class="form-group"><label class="form-label">States <small class="text-muted">(comma-separated)</small></label><textarea class="form-control" id="zone_states" rows="3" placeholder="Maharashtra, Gujarat, Rajasthan, Delhi"></textarea></div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('zoneModal')">Cancel</button>
      <button class="btn btn-gold" onclick="saveZone()"><i class="fa-solid fa-floppy-disk"></i> Save Zone</button>
    </div>
  </div>
</div>

<script>
function showShipTab(name,btn){
  document.querySelectorAll('.ship-section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.ship-tab').forEach(b=>b.classList.remove('active'));
  document.getElementById('ship-'+name).classList.add('active');
  btn.classList.add('active');
}

// Methods
function openMethodModal(m=null){
  document.getElementById('method_id').value=m?.id||'';
  document.getElementById('method_name').value=m?.name||'';
  document.getElementById('method_desc').value=m?.description||'';
  document.getElementById('method_type').value=m?.type||'flat';
  document.getElementById('method_cost').value=m?.base_cost||'';
  document.getElementById('method_status').value=m?.is_active??1;
  document.getElementById('methodModalTitle').textContent=m?'Edit Shipping Method':'Add Shipping Method';
  openModal('methodModal');
}
async function saveMethod(){
  const name=document.getElementById('method_name').value.trim();
  if(!name){showToast('Method name required','warning');return;}
  const payload={action:'save_method',id:document.getElementById('method_id').value,name,
    description:document.getElementById('method_desc').value,type:document.getElementById('method_type').value,
    base_cost:document.getElementById('method_cost').value||0,is_active:document.getElementById('method_status').value};
  const res=await fetch('shipping.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  const r=await res.json();if(r.success){showToast(r.message,'success');closeModal('methodModal');setTimeout(()=>location.reload(),700);}
  else showToast(r.message,'danger');
}
function toggleMethod(id){
  fetch('shipping.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle_method',id})})
  .then(r=>r.json()).then(d=>{if(d.success)setTimeout(()=>location.reload(),300);});
}
function deleteMethod(id){
  showConfirm('Delete Method','Remove this shipping method?',async()=>{
    await fetch('shipping.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete_method',id})});
    showToast('Method deleted','success');setTimeout(()=>location.reload(),700);
  });
}

// Rules
function openRuleModal(r=null){
  document.getElementById('rule_id').value=r?.id||'';
  document.getElementById('rule_method').value=r?.method_id||'';
  document.getElementById('rule_zone').value=r?.zone_id||'';
  document.getElementById('rule_type').value=r?.rule_type||'weight';
  document.getElementById('rule_min').value=r?.min_value||'';
  document.getElementById('rule_max').value=r?.max_value||'';
  document.getElementById('rule_cost').value=r?.cost||'';
  document.getElementById('rule_free').checked=!!(r?.is_free);
  document.getElementById('rule_cost').disabled=!!(r?.is_free);
  document.getElementById('ruleModalTitle').textContent=r?'Edit Shipping Rule':'Add Shipping Rule';
  openModal('ruleModal');
}
async function saveRule(){
  const method_id=document.getElementById('rule_method').value;
  if(!method_id){showToast('Please select a shipping method','warning');return;}
  const payload={action:'save_rule',id:document.getElementById('rule_id').value,method_id,
    zone_id:document.getElementById('rule_zone').value,rule_type:document.getElementById('rule_type').value,
    min_value:document.getElementById('rule_min').value||0,max_value:document.getElementById('rule_max').value,
    cost:document.getElementById('rule_cost').value||0,is_free:document.getElementById('rule_free').checked?1:0};
  const res=await fetch('shipping.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  const r=await res.json();if(r.success){showToast(r.message,'success');closeModal('ruleModal');setTimeout(()=>location.reload(),700);}
}
function deleteRule(id){
  showConfirm('Delete Rule','Remove this shipping rule?',async()=>{
    await fetch('shipping.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete_rule',id})});
    showToast('Rule deleted','success');setTimeout(()=>location.reload(),700);
  });
}

// Zones
function openZoneModal(z=null){
  document.getElementById('zone_id').value=z?.id||'';
  document.getElementById('zone_name').value=z?.name||'';
  try{const states=z?.states?JSON.parse(z.states):[];document.getElementById('zone_states').value=states.join(', ');}catch(e){document.getElementById('zone_states').value='';}
  openModal('zoneModal');
}
async function saveZone(){
  const name=document.getElementById('zone_name').value.trim();
  if(!name){showToast('Zone name required','warning');return;}
  const payload={action:'save_zone',id:document.getElementById('zone_id').value,name,states:document.getElementById('zone_states').value};
  const res=await fetch('shipping.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  const r=await res.json();if(r.success){showToast(r.message,'success');closeModal('zoneModal');setTimeout(()=>location.reload(),700);}
}
function deleteZone(id){
  showConfirm('Delete Zone','Remove this shipping zone?',async()=>{
    await fetch('shipping.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete_zone',id})});
    showToast('Zone deleted','success');setTimeout(()=>location.reload(),700);
  });
}

// Calculator
const rulesData = <?= json_encode($rules) ?>;
function calcShipping(){
  const price=parseFloat(document.getElementById('calc_price').value)||0;
  const weight=parseFloat(document.getElementById('calc_weight').value)||0;
  const zone=document.getElementById('calc_zone').value;
  if(!price&&!weight){document.getElementById('calcOutput').innerHTML='<i class="fa-solid fa-calculator" style="font-size:2rem;opacity:.3;"></i><br><span style="color:var(--text-muted)">Enter order details</span>';return;}
  const methods={};
  rulesData.forEach(r=>{
    if(!r.is_active)return;
    if(zone&&r.zone_id&&r.zone_id!=zone)return;
    let match=false;
    if(r.rule_type==='weight'&&weight>0){const min=parseFloat(r.min_value);const max=r.max_value?parseFloat(r.max_value):Infinity;if(weight>=min&&weight<=max)match=true;}
    if(r.rule_type==='price'&&price>0){const min=parseFloat(r.min_value);const max=r.max_value?parseFloat(r.max_value):Infinity;if(price>=min&&price<=max)match=true;}
    if(match){
      const key=r.method_name;
      if(!methods[key]||parseFloat(r.cost)<parseFloat(methods[key].cost)){methods[key]={cost:r.is_free?0:r.cost,is_free:r.is_free,type:r.rule_type};}
    }
  });
  const entries=Object.entries(methods);
  if(!entries.length){document.getElementById('calcOutput').innerHTML='<span style="color:var(--text-muted)">No matching rules for these details</span>';return;}
  document.getElementById('calcOutput').innerHTML=entries.map(([name,info])=>`
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--bg-elevated);border-radius:8px;margin-bottom:8px;">
      <div><div style="font-weight:600;font-size:.9rem;">${name}</div><div style="font-size:.75rem;color:var(--text-muted);">${info.type}-based</div></div>
      <div style="font-weight:700;color:${info.is_free?'var(--success)':'var(--gold-primary)'};">${info.is_free?'<i class="fa-solid fa-gift"></i> FREE':'₹'+Number(info.cost).toLocaleString('en-IN')}</div>
    </div>`).join('');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
