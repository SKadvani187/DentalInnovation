# DentInno Admin Panel — RBAC Redesign Roadmap

**Status:** Design (no code written yet)
**Goal:** Move from the current *static, code-defined* RBAC (`rolePermissions()` in `includes/auth.php`) to a *database-driven, configurable* RBAC where a super admin manages **custom roles** and a **page × action permission matrix** through the UI. New pages are added by inserting a `page_registry` row (migration) — the sidebar and the matrix pick them up automatically; the page's behaviour stays in code.

---

## 0. Decisions baked into this design

| # | Decision | Choice | Where it lives |
|---|----------|--------|----------------|
| 1 | Action model | **4 verbs** (View / Create / Edit / Delete). Special actions (approve, import, status-change, generate) **map onto** these. | `page_registry.supports_*` |
| 2 | Sensitive pages (Settings / Admins / Refunds / Roles / Permissions) | **Super-admin only**, excluded from the matrix. | `page_registry.is_super_only` |
| 3 | `super_admin` | Implicit ALL access; **not** shown in the matrix. | `roles.is_super` |
| 4 | Matrix UI layout | **Per-role editor** (pick a role → grid of pages × 4 checkboxes). *(proposed default — confirm)* | UI |
| 5 | When permission changes take effect | **Immediately, next request** via a version counter. *(proposed default — confirm)* | `rbac_meta.perm_version` |

Items 4 and 5 are still open for your final confirmation; everything else is settled.

---

## 1. Data model

Four tables (one is an alteration of the existing `admin_users`).

### 1.1 `roles`

