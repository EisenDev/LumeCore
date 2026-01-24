---
description: Start development environment with all required services
---

# LUME Development Startup

// turbo-all

Run these commands in separate terminals when starting development:

## Terminal 1: Start Laravel Sail
```bash
./vendor/bin/sail up -d
```

## Terminal 2: Start Vite Dev Server (Frontend)
```bash
./vendor/bin/sail npm run dev
```

## Terminal 3: Start Queue Worker (Jobs)
```bash
./vendor/bin/sail artisan queue:work --tries=3
```

## Terminal 4: Start Reverb WebSocket Server (Real-time)
```bash
./vendor/bin/sail artisan reverb:start
```

---

## Quick One-Liner (Run all in background):
```bash
./vendor/bin/sail up -d && ./vendor/bin/sail npm run dev &
```
Then open two more terminals for queue and reverb.

---

## Why Queue Worker Needs Manual Start in Dev:

In **production**, queue workers run continuously via **Supervisor** or **systemd** - they auto-restart on crash.

In **development**, you run them manually because:
1. You want to see the output/errors directly
2. Easier to restart when you change job code
3. No need for process managers

---

## Alternative: Use Sail's Built-in Queue Worker

If you add this to your `compose.yaml`, the queue worker starts automatically with Sail:

```yaml
laravel.queue:
    build:
        context: ./docker/8.5
        dockerfile: Dockerfile
    command: 'php /var/www/html/artisan queue:work --tries=3'
    volumes:
        - '.:/var/www/html'
    depends_on:
        - pgsql
        - redis
```

This is already in your compose.yaml! Just run `./vendor/bin/sail up -d` and it should work.

---

## Stopping Everything:
```bash
./vendor/bin/sail down
```
