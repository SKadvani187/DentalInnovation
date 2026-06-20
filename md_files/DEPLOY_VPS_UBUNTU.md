# Deploy Dental Innovation to an Ubuntu VPS with your domain

Target: a fresh **Ubuntu/Debian VPS** with SSH/root access and a **domain** you own.
End result (same single-origin layout as the local XAMPP setup, no CORS):

- `https://yourdomain.com/`         → React storefront (the built `dist/`)
- `https://yourdomain.com/dentinno` → PHP admin + API (`/dentinno/api/v1`)

Replace **`yourdomain.com`** and **`SERVER_IP`** with your real values everywhere below.

On the server everything lives in:
```
/var/www/dentalinnovation/
├── dentinno/   ← PHP backend (admin + API)
└── dist/       ← React build (frontend)
```

---

## Step 1 — Point your domain at the VPS (DNS)
In your domain registrar's DNS panel, create **A records** pointing to your VPS public IP:

| Type | Name | Value |
|------|------|-------|
| A | `@`   | `SERVER_IP` |
| A | `www` | `SERVER_IP` |

Save. DNS can take minutes to a few hours to propagate. Check from your PC:
```powershell
nslookup yourdomain.com
```
It should return `SERVER_IP` before continuing to SSL (Step 8).

## Step 2 — Connect to the VPS and update it
```bash
ssh root@SERVER_IP        # or: ssh youruser@SERVER_IP  then use sudo
sudo apt update && sudo apt upgrade -y
```

## Step 3 — Install the LAMP stack (Apache + PHP 8 + MariaDB)
```bash
sudo apt install -y apache2 mariadb-server \
  php php-cli php-mysql php-curl php-mbstring php-xml php-gd php-zip libapache2-mod-php
sudo systemctl enable --now apache2 mariadb
php -v        # confirm PHP 8.x
```
Enable the Apache modules the project's `.htaccess` relies on:
```bash
sudo a2enmod rewrite headers setenvif expires deflate
sudo systemctl restart apache2
```

## Step 4 — Open the firewall
```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'   # opens ports 80 and 443
sudo ufw enable
sudo ufw status
```

## Step 5 — Get your code onto the server
Create the folder, then upload. Two common ways:

**Option A — Git (if your repo is on GitHub/GitLab):**
```bash
sudo mkdir -p /var/www/dentalinnovation
sudo chown -R $USER:$USER /var/www/dentalinnovation
cd /var/www/dentalinnovation
git clone <your-repo-url> .
# this gives you ./dentinno and ./smart-dental-innovation
```

**Option B — Upload from your Windows PC with SCP** (run in PowerShell on your PC):
```powershell
# backend
scp -r "d:\ReactProjects\DentalInnovation\dentinno" root@SERVER_IP:/var/www/dentalinnovation/
# frontend build (see Step 6 first if you build locally)
scp -r "d:\ReactProjects\DentalInnovation\smart-dental-innovation\dist" root@SERVER_IP:/var/www/dentalinnovation/
```

## Step 6 — Build the frontend (pick ONE)
The React app must be compiled to static files (`dist/`) and `VITE_API_URL` must point at your domain's API.

**Option A — Build on your Windows PC, upload `dist/` (simplest, no Node on server):**
1. Edit `smart-dental-innovation/.env.production`:
   ```
   VITE_API_URL=https://yourdomain.com/dentinno/api/v1
   ```
2. Build, then upload (the SCP line in Step 5B):
   ```powershell
   cd d:\ReactProjects\DentalInnovation\smart-dental-innovation
   npm run build
   ```

**Option B — Build on the VPS:**
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
cd /var/www/dentalinnovation/smart-dental-innovation
echo "VITE_API_URL=https://yourdomain.com/dentinno/api/v1" > .env.production
npm install
npm run build      # creates ./dist
# move/symlink dist to the path the vhost expects:
ln -s /var/www/dentalinnovation/smart-dental-innovation/dist /var/www/dentalinnovation/dist
```

> The API URL is **baked in at build time** — if you change the domain later, rebuild and re-upload `dist/`.

## Step 7 — Create the database and import data
```bash
sudo mysql
```
In the MySQL prompt:
```sql
CREATE DATABASE dentinno_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'dentinno'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON dentinno_crm.* TO 'dentinno'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
Import the **core schema first** (it is NOT picked up by the migration runner), then run the migrations:
```bash
cd /var/www/dentalinnovation/dentinno
mysql -u dentinno -p dentinno_crm < database.sql      # core schema first
php migrate.php                                        # applies all database_*.sql in order
php migrate.php --status                               # verify: all [x]
```