```sql
CREATE TABLE roles (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL UNIQUE,        -- machine id, e.g. 'catalog-manager'
  description VARCHAR(255) DEFAULT NULL,
  is_super    TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = super_admin (bypasses all checks, hidden from matrix)
  is_system   TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = built-in (super_admin/admin/staff), cannot be deleted
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.2 `page_registry` — the single source of truth for menu + matrix

```sql
CREATE TABLE page_registry (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  page_key        VARCHAR(50)  NOT NULL UNIQUE,    -- stable id used in code + role_permissions, e.g. 'products'
  label           VARCHAR(80)  NOT NULL,           -- sidebar text
  url             VARCHAR(160) NOT NULL,           -- relative link, e.g. 'pages/products.php'
  icon            VARCHAR(50)  DEFAULT NULL,       -- font-awesome class
  nav_group       VARCHAR(40)  DEFAULT NULL,       -- sidebar section: 'CATALOG','SALES',...
  group_order     INT NOT NULL DEFAULT 0,          -- order of the group
  sort_order      INT NOT NULL DEFAULT 0,          -- order within the group

  -- Which verbs apply (drives which matrix checkboxes are enabled). View-only page => only supports_view=1.
  supports_view   TINYINT(1) NOT NULL DEFAULT 1,
  supports_create TINYINT(1) NOT NULL DEFAULT 0,
  supports_edit   TINYINT(1) NOT NULL DEFAULT 0,
  supports_delete TINYINT(1) NOT NULL DEFAULT 0,

  is_super_only   TINYINT(1) NOT NULL DEFAULT 0,   -- settings/admins/refunds/roles/permissions: never in matrix
  show_in_nav     TINYINT(1) NOT NULL DEFAULT 1,   -- reachable but not a top-level menu item when 0
  is_active       TINYINT(1) NOT NULL DEFAULT 1,   -- global on/off switch for a page
  is_system       TINYINT(1) NOT NULL DEFAULT 0,   -- protect core rows (dashboard/roles/permissions) from deletion
  description     VARCHAR(255) DEFAULT NULL,        -- help text shown in the matrix
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nav (is_active, show_in_nav, group_order, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 1.3 `role_permissions` — the matrix (one row per role per page)

```sql
CREATE TABLE role_permissions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  role_id     INT NOT NULL,
  page_id     INT NOT NULL,
  can_view    TINYINT(1) NOT NULL DEFAULT 0,
  can_create  TINYINT(1) NOT NULL DEFAULT 0,
  can_edit    TINYINT(1) NOT NULL DEFAULT 0,
  can_delete  TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_role_page (role_id, page_id),
  FOREIGN KEY (role_id) REFERENCES roles(id)         ON DELETE CASCADE,
  FOREIGN KEY (page_id) REFERENCES page_registry(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Code references pages by the readable `page_key`; the service resolves `page_key → page_id` once and caches it. Readable code **and** referential integrity (cascade cleans up permissions when a page or role is removed).

### 1.4 `admin_users` — change role string to FK

```sql
ALTER TABLE admin_users ADD COLUMN role_id INT NULL AFTER role;
ALTER TABLE admin_users ADD FOREIGN KEY (role_id) REFERENCES roles(id);
-- keep the old `role` VARCHAR temporarily for rollback; drop it after cut-over.
```

### 1.5 `rbac_meta` — live-change version counter (decision #5)

```sql
CREATE TABLE rbac_meta (
  id           TINYINT PRIMARY KEY DEFAULT 1,
  perm_version INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO rbac_meta (id, perm_version) VALUES (1,1);
-- bumped (+1) whenever any role/permission/registry row changes.
```

---

## 2. Page registry seed (current panel mapped to the 4 verbs)

`V`=view, `C`=create, `E`=edit, `D`=delete. **SO** = `is_super_only`, **SYS** = `is_system`.
Special actions are folded into a verb (shown in the "maps via" column).

| page_key | Group | V | C | E | D | SO | SYS | Notes / special-action mapping |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|---|
| dashboard | MAIN | ✓ | | | | | ✓ | Always visible to any logged-in user (landing). |
| products | CATALOG | ✓ | ✓ | ✓ | ✓ | | | CSV import → C; review approve/delete → E/D |
| categories | CATALOG | ✓ | ✓ | ✓ | ✓ | | | |
| combos | CATALOG | ✓ | ✓ | ✓ | ✓ | | | image upload → C/E |
| offers | CATALOG | ✓ | ✓ | ✓ | ✓ | | | |
| testimonials | CATALOG | ✓ | ✓ | ✓ | ✓ | | | |
| orders | SALES | ✓ | | ✓ | | | | status/payment/tracking/notes → E |
| refunds | SALES | ✓ | | ✓ | | ✓ | | approve/reject → E. **Super only.** |
| customers | SALES | ✓ | ✓ | ✓ | ✓ | | | CSV import → C |
| payments | SALES | ✓ | | | | | | read-only listing |
| messages | SALES | ✓ | | ✓ | ✓ | | | mark-read → E |
| bulk_quotes | SALES | ✓ | | ✓ | ✓ | | | status → E |
| coupons | MARKETING | ✓ | ✓ | ✓ | ✓ | | | generate codes → C |
| reviews | MARKETING | ✓ | | ✓ | ✓ | | | approve/verify → E |
| questions | MARKETING | ✓ | | ✓ | ✓ | | | answer/approve → E |
| wishlists | MARKETING | ✓ | | | | | | read-only |
| shipping | SHIPPING | ✓ | ✓ | ✓ | ✓ | | | methods/zones/rules/pincodes |
| shipping_calculator | SHIPPING | ✓ | | | | | | tool, read-only |
| events | ENGAGE | ✓ | ✓ | ✓ | ✓ | | | registrations PII is gated by view |
| courses | ENGAGE | ✓ | ✓ | ✓ | ✓ | | | enrollment PII is gated by view |
| reports | REPORTS | ✓ | | | | | | read-only analytics |
| settings | SYSTEM | ✓ | | ✓ | | ✓ | ✓ | all config + secrets. **Super only.** Sub-nav (Home/Contact/About/Catalog/General) are `settings.php?page=…` under this one key. |
| admins | SYSTEM | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | admin-user mgmt. **Super only.** |
| roles | SYSTEM | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | **NEW** — RBAC role mgmt. **Super only.** |
| permissions | SYSTEM | ✓ | | ✓ | | ✓ | ✓ | **NEW** — the matrix editor. **Super only.** |

This table = the action taxonomy decision in concrete form. View-only pages have C/E/D = blank → the matrix grays those out.

---

## 3. Backend permission service

New file `includes/rbac.php` (or extend `auth.php`). Public API:

```php
loadUserPermissions(int $roleId): array   // 1 query → [page_key => ['v'=>1,'c'=>0,'e'=>1,'d'=>0], ...]
userIsSuper(): bool                        // roles.is_super for the session role
can(string $pageKey, string $action): bool // 'view'|'create'|'edit'|'delete'; super bypass
requireView(string $pageKey): void         // top of a GET page → 403 HTML page if no view
requireAction(string $pageKey, string $action): void  // in an AJAX handler → 403 JSON if denied
navTree(): array                           // page_registry filtered by can(view), grouped, for the sidebar
```

`can()` logic:

```php
function can($pageKey, $action) {
    if (userIsSuper()) return true;                 // super bypass
    $p = $_SESSION['rbac'][$pageKey] ?? null;        // cached permission set
    if (!$p) return false;                           // unknown page / no row = deny
    return !empty($p[ ['view'=>'v','create'=>'c','edit'=>'e','delete'=>'d'][$action] ]);
}
```

**Caching + live invalidation (decision #5):**
- At login, `loadUserPermissions()` fills `$_SESSION['rbac']` and stores `$_SESSION['rbac_version'] = rbac_meta.perm_version`.
- On each request (in `auth.php`, cheap single-row read), if `rbac_meta.perm_version != $_SESSION['rbac_version']`, reload and update the session. So a matrix edit by a super admin is reflected on the affected user's **next request** without a forced logout.
- Saving the matrix / roles bumps `perm_version` by 1.

**Backward-compat shim** so we don't rewrite 24 pages at once:

```php
function hasPermission($legacyPerm) {           // old API keeps working
    static $map = ['manage_products'=>['products','edit'], 'view_reports'=>['reports','view'], ...];
    if (userIsSuper()) return true;
    [$key,$act] = $map[$legacyPerm] ?? [null,null];
    return $key ? can($key,$act) : false;
}
```

---

## 4. Enforcement points (defense in depth — hiding the menu is NOT security)

1. **Sidebar** (`includes/header.php`): build from `navTree()` — render a link only if `can($key,'view')`; **hide a group** when all its pages are hidden; **Dashboard always shown**.
2. **Page top** (every GET page): `requireView('products');` right after the auth include — typing the URL directly is still blocked.
3. **Action buttons** (templates):
   ```php
   <?php if (can('products','create')): ?> <button id="addProduct">+ Add Product</button> <?php endif; ?>
   ```
4. **AJAX handlers** (every write — the real boundary): replace `requirePermissionAjax('manage_products')` with the mapped action:
   ```php
   if (!verifyCsrf()) { ... 403 ... }
   requireAction('products','create');   // save/insert
   requireAction('products','edit');     // update/toggle/approve
   requireAction('products','delete');   // delete
   ```

**Special-action → verb mapping** used in handlers (the contract):
| Handler example | Verb |
|---|---|
| save (new id absent) / CSV import / coupon generate | `create` |
| save (edit) / toggle / approve / verify / answer / status change / mark-read / save settings | `edit` |
| delete / bulk delete | `delete` |
| listing / view detail / export / calculator / reports | `view` |

---

## 5. The two admin UIs (super-admin only)

### 5.1 Roles page (`pages/roles.php`)
- List roles (name, description, #users, system badge).
- Create / edit / delete a role. **Block delete** if users are assigned or `is_system=1`.
- Optional: **Clone role** (copies its matrix as a starting point).
- Gated by `requireView('roles')` + `requireAction('roles', …)`; the page itself is `is_super_only`.

### 5.2 Permissions Matrix (`pages/permissions.php`) — *per-role editor (decision #4)*
- Dropdown: pick a role (excludes super roles).
- Table: rows = `page_registry` where `is_super_only=0 AND is_active=1`, columns = View / Create / Edit / Delete.
- A checkbox is **disabled** when the page doesn't `support_*` that verb.
- UX rule: ticking Create/Edit/Delete **auto-ticks View** (and disables un-ticking View while any C/E/D is on) — create without view is meaningless.
- Save → upsert `role_permissions`, bump `perm_version`.
- Assign a role to a user is done on `pages/admins.php` (role dropdown sourced from `roles`).

---

## 6. Migration plan (phased, each step shippable & reversible)

**Phase 1 — Schema & seed (data only, no behaviour change)**
- Migration: create `roles`, `page_registry`, `role_permissions`, `rbac_meta`; add `admin_users.role_id`.
- Seed `roles`: `super_admin (is_super=1,is_system=1)`, `admin (is_system=1)`, `staff (is_system=1)`.
- Seed `page_registry` from Section 2.
- Seed `role_permissions` for admin & staff to **exactly match today's `rolePermissions()`** (so nothing changes for current users).
- Backfill `admin_users.role_id` from the existing `role` string.

**Phase 2 — Permission service + shim**
- Add `includes/rbac.php`; wire `loadUserPermissions` into login + the per-request version check in `auth.php`.
- Re-implement `hasPermission()`/`requirePermissionAjax()` as shims over `can()`. **All pages keep working unchanged.**

**Phase 3 — Enforcement wiring (page by page, one nav group at a time)**
- Add `requireView()` to each page top.
- Convert handlers from `requirePermissionAjax('manage_x')` to `requireAction('key','verb')`.
- Wrap action buttons in `can()`.
- Switch the sidebar to `navTree()`.

**Phase 4 — Admin UIs**
- Build `pages/roles.php` and `pages/permissions.php` (super-only). Register both in `page_registry`.

**Phase 5 — Cut-over & cleanup**
- Move user role assignment fully to `role_id`; stop reading the old `role` string.
- QA pass with a dedicated test-case sheet (mirror of the one already produced).
- Drop `rolePermissions()` and the legacy `admin_users.role` column once everything reads from the DB.

---

## 7. Risks & safeguards

| Risk | Safeguard |
|---|---|
| **Lockout** (a super admin can't reach RBAC) | super role always full; `roles`/`permissions`/`dashboard` rows are `is_system=1` and `is_super_only=1` → never disabled, never in matrix. Keep the last-super-admin guard from `admins.php`. |
| **Privilege escalation** (a role grants itself more) | The matrix is editable by super admins only; sensitive pages are `is_super_only` and excluded. A non-super role can never open `permissions.php`. |
| **Registry/code drift** (row → missing file, or file → no row) | Dev-only diagnostics check: compare `page_registry.url` to `file_exists()`, and `requireView()` keys to registry rows; warn on mismatch. |
| **Direct-URL bypass** | `requireView()` enforced server-side on every page, independent of the sidebar. |
| **Hidden-button bypass** | Every write handler enforces `requireAction()`; hidden buttons are UX only. |
| **Stale sessions after a permission change** | `perm_version` re-load on next request (decision #5). |
| **Empty / unknown role** | `can()` denies by default. |
| **View prerequisite** | UI auto-enforces View when any C/E/D is set; page gate requires View. |

---

## 8. What a "new page" looks like after this lands

1. Write `pages/foo.php` with `requireView('foo')` at the top and `requireAction('foo','create'|'edit'|'delete')` in its handlers; wrap its buttons in `can('foo', …)`.
2. Migration: `INSERT INTO page_registry (page_key,label,url,icon,nav_group,...,supports_*) VALUES ('foo', ...);` and bump `perm_version`.
3. (Optional) pre-grant some roles in `role_permissions`, or let the super admin tick the boxes in the matrix UI.

The sidebar entry and the matrix row appear automatically; access is governed entirely by the matrix.

---

## 9. Open items to confirm before coding

- **Decision #4** — Matrix layout: per-role editor (my recommendation) vs full grid vs per-page.
- **Decision #5** — Live changes: immediate via `perm_version` (my recommendation) vs next-login only.
- **Orders create/delete** — admin currently never creates/deletes orders (they come from the storefront). I mapped orders to **View + Edit** only. Confirm, or add Create if you want manual order entry.
- **Settings sub-pages** — treated as one `settings` key (super-only). Confirm you don't need per-section (Home/Contact/About) permissions for non-super roles.
