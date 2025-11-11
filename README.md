# EchoDB — The Sound of Change

Every change has a sound. EchoDB is a polished PHP 8.3 demo that turns database mutations into living visuals, stats, and audio. It shows how a lightweight stack can deliver Change Data Capture (CDC) vibes with Server-Sent Events, tasteful UX, and clean architecture.

![EchoDB logo](public/assets/logo.svg)

> 🛠️ Built with pure PHP, PDO, Monolog, and Dotenv — no heavy frameworks, no bundlers.

## Highlights
- **Real-time SSE stream** broadcasting database events with heartbeat + reconnection logic.
- **Animated visualizer** tracing the path _DB → Echo → UI_ with color-coded pulses.
- **Diff-aware timeline** rendering field-level changes with readable formatting.
- **Sound cues** for inserts, updates, and deletes via the Web Audio API (toggleable).
- **Live stats + sparkline** summarizing per-type counts and events per minute.
- **Secure & production-ready touches**: rate limiting, CORS controls, Monolog logging, PSR-12 code style, phpstan config.

## Architecture at a Glance
```
                   ┌────────┐     insert/update/delete     ┌──────────┐
Browser UI ◀───────┤ stream │◀─────────────────────────────┤ EventStore│
   ▲   ▲           └────────┘                               └─────┬────┘
   │   │                   ▲                                       │
   │   └──── AJAX / REST ──┘                                       │
   │                                                               │
Visualizer · Timeline · Sound                                 ┌────▼─────┐
   │                                                         │ Database │
   └───────────── Server-Sent Events pulses ─────────────────┤  (MySQL) │
                                                             └──────────┘
```

## Quick Start
1. **Install dependencies**
   ```bash
   composer install
   ```
2. **Configure environment**
   ```bash
   cp config/.env.example .env
   # edit DB credentials & APP_URL
   ```
3. **Provision the database**
   ```bash
   mysql -u <user> -p -e "CREATE DATABASE echodb DEFAULT CHARACTER SET utf8mb4;"
   mysql -u <user> -p echodb < sql/init.sql
   ```
4. **Run locally**
   ```bash
   php -S localhost:8080 -t public
   ```
5. **Open** `http://localhost:8080` and start crafting database ripples.

## API Reference
Base URL: `/api`

| Method | Endpoint         | Description                             |
|--------|------------------|-----------------------------------------|
| GET    | `/api/index`     | Health info + app version.              |
| GET    | `/api/events`    | List recent events (`limit`, `after_id`).|
| POST   | `/api/events`    | Emulate mutation + emit CDC event.      |
| GET    | `/api/stream`    | Server-Sent Events (Last-Event-ID aware).|
| GET    | `/api/stats`     | Counts per type/table + events per min. |

**Sample mutation**
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -d '{"table":"orders","row_id":1,"type":"update","changes":{"status":"shipped"},"actor":"demo"}' \
     http://localhost:8080/api/events
```

SSE stream from the terminal:
```bash
curl -N http://localhost:8080/api/stream
```

## Database Schema
```
users  (id, name, email)
orders (id, user_id, status ENUM, amount DECIMAL, updated_at TIMESTAMP)
events (id BIGINT, type ENUM, table_name, row_id, diff JSON, actor, created_at)
```
Example `events.diff` snapshot:
```json
{
  "status": {"old": "pending", "new": "shipped"},
  "amount": {"old": 24.9, "new": 24.9}
}
```

## Tooling & Quality
- **Coding standard**: `phpcs` with PSR-12 rules (`phpcs.xml`).
- **Static analysis**: `phpstan` level 6 (`phpstan.neon`).
- **Logging**: Monolog to `logs/app.log` (auto-created).
- **Configuration**: Dotenv + config fallback, no secrets committed.
- **Rate limiting**: simple per-IP sliding window persisted in `/tmp`.

## Deployment Notes
- Works on shared hosting / Apache via `public/.htaccess` rewrite.
- For Nginx:
  ```nginx
  server {
      listen 80;
      server_name example.com;
      root /var/www/echodb/public;

      location / {
          try_files $uri /index.php$is_args$args;
      }

      location /api/ {
          try_files $uri /api/index.php$is_args$args;
      }

      location ~ \.php$ {
          include fastcgi_params;
          fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
          fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
      }
  }
  ```
- Ensure the web user can write to `logs/` for Monolog.

## Screenshots & Demo
- `public/assets/` contains the logo. Add your own GIFs/screenshots showcasing the animations.
- Audio cues can be muted via the header toggle.

## Why It’s Cool
- ✅ Zero build steps — pure PHP + vanilla JS/CSS.
- ✅ Real-time CDC-like experience with SSE & Last-Event-ID handling.
- ✅ UI responds with visuals, stats, and sound for every change.
- ✅ Clean architecture with dedicated services (EventStore, StatsService, SseStreamer).
- ✅ Tooling ready for teams (PSR-12, phpstan, Monolog).

## Testing & Smoke Checks
See [`tests/smoke.md`](tests/smoke.md) for curl snippets and manual verification steps.

## License
Released under the MIT License. See [`LICENSE`](LICENSE).
