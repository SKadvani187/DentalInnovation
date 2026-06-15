<?php
requireLogin();
$admin = currentAdmin();
// Sidebar/topbar badges. The header uses its OWN $navBadges var so it never collides with a
// page-level $stats (e.g. questions.php / reviews.php set $stats to their own page counts).
// Reuse $stats only when it already carries the badge keys (the dashboard's full stats);
// otherwise run the cheap badge query — not the ~20-query dashboard aggregate.
$navBadges = (isset($stats) && is_array($stats) && array_key_exists('low_stock', $stats))
    ? $stats
    : getSidebarBadges();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= isset($page_title) ? $page_title . ' — ' : '' ?>DentInno CRM</title>
    <link rel="icon" href="<?= APP_URL ?>/assets/images/logo.png">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="<?= APP_URL ?>/assets/images/logo.png" alt="DentInno" class="logo-img">
        <div class="logo-text">
            <span class="logo-name">DentInno</span>
            <span class="logo-sub">CRM Dashboard</span>
        </div>
    </div>

    <nav class="sidebar-nav">
<?php
// ---- Dynamic, permission-filtered navigation (DB-driven page_registry via navTree()) ----
// Only pages the current role may VIEW are shown; empty groups disappear automatically.
$nav = function_exists('navTree') ? navTree() : [];
// Per-page badges (only a few pages carry them).
$rfc = 0;
try { $rfc = (int)(db()->fetchOne("SELECT COUNT(*) c FROM refund_requests WHERE status='pending'")['c'] ?? 0); } catch (Throwable $e) {}
$navBadgeHtml = [
    'products' => (($navBadges['low_stock'] ?? 0) > 0)     ? '<span class="nav-badge warn">' . (int)$navBadges['low_stock'] . '</span>' : '',
    'orders'   => (($navBadges['pending_orders'] ?? 0) > 0) ? '<span class="nav-badge">' . (int)$navBadges['pending_orders'] . '</span>' : '',
    'refunds'  => ($rfc > 0)                                 ? '<span class="nav-badge">' . $rfc . '</span>' : '',
];
// NOTE: use $navPages (not $pages) as the loop var — header.php is included midway through
// list pages (orders/products/customers) that hold their pagination count in $pages. A generic
// $pages here would clobber it, turning `for($i=1;$i<=$pages;...)` into an array comparison that
// is always true → an effectively infinite pagination loop (multi-GB page, minutes to "load").
foreach ($nav as $group => $navPages):
    // Super-only "Settings" sub-tabs as a CONFIGURATION quick-links block, just before SYSTEM.
    if ($group === 'SYSTEM' && can('settings', 'view')):
        $cfgNow = ($current_page === 'settings' && isset($_GET['page'])) ? preg_replace('/[^a-z]/', '', $_GET['page']) : '';
?>
        <div class="nav-section">
            <span class="nav-section-label">CONFIGURATION</span>
            <a href="<?= APP_URL ?>/pages/settings.php?page=home" class="nav-item <?= $cfgNow === 'home' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i><span>Home Page</span></a>
            <a href="<?= APP_URL ?>/pages/settings.php?page=contact" class="nav-item <?= $cfgNow === 'contact' ? 'active' : '' ?>"><i class="fa-solid fa-headset"></i><span>Contact Page</span></a>
            <a href="<?= APP_URL ?>/pages/settings.php?page=about" class="nav-item <?= $cfgNow === 'about' ? 'active' : '' ?>"><i class="fa-solid fa-circle-info"></i><span>About Page</span></a>
            <a href="<?= APP_URL ?>/pages/settings.php?page=catalog" class="nav-item <?= $cfgNow === 'catalog' ? 'active' : '' ?>"><i class="fa-solid fa-box-open"></i><span>Catalog / Products</span></a>
            <a href="<?= APP_URL ?>/pages/settings.php?page=general" class="nav-item <?= $cfgNow === 'general' ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i><span>General</span></a>
        </div>
<?php endif; ?>
        <div class="nav-section">
            <span class="nav-section-label"><?= htmlspecialchars($group) ?></span>
            <?php foreach ($navPages as $p):
                $key = $p['page_key'];
                $isActive = ($current_page === $key) || ($key === 'dashboard' && $current_page === 'index');
                if ($key === 'settings') $isActive = ($current_page === 'settings' && !isset($_GET['page']));
                $href = APP_URL . '/' . ltrim($p['url'], '/');
            ?>
            <a href="<?= htmlspecialchars($href) ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
                <i class="fa-solid <?= htmlspecialchars($p['icon'] ?: 'fa-circle') ?>"></i>
                <span><?= htmlspecialchars($p['label']) ?></span>
                <?= $navBadgeHtml[$key] ?? '' ?>
            </a>
            <?php endforeach; ?>
        </div>
