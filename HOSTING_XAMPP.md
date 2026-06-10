# Hosting Dental Innovation on XAMPP — Step by Step

This guide hosts **both apps** of this project on a single XAMPP (Apache + MySQL):

| App | Folder | Tech | Served at |
|-----|--------|------|-----------|
| **Frontend** (storefront) | `smart-dental-innovation/` (built into `dist/`) | React 19 + Vite (single-page app) | `http://HOST/` |
| **Backend** (admin + API) | `dentinno/` | PHP 8 + MySQL | `http://HOST/dentinno` → API at `http://HOST/dentinno/api/v1` |

Everything runs on **one Apache on port 80**, so the frontend and API share the same origin → **no CORS problems**.

> **You do NOT have to copy files into `htdocs`.** Apache is pointed directly at your project folders in `d:\ReactProjects\DentalInnovation` using `DocumentRoot` + `Alias` (Step 5). Your files stay where they are.

Throughout, **`HOST`** means:
- `localhost` while testing on this PC,
- your **LAN IP** (e.g. `192.168.1.50`) to serve other people on the same network,
- your **domain** (e.g. `shop.example.com`) for the internet.

---

## Step 0 — Start XAMPP
1. Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. **Start** → **Apache** and **MySQL** (both go green).
3. If Apache won't start, port 80 is taken (Skype / IIS / "World Wide Web Publishing Service"). Stop that service, **or** change Apache's port: Config → `httpd.conf` → change `Listen 80` to `Listen 8080`. Then every URL below becomes `http://HOST:8080/...`.

## Step 1 — Create the database (+ optional app user)
1. Open **http://localhost/phpmyadmin**.
2. **Databases** → create database **`dentinno_crm`** with collation **`utf8mb4_general_ci`**.
3. *(Recommended for serving others)* **User accounts → Add user account**:
   - User name `dentinno`, Host `localhost`, set a **strong password**.
   - Tick **"Grant all privileges on database `dentinno_crm`"**.
   - You'll enter these credentials in Step 3.

## Step 2 — Import schema + data (ORDER MATTERS)
The migration runner only processes files named `database_*.sql`. The core schema file **`database.sql`** (no underscore) is **not** included, so import it **first**.

**2a — Core schema first** (phpMyAdmin):
- Select `dentinno_crm` → **Import** → choose **`dentinno/database.sql`** → **Go**.

**2b — Apply everything else** (run the migration runner — safe to re-run):
```powershell
cd d:\ReactProjects\DentalInnovation\dentinno
C:\xampp\php\php.exe migrate.php
```
Expect `APPLIED database_additions.sql … database_initial_data_insert.sql …` ending in **"Done."**
Check status anytime:
```powershell
C:\xampp\php\php.exe migrate.php --status
```
*(Alternative: import each `database_*.sql` manually in phpMyAdmin in this order: `additions, auth_orders, bestsellers_fix, delivery_pincodes, initial_data_insert, otp, product_questions, razorpay, storefront, whatsapp`.)*

## Step 3 — Configure the backend
File: **`dentinno/includes/config.php`**
- `DB_USER` / `DB_PASS` → the user from Step 1 (or keep `root` / empty for **local-only**).
- `APP_URL` → **`http://HOST/dentinno`** (already set to `http://localhost/dentinno`; change `HOST` for LAN/domain).
- `OTP_SSL_INSECURE` → already set to **`false`** (production). Set `true` again only if local antivirus/proxy breaks SSL during testing.
- **Secrets:** Fast2SMS, Razorpay (test key `rzp_test_...`), and the blank Anthropic key are stored in plaintext here. For a real deployment, **rotate them** and move them into a git-ignored `config.local.php` or environment variables. Switch Razorpay to live keys only when ready.

## Step 4 — Build the frontend
1. Set the API URL the built app will call. File: **`smart-dental-innovation/.env.production`**
   ```
   VITE_API_URL=http://HOST/dentinno/api/v1
   ```
   (already set to `http://localhost/dentinno/api/v1` — change `HOST` to your IP/domain when serving others). It **must be an absolute URL**, and it is **baked in at build time** — rebuild whenever `HOST` changes.
2. Build:
   ```powershell
   cd d:\ReactProjects\DentalInnovation\smart-dental-innovation
   npm install        # first time only
   npm run build      # produces dist\
   ```
   No Vite `base` change is needed because the storefront is served at the site root `/`.

## Step 5 — Point Apache at your folders (no htdocs copy)
Open `httpd.conf` (XAMPP Control → **Apache → Config → httpd.conf**) and make these three changes.

