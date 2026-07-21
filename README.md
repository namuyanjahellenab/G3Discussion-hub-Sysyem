# Discussion Hub

A course discussion platform for students, lecturers, and administrators — topic
forums, group chat, quizzes, participation/marks tracking, and moderation
(warnings, blacklisting, flagged-content review), with a Laravel web app, a
Python ML gateway for content intelligence, and an optional JavaFX desktop
client.

## Contents

- [Architecture](#architecture)
- [Features](#features)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Demo accounts](#demo-accounts)
- [Project structure](#project-structure)
- [Testing](#testing)
- [Deployment](#deployment)

## Architecture

This repo is three components that run as separate processes:

| Component | Path | What it does |
|---|---|---|
| **Web app** | repo root (Laravel) | The actual product — auth, forums, groups, quizzes, marks, moderation, admin panel. |
| **ML gateway** | [`python/`](python/) | Flask service for topic classification, spam/relevance detection, recommendations, PDF export, and topic share links. Reads the same MySQL database. |
| **Desktop client** | [`src/`](src/) (Maven) | JavaFX client that authenticates against the web app via Sanctum tokens and gets realtime updates over Pusher. |

The web app is the only hard requirement to run the site. The ML gateway is a
**required** dependency for full functionality — without it running, pages
still load but classification, spam detection, recommendations, PDF export,
and share links silently degrade to simpler fallbacks (see `GET /health`).
The desktop client is optional and talks to the web app's API only.

## Features

- **Topic discussions** — per-group forums, threaded replies, accepted
  answers, attachments, flagging, and auto spam/relevance moderation via the
  ML gateway.
- **Groups** — join/leave, group-scoped topics, group-wide and threaded
  group chat with read tracking.
- **Quizzes** — lecturers schedule quizzes (MCQ + open text) per group,
  students take them with auto-submit on timeout, results and per-question
  review.
- **Marks & participation** — per-group participation scoring and quiz
  averages, with dedicated lecturer and student views.
- **Moderation** — warnings and time-bound blacklisting (manual or
  auto-triggered after repeated warnings), a flagged-content review queue,
  and student-facing notifications explaining any restriction.
- **Notifications** — a shared in-app notification feed (replies, quiz
  announcements, warnings, moderation actions) surfaced via a bell dropdown
  and the dashboard.
- **Announcements** — lecturer/admin broadcasts to a group or campus-wide,
  with optional per-student exclusions.
- **Recommendations** — personalized topic/group suggestions from the ML
  gateway, falling back to trending content for new users.
- **Admin panel** — dashboard/statistics, lecturer & student-ID management,
  blacklist/warning issuance, flagged-content moderation.
- **Theming** — per-user accent color (LUNA theme system) applied site-wide.

## Tech stack

- **Backend:** PHP 8.2+, Laravel 12, MySQL, Laravel Sanctum (API tokens),
  Laravel Reverb / Pusher (realtime)
- **Frontend:** Blade, Tailwind CSS, Alpine.js, Vite
- **ML gateway:** Python 3, Flask, scikit-learn (TF‑IDF + Naive Bayes),
  xhtml2pdf
- **Desktop client:** Java, JavaFX, Maven
- **Testing:** Pest (PHP), pytest (Python)

## Getting started

This section covers a local dev setup. For a production checklist (queue
workers, caching, process supervisors, health checks) see
[`DEPLOYMENT.md`](DEPLOYMENT.md).

### Prerequisites

- PHP 8.2+ and Composer
- Node.js and npm
- MySQL (this app requires MySQL, not SQLite — some table names are SQLite
  reserved words)
- Python 3.9+ (for the ML gateway)

### 1. Web app

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your `DB_*` credentials (create an empty `discussionhub`
database first). Every variable the app reads is documented inline in
`.env.example`.

```bash
php artisan migrate
php artisan db:seed   # optional — loads demo data, see below
npm run build          # or `npm run dev` while developing
php artisan serve
```

Or use the all-in-one dev script (serves the app, a queue listener, log
tailing, and Vite together):

```bash
composer run dev
```

### 2. ML gateway (required for full functionality)

```bash
cd python
pip install -r requirements.txt
python app.py
```

It reads the same root `.env` file, so `ML_GATEWAY_TOKEN` (Laravel → gateway)
and `GATEWAY_EXPECTED_TOKEN` (what the gateway checks) must be set to the
same value. See [`python/README.md`](python/README.md) for endpoint details.

### 3. Desktop client (optional)

```bash
./mvnw clean javafx:run
```

Point it at your running web app's URL; it authenticates via the
`/api` Sanctum token endpoints.

### Verify it's working

- `GET /health` — `200 {"status":"ok"}` if the database and ML gateway are
  both reachable, `503 {"status":"degraded"}` with per-check detail if not.
- `GET /up` — Laravel's own liveness check (app booted, nothing more).

## Demo accounts

`php artisan db:seed` seeds groups, topics/replies, a flagged reply, and an
announcement so the app is demoable without manual data entry. All demo
accounts use the password `password`:

| Role | Email |
|---|---|
| Administrator | `admin@demo.test` |
| Lecturer | `lecturer@demo.test` |
| Student | `student1@demo.test`, `student2@demo.test`, `student3@demo.test` |

Skip `db:seed` for a real deployment — it's for demos/QA, not live data.

## Project structure

```
app/                  Laravel application code (controllers, models, services, middleware)
resources/views/       Blade views — layouts/ (shared shell), by-feature folders (quizzes/, groups/, admin/, ...)
routes/web.php          All web routes
database/migrations/    Schema (PascalCase columns/tables, e.g. UserID, WarningID)
python/                 Flask ML gateway (see python/README.md)
src/                     JavaFX desktop client sources (Maven)
public/                 Compiled assets + entry point
```

## Testing

```bash
php artisan test        # PHP (Pest)
cd python && pytest     # Python gateway
```

## Deployment

See [`DEPLOYMENT.md`](DEPLOYMENT.md) for the full production checklist —
running the ML gateway and queue worker as supervised processes, config/route
caching, and recommended PHP extensions (OPcache, GD).
