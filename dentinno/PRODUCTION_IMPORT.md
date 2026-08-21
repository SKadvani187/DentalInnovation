# Production Catalogue Import — Runbook

Goal: replace the dummy/test data on the **production** server with the real Smart
Dental catalogue (555 source rows → ~525 products + ~30 combos), the same result
already verified locally.

Assumes **SSH + PHP CLI + MySQL** access on the server.

> ⚠️ Every step that deletes is **irreversible**. Do the backup in Step 1 and keep
> it until you've confirmed the live site looks right.

---

## 1. Back up production (do not skip)

```bash
mysqldump -u USER -p PROD_DB > prod_backup_$(date +%F).sql
# sanity check it's non-empty:
ls -lh prod_backup_*.sql
```

## 2. Enable maintenance mode

Storefront → Admin → Settings → **Maintenance Mode = ON** (customers see the
maintenance page instead of a half-updated catalogue).
SQL fallback if needed: the flag lives in `site_settings` (key `maintenanceMode`).

## 3. Bring production's schema up to date  ⚠️ most common failure point

Production likely has the **same schema drift** we found locally (e.g. the missing
`hsn_code` column that broke product save).

**First, find out exactly what's missing** — run the read-only drift check (reports
any expected table/column not present; changes nothing):

```bash
mysql -u USER -p PROD_DB < prod_schema_check.sql
```

- **No rows** → schema matches the reference; skip to Step 4.
- **`*** MISSING TABLE/COLUMN ***` rows** → apply the matching `database_*.sql`
  migration(s), then re-run the check until it's clean.

To apply migrations, the ones using `IF NOT EXISTS` are safe to re-run:

```bash
cd /path/to/dentinno
for f in database_*.sql; do
  case "$f" in
    database_purge_*.sql) continue ;;          # skip the purge scripts
  esac
  if grep -qiE "IF NOT EXISTS" "$f"; then
    echo ">> $f"; mysql -u USER -p PROD_DB < "$f"
  fi
done
```

Then make sure these specific recent ones are applied (re-running is harmless):
`database_gst_invoice.sql` (hsn_code), `database_product_cost_price.sql`,
and the migration that adds `is_new` to `products`.

The ~24 files **without** `IF NOT EXISTS` are one-time/data scripts
(`database_initial_data_insert.sql`, `database_razorpay.sql`, …) — do **not**
loop those; apply individually only if you know they're needed.

## 4. Configure production DB credentials

Create `dentinno/includes/config.local.php` on the server (git-ignored — never
commit it):

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'PROD_USER');
define('DB_PASS', 'PROD_PASS');
define('DB_NAME', 'PROD_DB');
define('APP_URL', 'https://yourdomain.com/dentinno');  // non-localhost => debug OFF
```

## 5. Upload the catalogue file

Copy `smartdental_import.json` into the production `dentinno/` directory
(scp / rsync / file manager).

## 6. Import — smoke test, then real run

```bash
php import_smartdental.php --limit=3      # import 3 only; confirm "Created: 3 ... Skipped: 0"
php import_smartdental.php --purge        # soft-delete dummy products + import all
```

Expected tail: `Created / Updated / Combos / Skipped: 0 / FAQs` and an active
product count around 525. Any per-row errors print to STDERR (usually a missing
column → go back to Step 3).

## 7. (Optional) Hard-delete the dummy products

`--purge` only *soft*-deletes the dummy rows (hidden, recoverable). To remove them
permanently along with their FK data:

```bash
# review first — order_items rows are order history:
mysql -u USER -p PROD_DB -e "SELECT id,name,sku FROM products WHERE is_deleted=1;"
mysql -u USER -p PROD_DB < database_purge_soft_deleted_products.sql
```

## 8. (Optional) Purge dummy orders / customers — clean launch

If you want zero fake order history at go-live (keeps `admin_users` + `coupons`):

```bash
mysql -u USER -p PROD_DB < database_purge_test_data.sql
```

See the comments in that file to also remove coupons or reset AUTO_INCREMENT.

## 9. Verify the live site

```bash
# API sanity (adjust path):
curl -s https://yourdomain.com/dentinno/api/v1/home.php | head -c 300
curl -s "https://yourdomain.com/dentinno/api/v1/products.php" | head -c 300
```

In the browser: product count, images load (catalogue images are CloudFront URLs —
no upload needed), categories, a product detail page, New Arrivals section + its
"View All" link.

## 10. Maintenance mode OFF

Admin → Settings → Maintenance Mode = OFF. Done.

---

## Rollback

If anything looks wrong, restore the Step 1 backup:

```bash
mysql -u USER -p PROD_DB < prod_backup_YYYY-MM-DD.sql
```

## Notes
- The importer is **idempotent by SKU** — re-running updates existing rows instead
  of duplicating, and never clobbers admin-curated `is_new` / `is_featured`.
- Combo-category items are routed to the `combos` table automatically.
- Keep secrets in `config.local.php` / env vars only — never in `config.php`.
