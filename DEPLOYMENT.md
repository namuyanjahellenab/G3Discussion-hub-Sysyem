# Deployment Guide — Discussion Hub

This app is **two separate processes**: the Laravel web app, and a Python/Flask
ML gateway (`python/app.py`). The gateway is a hard dependency for topic
classification, spam detection, recommendations, PDF export, and topic share
links — `php artisan serve` (or your production web server) does **not**
start it. Forgetting to run it is the single most common way this app looks
broken in a fresh deployment: pages still load, but those specific features
silently degrade (see `/health` below).

## 1. Install dependencies

```bash
composer install --no-dev --optimize-autoloader   # PHP
npm install && npm run build                       # JS/CSS — see note below
pip install -r python/requirements.txt              # Python gateway
```

**Asset build note:** `npm run build` (Vite) must be what's actually served.
If a `public/hot` file exists on the server, Laravel's `@vite()` directive
will try to load assets from a dev server instead of `public/build` — delete
it if present (`rm -f public/hot`) and confirm `npm run dev` was never run
against this deployment.

## 2. Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Then set, at minimum:
- `DB_*` — this app requires **MySQL**, not SQLite (several table names are
  SQLite reserved words, which breaks migrations that alter a column).
- `ML_GATEWAY_URL` — where the Python gateway is reachable (e.g.
  `http://127.0.0.1:5000` if colocated, or an internal service URL).
- `ML_GATEWAY_TOKEN` and `GATEWAY_EXPECTED_TOKEN` — **must be the same
  secret**. `ML_GATEWAY_TOKEN` is what Laravel sends; `GATEWAY_EXPECTED_TOKEN`
  is what the Python gateway checks incoming requests against. The gateway
  reads this same `.env` file if launched from the repo root.
- `APP_ENV=production`, `APP_DEBUG=false` — with debug mode on, errors
  (including database credentials in stack traces) render directly to
  visitors instead of the custom error pages in `resources/views/errors/`.
- `MAIL_*` — for password resets and other transactional mail.
- `QUEUE_CONNECTION=database` (the default) — required for step 5 below.

Every variable the app actually reads is documented with inline comments in
`.env.example`.

## 3. Database

```bash
php artisan migrate --force
php artisan db:seed --force   # optional — see "Demo data" below
```

`migrate --force` has been verified to run cleanly against a completely
empty database with no manual steps.

### Demo data

`php artisan db:seed --force` gets a fresh install to a fully demoable state
with zero manual data entry: 2 groups with members, a lecturer, an admin, 3
students, a couple of topics with replies (one with an accepted answer and a
threaded reply), one flagged reply (so the moderation queue isn't empty),
one restricted-audience topic, and an announcement. All demo accounts use
the password `password`:

| Role | Email |
|---|---|
| Administrator | `admin@demo.test` |
| Lecturer | `lecturer@demo.test` |
| Student | `student1@demo.test`, `student2@demo.test`, `student3@demo.test` |

Skip `db:seed` in a real production deployment — it's meant for demos/QA,
not live data.

## 4. Start the ML gateway (required, separate process)

```bash
cd python
python app.py
```

Run this under a process supervisor (systemd, supervisord, pm2, a Docker
sidecar container — whatever your infra already uses for long-running
processes), not as a one-off foreground command. If it goes down, the app
keeps working but Export/Share/spam-check/classification/recommendations
degrade (Share falls back to a plain PHP-built version; PDF export queues a
job that will fail; classification/recommendations just don't enrich
content). Check `/health` to see this at a glance instead of it being
mysterious.

## 5. Start a queue worker (required)

Topic classification and PDF export are dispatched as queued jobs, not run
synchronously in the request — nothing processes them without a worker
running:

```bash
php artisan queue:work --tries=3
```

Also run this under a process supervisor. Without it, "Export to PDF" will
create a `topic_exports` row stuck at `pending` forever and the user never
gets their download-ready notification.

## 6. Cache framework config/routes/views

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Nothing in this app reads config or route definitions at runtime in a way
that would break under caching — these are safe. Re-run after every
deployment (a new release with stale cached routes/config is a classic
self-inflicted outage); `php artisan optimize:clear` reverses all three if
you need to debug something in place.

## 7. Verify

- `GET /health` — reports `{"status": "ok"}` (200) or `{"status": "degraded"}`
  (503) with per-check detail (database, ML gateway). Point your uptime
  monitor / load balancer health check here instead of `/up`, which only
  confirms the Laravel process itself booted.
- `GET /up` — Laravel's own default liveness check.

## Production PHP configuration (recommended, not app-specific)

Two PHP extensions are commonly disabled by default in a fresh PHP install
but meaningfully affect this app:
- **OPcache** — without it, every request recompiles the entire framework
  from source. Enable `opcache.enable=1` in `php.ini`.
- **GD** — required for attachment image resizing (`AttachmentUploader`);
  without it, uploaded images are stored at full original resolution instead
  of being downscaled for the thumbnail-sized inline display they're
  actually used for.
