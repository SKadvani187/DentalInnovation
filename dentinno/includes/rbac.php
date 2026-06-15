<?php
// =====================================================================
// DB-driven RBAC service (Phase 2).
// Source of truth: roles / page_registry / role_permissions / rbac_meta.
// Requires: config.php (db()) and an active session. Loaded from auth.php.
//
// Public API:
//   can($pageKey, $action)        -> bool   ('view'|'create'|'edit'|'delete')
//   userIsSuper()                 -> bool
//   requireView($pageKey)         -> 403 HTML page + exit if no view
//   requireAction($pageKey,$act)  -> 403 JSON + exit if denied (AJAX handlers)
//   navTree()                     -> grouped, view-filtered page_registry for the sidebar
//   rbacLoad($roleId)             -> (re)load a role's permissions into the session
//   rbacEnsureLoaded()            -> per-request: load for old sessions, refresh on version bump
//   rbacBumpVersion()             -> call after any role/permission/registry change
// =====================================================================

if (!function_exists('can')) {

// ---- session-cached state ----------------------------------------------------

function userIsSuper(): bool {
    return !empty($_SESSION['rbac_is_super']);
}

// Global permission version (cheap, single-row). Bumped on any RBAC change so that
// already-logged-in users pick up changes on their next request without re-login.
function rbacVersion(): int {
    try {
        $r = db()->fetchOne("SELECT perm_version v FROM rbac_meta WHERE id=1");
        return $r ? (int)$r['v'] : 1;
    } catch (Throwable $e) { return 1; }
}

function rbacBumpVersion(): void {
    try { db()->execute("UPDATE rbac_meta SET perm_version = perm_version + 1 WHERE id=1"); }
    catch (Throwable $e) { /* best effort */ }
}

// Load a role's permission set + super flag + version snapshot into the session.
function rbacLoad(int $roleId): void {
    $perms = [];
    $isSuper = 0;
    try {
        $role = db()->fetchOne("SELECT is_super FROM roles WHERE id=? AND is_active=1", [$roleId]);
        if ($role) {
            $isSuper = (int)$role['is_super'];
            if (!$isSuper) {
                $rows = db()->fetchAll(
                    "SELECT pr.page_key, rp.can_view v, rp.can_create c, rp.can_edit e, rp.can_delete d
                       FROM role_permissions rp
                       JOIN page_registry pr ON pr.id = rp.page_id
                      WHERE rp.role_id = ? AND pr.is_active = 1",
                    [$roleId]
                );
                foreach ($rows as $r) {
                    $perms[$r['page_key']] = ['v'=>(int)$r['v'],'c'=>(int)$r['c'],'e'=>(int)$r['e'],'d'=>(int)$r['d']];
                }
            }
        }
    } catch (Throwable $e) { /* leave deny-all on error */ }

    $_SESSION['rbac']         = $perms;
    $_SESSION['rbac_is_super']= $isSuper;
    $_SESSION['rbac_role_id'] = $roleId;
    $_SESSION['rbac_version'] = rbacVersion();
}

// Per-request: ensure this admin's permissions are loaded and fresh.
// Handles (a) sessions created before Phase 2 (no rbac in session) and
// (b) live permission changes (version bump → reload).
function rbacEnsureLoaded(): void {
    if (!isLoggedIn()) return;
    if (!isset($_SESSION['rbac_role_id'])) {
        // Old session — resolve the user's role_id from the DB (fall back to role slug).
        $rid = 0;
        try {
            $row = db()->fetchOne("SELECT role_id, role FROM admin_users WHERE id=?", [$_SESSION['admin_id']]);
            $rid = (int)($row['role_id'] ?? 0);
            if (!$rid && !empty($row['role'])) {
                $r = db()->fetchOne("SELECT id FROM roles WHERE slug=?", [$row['role']]);
                $rid = (int)($r['id'] ?? 0);
            }
        } catch (Throwable $e) { /* deny-all */ }
        rbacLoad($rid);
        return;
    }
    if (($_SESSION['rbac_version'] ?? -1) !== rbacVersion()) {
        rbacLoad((int)$_SESSION['rbac_role_id']);
    }
}

// ---- the core check ----------------------------------------------------------

function can(string $pageKey, string $action = 'view'): bool {
    if (userIsSuper()) return true;                       // super bypass
    $p = $_SESSION['rbac'][$pageKey] ?? null;
    if (!$p) return false;                                // unknown page / no grant = deny
    $col = ['view'=>'v','create'=>'c','edit'=>'e','delete'=>'d'][$action] ?? null;
    return $col !== null && !empty($p[$col]);
}

// Map an AJAX action name to a CRUD verb for requireAction(). Lets a handler that dispatches
// many actions gate each with the right verb in one call:
//   save        -> create (no id) / edit (has id)
//   *delete*/*remove* -> delete       bulk -> delete if op=delete else edit
//   create/add/new/generate/import    -> create
//   get_* / view / list / calc / preview / search -> view
//   everything else (toggle/approve/verify/answer/status/update/restore/mark_*) -> edit
function rbacCrudVerb(string $action, array $data = []): string {
    $a = strtolower(trim($action));
    if ($a === 'bulk') return (($data['op'] ?? '') === 'delete') ? 'delete' : 'edit';
    if (strpos($a, 'delete') !== false || strpos($a, 'remove') !== false) return 'delete';
    if (strpos($a, 'save') === 0) return empty($data['id']) ? 'create' : 'edit';
    if (in_array($a, ['create','add','new','generate','import'], true)) return 'create';
    if (strpos($a, 'get_') === 0 || in_array($a, ['view','list','calc','preview','search'], true)) return 'view';
    return 'edit';
}

// ---- hard gates --------------------------------------------------------------

function requireView(string $pageKey): void {
    if (can($pageKey, 'view')) return;
    http_response_code(403);
    rbacRender403();
    exit;
}

function requireAction(string $pageKey, string $action): void {
    if (can($pageKey, $action)) return;
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You do not have permission for this action.']);
    exit;
}

function rbacRender403(): void {
    $url = defined('APP_URL') ? APP_URL : '';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 — Forbidden</title>'
       . '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#111;color:#eee;'
       . 'display:grid;place-items:center;min-height:100vh;margin:0}div{text-align:center}'
       . 'a{color:#D4A017}</style></head><body><div><h1>403 — Access denied</h1>'
       . '<p>You do not have permission to view this page.</p>'
       . '<p><a href="' . htmlspecialchars($url) . '/index.php">Back to dashboard</a></p>'
       . '</div></body></html>';
}

// ---- sidebar -----------------------------------------------------------------

// Returns ['GROUP' => [pageRow, ...], ...] of pages this admin may view, in nav order.
function navTree(): array {
    $tree = [];
    try {
        $pages = db()->fetchAll(
            "SELECT * FROM page_registry WHERE is_active=1 AND show_in_nav=1 ORDER BY group_order, sort_order"
        );
    } catch (Throwable $e) { return []; }
    foreach ($pages as $p) {
        if ($p['is_super_only'] && !userIsSuper()) continue;
        if (!can($p['page_key'], 'view')) continue;
        $tree[$p['nav_group'] ?: 'OTHER'][] = $p;
    }
    return $tree;
}

} // function_exists guard
