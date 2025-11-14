# EchoDB Smoke Test Checklist

## Preparation
1. Install dependencies:
   ```bash
   composer install
   ```
2. Copy environment file:
   ```bash
   cp config/.env.example .env
   ```
3. Update `.env` with your database credentials and create the database:
   ```bash
   mysql -u <user> -p -e "CREATE DATABASE echodb DEFAULT CHARACTER SET utf8mb4;"
   mysql -u <user> -p echodb < sql/init.sql
   ```
4. Launch the built-in server:
   ```bash
   php -S localhost:8080 -t public
   ```

## API Sanity Checks
- Health endpoint:
  ```bash
  curl http://localhost:8080/api/index
  ```
  Expect JSON with `status:"ok"` and version string.

- Event feed (Server-Sent Events):
  ```bash
  curl -N http://localhost:8080/api/stream
  ```
  You should see `event: update` style messages and `:keep-alive` comments every ~15 seconds.

- Mutation emulation:
  ```bash
  curl -X POST \
       -H "Content-Type: application/json" \
       -d '{"table":"orders","row_id":1,"type":"update","changes":{"status":"shipped"},"actor":"demo"}' \
       http://localhost:8080/api/events
  ```
  Response should include the created event with a diff block.

## UI Verification
1. Open `http://localhost:8080` in a browser.
2. Perform an update using the left-side form.
3. Confirm the right-side widgets react instantly:
   - Visualizer animates a pulse traveling from DB to UI.
   - Timeline prepends a row showing the diff (e.g., `status: pending → shipped`).
   - Stats counters increment and the sparkline updates.
   - Optional: toggle sound to hear the matching tone.
4. Toggle dark/light mode to verify theming.

If any step fails, inspect `logs/app.log` for detailed traces.
