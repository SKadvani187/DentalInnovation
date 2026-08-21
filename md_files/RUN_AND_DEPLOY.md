# Run & Deploy — Smart Dental Innovations

Two apps:
- **Admin + API** — PHP/MySQL in `dentinno/` (XAMPP at `D:\DIP\OtherData\xampp`)
- **Storefront** — React/Vite in `smart-dental-innovation/`

---

## Local dev — start everything

### 1. MySQL (MariaDB)
XAMPP Control Panel → **Start MySQL**, OR:
```
D:\DIP\OtherData\xampp\mysql\bin\mysqld.exe --defaults-file="D:\DIP\OtherData\xampp\mysql\bin\my.ini"
```

### 2. PHP admin + API  (port 8088)
```
D:\DIP\OtherData\xampp\php\php.exe -S localhost:8088 -t "d:\DIP\OtherData\Project\DentalInnovation\dentinno"
```
- Admin UI: http://localhost:8088  (login `admin@dentinno.com` / `password`)
- API root: http://localhost:8088/api/v1/

### 3. React storefront  (port 5173)
```
cd d:\DIP\OtherData\Project\DentalInnovation\smart-dental-innovation
npm install        # first time only
npm run dev
```
- Store: http://localhost:5173
- Reads `VITE_API_URL` from `.env` (= http://localhost:8088/api/v1)

> Apache is NOT used locally — Windows `System` holds ports 80/8080, so the PHP built-in server is used instead. Same behaviour.

---

## Re-seed the DB from React static data
If you change `src/data/*.js` and want it in the DB:
```
cd smart-dental-innovation
node export-seed.mjs                      # writes seed-data.json
cd ..\dentinno
D:\DIP\OtherData\xampp\php\php.exe seed_from_react.php
```

## DB schema files (import order)
1. `dentinno/database.sql`            (core CRM — 10 tables)
2. `dentinno/database_additions.sql`  (events, courses, shipping, reviews — 11 tables)
3. `dentinno/database_storefront.sql` (combos, offers, testimonials)
4. `dentinno/database_auth_orders.sql`(customer token/addresses/wishlist, order_items extras)

---

## API summary (`/api/v1`)
Public (read): products, categories, combos, events, offers, testimonials, home, coupon
Auth (Bearer token): auth (login/me/profile), orders (place/list), wishlist (get/sync)

---

## Production deploy

### PHP API + admin
- Host `dentinno/` on any PHP 8 + MySQL server (shared host / VPS / cPanel).
- Import the 4 SQL files into the production DB.
- Edit `dentinno/includes/config.php`:
  - `DB_HOST/DB_USER/DB_PASS/DB_NAME` → prod credentials
  - `APP_URL` → your admin URL (e.g. `https://admin.yourdomain.com`)
- In `dentinno/api/v1/_bootstrap.php`, add your storefront origin to `$allowed` (CORS).

### React storefront
- Set `smart-dental-innovation/.env.production` → `VITE_API_URL=https://YOUR-DOMAIN/api/v1`
- Build: `npm run build`  → static files in `dist/`
- Host `dist/` on any static host (Netlify, Vercel, Nginx, S3+CloudFront).

### Checklist
- [ ] HTTPS on both origins
- [ ] CORS allowlist set to storefront origin (not `*`)
- [ ] Change default admin password
- [ ] Move product images off external CDNs (admin upload → `assets/images/products/`)
- [ ] DB backups
