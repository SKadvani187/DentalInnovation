<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Settings';

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
