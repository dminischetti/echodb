# EchoDB - The Sound of Change

Every change has a sound. EchoDB is a real-time demo showing how lightweight PHP can deliver CDC-style event streaming, synchronized visuals, and Web Audio cues - all without frameworks or bundlers.

![EchoDB logo](public/assets/logo.svg)

> ⚡ Powered by pure PHP 8.3, PDO, Monolog, Dotenv, and Server-Sent Events.

---

## Overview
EchoDB turns **database mutations** into **live events** the browser can visualize and sonify.
When a row changes, the backend logs it, streams it, and the UI responds instantly with:

- Animated pulses
- Diff-aware timeline entries
- Web Audio tones
- Real-time counters & sparkline stats

It’s a compact demonstration of event-driven thinking with a fully transparent architecture.

---

## Highlights
- **Real-time SSE stream** with heartbeat, Last-Event-ID resume, and safe reconnection.
- **Animated visualizer** mapping insert/update/delete into color-coded pulses.
- **Structured diffs** for each mutation (`old → new`) rendered in a readable timeline.
- **Sound cues** via Web Audio API (toggleable).
- **Live analytics** (counts + events/min) powered by a small StatsService.
- **Secure defaults**: basic rate limiting, CORS controls, Monolog logging, PSR-12 code style.
- **Framework-free**: clean PHP namespaces, router, controllers, and services.

---

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

The flow is simple:

1. Client makes a mutation → POST `/api/events`
2. PHP validates, applies the mutation, stores a diff, logs an event
3. SSE streamer emits new events to all connected clients
4. Browser updates visuals, timeline, counters, and audio

---

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
| GET    | `/index`     | Health info + app version.              |
| GET    | `/events`    | List recent events (`limit`, `after_id`).|
| POST   | `/events`    | Emulate mutation + emit CDC event.      |
| GET    | `/stream`    | Server-Sent Events (Last-Event-ID aware).|
| GET    | `/stats`     | Counts per type/table + events per min. |

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
Example diff:
```json
{
  "status": {"old": "pending", "new": "shipped"},
  "amount": {"old": 24.9, "new": 24.9}
}
```

## Tooling & Quality
- **Code style:** PSR-12 via phpcs
- **Static analysis:** PHPStan level 6
- **Logging:** Monolog → logs/app.log
- **Config:** Dotenv with fallback defaults
- **Rate limiting:** Simple per-IP sliding window in /tmp (swappable for Redis)

## Deployment Notes
- Apache / shared hosting
- Works out-of-the-box with public/.htaccess.
- Nginx example
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
- Ensure the PHP user can write to logs/.

## Screenshots & Demo Assets
- Logo + UX assets inside public/assets/.
- Add your own GIFs or screen recordings to showcase:
   - visualizer pulses
   - diff timeline
   - sparkline stats
   - audio toggle

## Why It’s Cool
- Zero build step - pure PHP + native browser APIs
- CDC-inspired architecture without Kafka, Debezium, or heavy infra
- SSE done right (heartbeats, resume, reconnection)
- Readable diffs mapped straight from DB mutations
- Web Audio integration adds a playful, memorable twist
- Clean service layout: Router · EventStore · StatsService · SseStreamer

## Testing & Smoke Checks
See [`tests/smoke.md`](tests/smoke.md) for curl-based checks covering:
- Mutation flow
- SSE streaming
- Stats
- Visualizer sync
- Error handling

License
MIT - see LICENSE.
