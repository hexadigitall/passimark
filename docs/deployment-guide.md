# Passimark Deployment Guide (v4.0)

**Audience:** whoever ships Passimark to a server. Covers a single-node PHP host; scale-out
notes at the end. The stack is Laravel 11 + Inertia + React (Vite), no SSR and no external
services required — the default install runs on SQLite and the file session driver.

---

## 1. Requirements

| Component | Version | Notes |
|---|---|---|
| PHP | 8.2+ | extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `zip` (`.psmk` packages) |
| Composer | 2.x | `composer install --no-dev --optimize-autoloader` |
| Node.js | 18+ (20 LTS recommended) | build-time only; not needed to serve traffic |
| Database | SQLite (default) / MySQL 8 / PostgreSQL 13+ | SQLite is fine for a single node |
| Web server | Nginx or Apache | document root **must** be `public/` |

---

## 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

A production `.env` should look like:

```dotenv
APP_NAME=Passimark
APP_ENV=production
APP_DEBUG=false
APP_URL=https://passimark.example.com
APP_KEY=base64:...            # generated above

DB_CONNECTION=sqlite          # or mysql / pgsql
# DB_HOST=127.0.0.1
# DB_DATABASE=passimark
# DB_USERNAME=passimark
# DB_PASSWORD=...

SESSION_DRIVER=database       # file is fine for one node
CACHE_STORE=database
QUEUE_CONNECTION=database
```

> `APP_DEBUG=false` and a real `APP_URL` are mandatory: the public `/verify/{credentialId}` page
> and the certificate QR codes must resolve to the deployed origin.

### Catalog selection (seed-time switch)

`SEED_CATALOG` picks the content load path for a fresh database:

| `SEED_CATALOG` | Content | Notes |
|---|---|---|
| *(unset)* | CISSP textbook bundle | 46 sessions / 1,025 questions, approval-gated |
| `worldwide` | 17 flagship certs | + deterministic original question bank; auto progression |
| `uniform` | 205-cert uniform ladder | generated 14-session ladder per cert |

`SEED_REGIONS`, `SEED_CERTS`, and `SEED_LIMIT` narrow the worldwide/uniform seeders for a
smaller demo footprint.

---

## 3. Install

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build                       # writes public/build + syncs the Vite manifest

php artisan migrate --force
php artisan db:seed --force         # honours SEED_CATALOG
```

Run one catalog explicitly if needed:

```bash
php artisan db:seed --class=WorldwidePassimarkCatalogSeeder --force
php artisan db:seed --class=WorldwideOriginalQuestionBankSeeder --force
php artisan db:seed --class=Uniform205CatalogSeeder --force
php artisan db:seed --class=CISSPBundleSeeder --force
```

`CISSPBundleSeeder` and `WorldwideOriginalQuestionBankSeeder` are idempotent: re-running them
backfills missing pools/keys without duplicating content or disturbing enrollments.

---

## 4. Production bootstrap

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Re-run these after every deploy that changes config/routes/views. To undo during debugging:
`php artisan optimize:clear`.

Writable paths (owned by the PHP-FPM user): `storage/` and `bootstrap/cache/`. For SQLite the
database file (and its `-wal`/`-shm` siblings) must be writable too.

**Migrations run with `--force`** — never run `migrate:fresh` in production; it drops all data.

---

## 5. Web server

The Laravel front controller is `public/index.php`; expose only the `public/` directory.

**Nginx**

```nginx
server {
    listen 443 ssl http2;
    server_name passimark.example.com;
    root /var/www/passimark/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

**Apache** — point the vhost `DocumentRoot` at `public/` and allow `.htaccess` overrides (the
shipped `public/.htaccess` handles rewrites).

Terminate TLS at the web server (or a load balancer). The app trusts the proxy headers Laravel
handles by default for HTTPS detection.

---

## 6. Verify the deploy

1. `https://…/login` renders and the seeded accounts work
   (`admin@passimark.com` / `student@passimark.com`, password `password` — **change before going
   live, or seed your own and disable the demo users**).
2. `GET https://…/verify/PMK-DEMO-0000-00000000` returns the **invalid credential** page (not a
   500) — public route, no auth.
3. Pass a final on a test learner and open the emailed/linked `/certificate/{id}`: the QR encodes
   the `APP_URL`-based verify URL, and scanning it resolves on another device.
4. `php artisan about` shows `production` and the intended DB connection.
5. Optional pre-flight: `php vendor/bin/phpunit` (green on the release commit).

---

## 7. Backups & operations

- **SQLite:** back up `database/database.sqlite` (use `sqlite3 … ".backup"` or stop writes) plus
  `storage/app` for any uploaded media.
- **MySQL/Postgres:** `mysqldump` / `pg_dump` on a schedule; the DB holds catalog, progress,
  approvals, and credential records.
- Certificates are derived data: `credential_id`/`credential_hash` live in `passimark_progress`,
  so a DB restore restores verifiability. Back up the app key — losing `APP_KEY` invalidates
  encrypted/signed payloads.
- Queue workers are only needed if you enable queued work; otherwise the DB queue is inert.

---

## 8. Upgrades

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan db:seed --class=CISSPBundleSeeder --force   # only if you want new content
```

Roll the app and web server. Because migrations are additive and idempotent seeders backfill
gaps, an upgrade is safe to run on a live database.

---

## 9. Scaling notes

The default install is intentionally a single node. To scale:

- Move `SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION` off `file`/`database` to Redis so all
  nodes share state.
- Move SQLite to MySQL/PostgreSQL and run the app behind a load balancer; uploads/builds no
  longer need node affinity.
- Inertia is server-driven with no SSR, so no extra Node runtime is required in production; the
  Vite assets are static files served from `public/build/`.
- The PWA manifest is already slate-themed (`#0F172A`); serve the app over HTTPS for install
  prompts to appear.

---

## 10. CI

`.github/workflows/ci.yml` runs the PHPUnit suite against `PassimarkSeeder` + an in-memory SQLite
database (no external services). Keep the suite green on the release commit before deploying.