**5a — Serve the React build at the site root.** Find the existing `DocumentRoot` / `<Directory>` lines (around the `htdocs` definition) and replace them with:
```apache
DocumentRoot "d:/ReactProjects/DentalInnovation/smart-dental-innovation/dist"
<Directory "d:/ReactProjects/DentalInnovation/smart-dental-innovation/dist">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

**5b — Expose the PHP backend at `/dentinno`.** Add this block (anywhere after 5a):
```apache
Alias /dentinno "d:/ReactProjects/DentalInnovation/dentinno"
<Directory "d:/ReactProjects/DentalInnovation/dentinno">
    Options -Indexes +FollowSymLinks
    AllowOverride All          # lets dentinno/.htaccess run — needed for Bearer-token auth
    Require all granted
</Directory>
```

**5c — Confirm these modules are enabled** (search for the lines; delete any leading `#`):
```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule setenvif_module modules/mod_setenvif.so
```

Save, then **Stop → Start Apache** (config changes require a restart).

> Use **forward slashes** in Windows paths inside Apache config — this is correct and intentional.

## Step 6 — Test locally
- API: open **http://localhost/dentinno/api/v1/products.php** → should return **JSON** with products.
- Storefront: open **http://localhost/** → products and categories load.
- Admin: open **http://localhost/dentinno** → log in with `admin@dentinno.com` / `password`.
- If the store loads but is empty, open **DevTools → Network** and check the API calls go to `http://localhost/dentinno/api/v1/...` and return `200`. A wrong `VITE_API_URL` is the usual cause — fix `.env.production` and rebuild (Step 4).

## Step 7 — Serve it to other people (LAN)
1. Find this PC's IP: run `ipconfig` → note the **IPv4 Address** (e.g. `192.168.1.50`).
2. **Allow Apache through Windows Firewall:** Windows Defender Firewall → *Allow an app* → add `C:\xampp\apache\bin\httpd.exe` (tick **Private**; add **Public** only if needed), or allow inbound **TCP port 80**.
3. Set `HOST` to that IP in **both** `config.php` (`APP_URL`, Step 3) **and** `.env.production` (`VITE_API_URL`, Step 4), then **rebuild the frontend** so the baked-in API URL matches.
4. Others on the same network open **http://192.168.1.50/**.
   - For **internet** access you need either router port-forwarding (+ a static IP or dynamic-DNS) or a real server/host with a domain.

## Step 8 — Production hardening (you're serving others)
- **Change the admin password** (default `admin@dentinno.com` / `password`) from the admin UI, or update the hash in the `admin_users` table.
- **Set a MySQL password** for the app user — never ship `root` with an empty password.
- **HTTPS:** for internet exposure, serve over HTTPS (XAMPP SSL vhost, or a reverse proxy / host with a TLS certificate). Then change `APP_URL` and `VITE_API_URL` to `https://...` and rebuild.
- **Lock CORS (optional):** in `dentinno/api/v1/_bootstrap.php`, add your real storefront origin to `$allowed` and consider removing the `Access-Control-Allow-Origin: *` fallback. Not required while same-origin.
- **Rotate** the committed Fast2SMS / Razorpay keys (see Step 3).

---

## Quick verification checklist
1. `C:\xampp\php\php.exe migrate.php --status` → all files `[x]`.
2. `http://localhost/dentinno/api/v1/products.php` → JSON with products.
3. `http://localhost/` → storefront shows products; Network tab shows `200`s.
4. `http://localhost/dentinno` → admin login works.
5. From another device on the LAN: `http://<your-IP>/` loads the store.

## How to switch HOST later (recap)
1. `dentinno/includes/config.php` → `APP_URL` = `http://NEWHOST/dentinno`
2. `smart-dental-innovation/.env.production` → `VITE_API_URL` = `http://NEWHOST/dentinno/api/v1`
3. `cd smart-dental-innovation && npm run build`
4. Restart Apache. (Apache paths in `httpd.conf` don't change — only `HOST` in the two files above.)

---

## Deploying to a real server (cPanel / VPS) — short version
Same idea, different machine:
1. Upload `dentinno/` to the web root (or a subfolder) on a **PHP 8 + MySQL** host.
2. Create the DB, import `database.sql`, then run `php migrate.php` (or import the `database_*.sql` files in order).
3. Edit `config.php`: DB credentials, `APP_URL`, keep `OTP_SSL_INSECURE=false`, set real API keys.
4. Set `smart-dental-innovation/.env.production` → `VITE_API_URL=https://yourdomain/dentinno/api/v1`, run `npm run build`, and upload the contents of `dist/` to the site root.
5. Ensure `mod_rewrite`/`mod_headers` are enabled and `.htaccess` is honored (`AllowOverride All`).
6. Enable HTTPS and complete the Step 8 hardening.