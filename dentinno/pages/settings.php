<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Settings';

// Image upload for banners (shared products folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['banner_image'])) {
    header('Content-Type: application/json');
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['banner_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large']); exit; }
    $fname = 'banner_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/products/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Upload failed']); }
    exit;
}

// AJAX: save a site_settings key (JSON value) — used for storefront config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true);
    if (($d['action'] ?? '') === 'save_setting') {
        $key = preg_replace('/[^a-zA-Z]/', '', $d['key'] ?? '');
        $val = $d['value'] ?? null;
        if ($key) {
            db()->query("INSERT INTO site_settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)",
                [$key, json_encode($val)]);
            echo json_encode(['success'=>true,'message'=>'Saved']);
        } else { echo json_encode(['success'=>false,'message'=>'Invalid key']); }
    } else { echo json_encode(['success'=>false,'message'=>'Unknown action']); }
    exit;
}

// Load storefront site settings for forms
$siteRows = db()->fetchAll("SELECT skey, svalue FROM site_settings");
$site = [];
foreach ($siteRows as $r) { $site[$r['skey']] = json_decode($r['svalue'] ?? 'null', true); }
$company  = $site['company'] ?? [];

// Product list for "links to product" dropdowns (with description + image for auto-fill)
$linkProducts = db()->fetchAll("SELECT slug, name, description, JSON_EXTRACT(images,'$[0]') AS img FROM products WHERE is_active=1 ORDER BY name");
$linkCombos   = db()->fetchAll("SELECT slug, name, description, image AS img FROM combos WHERE is_active=1 ORDER BY name");
// normalize img (strip JSON quotes)
foreach ($linkProducts as &$lp) { $lp['img'] = trim((string)$lp['img'], '"'); } unset($lp);

