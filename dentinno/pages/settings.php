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

// Active config page (tab). 'account' = no ?page (Settings menu).
$cfgPage = isset($_GET['page']) ? (preg_replace('/[^a-z]/','', $_GET['page']) ?: 'home') : 'account';

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
        <?php
        $cfgTitles = ['account'=>['Settings','Manage your account and system preferences'],'home'=>['Home Page Config','Customize the storefront home page'],'contact'=>['Contact Page Config','Contact info, departments, FAQs'],'about'=>['About Page Config','Story, values, team, milestones'],'catalog'=>['Catalog Config','Products, payments, pricing rules'],'general'=>['General Config','Socials and site-wide settings']];
        $ct = $cfgTitles[$cfgPage ?? 'account'] ?? $cfgTitles['account'];
        ?>
        <h1><?= $ct[0] ?></h1>
        <p><?= $ct[1] ?></p>
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

<div class="grid-2 fade-in" data-cfg="account">
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

<?php if ($cfgPage !== 'account'): ?>
<!-- ===== Storefront Configuration (page-wise tabs) ===== -->
<div class="card fade-in" style="margin-top:24px;padding:6px;">
  <div style="display:flex;gap:6px;flex-wrap:wrap;padding:8px;">
    <?php $tabs = ['home'=>'🏠 Home Page','contact'=>'📞 Contact Page','about'=>'ℹ️ About Page','catalog'=>'🛒 Catalog / Products','general'=>'⚙️ General']; ?>
    <?php foreach($tabs as $k=>$lbl): ?>
      <a href="<?= APP_URL ?>/pages/settings.php?page=<?= $k ?>" class="btn <?= $cfgPage===$k?'btn-gold':'btn-ghost' ?> btn-sm"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div data-cfg="contact">
<!-- Storefront Company / Contact Info -->
<div class="card fade-in" style="margin-top:18px;">
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
</div><!-- /contact group -->
<div data-cfg="home">
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

</div><!-- /home group -->
<div data-cfg="contact">
<?php $L = $site['contactConfig']['labels'] ?? []; ?>
<div style="background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:10px;padding:10px 14px;margin-top:18px;font-size:.82rem;color:var(--text-secondary);">
  <i class="fa-solid fa-circle-info text-gold"></i> Sections below match the storefront Contact page top-to-bottom. One <b>Save</b> at the end updates everything.
</div>

<!-- 1. Hero section -->
<div class="card fade-in" style="margin-top:14px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-rectangle-ad text-gold" style="margin-right:8px;"></i>1. Hero Banner</span><small class="text-muted">Top blue header</small></div>
  <div class="card-body">
    <div class="grid-2" style="gap:14px;">
      <div class="form-group"><label class="form-label">Badge — when OPEN <small class="text-muted">(green)</small></label><input type="text" class="form-control" id="cc_heroBadge" value="<?= htmlspecialchars($site['contactConfig']['heroBadge'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Badge — when CLOSED <small class="text-muted">(red)</small></label><input type="text" class="form-control" id="cc_heroBadgeClosed" value="<?= htmlspecialchars($site['contactConfig']['heroBadgeClosed'] ?? '') ?>"></div>
      <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Title</label><input type="text" class="form-control" id="cc_heroTitle" value="<?= htmlspecialchars($site['contactConfig']['heroTitle'] ?? '') ?>"></div>
      <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Sub-line / slogan (full sentence)</label><textarea class="form-control" id="cc_heroSubtitle" rows="2"><?= htmlspecialchars($site['contactConfig']['heroSubtitle'] ?? '') ?></textarea></div>
    </div>
    <label class="form-label" style="font-weight:700;margin-top:10px;">Stat Chips</label>
    <div id="chip_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addChipRow()"><i class="fa-solid fa-plus"></i> Add Chip</button>
  </div>
</div>

