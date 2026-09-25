---
name: deploying-laragram
description: "Deploys and operates LaraGram applications in production. Use when the user wants to deploy a LaraGram bot, web app, or Telegram Mini App; configure Nginx or PHP-FPM for LaraGram; switch a bot from local development to a production webhook; run LaraGram Surge; set up queue workers, the scheduler, or MTProto client sessions under a process supervisor; optimize caches (config, events, routes, listens, views, templates); set up multiple bot connections or a self-hosted Bot API server; or debug a bot that stopped responding after deployment."
license: MIT
metadata:
  author: laraxgram
---

# Deploying LaraGram

A production LaraGram application has up to four long-lived parts. Identify which the project uses before changing anything:

| Part | Needed when | Runs as |
| --- | --- | --- |
| HTTP entry (`public/index.php` or Surge) | Always (webhook updates, web routes, Mini Apps) | Nginx + PHP-FPM, or `php laragram surge:start` |
| Queue workers | The app dispatches jobs or queued listeners | `php laragram queue:work` under a supervisor |
| Scheduler | `listens/console.php` or providers schedule tasks | `php laragram schedule:run` every minute (cron) or `schedule:work` |
| MTProto sessions | `laraxgram/mtproto` is installed and sessions are authorized | Inside Surge automatically, or `php laragram client:start` under a supervisor |

Use `search-docs` for the exact options (`deployment`, `surge`, `queues`, `scheduling`, `mtproto-configuration`).

## Safety Rules

- Never commit or print `.env`, bot tokens, `API_HASH`, webhook secret tokens, or MTProto session files (`storage/` session directory).
- Ask before running anything that changes the live bot: `webhook:set`, `webhook:delete`, `webhook:drop`, `client:auth`, or restarting production services.
- Production must run with `APP_ENV=production` and `APP_DEBUG=false`.

## Workflow

### 1. Server Requirements

PHP 8.5 with the ctype, cURL, fileinfo, filter, hash, mbstring, OpenSSL, PCRE, PDO, tokenizer, and intl extensions. Surge additionally needs the Swoole or OpenSwoole extension. The web server user must be able to write `storage/` and `bootstrap/cache/`.

### 2. Web Server

Point the document root at `public/` and send every request to `public/index.php`. The entry point forwards Telegram updates to the bot kernel and everything else to the web kernel, so a single Nginx server block serves both:

```nginx
location / {
    try_files $uri /index.php$is_args$args;
}

location ~ ^/index\.php(/|$) {
    fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

With Surge, proxy requests to the Surge port (9000 by default) instead of PHP-FPM, serve static files from Nginx, set `SURGE_HTTPS=true` when TLS terminates at the proxy, and run `php laragram surge:reload` after each deployment.

### 3. Deploy Steps

```bash
composer install --no-dev --optimize-autoloader
php laragram migrate --force
php laragram optimize          # config, events, routes, listens, views, and templates caches
npm ci && npm run build        # only when the app has a web frontend or Luna
php laragram queue:restart     # workers pick up the new code
php laragram surge:reload      # only when using Surge (restart the Surge server instead when the MTProto pump or other Surge processes changed)
```

Remember that `config:cache` stops `.env` from being read at runtime: only call `env()` inside `config/` files.

### 4. Webhook

- Set the production `url` and a unique `secret_token` for each connection in `config/bot.php` (read from `.env`).
- Run `php laragram webhook:set` (with the user's approval) and confirm with `php laragram webhook:info`.
- Telegram requires HTTPS on ports 443, 80, 88, or 8443.
- For several bots in one application, set `bot.default` to `auto`, give each connection its own `secret_token` (or its own webhook `url`), and run `webhook:set` for every connection.
- A self-hosted Bot API server is started with `php laragram start:apiserver` (requires `api_id` and `api_hash`), and `bot.api_server.endpoint` must point at it.

### 5. Shared State

Webhook bots handle each update in a separate PHP process, so cache-backed state must live in a shared store:

- Set `CACHE_STORE` (or `conversation.store`) to `redis` or `database` for conversations and steps.
- Set `ANTI_FLOOD_STORE=redis` when anti-flood is enabled.
- Use `redis` or `database` queues rather than `sync` in production.

### 6. Process Supervisor

Keep workers running with Supervisor or systemd, and restart them on deploy:

```ini
[program:laragram-worker]
command=php /srv/app/laragram queue:work --sleep=3 --tries=3 --max-time=3600
autorestart=true
stopwaitsecs=3600
numprocs=2
```

Add one program for `php laragram surge:start` when using Surge, and one for `php laragram client:start --all` when MTProto sessions run outside Surge. Schedule `php laragram schedule:run` every minute with cron.

### 7. Verify

- `php laragram webhook:info` shows no recent delivery errors.
- Send the bot `/start` and watch `storage/logs` (or `php laragram watchdog`).
- `php laragram surge:status` when using Surge; `php laragram queue:failed` for failed jobs.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Bot does not respond | `webhook:info` last error (delivery problems only); HTTPS certificate; handler exceptions in `storage/logs`, because updates are processed after Telegram's request is answered (PHP-FPM backgrounds each update with `php`, so the CLI `php` binary must work for the web server user) |
| Server overloaded during bursts | Every update starts a background process (PHP-FPM) or uses a task worker (Surge): queue slow work, and prefer Surge for high traffic |
| Conversations or steps forget state | The cache store is `array` or `file` across servers; use `redis` |
| `429 Too Many Requests` | Enable anti-flood with a shared store; send bulk messages with the `Broadcast` facade, which paces them with the `broadcast` scope |
| Broadcast stuck or recipients messaged twice | A queue worker must run; the progress cache store must be shared; each chunk job must finish before the queue connection's `retry_after` (lower `chunk()`) |
| New listens not picked up | Run `php laragram listen:clear` / `optimize`, and `surge:reload` under Surge (restart Surge when the listens belong to MTProto sessions) |
| Template changes not visible | `php laragram template:clear` (or `optimize:clear`) after deploying template edits without `optimize` |
| MTProto session stopped | Session not authorized on the server (`client:auth`), or another process uses the same session |
