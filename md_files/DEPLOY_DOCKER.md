# Deploy Dental Innovation with Docker

This runs the whole app as containers, in the same **single-origin** layout as the
XAMPP/VPS setups (no CORS):

- `https://yourdomain.com/`          → React storefront (the built `dist/`)
- `https://yourdomain.com/dentinno`  → PHP admin + API (`/dentinno/api/v1`)

Two containers:

| Service | Image            | Role                                              |
|---------|------------------|---------------------------------------------------|
| `web`   | built from `Dockerfile` (PHP 8.2 + Apache) | serves storefront **and** PHP backend |
| `db`    | `mariadb:11`     | MySQL-compatible database (data in a named volume) |

Optional third container `caddy` adds automatic HTTPS.

Files that make this work (already in the repo):
`Dockerfile`, `docker-compose.yml`, `docker-compose.caddy.yml`,
`.dockerignore`, `.env.docker.example`, `docker/apache-vhost.conf`,
`docker/entrypoint.sh`, `docker/Caddyfile`.

---

## Step 0 — Prerequisites on the server
A Linux server (Ubuntu recommended) with Docker Engine + Compose plugin:
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER   # log out/in so 'docker' works without sudo
docker version && docker compose version
```
Open ports **80** (and **443** if you use HTTPS) in the firewall/security group.

## Step 1 — Get the code onto the server
```bash
git clone <your-repo-url> dentalinnovation
cd dentalinnovation
```
(Or `scp -r` the project folder up from your PC.)

## Step 2 — Create your `.env`
```bash
cp .env.docker.example .env
nano .env
```
Fill in **at minimum**:
- `DOMAIN`, `APP_URL`, `VITE_API_URL` — your real domain. Keep them consistent:
  `APP_URL=https://yourdomain.com/dentinno` and
  `VITE_API_URL=https://yourdomain.com/dentinno/api/v1`.
  (Use `http://` here if you're testing without HTTPS first.)
- `DB_PASS`, `DB_ROOT_PASS` — strong passwords.
- `RAZORPAY_*` — live keys (`rzp_live_…`) when you're ready to take real payments.
- `FAST2SMS_API_KEY` / `SMTP_*` — only if you use SMS/email OTP.

> `VITE_API_URL` is **baked into the frontend at build time**. If you change the
> domain later you must rebuild the `web` image (Step 6).

## Step 3 — Build and start
```bash
docker compose up -d --build
```
What happens automatically:
1. `db` starts and, on its **first** run, imports `dentinno/database.sql` (core schema).
2. `web` waits for the DB, runs `php migrate.php` (all `database_*.sql` migrations,
   idempotent), then starts Apache.

Watch it come up:
```bash
docker compose ps
docker compose logs -f web      # look for "applying migrations" then Apache start
```

## Step 4 — Verify (HTTP)
With your DNS A record pointing at the server (or via the server IP for a quick check):
- `http://yourdomain.com/dentinno/api/v1/products.php` → JSON list of products
- `http://yourdomain.com/` → storefront renders
- `http://yourdomain.com/dentinno` → admin login

## Step 5 — Enable HTTPS (recommended)
Point your domain's **A record** at the server IP first, then bring the stack up
with the Caddy override — it fetches and auto-renews a Let's Encrypt certificate:
```bash
docker compose -f docker-compose.yml -f docker-compose.caddy.yml up -d --build
```
Make sure `APP_URL` / `VITE_API_URL` in `.env` use `https://` (rebuild if you changed them).
Now the site is live at **https://yourdomain.com/**.

> Prefer your own nginx / cloud load balancer for TLS? Skip Caddy, keep the base
> `docker-compose.yml` (web on port 80), and proxy to it from your existing setup.

## Step 6 — Updating later
```bash
git pull
# Backend-only change (PHP/SQL): rebuild web; new migrations apply on start.
docker compose up -d --build web
# Frontend change OR you changed the domain in .env: same command — the React
# bundle is rebuilt with the current VITE_API_URL.
```

---

## First-run hardening
- **Change the admin password** (default `admin@dentinno.com` / `password`):
  log in at `/dentinno` and update it.
- Confirm `APP_DEBUG`, `OTP_DEV_RETURN`, `OTP_SSL_INSECURE` are `false` (the compose
  file sets them) so no stack traces or OTPs leak to clients.
- Keep `.env` out of git (already in `.gitignore`).

## Data & backups
- Database lives in the `db_data` volume; uploaded product/banner images in `uploads`.
  Both survive `docker compose down` and image rebuilds — but **not** `down -v`.
- Back up the database:
  ```bash
  docker compose exec db sh -c 'exec mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' > backup.sql
  ```
- Restore into a running db:
  ```bash
  docker compose exec -T db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < backup.sql
  ```

## Common commands
```bash
docker compose logs -f web              # tail web/Apache + entrypoint logs
docker compose exec web bash            # shell inside the web container
docker compose exec web php /var/www/html/dentinno/migrate.php --status
docker compose down                     # stop (keeps volumes/data)
docker compose down -v                  # stop AND DELETE all data (careful!)
```

## Troubleshooting
- **"Service temporarily unavailable" / DB errors** → check `docker compose logs db`;
  verify `DB_*` in `.env`. If you changed DB creds after the first run, the volume
  still has the old user — `docker compose down -v` to reset (destroys data) or fix
  the user inside the DB.
- **Storefront calls the wrong API host** → `VITE_API_URL` was wrong at build time;
  fix `.env` and `docker compose up -d --build web`.
- **Schema looks empty** → `database.sql` only imports on the **first** db start
  (empty volume). Reset with `docker compose down -v && docker compose up -d --build`
  (deletes data), or import manually:
  `docker compose exec -T db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < dentinno/database.sql`.
- **Uploaded images vanish after rebuild** → ensure you didn't run `down -v`; images
  persist in the `uploads` volume.