<!-- 2. Quick action cards -->
<div class="card fade-in" style="margin-top:14px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-bolt text-gold" style="margin-right:8px;"></i>2. Quick Action Cards</span><small class="text-muted">WhatsApp / Call / Email / Visit labels</small></div>
  <div class="card-body">
    <div class="grid-2" style="gap:10px;">
      <div class="form-group"><label class="form-label">WhatsApp label</label><input type="text" class="form-control" id="lbl_whatsapp" value="<?= htmlspecialchars($L['whatsapp'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">WhatsApp sub-text</label><input type="text" class="form-control" id="lbl_whatsappSub" value="<?= htmlspecialchars($L['whatsappSub'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Call label</label><input type="text" class="form-control" id="lbl_call" value="<?= htmlspecialchars($L['call'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Email label</label><input type="text" class="form-control" id="lbl_email" value="<?= htmlspecialchars($L['email'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Visit label</label><input type="text" class="form-control" id="lbl_visit" value="<?= htmlspecialchars($L['visit'] ?? '') ?>"></div>
    </div>
  </div>
</div>

<!-- 3. Contact form -->
<div class="card fade-in" style="margin-top:14px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-pen-to-square text-gold" style="margin-right:8px;"></i>3. Contact Form</span><small class="text-muted">Heading + department options</small></div>
  <div class="card-body">
    <div class="grid-2" style="gap:14px;">
      <div class="form-group"><label class="form-label">Form Title</label><input type="text" class="form-control" id="cc_formTitle" value="<?= htmlspecialchars($site['contactConfig']['formTitle'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Form Chip (Replies in 4 hrs)</label><input type="text" class="form-control" id="cc_formChip" value="<?= htmlspecialchars($site['contactConfig']['formChip'] ?? '') ?>"></div>
      <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Form Subtitle</label><input type="text" class="form-control" id="lbl_formSubtitle" value="<?= htmlspecialchars($L['formSubtitle'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">"What can we help" heading</label><input type="text" class="form-control" id="lbl_deptHelp" value="<?= htmlspecialchars($L['deptHelp'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Name field label</label><input type="text" class="form-control" id="lbl_fieldName" value="<?= htmlspecialchars($L['fieldName'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Phone field label</label><input type="text" class="form-control" id="lbl_fieldPhone" value="<?= htmlspecialchars($L['fieldPhone'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Email field label</label><input type="text" class="form-control" id="lbl_fieldEmail" value="<?= htmlspecialchars($L['fieldEmail'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Message field label</label><input type="text" class="form-control" id="lbl_fieldMsg" value="<?= htmlspecialchars($L['fieldMsg'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Message hint</label><input type="text" class="form-control" id="lbl_msgHint" value="<?= htmlspecialchars($L['msgHint'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Submit button text</label><input type="text" class="form-control" id="lbl_sendBtn" value="<?= htmlspecialchars($L['sendBtn'] ?? '') ?>"></div>
      <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Privacy note (below button)</label><input type="text" class="form-control" id="lbl_privacyNote" value="<?= htmlspecialchars($L['privacyNote'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Success Title (after submit)</label><input type="text" class="form-control" id="lbl_successTitle" value="<?= htmlspecialchars($L['successTitle'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Success Note</label><input type="text" class="form-control" id="cc_responseNote" value="<?= htmlspecialchars($site['contactConfig']['responseNote'] ?? '') ?>"></div>
    </div>
    <label class="form-label" style="font-weight:700;margin-top:10px;">Departments (What can we help with?)</label>
    <div id="dept_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addDeptRow()"><i class="fa-solid fa-plus"></i> Add Department</button>
  </div>
</div>

<!-- 4. Reach us directly (info comes from Company Info above + Socials in General) -->
<div class="card fade-in" style="margin-top:14px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-address-book text-gold" style="margin-right:8px;"></i>4. Reach Us Directly</span><small class="text-muted">Heading (phone/email = Company Info • socials = General config)</small></div>
  <div class="card-body">
    <div class="grid-2" style="gap:10px;">
      <div class="form-group"><label class="form-label">Section Heading</label><input type="text" class="form-control" id="lbl_reachHeading" value="<?= htmlspecialchars($L['reachHeading'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Sales label</label><input type="text" class="form-control" id="lbl_reachSales" value="<?= htmlspecialchars($L['reachSales'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Support label</label><input type="text" class="form-control" id="lbl_reachSupport" value="<?= htmlspecialchars($L['reachSupport'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Email Sales label</label><input type="text" class="form-control" id="lbl_reachEmailSales" value="<?= htmlspecialchars($L['reachEmailSales'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">General Info label</label><input type="text" class="form-control" id="lbl_reachGeneral" value="<?= htmlspecialchars($L['reachGeneral'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">"Follow us" heading</label><input type="text" class="form-control" id="lbl_followHeading" value="<?= htmlspecialchars($L['followHeading'] ?? '') ?>"></div>
    </div>
  </div>
</div>

<!-- 5. Business hours -->
<div class="card fade-in" style="margin-top:14px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-clock text-gold" style="margin-right:8px;"></i>5. Business Hours</span></div>
  <div class="card-body">
    <div class="form-group"><label class="form-label">Section Heading</label><input type="text" class="form-control" id="lbl_hoursHeading" value="<?= htmlspecialchars($L['hoursHeading'] ?? '') ?>"></div>
    <div id="bh_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addBhRow()"><i class="fa-solid fa-plus"></i> Add Row</button>

    <?php $OH = $site['contactConfig']['openHours'] ?? []; ?>
    <label class="form-label" style="font-weight:700;margin-top:14px;">Live "Open / Closed" badge</label>
    <div class="text-muted" style="font-size:.72rem;margin-bottom:8px;">Auto shows green "Open now" during these hours/days, else red "Closed".</div>
    <div class="grid-2" style="gap:10px;">
      <div class="form-group"><label class="form-label">Open hour (0–23)</label><input type="number" min="0" max="23" class="form-control" id="oh_openHour" value="<?= htmlspecialchars($OH['openHour'] ?? 10) ?>"></div>
      <div class="form-group"><label class="form-label">Close hour (0–23)</label><input type="number" min="0" max="23" class="form-control" id="oh_closeHour" value="<?= htmlspecialchars($OH['closeHour'] ?? 19) ?>"></div>
      <div class="form-group"><label class="form-label">"Open" label</label><input type="text" class="form-control" id="oh_openLabel" value="<?= htmlspecialchars($OH['openLabel'] ?? 'Open now') ?>"></div>
      <div class="form-group"><label class="form-label">"Closed" label</label><input type="text" class="form-control" id="oh_closedLabel" value="<?= htmlspecialchars($OH['closedLabel'] ?? 'Closed') ?>"></div>
    </div>
    <label class="form-label">Open days</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
      <?php $dayNames=['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; $openDays=$OH['openDays'] ?? [1,2,3,4,5,6];
      foreach($dayNames as $di=>$dn): ?>
        <label style="display:flex;align-items:center;gap:4px;font-size:.8rem;cursor:pointer;"><input type="checkbox" class="oh-day" value="<?= $di ?>" <?= in_array($di,$openDays)?'checked':'' ?>> <?= $dn ?></label>
      <?php endforeach; ?>
    </div>
    <div class="form-group" style="margin-top:6px;"><label class="form-label">Timezone Label</label><input type="text" class="form-control" id="cc_timezone" value="<?= htmlspecialchars($site['contactConfig']['timezone'] ?? '') ?>"></div>
  </div>
</div>

<!-- 6. Our Office -->
<div class="card fade-in" style="margin-top:14px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-location-dot text-gold" style="margin-right:8px;"></i>6. Our Office</span><small class="text-muted">Map uses Company address • highlights below</small></div>
  <div class="card-body">
    <div class="grid-2" style="gap:10px;">
      <div class="form-group"><label class="form-label">Badge (Visit Us)</label><input type="text" class="form-control" id="lbl_visitBadge" value="<?= htmlspecialchars($L['visitBadge'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Heading (Our Office)</label><input type="text" class="form-control" id="lbl_officeHeading" value="<?= htmlspecialchars($L['officeHeading'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label class="form-label">Office Subtitle</label><input type="text" class="form-control" id="cc_officeSubtitle" value="<?= htmlspecialchars($site['contactConfig']['officeSubtitle'] ?? '') ?>"></div>
    <label class="form-label" style="font-weight:700;">Highlights</label>
    <div id="office_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addOfficeRow()"><i class="fa-solid fa-plus"></i> Add Highlight</button>
  </div>
</div>

<!-- 7. FAQs -->
<div class="card fade-in" style="margin-top:14px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-circle-question text-gold" style="margin-right:8px;"></i>7. FAQs</span></div>
  <div class="card-body">
    <div class="form-group"><label class="form-label">FAQ Section Heading</label><input type="text" class="form-control" id="lbl_faqHeading" value="<?= htmlspecialchars($L['faqHeading'] ?? '') ?>"></div>
    <div id="cfaq_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addCfaqRow()"><i class="fa-solid fa-plus"></i> Add FAQ</button>
    <div style="margin-top:16px;border-top:1px solid var(--border-color);padding-top:14px;"><button class="btn btn-gold" onclick="saveContactConfig()"><i class="fa-solid fa-floppy-disk"></i> Save Contact Page Configuration</button></div>
  </div>
</div>

</div><!-- /contact group -->

<?php $A = $site['aboutConfig'] ?? []; ?>
<div data-cfg="about">
<div style="background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:10px;padding:10px 14px;margin-top:18px;font-size:.82rem;color:var(--text-secondary);">
  <i class="fa-solid fa-circle-info text-gold"></i> About page sections, top-to-bottom. One <b>Save</b> at the end.
</div>

<!-- A1 Hero -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-rectangle-ad text-gold" style="margin-right:8px;"></i>1. Hero</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Badge</label><input type="text" class="form-control" id="ab_hero_badge" value="<?= htmlspecialchars($A['hero']['badge'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Card Title</label><input type="text" class="form-control" id="ab_hero_cardTitle" value="<?= htmlspecialchars($A['hero']['cardTitle'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Title</label><input type="text" class="form-control" id="ab_hero_title" value="<?= htmlspecialchars($A['hero']['title'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Description</label><textarea class="form-control" id="ab_hero_desc" rows="2"><?= htmlspecialchars($A['hero']['description'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">CTA Button text</label><input type="text" class="form-control" id="ab_hero_cta" value="<?= htmlspecialchars($A['hero']['ctaText'] ?? '') ?>"></div>
  </div>
  <label class="form-label" style="font-weight:700;margin-top:8px;">Hero Stats</label><div id="ab_herostats"></div>
  <button class="btn btn-ghost btn-sm" onclick="abAdd('heroStats',{value:'',label:''})"><i class="fa-solid fa-plus"></i> Add Stat</button>
</div></div>

<!-- A2 Our Story -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-book-open text-gold" style="margin-right:8px;"></i>2. Our Story</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_story_label" value="<?= htmlspecialchars($A['story']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_story_heading" value="<?= htmlspecialchars($A['story']['heading'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Parent Label</label><input type="text" class="form-control" id="ab_story_parentLabel" value="<?= htmlspecialchars($A['story']['parentLabel'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Parent Name</label><input type="text" class="form-control" id="ab_story_parentName" value="<?= htmlspecialchars($A['story']['parentName'] ?? '') ?>"></div>
  </div>
  <label class="form-label" style="font-weight:700;margin-top:8px;">Paragraphs</label><div id="ab_storyparas"></div>
  <button class="btn btn-ghost btn-sm" onclick="abAdd('storyParas','')"><i class="fa-solid fa-plus"></i> Add Paragraph</button>
  <label class="form-label" style="font-weight:700;margin-top:8px;">Promises</label><div id="ab_promises"></div>
  <button class="btn btn-ghost btn-sm" onclick="abAdd('promises',{title:'',text:''})"><i class="fa-solid fa-plus"></i> Add Promise</button>
</div></div>

<!-- A3 Stats Strip -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-chart-simple text-gold" style="margin-right:8px;"></i>3. Stats Strip</span></div><div class="card-body">
  <div id="ab_stats"></div><button class="btn btn-ghost btn-sm" onclick="abAdd('stats',{value:'',label:''})"><i class="fa-solid fa-plus"></i> Add Stat</button>
</div></div>

<!-- A4 Milestones -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-timeline text-gold" style="margin-right:8px;"></i>4. Milestones</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_ms_label" value="<?= htmlspecialchars($A['milestones']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_ms_heading" value="<?= htmlspecialchars($A['milestones']['heading'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Subtitle</label><input type="text" class="form-control" id="ab_ms_subtitle" value="<?= htmlspecialchars($A['milestones']['subtitle'] ?? '') ?>"></div>
  </div>
  <div id="ab_milestones"></div><button class="btn btn-ghost btn-sm" onclick="abAdd('milestones',{year:'',title:'',text:''})"><i class="fa-solid fa-plus"></i> Add Milestone</button>
</div></div>

<!-- A5 Core Values -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-gem text-gold" style="margin-right:8px;"></i>5. Core Values</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_cv_label" value="<?= htmlspecialchars($A['coreValues']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_cv_heading" value="<?= htmlspecialchars($A['coreValues']['heading'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Subtitle</label><input type="text" class="form-control" id="ab_cv_subtitle" value="<?= htmlspecialchars($A['coreValues']['subtitle'] ?? '') ?>"></div>
  </div>
  <div id="ab_values"></div><button class="btn btn-ghost btn-sm" onclick="abAdd('values',{n:'',icon:'⭐',title:'',text:''})"><i class="fa-solid fa-plus"></i> Add Value</button>
</div></div>

<!-- A6 Leadership -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-users text-gold" style="margin-right:8px;"></i>6. Leadership / Team</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_ld_label" value="<?= htmlspecialchars($A['leadership']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_ld_heading" value="<?= htmlspecialchars($A['leadership']['heading'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Subtitle</label><input type="text" class="form-control" id="ab_ld_subtitle" value="<?= htmlspecialchars($A['leadership']['subtitle'] ?? '') ?>"></div>
  </div>
  <div id="ab_team"></div><button class="btn btn-ghost btn-sm" onclick="abAdd('team',{name:'',role:'',bio:'',img:''})"><i class="fa-solid fa-plus"></i> Add Member</button>
  <input type="file" id="abFileInput" accept="image/*" style="display:none">
</div></div>

<!-- A7 Why Trust -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-handshake text-gold" style="margin-right:8px;"></i>7. Why Trust Us</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_wt_label" value="<?= htmlspecialchars($A['whyTrust']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_wt_heading" value="<?= htmlspecialchars($A['whyTrust']['heading'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Subtitle</label><textarea class="form-control" id="ab_wt_subtitle" rows="2"><?= htmlspecialchars($A['whyTrust']['subtitle'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">Satisfaction Title</label><input type="text" class="form-control" id="ab_wt_satTitle" value="<?= htmlspecialchars($A['whyTrust']['satTitle'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Satisfaction Rating</label><input type="text" class="form-control" id="ab_wt_satRating" value="<?= htmlspecialchars($A['whyTrust']['satRating'] ?? '') ?>"></div>
  </div>
  <label class="form-label" style="font-weight:700;margin-top:8px;">Trust Rows</label><div id="ab_trustrows"></div>
  <button class="btn btn-ghost btn-sm" onclick="abAdd('trustRows',{icon:'check',title:'',text:''})"><i class="fa-solid fa-plus"></i> Add Row</button>
  <label class="form-label" style="font-weight:700;margin-top:8px;">Satisfaction Bars</label><div id="ab_satbars"></div>
  <button class="btn btn-ghost btn-sm" onclick="abAdd('satBars',{label:'',value:90})"><i class="fa-solid fa-plus"></i> Add Bar</button>
</div></div>

<!-- A8 Mission/Vision -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-bullseye text-gold" style="margin-right:8px;"></i>8. Mission & Vision</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_mv_label" value="<?= htmlspecialchars($A['missionVision']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_mv_heading" value="<?= htmlspecialchars($A['missionVision']['heading'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Subtitle</label><textarea class="form-control" id="ab_mv_subtitle" rows="2"><?= htmlspecialchars($A['missionVision']['subtitle'] ?? '') ?></textarea></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Mission</label><textarea class="form-control" id="ab_mv_mission" rows="3"><?= htmlspecialchars($A['missionVision']['mission'] ?? '') ?></textarea></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Vision</label><textarea class="form-control" id="ab_mv_vision" rows="3"><?= htmlspecialchars($A['missionVision']['vision'] ?? '') ?></textarea></div>
  </div>
</div></div>

<!-- A9 Testimonials -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-quote-left text-gold" style="margin-right:8px;"></i>9. Testimonials</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_ts_label" value="<?= htmlspecialchars($A['testimonials']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_ts_heading" value="<?= htmlspecialchars($A['testimonials']['heading'] ?? '') ?>"></div>
  </div>
  <div id="ab_testimonials"></div><button class="btn btn-ghost btn-sm" onclick="abAdd('testimonials',{name:'',clinic:'',stars:5,text:''})"><i class="fa-solid fa-plus"></i> Add Review</button>
</div></div>

<!-- A10 Certifications -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-certificate text-gold" style="margin-right:8px;"></i>10. Certifications</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_ce_label" value="<?= htmlspecialchars($A['certifications']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_ce_heading" value="<?= htmlspecialchars($A['certifications']['heading'] ?? '') ?>"></div>
  </div>
  <div id="ab_certs"></div><button class="btn btn-ghost btn-sm" onclick="abAdd('certs',{icon:'📋',label:'',desc:''})"><i class="fa-solid fa-plus"></i> Add Cert</button>
</div></div>

<!-- A11 CTA -->
<div class="card fade-in" style="margin-top:14px;"><div class="card-header"><span class="card-title"><i class="fa-solid fa-bullhorn text-gold" style="margin-right:8px;"></i>11. Bottom CTA</span></div><div class="card-body">
  <div class="grid-2" style="gap:12px;">
    <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="ab_cta_label" value="<?= htmlspecialchars($A['cta']['label'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Heading</label><input type="text" class="form-control" id="ab_cta_heading" value="<?= htmlspecialchars($A['cta']['heading'] ?? '') ?>"></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Subtitle</label><textarea class="form-control" id="ab_cta_subtitle" rows="2"><?= htmlspecialchars($A['cta']['subtitle'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">Shop button</label><input type="text" class="form-control" id="ab_cta_shop" value="<?= htmlspecialchars($A['cta']['shopText'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Contact button</label><input type="text" class="form-control" id="ab_cta_contact" value="<?= htmlspecialchars($A['cta']['contactText'] ?? '') ?>"></div>
  </div>
  <div style="margin-top:16px;border-top:1px solid var(--border-color);padding-top:14px;"><button class="btn btn-gold" onclick="saveAbout()"><i class="fa-solid fa-floppy-disk"></i> Save About Page Configuration</button></div>
</div></div>
</div><!-- /about group -->

<div data-cfg="home">
<!-- Home Layout (section order + visibility) -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-table-cells text-gold" style="margin-right:8px;"></i>Home Page Layout</span><small class="text-muted">Show/hide sections and change their order</small></div>
  <div class="card-body">
    <div id="home_rows"></div>
    <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap;border-top:1px solid var(--border-color);padding-top:12px;">
      <span class="text-muted" style="font-size:.8rem;">Add a category section:</span>
      <select class="form-control" id="add_section_cat" style="width:auto;min-width:200px;">
        <?php foreach ($linkProducts ? db()->fetchAll("SELECT slug,name FROM categories WHERE is_active=1 ORDER BY name") : [] as $cat): ?>
          <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost btn-sm" onclick="addCategorySection()"><i class="fa-solid fa-plus"></i> Add Section</button>
    </div>
    <button class="btn btn-gold" style="margin-top:12px;" onclick="saveHomeLayout()"><i class="fa-solid fa-floppy-disk"></i> Save Home Layout</button>
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

</div><!-- /home group -->
<div data-cfg="general">
<!-- Socials -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-share-nodes text-gold" style="margin-right:8px;"></i>Social Links</span></div>
  <div class="card-body">
    <div id="social_rows"></div>
    <button class="btn btn-ghost btn-sm" onclick="addSocialRow()"><i class="fa-solid fa-plus"></i> Add Social</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="saveSocials()"><i class="fa-solid fa-floppy-disk"></i> Save Socials</button>
  </div>
</div>

<!-- Policy Pages -->
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-file-contract text-gold" style="margin-right:8px;"></i>Policy Pages</span><small class="text-muted">Return / Terms / Privacy — shown in footer links</small></div>
  <div class="card-body">
    <div style="display:flex;gap:6px;margin-bottom:10px;">
      <?php foreach(['return'=>'Return','terms'=>'Terms','privacy'=>'Privacy'] as $pk=>$pl): ?>
        <button class="btn btn-ghost btn-sm" onclick="showPolicy('<?= $pk ?>')" id="polTab_<?= $pk ?>"><?= $pl ?></button>
      <?php endforeach; ?>
    </div>
    <div class="form-group"><label class="form-label">Title</label><input type="text" class="form-control" id="pol_title"></div>
    <label class="form-label" style="font-weight:700;">Sections</label>
    <div id="pol_sections"></div>
    <button class="btn btn-ghost btn-sm" onclick="addPolSection()"><i class="fa-solid fa-plus"></i> Add Section</button>
    <button class="btn btn-gold" style="margin-left:8px;" onclick="savePolicies()"><i class="fa-solid fa-floppy-disk"></i> Save Policies</button>
  </div>
</div>

</div><!-- /general group -->
<div data-cfg="catalog">
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
      <div class="form-group"><label class="form-label">Great Value min discount (%)</label><input type="number" class="form-control" id="gvp_threshold" value="<?= htmlspecialchars($site['gvpThreshold'] ?? 10) ?>"><small class="text-muted" style="font-size:.7rem;">Products with this % off or more show under "Great Value Products"</small></div>
    </div>
    <button class="btn btn-gold" onclick="savePricingRules()"><i class="fa-solid fa-floppy-disk"></i> Save Pricing Rules</button>
  </div>
</div>

<!-- Offer Zone Hero -->
<?php $OZ = $site['offerZoneHero'] ?? []; ?>
<div class="card fade-in" style="margin-top:18px;">
  <div class="card-header"><span class="card-title"><i class="fa-solid fa-fire text-gold" style="margin-right:8px;"></i>Offer Zone Hero</span><small class="text-muted">Save amount + counts auto-computed from offers</small></div>
  <div class="card-body">
    <div class="grid-2" style="gap:12px;">
      <div class="form-group"><label class="form-label">Badge</label><input type="text" class="form-control" id="oz_badge" value="<?= htmlspecialchars($OZ['badge'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Title</label><input type="text" class="form-control" id="oz_title" value="<?= htmlspecialchars($OZ['title'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Save prefix</label><input type="text" class="form-control" id="oz_savePrefix" value="<?= htmlspecialchars($OZ['savePrefix'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Save suffix</label><input type="text" class="form-control" id="oz_saveSuffix" value="<?= htmlspecialchars($OZ['saveSuffix'] ?? '') ?>"></div>
      <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Subtitle</label><textarea class="form-control" id="oz_subtitle" rows="2"><?= htmlspecialchars($OZ['subtitle'] ?? '') ?></textarea></div>
      <div class="form-group"><label class="form-label">Countdown label</label><input type="text" class="form-control" id="oz_expiryLabel" value="<?= htmlspecialchars($OZ['expiryLabel'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Restock note</label><input type="text" class="form-control" id="oz_restockNote" value="<?= htmlspecialchars($OZ['restockNote'] ?? '') ?>"></div>
    </div>
    <button class="btn btn-gold" style="margin-top:10px;" onclick="saveOfferHero()"><i class="fa-solid fa-floppy-disk"></i> Save Offer Zone Hero</button>
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

</div><!-- /catalog group -->
<div data-cfg="home">
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
    saveSetting('productDefaults', pd);
    saveSetting('gvpThreshold', parseInt(document.getElementById('gvp_threshold').value)||10, 'Pricing rules');
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

// ---- Contact Page config ----
let CC = <?= json_encode($site['contactConfig'] ?? ['departments'=>[],'faqs'=>[],'businessHours'=>[]], JSON_UNESCAPED_SLASHES) ?>;
CC.departments = CC.departments||[]; CC.faqs = CC.faqs||[]; CC.businessHours = CC.businessHours||[]; CC.statChips = CC.statChips||[]; CC.officeBullets = CC.officeBullets||[];
function renderCC(){
  document.getElementById('office_rows').innerHTML = CC.officeBullets.map((b,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="Highlight (e.g. Free parking)" value="${(b||'').replace(/"/g,'&quot;')}" oninput="CC.officeBullets[${i}]=this.value" style="flex:1;">
      <button class="btn btn-ghost btn-sm" onclick="CC.officeBullets.splice(${i},1);renderCC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
  document.getElementById('chip_rows').innerHTML = CC.statChips.map((c,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="icon" value="${(c.icon||'').replace(/"/g,'&quot;')}" oninput="CC.statChips[${i}].icon=this.value" style="width:60px;">
      <input class="form-control" placeholder="Label" value="${(c.label||'').replace(/"/g,'&quot;')}" oninput="CC.statChips[${i}].label=this.value" style="flex:1;">
      <button class="btn btn-ghost btn-sm" onclick="CC.statChips.splice(${i},1);renderCC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
  document.getElementById('dept_rows').innerHTML = CC.departments.map((d,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="id" value="${(d.id||'').replace(/"/g,'&quot;')}" oninput="CC.departments[${i}].id=this.value" style="width:110px;">
      <input class="form-control" placeholder="icon" value="${(d.icon||'').replace(/"/g,'&quot;')}" oninput="CC.departments[${i}].icon=this.value" style="width:60px;">
      <input class="form-control" placeholder="Label" value="${(d.label||'').replace(/"/g,'&quot;')}" oninput="CC.departments[${i}].label=this.value" style="flex:1;">
      <input class="form-control" placeholder="Description" value="${(d.desc||'').replace(/"/g,'&quot;')}" oninput="CC.departments[${i}].desc=this.value" style="flex:2;">
      <button class="btn btn-ghost btn-sm" onclick="CC.departments.splice(${i},1);renderCC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
  document.getElementById('cfaq_rows').innerHTML = CC.faqs.map((f,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="Question" value="${(f.q||'').replace(/"/g,'&quot;')}" oninput="CC.faqs[${i}].q=this.value" style="flex:1;">
      <input class="form-control" placeholder="Answer" value="${(f.a||'').replace(/"/g,'&quot;')}" oninput="CC.faqs[${i}].a=this.value" style="flex:2;">
      <button class="btn btn-ghost btn-sm" onclick="CC.faqs.splice(${i},1);renderCC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
  document.getElementById('bh_rows').innerHTML = CC.businessHours.map((h,i)=>`
    <div style="display:flex;gap:6px;margin-bottom:6px;">
      <input class="form-control" placeholder="Day (Mon – Sat)" value="${(h.day||'').replace(/"/g,'&quot;')}" oninput="CC.businessHours[${i}].day=this.value" style="flex:1;">
      <input class="form-control" placeholder="Hours (10 AM – 7 PM / Closed)" value="${(h.hours||'').replace(/"/g,'&quot;')}" oninput="CC.businessHours[${i}].hours=this.value" style="flex:1;">
      <button class="btn btn-ghost btn-sm" onclick="CC.businessHours.splice(${i},1);renderCC()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`).join('');
}
function addDeptRow(){ CC.departments.push({id:'',icon:'💬',label:'',desc:''}); renderCC(); }
function addCfaqRow(){ CC.faqs.push({q:'',a:''}); renderCC(); }
function addBhRow(){ CC.businessHours.push({day:'',hours:''}); renderCC(); }
function addChipRow(){ CC.statChips.push({icon:'⚡',label:''}); renderCC(); }
function addOfficeRow(){ CC.officeBullets.push(''); renderCC(); }
function saveContactConfig(){
  CC.heroBadge = document.getElementById('cc_heroBadge').value;
  CC.heroBadgeClosed = document.getElementById('cc_heroBadgeClosed').value;
  CC.heroTitle = document.getElementById('cc_heroTitle').value;
  CC.heroSubtitle = document.getElementById('cc_heroSubtitle').value;
  CC.officeSubtitle = document.getElementById('cc_officeSubtitle').value;
  CC.openHours = {
    openHour: parseInt(document.getElementById('oh_openHour').value) || 0,
    closeHour: parseInt(document.getElementById('oh_closeHour').value) || 0,
    openLabel: document.getElementById('oh_openLabel').value,
    closedLabel: document.getElementById('oh_closedLabel').value,
    openDays: Array.from(document.querySelectorAll('.oh-day:checked')).map(c => parseInt(c.value)),
  };
  CC.formTitle = document.getElementById('cc_formTitle').value;
  CC.formChip = document.getElementById('cc_formChip').value;
  CC.responseNote = document.getElementById('cc_responseNote').value;
  CC.timezone = document.getElementById('cc_timezone').value;
  CC.labels = {
    ...(CC.labels || {}),  // preserve labels that have no admin input (field labels, headings, etc)
    whatsapp: document.getElementById('lbl_whatsapp').value,
    whatsappSub: document.getElementById('lbl_whatsappSub').value,
    call: document.getElementById('lbl_call').value,
    email: document.getElementById('lbl_email').value,
    visit: document.getElementById('lbl_visit').value,
    reachHeading: document.getElementById('lbl_reachHeading').value,
    faqHeading: document.getElementById('lbl_faqHeading').value,
    successTitle: document.getElementById('lbl_successTitle').value,
    formSubtitle: document.getElementById('lbl_formSubtitle').value,
    msgHint: document.getElementById('lbl_msgHint').value,
    sendBtn: document.getElementById('lbl_sendBtn').value,
    deptHelp: document.getElementById('lbl_deptHelp').value,
    fieldName: document.getElementById('lbl_fieldName').value,
    fieldPhone: document.getElementById('lbl_fieldPhone').value,
    fieldEmail: document.getElementById('lbl_fieldEmail').value,
    fieldMsg: document.getElementById('lbl_fieldMsg').value,
    visitBadge: document.getElementById('lbl_visitBadge').value,
    officeHeading: document.getElementById('lbl_officeHeading').value,
    reachSales: document.getElementById('lbl_reachSales').value,
    reachSupport: document.getElementById('lbl_reachSupport').value,
    reachEmailSales: document.getElementById('lbl_reachEmailSales').value,
    reachGeneral: document.getElementById('lbl_reachGeneral').value,
    privacyNote: document.getElementById('lbl_privacyNote').value,
    followHeading: document.getElementById('lbl_followHeading').value,
    hoursHeading: document.getElementById('lbl_hoursHeading').value,
  };
  saveSetting('contactConfig', CC, 'Contact page');
}

// ---- About page config ----
const AB = <?= json_encode($site['aboutConfig'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?> || {};
// working arrays
const ABA = {
  heroStats: AB.hero?.stats || [], storyParas: AB.story?.paragraphs || [], promises: AB.story?.promises || [],
  stats: AB.stats || [], milestones: AB.milestones?.items || [], values: AB.coreValues?.items || [],
  team: AB.leadership?.team || [], trustRows: AB.whyTrust?.rows || [], satBars: AB.whyTrust?.satBars || [],
  testimonials: AB.testimonials?.items || [], certs: AB.certifications?.items || [],
};
const esc = (s) => (s==null?'':String(s)).replace(/"/g,'&quot;');
function abAdd(key, item){ ABA[key].push(typeof item==='object'?{...item}:item); renderAbout(); }
function abDel(key, i){ ABA[key].splice(i,1); renderAbout(); }
function abSet(key, i, field, val){ if(field===null) ABA[key][i]=val; else ABA[key][i][field]=val; }
function abUploadTeam(i){
  const inp=document.getElementById('abFileInput');
  inp.onchange=async()=>{ const f=inp.files[0]; if(!f)return; const fd=new FormData(); fd.append('banner_image',f);
    const r=await fetch('settings.php',{method:'POST',body:fd}); const d=await r.json();
    if(d.success){ ABA.team[i].img=d.url; renderAbout(); } inp.value=''; };
  inp.click();
}
function rowInput(key,i,field,ph,val,w){ return `<input class="form-control" placeholder="${ph}" value="${esc(val)}" oninput="abSet('${key}',${i},'${field}',this.value)" style="${w||'flex:1'}">`; }
function renderAbout(){
  const simple = (key, fields) => ABA[key].map((it,i)=>`<div style="display:flex;gap:6px;margin-bottom:6px;align-items:center;">${fields.map(f=>rowInput(key,i,f.k,f.ph,it[f.k],f.w)).join('')}<button class="btn btn-ghost btn-sm" onclick="abDel('${key}',${i})"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button></div>`).join('');
  document.getElementById('ab_herostats').innerHTML = simple('heroStats',[{k:'value',ph:'Value',w:'width:120px'},{k:'label',ph:'Label'}]);
  document.getElementById('ab_storyparas').innerHTML = ABA.storyParas.map((p,i)=>`<div style="display:flex;gap:6px;margin-bottom:6px;"><textarea class="form-control" rows="2" oninput="abSet('storyParas',${i},null,this.value)" style="flex:1;">${p||''}</textarea><button class="btn btn-ghost btn-sm" onclick="abDel('storyParas',${i})"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button></div>`).join('');
  document.getElementById('ab_promises').innerHTML = simple('promises',[{k:'title',ph:'Title'},{k:'text',ph:'Text',w:'flex:2'}]);
  document.getElementById('ab_stats').innerHTML = simple('stats',[{k:'value',ph:'Value',w:'width:120px'},{k:'label',ph:'Label'}]);
  document.getElementById('ab_milestones').innerHTML = simple('milestones',[{k:'year',ph:'Year',w:'width:90px'},{k:'title',ph:'Title'},{k:'text',ph:'Text',w:'flex:2'}]);
  document.getElementById('ab_values').innerHTML = simple('values',[{k:'n',ph:'No',w:'width:60px'},{k:'icon',ph:'Icon',w:'width:60px'},{k:'title',ph:'Title'},{k:'text',ph:'Text',w:'flex:2'}]);
  document.getElementById('ab_team').innerHTML = ABA.team.map((t,i)=>`<div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;border:1px solid var(--border-color);border-radius:8px;padding:8px;">
    <div onclick="abUploadTeam(${i})" style="width:48px;height:48px;border:2px dashed var(--border-active);border-radius:50%;cursor:pointer;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;">${t.img?`<img src="${esc(t.img)}" style="width:100%;height:100%;object-fit:cover;">`:'<i class="fa-solid fa-upload text-gold"></i>'}</div>
    ${rowInput('team',i,'name','Name',t.name)}${rowInput('team',i,'role','Role',t.role)}${rowInput('team',i,'bio','Bio',t.bio,'flex:2')}
    <button class="btn btn-ghost btn-sm" onclick="abDel('team',${i})"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button></div>`).join('');
  document.getElementById('ab_trustrows').innerHTML = simple('trustRows',[{k:'icon',ph:'icon (check/shield/clock/chat/dollar)',w:'width:200px'},{k:'title',ph:'Title'},{k:'text',ph:'Text',w:'flex:2'}]);
  document.getElementById('ab_satbars').innerHTML = simple('satBars',[{k:'label',ph:'Label'},{k:'value',ph:'%',w:'width:80px'}]);
  document.getElementById('ab_testimonials').innerHTML = simple('testimonials',[{k:'name',ph:'Name'},{k:'clinic',ph:'Clinic'},{k:'stars',ph:'★',w:'width:60px'},{k:'text',ph:'Text',w:'flex:2'}]);
  document.getElementById('ab_certs').innerHTML = simple('certs',[{k:'icon',ph:'Icon',w:'width:60px'},{k:'label',ph:'Label'},{k:'desc',ph:'Desc',w:'flex:2'}]);
}
function saveAbout(){
  const v = id => document.getElementById(id).value;
  const cfg = {
    hero: { badge:v('ab_hero_badge'), cardTitle:v('ab_hero_cardTitle'), title:v('ab_hero_title'), description:v('ab_hero_desc'), ctaText:v('ab_hero_cta'), stats:ABA.heroStats },
    story: { label:v('ab_story_label'), heading:v('ab_story_heading'), parentLabel:v('ab_story_parentLabel'), parentName:v('ab_story_parentName'), paragraphs:ABA.storyParas, promises:ABA.promises },
    stats: ABA.stats,
    milestones: { label:v('ab_ms_label'), heading:v('ab_ms_heading'), subtitle:v('ab_ms_subtitle'), items:ABA.milestones },
    coreValues: { label:v('ab_cv_label'), heading:v('ab_cv_heading'), subtitle:v('ab_cv_subtitle'), items:ABA.values },
    leadership: { label:v('ab_ld_label'), heading:v('ab_ld_heading'), subtitle:v('ab_ld_subtitle'), team:ABA.team },
    whyTrust: { label:v('ab_wt_label'), heading:v('ab_wt_heading'), subtitle:v('ab_wt_subtitle'), satTitle:v('ab_wt_satTitle'), satRating:v('ab_wt_satRating'), rows:ABA.trustRows, satBars:ABA.satBars.map(b=>({label:b.label,value:parseInt(b.value)||0})) },
    missionVision: { label:v('ab_mv_label'), heading:v('ab_mv_heading'), subtitle:v('ab_mv_subtitle'), mission:v('ab_mv_mission'), vision:v('ab_mv_vision') },
    testimonials: { label:v('ab_ts_label'), heading:v('ab_ts_heading'), items:ABA.testimonials.map(t=>({...t,stars:parseInt(t.stars)||5})) },
    certifications: { label:v('ab_ce_label'), heading:v('ab_ce_heading'), items:ABA.certs },
    cta: { label:v('ab_cta_label'), heading:v('ab_cta_heading'), subtitle:v('ab_cta_subtitle'), shopText:v('ab_cta_shop'), contactText:v('ab_cta_contact') },
  };
  saveSetting('aboutConfig', cfg, 'About page');
}

// ---- Policy pages ----
let POL = <?= json_encode($site['policies'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?> || {};
['return','terms','privacy'].forEach(k => { if(!POL[k]) POL[k] = {title:'',sections:[]}; if(!POL[k].sections) POL[k].sections=[]; });
let polCur = 'return';
function showPolicy(k){
  // persist current edits before switching
  POL[polCur].title = document.getElementById('pol_title').value;
  polCur = k;
  document.getElementById('pol_title').value = POL[k].title || '';
  ['return','terms','privacy'].forEach(t => { const b=document.getElementById('polTab_'+t); if(b) b.className = 'btn btn-sm '+(t===k?'btn-gold':'btn-ghost'); });
  renderPolSections();
}
function renderPolSections(){
  document.getElementById('pol_sections').innerHTML = POL[polCur].sections.map((s,i)=>`
    <div style="border:1px solid var(--border-color);border-radius:8px;padding:10px;margin-bottom:8px;">
      <input class="form-control" placeholder="Section heading" value="${(s.h||'').replace(/"/g,'&quot;')}" oninput="POL[polCur].sections[${i}].h=this.value" style="margin-bottom:6px;">
      <textarea class="form-control" placeholder="Section text" rows="3" oninput="POL[polCur].sections[${i}].p=this.value">${(s.p||'')}</textarea>
      <button class="btn btn-ghost btn-sm" style="margin-top:6px;" onclick="POL[polCur].sections.splice(${i},1);renderPolSections()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i> Remove</button>
    </div>`).join('');
}
function addPolSection(){ POL[polCur].sections.push({h:'',p:''}); renderPolSections(); }
function savePolicies(){ POL[polCur].title = document.getElementById('pol_title').value; saveSetting('policies', POL, 'Policies'); }

function saveOfferHero(){
  const v = id => document.getElementById(id).value;
  saveSetting('offerZoneHero', {
    badge:v('oz_badge'), title:v('oz_title'), savePrefix:v('oz_savePrefix'), saveSuffix:v('oz_saveSuffix'),
    subtitle:v('oz_subtitle'), expiryLabel:v('oz_expiryLabel'), restockNote:v('oz_restockNote'),
  }, 'Offer Zone hero');
}

// ---- Home Layout (order + visibility) ----
let HOME = <?= json_encode($site['homeSections'] ?? [], JSON_UNESCAPED_SLASHES) ?> || [];
function renderHome(){ document.getElementById('home_rows').innerHTML = HOME.map((s,i)=>`
  <div style="display:flex;gap:10px;align-items:center;border:1px solid var(--border-color);border-radius:8px;padding:10px;margin-bottom:6px;background:${s.enabled===false?'var(--bg-elevated)':'transparent'};">
    <span style="color:var(--text-muted);font-size:.8rem;width:26px;text-align:center;">${i+1}</span>
    <div style="flex:1;">
      <div class="font-bold" style="font-size:.9rem;color:${s.enabled===false?'var(--text-muted)':'var(--text-primary)'};">${(s.label||s.key).replace(/</g,'&lt;')}</div>
      <div class="text-muted" style="font-size:.7rem;">${s.type}${s.source?' · '+s.source:''}</div>
    </div>
    <label style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--text-secondary);cursor:pointer;">
      <input type="checkbox" ${s.enabled!==false?'checked':''} onchange="HOME[${i}].enabled=this.checked;renderHome()"> Show
    </label>
    <button class="btn btn-ghost btn-sm" ${i===0?'disabled style="opacity:.3;"':''} onclick="moveHome(${i},-1)" title="Up"><i class="fa-solid fa-arrow-up"></i></button>
    <button class="btn btn-ghost btn-sm" ${i===HOME.length-1?'disabled style="opacity:.3;"':''} onclick="moveHome(${i},1)" title="Down"><i class="fa-solid fa-arrow-down"></i></button>
    ${s.removable ? `<button class="btn btn-ghost btn-sm" onclick="deleteHomeSection(${i})" title="Delete"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>` : ''}
  </div>`).join(''); }
function moveHome(i, dir){
  const j = i + dir;
  if (j < 0 || j >= HOME.length) return;
  [HOME[i], HOME[j]] = [HOME[j], HOME[i]];
  renderHome();
}
function deleteHomeSection(i){
  showConfirm('Delete Section','Remove this section from the home page?', () => { HOME.splice(i,1); renderHome(); });
}
function addCategorySection(){
  const sel = document.getElementById('add_section_cat');
  const slug = sel.value; if(!slug) return;
  const label = sel.options[sel.selectedIndex].text;
  HOME.push({ key:'cat-'+slug+'-'+Date.now(), type:'productSection', label, source:slug, enabled:true, removable:true });
  renderHome();
}
function saveHomeLayout(){ saveSetting('homeSections', HOME, 'Home layout'); }

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
renderTiers(); renderFbt(); renderFg(); renderFeat(); renderSort(); renderPp(); renderPC(); renderHome(); renderCC(); renderAbout(); showPolicy('return');
// Show only the active config page's section groups
(function(){
  const active = '<?= $cfgPage ?>';
  document.querySelectorAll('[data-cfg]').forEach(el => {
    el.style.display = (el.getAttribute('data-cfg') === active) ? '' : 'none';
  });
})();
</script>

</div><!-- /home group (Premium) -->
<!-- Setup Instructions -->
<div class="card fade-in" style="margin-top:24px;" data-cfg="account">
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