## Step 8 — Configure the backend for production
Edit `/var/www/dentalinnovation/dentinno/includes/config.php`:
```php
define('DB_USER', 'dentinno');
define('DB_PASS', 'STRONG_PASSWORD_HERE');
define('APP_URL', 'https://yourdomain.com/dentinno');
define('OTP_SSL_INSECURE', false);   // keep false on a real server
```
Also **rotate** the committed Fast2SMS / Razorpay keys (and switch Razorpay from `rzp_test_...` to live keys when ready).

Set correct ownership so Apache can read the files:
```bash
sudo chown -R www-data:www-data /var/www/dentalinnovation
sudo find /var/www/dentalinnovation -type d -exec chmod 755 {} \;
sudo find /var/www/dentalinnovation -type f -exec chmod 644 {} \;
```

## Step 9 — Configure the Apache VirtualHost
Create the site config:
```bash
sudo nano /etc/apache2/sites-available/dentalinnovation.conf
```
Paste:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com

    # Frontend (React build) at the site root
    DocumentRoot /var/www/dentalinnovation/dist
    <Directory /var/www/dentalinnovation/dist>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Backend (PHP admin + API) at /dentinno
    Alias /dentinno /var/www/dentalinnovation/dentinno
    <Directory /var/www/dentalinnovation/dentinno>
        Options -Indexes +FollowSymLinks
        AllowOverride All          # lets dentinno/.htaccess run (Bearer-token auth)
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/dentalinnovation_error.log
    CustomLog ${APACHE_LOG_DIR}/dentalinnovation_access.log combined
</VirtualHost>
```
Enable it and reload:
```bash
sudo a2ensite dentalinnovation.conf
sudo a2dissite 000-default.conf      # disable the default placeholder site
sudo apache2ctl configtest           # should say: Syntax OK
sudo systemctl reload apache2
```
Test over HTTP first: open `http://yourdomain.com/` (store) and `http://yourdomain.com/dentinno/api/v1/products.php` (JSON).

## Step 10 — Enable free HTTPS (Let's Encrypt)
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```
Choose **redirect HTTP → HTTPS** when prompted. Certbot edits your vhost for port 443 and auto-renews (test renewal: `sudo certbot renew --dry-run`).

Now your site is live at **https://yourdomain.com/**.

> Because the storefront calls `https://yourdomain.com/dentinno/api/v1` (set in Step 6) and the API is on the same domain, there are no mixed-content or CORS issues. If you built `dist/` *before* deciding on HTTPS, rebuild with the `https://` URL and re-upload.

## Step 11 — Final hardening
- **Change the admin password** (default `admin@dentinno.com` / `password`): log into `https://yourdomain.com/dentinno` and update it, or change the hash in the `admin_users` table.
- **Secure MariaDB:** `sudo mysql_secure_installation`.
- **Keep secrets out of git:** move the API keys into a git-ignored `config.local.php` or environment variables.
- **Optional CORS lockdown:** in `dentinno/api/v1/_bootstrap.php`, add `https://yourdomain.com` to `$allowed` and drop the `*` fallback (not strictly needed while same-origin).
- **Backups:** schedule `mysqldump dentinno_crm` regularly.

---

## Verification checklist
1. `nslookup yourdomain.com` → your `SERVER_IP`.
2. `https://yourdomain.com/dentinno/api/v1/products.php` → JSON with products.
3. `https://yourdomain.com/` → storefront renders; browser shows a padlock (valid SSL).
4. `https://yourdomain.com/dentinno` → admin login works.
5. Browser DevTools → Network → API calls go to `https://yourdomain.com/dentinno/api/v1/...` and return `200`.

## Updating the site later
- **Backend change:** upload changed PHP files (or `git pull`), run `php migrate.php` if there are new `database_*.sql` files.
- **Frontend change:** `npm run build` (with the `https://yourdomain.com/...` API URL) and re-upload `dist/`.
- **New domain:** update `APP_URL` + `VITE_API_URL`, rebuild `dist/`, update `ServerName` in the vhost, re-run certbot.