// Handle profile update
$success_msg = '';
$error_msg   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        if ($name && $email) {
            db()->execute("UPDATE admin_users SET name=?, email=? WHERE id=?",
                [$name, $email, $_SESSION['admin_id']]);
            $_SESSION['admin_name']  = $name;
            $_SESSION['admin_email'] = $email;
            $success_msg = 'Profile updated successfully';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $admin   = db()->fetchOne("SELECT password FROM admin_users WHERE id=?", [$_SESSION['admin_id']]);
        if (!password_verify($current, $admin['password'])) {
            $error_msg = 'Current password is incorrect';
        } elseif (strlen($new) < 8) {
            $error_msg = 'New password must be at least 8 characters';
        } elseif ($new !== $confirm) {
            $error_msg = 'Passwords do not match';
        } else {
            db()->execute("UPDATE admin_users SET password=? WHERE id=?",
                [password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            $success_msg = 'Password changed successfully';
        }
    }
}

$current_admin = db()->fetchOne("SELECT * FROM admin_users WHERE id=?", [$_SESSION['admin_id']]);
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Settings</h1>
        <p>Manage your account and system preferences</p>
    </div>
</div>

<?php if ($success_msg): ?>
<div style="background:rgba(46,204,113,0.1);border:1px solid rgba(46,204,113,0.3);color:var(--success);padding:12px 16px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;" class="flash-msg">
    <i class="fa-solid fa-circle-check"></i> <?= $success_msg ?>
</div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div style="background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,0.3);color:var(--danger);padding:12px 16px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;" class="flash-msg">
    <i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?>
</div>
<?php endif; ?>

<div class="grid-2 fade-in">
    <!-- Profile Settings -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-user text-gold" style="margin-right:8px;"></i>Profile Settings</span>
        </div>
        <div class="card-body">
            <!-- Avatar -->
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding:16px;background:var(--bg-elevated);border-radius:10px;">
                <div style="width:64px;height:64px;border-radius:50%;background:var(--gold-gradient);color:var(--bg-base);font-size:1.5rem;font-weight:700;display:grid;place-items:center;border:3px solid rgba(201,168,76,0.3);">
                    <?= strtoupper(substr($current_admin['name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="font-bold" style="font-size:1rem;"><?= htmlspecialchars($current_admin['name']) ?></div>
                    <div class="text-muted"><?= $current_admin['email'] ?></div>
                    <span class="badge badge-warning" style="margin-top:6px;"><?= ucfirst(str_replace('_',' ',$current_admin['role'])) ?></span>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($current_admin['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($current_admin['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?= ucfirst(str_replace('_',' ',$current_admin['role'])) ?>" disabled style="opacity:0.6;">
                    <small class="text-muted" style="font-size:0.73rem;">Role can only be changed by a Super Admin</small>
                </div>
                <button type="submit" class="btn btn-gold">
                    <i class="fa-solid fa-floppy-disk"></i> Update Profile
                </button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-lock text-gold" style="margin-right:8px;"></i>Change Password</span>
        </div>
        <div class="card-body">
            <div style="padding:14px;background:rgba(201,168,76,0.06);border:1px solid rgba(201,168,76,0.15);border-radius:10px;margin-bottom:20px;">
                <div style="font-size:0.82rem;color:var(--text-secondary);">
                    <i class="fa-solid fa-shield-halved text-gold" style="margin-right:6px;"></i>
                    Use a strong password with at least 8 characters, including numbers and symbols.
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Min 8 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn btn-gold">
                    <i class="fa-solid fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>

    <!-- System Information -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-server text-gold" style="margin-right:8px;"></i>System Information</span>
        </div>
        <div class="card-body">
            <?php
            $sys = [
                'App Version'    => APP_VERSION,
                'PHP Version'    => phpversion(),
                'Server'         => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'Database'       => 'MySQL / MariaDB',
                'Timezone'       => TIMEZONE,
                'Current Date'   => date('d M Y, h:i A'),
            ];
            ?>
            <?php foreach ($sys as $key => $val): ?>
            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color);font-size:0.85rem;">
                <span class="text-muted"><?= $key ?></span>
                <span class="font-bold"><?= htmlspecialchars($val) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-database text-gold" style="margin-right:8px;"></i>Database Stats</span>
        </div>
        <div class="card-body">
            <?php
            $db_stats = [
                'Total Products'   => db()->fetchOne("SELECT COUNT(*) as c FROM products")['c'],
                'Total Categories' => db()->fetchOne("SELECT COUNT(*) as c FROM categories")['c'],
                'Total Customers'  => db()->fetchOne("SELECT COUNT(*) as c FROM customers")['c'],
                'Total Orders'     => db()->fetchOne("SELECT COUNT(*) as c FROM orders")['c'],
                'Active Coupons'   => db()->fetchOne("SELECT COUNT(*) as c FROM coupons WHERE is_active=1")['c'],
                'Admin Users'      => db()->fetchOne("SELECT COUNT(*) as c FROM admin_users")['c'],
            ];
            ?>
            <?php foreach ($db_stats as $key => $val): ?>
            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color);font-size:0.85rem;">
                <span class="text-muted"><?= $key ?></span>
                <span class="font-bold text-gold"><?= number_format($val) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Storefront Company / Contact Info -->
<div class="card fade-in" style="margin-top:24px;">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-store text-gold" style="margin-right:8px;"></i>Storefront Company Info</span>
        <small class="text-muted">Shown across the storefront (header, footer, contact page)</small>
    </div>
    <div class="card-body">
        <div class="grid-2" style="gap:16px;">
            <div class="form-group"><label class="form-label">Company Name</label><input type="text" class="form-control" id="co_name" value="<?= htmlspecialchars($company['name'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Tagline</label><input type="text" class="form-control" id="co_tagline" value="<?= htmlspecialchars($company['tagline'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Phone</label><input type="text" class="form-control" id="co_phone" value="<?= htmlspecialchars($company['phone'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Sales Phone</label><input type="text" class="form-control" id="co_phoneSales" value="<?= htmlspecialchars($company['phoneSales'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Email</label><input type="text" class="form-control" id="co_email" value="<?= htmlspecialchars($company['email'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Sales Email</label><input type="text" class="form-control" id="co_emailSales" value="<?= htmlspecialchars($company['emailSales'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">City</label><input type="text" class="form-control" id="co_city" value="<?= htmlspecialchars($company['city'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Pincode</label><input type="text" class="form-control" id="co_pincode" value="<?= htmlspecialchars($company['pincode'] ?? '') ?>"></div>
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Full Address</label><textarea class="form-control" id="co_address" rows="2"><?= htmlspecialchars($company['address'] ?? '') ?></textarea></div>
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Business Hours</label><input type="text" class="form-control" id="co_hours" value="<?= htmlspecialchars($company['hours'] ?? '') ?>"></div>
        </div>
        <button class="btn btn-gold" onclick="saveCompany()"><i class="fa-solid fa-floppy-disk"></i> Save Company Info</button>
    </div>
</div>

<script>
async function saveCompany() {
    const company = {
        name: document.getElementById('co_name').value,
        shortName: <?= json_encode($company['shortName'] ?? 'Dentinno') ?>,
        parent: <?= json_encode($company['parent'] ?? '') ?>,
        tagline: document.getElementById('co_tagline').value,
        description: <?= json_encode($company['description'] ?? '') ?>,
        city: document.getElementById('co_city').value,
        state: <?= json_encode($company['state'] ?? '') ?>,
        pincode: document.getElementById('co_pincode').value,
        address: document.getElementById('co_address').value,
        addressShort: <?= json_encode($company['addressShort'] ?? '') ?>,
        email: document.getElementById('co_email').value,
        emailSales: document.getElementById('co_emailSales').value,
        phone: document.getElementById('co_phone').value,
        phoneSales: document.getElementById('co_phoneSales').value,
        hours: document.getElementById('co_hours').value,
    };
    const res = await fetch('settings.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'save_setting',key:'company',value:company})});
    const r = await res.json();
    if(r.success){ showToast('Company info saved','success'); }
    else showToast(r.message||'Save failed','danger');
}

// Shared: save any setting key with a JSON value
async function saveSetting(key, value, label) {
    const res = await fetch('settings.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'save_setting',key,value})});
    const r = await res.json();
    showToast(r.success ? ((label||key)+' saved') : (r.message||'Save failed'), r.success?'success':'danger');
}
</script>

<?php
// Product picker <select>: shows product name, value = slug. $extra = inline JS oninput.
function productSelect($id, $value, $extra = '') {
    global $linkProducts, $linkCombos;
    $opts = '<option value="">— None —</option>';
    foreach ($linkProducts as $p) {
        $sel = ($p['slug'] === $value) ? 'selected' : '';
        $nm = htmlspecialchars($p['name']);
        $sl = htmlspecialchars($p['slug']);
        $opts .= "<option value=\"$sl\" $sel>$nm</option>";
    }
    foreach ($linkCombos as $c) {
        $sel = ($c['slug'] === $value) ? 'selected' : '';
        $nm = htmlspecialchars('[Combo] ' . $c['name']);
        $sl = htmlspecialchars($c['slug']);
        $opts .= "<option value=\"$sl\" $sel>$nm</option>";
    }
    return "<select class=\"form-control\" id=\"$id\" $extra>$opts</select>";
}

// Image upload box: clickable dropzone + preview, hidden URL input (id holds the value).
function imgUploadBox($id, $url, $onclick) {
    $u = htmlspecialchars($url);
    $hasImg = $url ? '' : 'style="display:none;"';
    $hasPh  = $url ? 'style="display:none;"' : '';
    return <<<HTML
<div class="img-up-box" onclick="$onclick" style="border:2px dashed var(--border-active);border-radius:10px;padding:10px;text-align:center;cursor:pointer;min-height:90px;display:flex;align-items:center;justify-content:center;position:relative;">
  <input type="hidden" id="$id" value="$u">
  <img id="{$id}_prev" src="$u" $hasImg style="max-height:90px;max-width:100%;border-radius:6px;object-fit:contain;">
  <div id="{$id}_ph" $hasPh style="color:var(--text-secondary);font-size:.82rem;">
    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.5rem;color:var(--gold-primary);display:block;margin-bottom:6px;"></i>
    Click to upload image
  </div>
</div>
HTML;
}

// Helper to render a JSON-config card with a textarea editor
function settingJsonCard($key, $title, $desc, $value) {
    $json = htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo <<<HTML
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-sliders text-gold" style="margin-right:8px;"></i>$title</span><small class="text-muted">$desc</small></div>
  <div class="card-body">
    <textarea class="form-control" id="json_$key" rows="8" style="font-family:monospace;font-size:.8rem;">$json</textarea>
    <button class="btn btn-gold" style="margin-top:10px;" onclick="saveJson('$key')"><i class="fa-solid fa-floppy-disk"></i> Save $title</button>
  </div>
</div>
HTML;
}
?>

<?php $bn = $site['banners'] ?? []; $promo = $bn['promo'] ?? []; ?>
<!-- Promo Banner Grid -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-panorama text-gold" style="margin-right:8px;"></i>Promo Banner Grid</span><small class="text-muted">3 home banners (desktop + mobile image) + product links</small></div>
  <div class="card-body">
    <?php
    $slots = [
      ['left','Left (large)','leftImg','leftImgM','leftId'],
      ['tr','Top Right','topRightImg','topRightImgM','topRightId'],
      ['br','Bottom Right','bottomRightImg','bottomRightImgM','bottomRightId'],
    ];
    foreach ($slots as $sl): ?>
    <div style="border:1px solid var(--border-color);border-radius:10px;padding:12px;margin-bottom:12px;">
      <div class="font-bold" style="margin-bottom:10px;"><?= $sl[1] ?></div>
      <div class="grid-2" style="gap:14px;">
        <div>
          <label class="form-label">Desktop Image</label>
          <?= imgUploadBox("pm_{$sl[0]}_d", $promo[$sl[2]] ?? '', "uploadPromo('{$sl[0]}','d')") ?>
        </div>
        <div>
          <label class="form-label">Mobile Image</label>
          <?= imgUploadBox("pm_{$sl[0]}_m", $promo[$sl[3]] ?? '', "uploadPromo('{$sl[0]}','m')") ?>
        </div>
      </div>
      <div class="form-group" style="margin-top:10px;"><label class="form-label">Opens Product (on click) <small class="text-muted">(optional)</small></label><?= productSelect("pm_{$sl[0]}_id", $promo[$sl[4]] ?? '') ?></div>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-gold" onclick="savePromo()"><i class="fa-solid fa-floppy-disk"></i> Save Promo Banners</button>
    <input type="file" id="promoFileInput" accept="image/*" style="display:none">
  </div>
</div>

<?php $rf = $site['rfSection'] ?? []; ?>
<!-- RF Cautery Showcase Section -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-bolt text-gold" style="margin-right:8px;"></i>RF Cautery Showcase</span><small class="text-muted">Featured product banner on home</small></div>
  <div class="card-body">
    <div class="grid-2" style="gap:14px;">
      <div class="form-group"><label class="form-label">Title</label><input type="text" class="form-control" id="rf_title" value="<?= htmlspecialchars($rf['title'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Opens Product (on click)</label><?= productSelect('rf_pid', $rf['productId'] ?? '') ?></div>
    </div>
    <div class="form-group"><label class="form-label">Main Image</label><?= imgUploadBox('rf_image', $rf['image'] ?? '', "uploadRf('rf_image')") ?></div>
    <div class="form-group"><label class="form-label">Short Description (mobile)</label><textarea class="form-control" id="rf_descShort" rows="2"><?= htmlspecialchars($rf['descShort'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">Full Description</label><textarea class="form-control" id="rf_description" rows="3"><?= htmlspecialchars($rf['description'] ?? '') ?></textarea></div>
    <label class="form-label" style="font-weight:700;margin-top:6px;">Features</label>
    <div id="rf_features"></div>
    <button class="btn btn-ghost btn-sm" onclick="addRfFeature()"><i class="fa-solid fa-plus"></i> Add Feature</button>
    <div style="margin-top:12px;"><button class="btn btn-gold" onclick="saveRf()"><i class="fa-solid fa-floppy-disk"></i> Save RF Section</button></div>
    <input type="file" id="rfFileInput" accept="image/*" style="display:none">
  </div>
</div>

<!-- Trust Badges -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-award text-gold" style="margin-right:8px;"></i>Trust Badges Strip</span><small class="text-muted">Home strip under category grid (Products / Service / Original / Price)</small></div>
  <div class="card-body">
    <div id="trust_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addTrustRow()"><i class="fa-solid fa-plus"></i> Add Badge</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="saveTrust()"><i class="fa-solid fa-floppy-disk"></i> Save Trust Badges</button>
    <div class="text-muted" style="font-size:.72rem;margin-top:8px;">Icon = Font Awesome class (e.g. <code>fa-solid fa-cube</code>). Tick "Live product count" to auto-prefix the product total.</div>
  </div>
</div>

<!-- Hero Slides (banners) -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-images text-gold" style="margin-right:8px;"></i>Hero Slider (Home Banners)</span><small class="text-muted">Top homepage carousel — image + product link</small></div>
  <div class="card-body">
    <div id="hero_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addHeroRow()"><i class="fa-solid fa-plus"></i> Add Slide</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="saveHero()"><i class="fa-solid fa-floppy-disk"></i> Save Hero Slides</button>
    <input type="file" id="heroFileInput" accept="image/*" style="display:none">
  </div>
</div>

<!-- Stats -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-chart-simple text-gold" style="margin-right:8px;"></i>Stats Bar</span><small class="text-muted">Homepage counters (Products, Followers, Rating, etc)</small></div>
  <div class="card-body">
    <div id="stats_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addStatRow()"><i class="fa-solid fa-plus"></i> Add Stat</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="saveStats()"><i class="fa-solid fa-floppy-disk"></i> Save Stats</button>
  </div>
</div>

<!-- Socials -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-share-nodes text-gold" style="margin-right:8px;"></i>Social Links</span></div>
  <div class="card-body">
    <div id="social_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addSocialRow()"><i class="fa-solid fa-plus"></i> Add Social</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="saveSocials()"><i class="fa-solid fa-floppy-disk"></i> Save Socials</button>
  </div>
</div>

<!-- Payments -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-credit-card text-gold" style="margin-right:8px;"></i>Payment Methods</span></div>
  <div class="card-body">
    <div id="pay_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addPayRow()"><i class="fa-solid fa-plus"></i> Add Payment</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="savePayments()"><i class="fa-solid fa-floppy-disk"></i> Save Payments</button>
  </div>
</div>

<!-- Product Benefits -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-shield-halved text-gold" style="margin-right:8px;"></i>Product Benefits</span><small class="text-muted">Secure Payment / Replacement / Genuine badges</small></div>
  <div class="card-body">
    <div id="benefit_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addBenefitRow()"><i class="fa-solid fa-plus"></i> Add Benefit</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="saveBenefits()"><i class="fa-solid fa-floppy-disk"></i> Save Benefits</button>
  </div>
</div>

<!-- Pricing rules: bulk + tier + freeGifts threshold + priceBounds + productDefaults -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-tags text-gold" style="margin-right:8px;"></i>Pricing & Offers Rules</span></div>
  <div class="card-body">
    <div class="grid-2" style="gap:16px;">
      <div class="form-group"><label class="form-label">Bulk Min Qty</label><input type="number" class="form-control" id="bulk_minQty" value="<?= htmlspecialchars($site['bulkRule']['minQty'] ?? 2) ?>"></div>
      <div class="form-group"><label class="form-label">Bulk Rate (e.g. 0.1 = 10%)</label><input type="number" step="0.01" class="form-control" id="bulk_rate" value="<?= htmlspecialchars($site['bulkRule']['rate'] ?? 0.1) ?>"></div>
      <div class="form-group"><label class="form-label">Free Gift Threshold (₹)</label><input type="number" class="form-control" id="fg_threshold" value="<?= htmlspecialchars($site['freeGifts']['threshold'] ?? 5000) ?>"></div>
      <div class="form-group"><label class="form-label">Delivery Days</label><input type="text" class="form-control" id="pd_deliveryDays" value="<?= htmlspecialchars($site['productDefaults']['deliveryDays'] ?? '3–5 business days') ?>"></div>
      <div class="form-group"><label class="form-label">Price Min (₹)</label><input type="number" class="form-control" id="pb_min" value="<?= htmlspecialchars($site['priceBounds']['min'] ?? 10) ?>"></div>
      <div class="form-group"><label class="form-label">Price Max (₹)</label><input type="number" class="form-control" id="pb_max" value="<?= htmlspecialchars($site['priceBounds']['max'] ?? 500000) ?>"></div>
    </div>
    <button class="btn btn-gold" onclick="savePricingRules()"><i class="fa-solid fa-floppy-disk"></i> Save Pricing Rules</button>
  </div>
</div>

<?php
function listCard($id, $title, $desc, $addLabel, $saveFn, $addFn) {
  echo <<<HTML
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-list text-gold" style="margin-right:8px;"></i>$title</span><small class="text-muted">$desc</small></div>
  <div class="card-body">
    <div id="{$id}_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="$addFn()"><i class="fa-solid fa-plus"></i> $addLabel</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="$saveFn()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
  </div>
</div>
HTML;
}
listCard('tier', 'Tier Offers', 'Bulk discount tiers (Buy 2 / Buy 5)', 'Add Tier', 'saveTiers', 'addTierRow');
listCard('fbt', 'Frequently Bought Together', 'Cart cross-sell items', 'Add Item', 'saveFbt', 'addFbtRow');
listCard('fg', 'Free Gift Items', 'Gifts unlocked above threshold', 'Add Gift', 'saveFreeGifts', 'addFgRow');
listCard('feat', 'Featured Showcase Cards', 'Home featured product banners', 'Add Card', 'saveFeatured', 'addFeatRow');
listCard('sort', 'Sort Options', 'Category / combos sort dropdown', 'Add Option', 'saveSort', 'addSortRow');
listCard('pp', 'Price Presets', 'Shop-by-price quick filters', 'Add Preset', 'savePresets', 'addPpRow');
?>

<!-- Product Content (FAQ / Highlights / Accordions) -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-circle-question text-gold" style="margin-right:8px;"></i>Product Content (Default FAQ / Highlights)</span><small class="text-muted">Default content shown on product pages</small></div>
  <div class="card-body">
    <label class="form-label" style="font-weight:700;">Highlights</label>
    <div id="pc_highlights"></div>
    <button class="btn btn-ghost btn-sm" onclick="addHighlight()"><i class="fa-solid fa-plus"></i> Add Highlight</button>
    <label class="form-label" style="font-weight:700;margin-top:14px;">Accordions</label>
    <div id="pc_accordions"></div>
    <button class="btn btn-ghost btn-sm" onclick="addAccordion()"><i class="fa-solid fa-plus"></i> Add Accordion</button>
    <label class="form-label" style="font-weight:700;margin-top:14px;">FAQs</label>
    <div id="pc_faqs"></div>
    <button class="btn btn-ghost btn-sm" onclick="addFaq()"><i class="fa-solid fa-plus"></i> Add FAQ</button>
    <div style="margin-top:12px;"><button class="btn btn-gold" onclick="saveProductContent()"><i class="fa-solid fa-floppy-disk"></i> Save Product Content</button></div>
    <input type="file" id="fbtFileInput" accept="image/*" style="display:none">
    <input type="file" id="fgFileInput" accept="image/*" style="display:none">
    <input type="file" id="featFileInput" accept="image/*" style="display:none">
  </div>
</div>

<!-- Premium Categories (form) -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-star text-gold" style="margin-right:8px;"></i>Premium Categories</span><small class="text-muted">Home premium showcase cards (image + title + description)</small></div>
  <div class="card-body">
    <div id="premium_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addPremiumRow()"><i class="fa-solid fa-plus"></i> Add Card</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="savePremium()"><i class="fa-solid fa-floppy-disk"></i> Save Premium Categories</button>
    <input type="file" id="premiumFileInput" accept="image/*" style="display:none">
  </div>
</div>

<script>
// ---- Stats ----
let STATS = <?= json_encode($site['stats'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderStats(){ document.getElementById('stats_rows').innerHTML = STATS.map((s,i)=>`
  <div style="display:flex;gap:6px;margin-bottom:6px;">
    <input class="form-control" placeholder="Value" value="${(s.value||'').toString().replace(/"/g,'&quot;')}" oninput="STATS[${i}].value=this.value" style="flex:1;">
    <input class="form-control" placeholder="Suffix" value="${(s.suffix||'').replace(/"/g,'&quot;')}" oninput="STATS[${i}].suffix=this.value" style="width:80px;">
    <input class="form-control" placeholder="Label" value="${(s.label||'').replace(/"/g,'&quot;')}" oninput="STATS[${i}].label=this.value" style="flex:1;">
    <button class="btn btn-ghost btn-sm" onclick="STATS.splice(${i},1);renderStats()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addStatRow(){ STATS.push({value:'',suffix:'',label:''}); renderStats(); }
function saveStats(){ saveSetting('stats', STATS, 'Stats'); }

// ---- Socials ----
let SOCIALS = <?= json_encode($site['socials'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderSocials(){ document.getElementById('social_rows').innerHTML = SOCIALS.map((s,i)=>`
  <div style="display:flex;gap:6px;margin-bottom:6px;">
    <input class="form-control" placeholder="id" value="${(s.id||'').replace(/"/g,'&quot;')}" oninput="SOCIALS[${i}].id=this.value" style="width:110px;">
    <input class="form-control" placeholder="Label" value="${(s.label||'').replace(/"/g,'&quot;')}" oninput="SOCIALS[${i}].label=this.value" style="flex:1;">
    <input class="form-control" placeholder="URL" value="${(s.url||'').replace(/"/g,'&quot;')}" oninput="SOCIALS[${i}].url=this.value" style="flex:2;">
    <button class="btn btn-ghost btn-sm" onclick="SOCIALS.splice(${i},1);renderSocials()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addSocialRow(){ SOCIALS.push({id:'',label:'',url:''}); renderSocials(); }
function saveSocials(){ saveSetting('socials', SOCIALS, 'Socials'); }

// ---- Payments ----
let PAYMENTS = <?= json_encode($site['payments'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderPay(){ document.getElementById('pay_rows').innerHTML = PAYMENTS.map((p,i)=>`
  <div style="display:flex;gap:6px;margin-bottom:6px;">
    <input class="form-control" placeholder="id" value="${(p.id||'').replace(/"/g,'&quot;')}" oninput="PAYMENTS[${i}].id=this.value" style="width:130px;">
    <input class="form-control" placeholder="Label" value="${(p.label||'').replace(/"/g,'&quot;')}" oninput="PAYMENTS[${i}].label=this.value" style="flex:1;">
    <button class="btn btn-ghost btn-sm" onclick="PAYMENTS.splice(${i},1);renderPay()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addPayRow(){ PAYMENTS.push({id:'',label:''}); renderPay(); }
function savePayments(){ saveSetting('payments', PAYMENTS, 'Payments'); }

// ---- Benefits ----
let BENEFITS = <?= json_encode($site['productBenefits'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderBenefits(){ document.getElementById('benefit_rows').innerHTML = BENEFITS.map((b,i)=>`
  <div style="display:flex;gap:6px;margin-bottom:6px;">
    <input class="form-control" placeholder="id" value="${(b.id||'').replace(/"/g,'&quot;')}" oninput="BENEFITS[${i}].id=this.value" style="width:110px;">
    <input class="form-control" placeholder="Label" value="${(b.label||'').replace(/"/g,'&quot;')}" oninput="BENEFITS[${i}].label=this.value" style="flex:1;">
    <input class="form-control" placeholder="icon (shield/x/refresh/check)" value="${(b.icon||'').replace(/"/g,'&quot;')}" oninput="BENEFITS[${i}].icon=this.value" style="width:160px;">
    <button class="btn btn-ghost btn-sm" onclick="BENEFITS.splice(${i},1);renderBenefits()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addBenefitRow(){ BENEFITS.push({id:'',label:'',icon:'check'}); renderBenefits(); }
function saveBenefits(){ saveSetting('productBenefits', BENEFITS, 'Benefits'); }

// ---- Pricing rules ----
function savePricingRules(){
    saveSetting('bulkRule', { minQty: parseInt(document.getElementById('bulk_minQty').value)||2, rate: parseFloat(document.getElementById('bulk_rate').value)||0.1 });
    const fg = <?= json_encode($site['freeGifts'] ?? ['items'=>[]], JSON_UNESCAPED_SLASHES) ?>;
    fg.threshold = parseInt(document.getElementById('fg_threshold').value)||5000;
    saveSetting('freeGifts', fg);
    saveSetting('priceBounds', { min: parseInt(document.getElementById('pb_min').value)||10, max: parseInt(document.getElementById('pb_max').value)||500000 });
    const pd = <?= json_encode($site['productDefaults'] ?? [], JSON_UNESCAPED_SLASHES) ?>;
    pd.deliveryDays = document.getElementById('pd_deliveryDays').value;
    saveSetting('productDefaults', pd, 'Pricing rules');
}

// ---- Tier Offers ----
let TIERS = <?= json_encode($site['tierOffers'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderTiers(){ document.getElementById('tier_rows').innerHTML = TIERS.map((t,i)=>`
  <div style="display:flex;gap:6px;margin-bottom:6px;align-items:center;">
    <input class="form-control" type="number" placeholder="Min Qty" value="${t.minQty||''}" oninput="TIERS[${i}].minQty=parseInt(this.value)||0" style="width:110px;">
    <input class="form-control" type="number" step="0.01" placeholder="Rate (0.05=5%)" value="${t.rate||''}" oninput="TIERS[${i}].rate=parseFloat(this.value)||0" style="width:150px;">
    <input class="form-control" placeholder="Label (Buy 2 or above)" value="${(t.label||'').replace(/"/g,'&quot;')}" oninput="TIERS[${i}].label=this.value" style="flex:1;">
    <button class="btn btn-ghost btn-sm" onclick="TIERS.splice(${i},1);renderTiers()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addTierRow(){ TIERS.push({minQty:2,rate:0.05,label:''}); renderTiers(); }
function saveTiers(){ saveSetting('tierOffers', TIERS, 'Tier offers'); }

// ---- FBT items ----
let FBT = <?= json_encode($site['fbtItems'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderFbt(){ document.getElementById('fbt_rows').innerHTML = FBT.map((f,i)=>`
  <div style="border:1px solid var(--border-color);border-radius:8px;padding:8px;margin-bottom:8px;display:flex;gap:8px;align-items:center;">
    <div onclick="uploadFbt(${i})" style="width:56px;height:56px;border:2px dashed var(--border-active);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
      ${f.image?`<img src="${(f.image||'').replace(/"/g,'&quot;')}" style="width:100%;height:100%;object-fit:contain;">`:`<i class="fa-solid fa-upload text-gold"></i>`}
    </div>
    <input class="form-control" placeholder="Name" value="${(f.name||'').replace(/"/g,'&quot;')}" oninput="FBT[${i}].name=this.value" style="flex:2;">
    <input class="form-control" type="number" placeholder="MRP" value="${f.mrp||''}" oninput="FBT[${i}].mrp=parseFloat(this.value)||0" style="width:90px;">
    <input class="form-control" type="number" placeholder="Price" value="${f.price||''}" oninput="FBT[${i}].price=parseFloat(this.value)||0" style="width:90px;">
    <button class="btn btn-ghost btn-sm" onclick="FBT.splice(${i},1);renderFbt()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addFbtRow(){ FBT.push({id:'fbt-'+Date.now(),name:'',mrp:0,price:0,warranty:'',discount:0,image:''}); renderFbt(); }
function uploadFbt(i){ genericUpload((url)=>{ FBT[i].image=url; renderFbt(); })('fbtFileInput'); }
function saveFbt(){ saveSetting('fbtItems', FBT, 'Frequently bought'); }

// ---- Free Gifts ----
let FG = <?= json_encode($site['freeGifts'] ?? ['threshold'=>5000,'items'=>[]], JSON_UNESCAPED_SLASHES) ?>;
if(!FG.items) FG.items = [];
function renderFg(){ document.getElementById('fg_rows').innerHTML = FG.items.map((g,i)=>`
  <div style="border:1px solid var(--border-color);border-radius:8px;padding:8px;margin-bottom:8px;display:flex;gap:8px;align-items:center;">
    <div onclick="uploadFg(${i})" style="width:56px;height:56px;border:2px dashed var(--border-active);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
      ${g.image?`<img src="${(g.image||'').replace(/"/g,'&quot;')}" style="width:100%;height:100%;object-fit:contain;">`:`<i class="fa-solid fa-upload text-gold"></i>`}
    </div>
    <input class="form-control" placeholder="Gift name" value="${(g.name||'').replace(/"/g,'&quot;')}" oninput="FG.items[${i}].name=this.value" style="flex:2;">
    <input class="form-control" type="number" placeholder="MRP" value="${g.mrp||''}" oninput="FG.items[${i}].mrp=parseFloat(this.value)||0" style="width:100px;">
    <button class="btn btn-ghost btn-sm" onclick="FG.items.splice(${i},1);renderFg()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addFgRow(){ FG.items.push({id:'g-'+Date.now(),name:'',mrp:0,image:''}); renderFg(); }
function uploadFg(i){ genericUpload((url)=>{ FG.items[i].image=url; renderFg(); })('fgFileInput'); }
function saveFreeGifts(){ saveSetting('freeGifts', FG, 'Free gifts'); }

// ---- Featured cards ----
let FEAT = <?= json_encode($site['featured'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderFeat(){ document.getElementById('feat_rows').innerHTML = FEAT.map((f,i)=>`
  <div style="border:1px solid var(--border-color);border-radius:8px;padding:10px;margin-bottom:8px;display:flex;gap:10px;align-items:flex-start;">
    <div onclick="uploadFeat(${i})" style="width:70px;height:70px;border:2px dashed var(--border-active);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
      ${f.image?`<img src="${(f.image||'').replace(/"/g,'&quot;')}" style="width:100%;height:100%;object-fit:contain;">`:`<i class="fa-solid fa-upload text-gold"></i>`}
    </div>
    <div style="flex:1;">
      <input class="form-control" placeholder="Title" value="${(f.title||'').replace(/"/g,'&quot;')}" oninput="FEAT[${i}].title=this.value" style="margin-bottom:6px;">
      <input class="form-control" placeholder="Tagline" value="${(f.tagline||'').replace(/"/g,'&quot;')}" oninput="FEAT[${i}].tagline=this.value" style="margin-bottom:6px;">
      <textarea class="form-control" placeholder="Description" rows="2" oninput="FEAT[${i}].description=this.value">${(f.description||'')}</textarea>
      <div style="display:flex;gap:6px;margin-top:6px;">
        <input class="form-control" type="number" placeholder="MRP" value="${f.mrp||''}" oninput="FEAT[${i}].mrp=parseFloat(this.value)||0" style="width:100px;">
        <input class="form-control" type="number" placeholder="Price" value="${f.price||''}" oninput="FEAT[${i}].price=parseFloat(this.value)||0" style="width:100px;">
        <select class="form-control" onchange="FEAT[${i}].productId=this.value" style="flex:1;">${productOptions(f.productId||'')}</select>
      </div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="FEAT.splice(${i},1);renderFeat()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addFeatRow(){ FEAT.push({id:'f-'+Date.now(),title:'',tagline:'',description:'',image:'',mrp:0,price:0,productId:'',bullets:[]}); renderFeat(); }
function uploadFeat(i){ genericUpload((url)=>{ FEAT[i].image=url; renderFeat(); })('featFileInput'); }
function saveFeatured(){ saveSetting('featured', FEAT, 'Featured'); }

// ---- Sort options ----
let SORT = <?= json_encode($site['sortOptions'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderSort(){ document.getElementById('sort_rows').innerHTML = SORT.map((o,i)=>`
  <div style="display:flex;gap:6px;margin-bottom:6px;">
    <input class="form-control" placeholder="id (price-asc)" value="${(o.id||'').replace(/"/g,'&quot;')}" oninput="SORT[${i}].id=this.value" style="width:160px;">
    <input class="form-control" placeholder="Label" value="${(o.label||'').replace(/"/g,'&quot;')}" oninput="SORT[${i}].label=this.value" style="flex:1;">
    <button class="btn btn-ghost btn-sm" onclick="SORT.splice(${i},1);renderSort()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addSortRow(){ SORT.push({id:'',label:''}); renderSort(); }
function saveSort(){ saveSetting('sortOptions', SORT, 'Sort options'); }

// ---- Price presets ----
let PP = <?= json_encode($site['pricePresets'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderPp(){ document.getElementById('pp_rows').innerHTML = PP.map((p,i)=>`
  <div style="display:flex;gap:6px;margin-bottom:6px;">
    <input class="form-control" placeholder="Label (Below ₹499)" value="${(p.label||'').replace(/"/g,'&quot;')}" oninput="PP[${i}].label=this.value" style="flex:1;">
    <input class="form-control" type="number" placeholder="Max ₹" value="${p.max||''}" oninput="PP[${i}].max=parseInt(this.value)||0" style="width:120px;">
    <button class="btn btn-ghost btn-sm" onclick="PP.splice(${i},1);renderPp()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addPpRow(){ PP.push({label:'',max:0}); renderPp(); }
function savePresets(){ saveSetting('pricePresets', PP, 'Price presets'); }

// ---- Product Content (highlights / accordions / faqs) ----
let PC = <?= json_encode($site['productContent'] ?? ['highlights'=>[],'accordions'=>[],'faqs'=>[]], JSON_UNESCAPED_SLASHES) ?>;
PC.highlights = PC.highlights||[]; PC.accordions = PC.accordions||[]; PC.faqs = PC.faqs||[];
function renderPC(){
  document.getElementById('pc_highlights').innerHTML = PC.highlights.map((h,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="Title" value="${(h.title||'').replace(/"/g,'&quot;')}" oninput="PC.highlights[${i}].title=this.value" style="flex:1;">
      <input class="form-control" placeholder="Text" value="${(h.text||'').replace(/"/g,'&quot;')}" oninput="PC.highlights[${i}].text=this.value" style="flex:2;">
      <button class="btn btn-ghost btn-sm" onclick="PC.highlights.splice(${i},1);renderPC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
  document.getElementById('pc_accordions').innerHTML = PC.accordions.map((a,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="Title" value="${(a.title||'').replace(/"/g,'&quot;')}" oninput="PC.accordions[${i}].title=this.value" style="flex:1;">
      <input class="form-control" placeholder="Body" value="${(a.body||'').replace(/"/g,'&quot;')}" oninput="PC.accordions[${i}].body=this.value" style="flex:2;">
      <button class="btn btn-ghost btn-sm" onclick="PC.accordions.splice(${i},1);renderPC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
  document.getElementById('pc_faqs').innerHTML = PC.faqs.map((q,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="Question" value="${(q.q||'').replace(/"/g,'&quot;')}" oninput="PC.faqs[${i}].q=this.value" style="flex:1;">
      <input class="form-control" placeholder="Answer" value="${(q.a||'').replace(/"/g,'&quot;')}" oninput="PC.faqs[${i}].a=this.value" style="flex:2;">
      <button class="btn btn-ghost btn-sm" onclick="PC.faqs.splice(${i},1);renderPC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
}
function addHighlight(){ PC.highlights.push({title:'',text:''}); renderPC(); }
function addAccordion(){ PC.accordions.push({id:'acc-'+Date.now(),title:'',body:''}); renderPC(); }
function addFaq(){ PC.faqs.push({id:'f-'+Date.now(),q:'',a:''}); renderPC(); }
function saveProductContent(){ saveSetting('productContent', PC, 'Product content'); }

// Product options for "links to product" dropdowns (slug -> name + description + image)
const PRODUCT_OPTS = <?= json_encode(array_merge(
    array_map(fn($p) => ['slug'=>$p['slug'],'name'=>$p['name'],'description'=>$p['description'],'image'=>$p['img']], $linkProducts),
    array_map(fn($c) => ['slug'=>$c['slug'],'name'=>'[Combo] '.$c['name'],'description'=>$c['description'],'image'=>$c['img']], $linkCombos)
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
function productOptions(selected){
  let html = '<option value="">— None —</option>';
  for (const p of PRODUCT_OPTS) {
    const sel = p.slug === selected ? 'selected' : '';
    html += `<option value="${p.slug}" ${sel}>${p.name.replace(/</g,'&lt;')}</option>`;
  }
  return html;
}
function productById(slug){ return PRODUCT_OPTS.find(p => p.slug === slug); }

// ---- Hero Slides ----
let HERO = <?= json_encode($site['heroSlides'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderHero(){ document.getElementById('hero_rows').innerHTML = HERO.map((h,i)=>`
  <div style="display:flex;gap:10px;margin-bottom:10px;align-items:center;border:1px solid var(--border-color);border-radius:10px;padding:10px;">
    <div onclick="uploadHero(${i})" style="width:120px;height:60px;border:2px dashed var(--border-active);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
      ${h.src ? `<img src="${(h.src||'').replace(/"/g,'&quot;')}" style="width:100%;height:100%;object-fit:cover;">` : `<span style="color:var(--gold-primary);font-size:.7rem;text-align:center;"><i class="fa-solid fa-cloud-arrow-up"></i><br>Upload</span>`}
    </div>
    <select class="form-control" onchange="HERO[${i}].productId=this.value" style="flex:1;">${productOptions(h.productId||'')}</select>
    <button class="btn btn-ghost btn-sm" onclick="HERO.splice(${i},1);renderHero()" title="Remove"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addHeroRow(){ HERO.push({src:'',productId:''}); renderHero(); }
function uploadHero(i){
  const inp = document.getElementById('heroFileInput');
  inp.onchange = async () => {
    const file = inp.files[0]; if(!file) return;
    const fd = new FormData(); fd.append('banner_image', file);
    const res = await fetch('settings.php',{method:'POST',body:fd});
    const data = await res.json();
    if(data.success){ HERO[i].src = data.url; renderHero(); } else showToast(data.message,'danger');
    inp.value='';
  };
  inp.click();
}
function saveHero(){ saveSetting('heroSlides', HERO, 'Hero slides'); }

// ---- Promo + Patti banners ----
function genericUpload(cb){
  return (inputId) => {
    const inp = document.getElementById(inputId);
    inp.onchange = async () => {
      const file = inp.files[0]; if(!file) return;
      const fd = new FormData(); fd.append('banner_image', file);
      const res = await fetch('settings.php',{method:'POST',body:fd});
      const data = await res.json();
      if(data.success){ cb(data.url); } else showToast(data.message,'danger');
      inp.value='';
    };
    inp.click();
  };
}
function setImg(id, url){
  document.getElementById(id).value = url;
  const prev = document.getElementById(id+'_prev'); const ph = document.getElementById(id+'_ph');
  if(prev){ prev.src = url; prev.style.display = url?'':'none'; }
  if(ph){ ph.style.display = url?'none':''; }
}
function uploadPromo(slot, variant){
  genericUpload((url)=> setImg(`pm_${slot}_${variant}`, url))('promoFileInput');
}
function uploadRf(id){
  genericUpload((url)=> setImg(id, url))('rfFileInput');
}

// ---- Premium Categories ----
let PREMIUM = <?= json_encode($site['premiumCategories'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderPremium(){ document.getElementById('premium_rows').innerHTML = PREMIUM.map((c,i)=>`
  <div style="border:1px solid var(--border-color);border-radius:8px;padding:10px;margin-bottom:8px;display:flex;gap:10px;align-items:flex-start;">
    <div onclick="uploadPremium(${i})" style="width:80px;height:80px;border:2px dashed var(--border-active);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
      ${c.imgSrc ? `<img src="${(c.imgSrc||'').replace(/"/g,'&quot;')}" style="width:100%;height:100%;object-fit:contain;">` : `<i class="fa-solid fa-upload text-gold"></i>`}
    </div>
    <div style="flex:1;">
      <input class="form-control" placeholder="Card title" value="${(c.title||'').replace(/"/g,'&quot;')}" oninput="PREMIUM[${i}].title=this.value" style="margin-bottom:6px;">
      <textarea class="form-control" placeholder="Description" rows="3" oninput="PREMIUM[${i}].description=this.value">${(c.description||'')}</textarea>
      <label class="form-label" style="margin-top:6px;display:block;font-size:.72rem;">Pick Product (auto-fills title / description / image)</label>
      <select class="form-control" onchange="premiumPick(${i}, this.value)">${productOptions(c.id||'')}</select>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="PREMIUM.splice(${i},1);renderPremium()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addPremiumRow(){ PREMIUM.push({id:'',title:'',description:'',imgSrc:''}); renderPremium(); }
function premiumPick(i, slug){
  PREMIUM[i].id = slug;
  const p = productById(slug);
  if (p) {  // auto-fill from the chosen product
    PREMIUM[i].title = p.name.replace(/^\[Combo\]\s*/, '');
    PREMIUM[i].description = p.description || PREMIUM[i].description;
    if (p.image) PREMIUM[i].imgSrc = p.image;
  }
  renderPremium();
}
function uploadPremium(i){
  genericUpload((url)=>{ PREMIUM[i].imgSrc=url; renderPremium(); })('premiumFileInput');
}
function savePremium(){ saveSetting('premiumCategories', PREMIUM, 'Premium categories'); }

// ---- RF Cautery features ----
let RFFEAT = <?= json_encode($rf['features'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderRfFeatures(){ document.getElementById('rf_features').innerHTML = RFFEAT.map((f,i)=>`
  <div style="border:1px solid var(--border-color);border-radius:8px;padding:10px;margin-bottom:8px;display:flex;gap:10px;align-items:flex-start;">
    <div onclick="uploadRfFeat(${i})" style="width:70px;height:70px;border:2px dashed var(--border-active);border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
      ${f.image ? `<img src="${(f.image||'').replace(/"/g,'&quot;')}" style="width:100%;height:100%;object-fit:contain;">` : `<i class="fa-solid fa-upload text-gold"></i>`}
    </div>
    <div style="flex:1;">
      <input class="form-control" placeholder="Feature title" value="${(f.title||'').replace(/"/g,'&quot;')}" oninput="RFFEAT[${i}].title=this.value" style="margin-bottom:6px;">
      <textarea class="form-control" placeholder="Feature description" rows="2" oninput="RFFEAT[${i}].desc=this.value">${(f.desc||'')}</textarea>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="RFFEAT.splice(${i},1);renderRfFeatures()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addRfFeature(){ RFFEAT.push({image:'',title:'',desc:''}); renderRfFeatures(); }
function uploadRfFeat(i){
  genericUpload((url)=>{ RFFEAT[i].image=url; renderRfFeatures(); })('rfFileInput');
}
function saveRf(){
  const rf = {
    title: document.getElementById('rf_title').value,
    productId: document.getElementById('rf_pid').value,
    image: document.getElementById('rf_image').value,
    descShort: document.getElementById('rf_descShort').value,
    description: document.getElementById('rf_description').value,
    features: RFFEAT,
  };
  saveSetting('rfSection', rf, 'RF section');
}
function savePromo(){
  const v = id => document.getElementById(id).value;
  const promo = {
    leftId: v('pm_left_id'), topRightId: v('pm_tr_id'), bottomRightId: v('pm_br_id'),
    leftImg: v('pm_left_d'), topRightImg: v('pm_tr_d'), bottomRightImg: v('pm_br_d'),
    leftImgM: v('pm_left_m'), topRightImgM: v('pm_tr_m'), bottomRightImgM: v('pm_br_m'),
  };
  const banners = <?= json_encode($site['banners'] ?? [], JSON_UNESCAPED_SLASHES) ?> || {};
  banners.promo = promo;
  saveSetting('banners', banners, 'Promo banners');
}

// ---- Trust Badges ----
let TRUST = <?= json_encode($site['trustBadges'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderTrust(){ document.getElementById('trust_rows').innerHTML = TRUST.map((t,i)=>`
  <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;border:1px solid var(--border-color);border-radius:8px;padding:8px;">
    <span style="width:36px;text-align:center;"><i class="${(t.icon||'fa-solid fa-circle-check').replace(/"/g,'&quot;')}" style="font-size:1.2rem;color:var(--text-primary);"></i></span>
    <input class="form-control" placeholder="Icon class (fa-solid fa-cube)" value="${(t.icon||'').replace(/"/g,'&quot;')}" oninput="TRUST[${i}].icon=this.value;renderTrust()" style="flex:1;">
    <input class="form-control" placeholder="Label" value="${(t.label||'').replace(/"/g,'&quot;')}" oninput="TRUST[${i}].label=this.value" style="flex:2;">
    <label style="display:flex;align-items:center;gap:4px;font-size:.72rem;white-space:nowrap;color:var(--text-secondary);"><input type="checkbox" ${t.dynamic==='productCount'?'checked':''} onchange="TRUST[${i}].dynamic=this.checked?'productCount':undefined"> Live count</label>
    <button class="btn btn-ghost btn-sm" onclick="TRUST.splice(${i},1);renderTrust()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
  </div>`).join(''); }
function addTrustRow(){ TRUST.push({icon:'fa-solid fa-circle-check',label:''}); renderTrust(); }
function saveTrust(){ saveSetting('trustBadges', TRUST, 'Trust badges'); }

// init
renderStats(); renderSocials(); renderPay(); renderBenefits(); renderHero(); renderTrust(); renderRfFeatures(); renderPremium();
renderTiers(); renderFbt(); renderFg(); renderFeat(); renderSort(); renderPp(); renderPC();
</script>

<!-- Setup Instructions -->
<div class="card fade-in" style="margin-top:24px;">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-circle-info text-gold" style="margin-right:8px;"></i>Setup & Configuration Guide</span>
    </div>
    <div class="card-body">
        <div class="grid-2" style="gap:20px;">
            <div>
                <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:12px;color:var(--gold-primary);">📦 Installation Steps</h4>
                <ol style="font-size:0.83rem;color:var(--text-secondary);line-height:2;padding-left:20px;">
                    <li>Copy <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">dentinno/</code> folder to your web server (htdocs / www)</li>
                    <li>Create MySQL database: <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">dentinno_crm</code></li>
                    <li>Import <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">database.sql</code> into your database</li>
                    <li>Edit <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">includes/config.php</code> with your DB credentials</li>
                    <li>Update <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">APP_URL</code> in config.php</li>
                    <li>Open browser → <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">http://localhost/dentinno/login.php</code></li>
                    <li>Login: <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">admin@dentinno.com</code> / <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">password</code></li>
                    <li>Change default password immediately!</li>
                </ol>
            </div>
            <div>
                <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:12px;color:var(--gold-primary);">⚙️ Config.php Settings</h4>
                <pre style="background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:8px;padding:14px;font-size:0.75rem;color:var(--text-secondary);overflow-x:auto;line-height:1.8;">define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'dentinno_crm');
define('APP_URL', 'http://localhost/dentinno');</pre>
                <div style="margin-top:12px;padding:10px;background:rgba(231,76,60,0.08);border:1px solid rgba(231,76,60,0.2);border-radius:8px;font-size:0.78rem;color:var(--danger);">
                    <i class="fa-solid fa-triangle-exclamation"></i> &nbsp;Default login password is <strong>"password"</strong> — change it immediately after setup!
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
