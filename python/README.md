# Discussion Hub Gateway

This service provides the ML and integration endpoints for the Discussion Hub
project. It's called by the Laravel app's `App\Services\MlGatewayClient` and
reads live data from the same MySQL database Laravel uses.

## Project structure
```
python/
  app.py       Flask app instance + route handlers (the only file with @app.route)
  config.py    constants + env-driven settings + request auth check
  db.py        all database access (single source of truth for SQL)
  catalog.py   TF-IDF catalog build + classification/ranking
  spam.py      local ML content moderation (TF-IDF + Naive Bayes, no external API)
  pdf.py       topic -> PDF rendering (xhtml2pdf)
  share.py     topic -> social share URL/text construction
  test_app.py  pytest suite
  requirements.txt
```
Every helper is imported into `app.py` by name (`from db import _fetch_user_recent_text`,
not `import db` + `db._fetch_user_recent_text(...)`), so route handlers call
them unqualified and tests can keep monkeypatching functions directly on the
`app` module (e.g. `monkeypatch.setattr(appModule, "_fetch_user_recent_text", ...)`).

## Endpoints
- `POST /classify` — predict a category for a piece of text (with a local ML spam/relevance short-circuit).
- `POST /recommend-topics` — rank specific candidate topics for a user by relevance to their interests/recent activity.
- `POST /trending-groups` — rank every group by member count + weighted recent activity, joined or not.
- `POST /export-topic-pdf` — render a topic + its posts/replies as a downloadable PDF (no PHP/Dompdf involvement on this side).
- `POST /topic-share-links` — build a canonical share URL, text snippet, and WhatsApp/Twitter/Facebook/Email links for a topic.

## Environment variables
Loaded from the project root `.env` (the same file Laravel uses, one level up
from this folder) via `python-dotenv` — `load_dotenv()` walks up from this
file's location to find it, so it works whether you run `python app.py` from
inside `python/` or `python python/app.py` from the project root.

Gateway auth:
- `GATEWAY_EXPECTED_TOKEN` (or `GATEWAY_TOKEN`) — shared secret; must match Laravel's `ML_GATEWAY_TOKEN`.
- `GATEWAY_TOKEN_PREFIX` — defaults to `Bearer `.

Classification/recommendation behavior:
- `DEFAULT_CATEGORY`
- `RECOMMENDATION_LIMIT`
- `PORT` — defaults to `5000`.

Database (reused from Laravel's `.env`, same names):
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

If the database is unreachable, every endpoint degrades gracefully (falls back
to the static seed catalog / empty results) instead of erroring.

## Notes
- Classification/ranking uses TF-IDF + cosine similarity against a catalog
  that's a static seed list enriched with real `Topic` data from the
  database, rebuilt on a ~60s TTL — this is how it "retrains" without a
  separate training pipeline.
- `/recommend-topics` scores each candidate topic's title against the
  caller's stated interests, recent messages, and their real `Post`/`Reply`
  history (fetched here via `db.py`). If none of those yield any usable
  text, every topic gets a flat `0.0` relevance score — the caller (Laravel's
  `RecommendationScorer`) is what blends that with real engagement signals
  so a cold-start user still sees something reasonable, not this endpoint.
- Content moderation (`spam.py`) uses two local TF-IDF + Naive Bayes
  classifiers trained at import time on a small bundled dataset — one spots
  spam, one spots generic off-topic/casual text. No external API, no key,
  nothing to configure. Replies also get a cosine-similarity check against
  the thread they're replying to, so a reply can be flagged as irrelevant
  even if it reads as generically "educational" on its own. Laravel doesn't
  block on a spam verdict for topics/messages — it auto-flags the post for
  the admin moderation queue instead (see `admin.flagged-content.*` routes);
  replies are blocked outright on either spam or irrelevance, with a
  specific reason shown to the sender.

## Running locally
```
cd python
pip install -r requirements.txt
python app.py
```
This runs Flask's built-in development server, which is fine for local use
alongside `php artisan serve`, but it logs a warning that it isn't meant for
production traffic (single-threaded, no process management).

## Running tests
```
cd python
pytest
```

## Running in production
Use a real WSGI server instead of `python app.py`, run from inside `python/`:

**Linux/macOS** (gunicorn):
```
pip install gunicorn
gunicorn -w 4 -b 0.0.0.0:5000 app:app
```

**Windows** (gunicorn doesn't support Windows; use waitress):
```
pip install waitress
waitress-serve --host=0.0.0.0 --port=5000 app:app
```

Either way, keep `GATEWAY_EXPECTED_TOKEN`/`ML_GATEWAY_TOKEN` out of source
control, and put the service behind the same network boundary/reverse proxy
as the rest of the deployment rather than exposing port 5000 publicly.