<?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <!-- Links to Settings (account/profile + password) — available to every logged-in admin,
             even roles that don't have the Settings page in their nav. -->
        <a href="<?= APP_URL ?>/pages/settings.php" class="admin-info" title="My account &amp; password" style="text-decoration:none;color:inherit;">
            <div class="admin-avatar"><?= strtoupper(substr($admin['name'] ?? '', 0, 1)) ?></div>
            <div class="admin-details">
                <span class="admin-name"><?= htmlspecialchars($admin['name'] ?? '') ?></span>
                <span class="admin-role"><?= ucfirst(str_replace('_', ' ', $admin['role'] ?? '')) ?></span>
            </div>
        </a>
        <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</aside>

<!-- Main Content -->
<div class="main-wrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-breadcrumb">
                <span class="breadcrumb-home">DentInno</span>
                <i class="fa-solid fa-chevron-right"></i>
                <span><?= isset($page_title) ? $page_title : 'Dashboard' ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <!-- Search -->
            <div class="topbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search products, orders… (Enter)" id="globalSearch"
                    onkeydown="if(event.key==='Enter'){var q=this.value.trim(); if(!q)return; var base='<?= APP_URL ?>/pages/'; var dest=/^(#|ord|sdi|inv)/i.test(q)?'orders.php?search=':'products.php?search='; window.location.href=base+dest+encodeURIComponent(q.replace(/^#/,''));}">
            </div>

            <!-- Notifications -->
            <div class="notif-wrapper">
                <button class="icon-btn" id="notifBtn">
                    <i class="fa-solid fa-bell"></i>
                    <?php if($navBadges['notif_count'] > 0): ?>
                    <span class="notif-dot"><?= $navBadges['notif_count'] ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <a href="#" onclick="markAllNotifs(event)">Mark all read</a>
                    </div>
                    <?php foreach($navBadges['notifications'] as $notif): ?>
                    <div class="notif-item notif-<?= $notif['type'] ?>" data-nid="<?= (int)$notif['id'] ?>" onclick="markNotif(this)" style="cursor:pointer;">
                        <div class="notif-icon">
                            <?php
                            $icons = ['order'=>'cart-shopping','payment'=>'indian-rupee-sign','stock'=>'boxes-stacked','customer'=>'user','system'=>'gear'];
                            echo '<i class="fa-solid fa-' . ($icons[$notif['type']] ?? 'bell') . '"></i>';
                            ?>
                        </div>
                        <div class="notif-content">
                            <span class="notif-title"><?= htmlspecialchars($notif['title']) ?></span>
                            <span class="notif-msg"><?= htmlspecialchars($notif['message']) ?></span>
                            <span class="notif-time"><?= timeAgo($notif['created_at']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($navBadges['notifications'])): ?>
                    <div class="notif-empty">No new notifications</div>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            (function(){
              const tok = document.querySelector('meta[name="csrf-token"]')?.content || '';
              async function post(body){
                try { const r = await fetch('<?= APP_URL ?>/pages/notifications.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-Token':tok},body:JSON.stringify(body)}); return r.json(); }
                catch(e){ return {success:false}; }
              }
              function bumpDot(setTo){
                const dot=document.querySelector('.notif-dot');
                if(!dot) return;
                if(setTo!==undefined){ if(setTo<=0) dot.remove(); else dot.textContent=setTo; return; }
                const v=Math.max(0,(parseInt(dot.textContent)||0)-1); if(v<=0) dot.remove(); else dot.textContent=v;
              }
              window.markNotif = async function(el){
                const id=el.getAttribute('data-nid'); if(!id||el.dataset.done) return;
                const r=await post({action:'read',id:parseInt(id)});
                if(r.success){ el.dataset.done='1'; el.style.opacity='.45'; el.style.pointerEvents='none'; bumpDot(); }
              };
              window.markAllNotifs = async function(e){
                e.preventDefault();
                const r=await post({action:'read_all'});
                if(r.success){ document.querySelectorAll('.notif-item').forEach(n=>{n.dataset.done='1';n.style.opacity='.45';n.style.pointerEvents='none';}); bumpDot(0); }
              };
            })();
            </script>

            <!-- Admin -->
            <div class="admin-chip">
                <div class="admin-chip-avatar"><?= strtoupper(substr($admin['name'] ?? '', 0, 1)) ?></div>
                <span><?= htmlspecialchars(explode(' ', $admin['name'] ?? '')[0]) ?></span>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">
