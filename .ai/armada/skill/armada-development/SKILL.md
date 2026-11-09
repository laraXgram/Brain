---
name: armada-development
description: "Configures and operates LaraGram Armada, the Docker environment for LaraGram applications. Activate when running or debugging commands inside Armada containers; editing compose.yaml or compose.production.yaml; using armada:install, armada:add, armada:publish, or armada:production; choosing or switching the Bot API server container (official telegram-bot-api or LaraGram API Server); setting ARMADA_SERVER, ARMADA_UPDATES, ARMADA_PROCESSES, or ARMADA_ROLE; running Surge, MTProto, Luna SSR, Sentinel, Watchdog, queues, or the scheduler under Armada; or deploying a LaraGram application with Docker."
license: MIT
metadata:
  author: laraxgram
---

# Armada Development

Armada runs a LaraGram application in Docker. The application container is `laragram.test` (`app` in production), and the Bot API server container is always `bot-api`. Use `search-docs` with `packages: ['laraxgram/armada']`.

## Safety Rules

- Never print or commit `.env`, bot tokens, `API_HASH`, or the contents of the `armada-bot-api` and `armada-storage` volumes. They hold bot and MTProto sessions.
- Ask before running `armada bot-api logout` (the bot cannot return to `api.telegram.org` for 10 minutes), `armada down -v` (deletes databases and sessions), `webhook:set`, `webhook:delete`, or `client:auth`.
- `armada build` also rebuilds `bot-api`, which can compile for up to 40 minutes. Name the service: `armada build laragram.test`.

## Running Commands

Prefix commands with `./vendor/bin/armada`. Containers must be running (`armada up -d`).

| Task | Command |
| --- | --- |
| Commander | `armada laragram <command>` |
| PHP, Composer, Node tools | `armada php`, `armada composer`, `armada npm`, `armada pnpm`, `armada yarn`, `armada bun` |
| REPL and logs | `armada probe`, `armada watchdog` |
| Database shells | `armada mysql`, `armada mariadb`, `armada psql`, `armada mongodb`, `armada redis`, `armada valkey` |
| Bot API server | `armada bot-api status`, `logs`, `restart`, `logout` |
| Production | `armada prod <any command>` targets `compose.production.yaml` |

Do not run `php laragram dev` or `start:apiserver` inside Armada. Supervisor already runs the server and processes, and `bot-api` is its own container.

## Installing and Changing the Setup

```bash
php laragram armada:install --with=mysql,redis --bot-api=official --updates=webhook --processes=queue,watchdog --php=8.5 -n
php laragram armada:add valkey
php laragram armada:add --bot-api=laragram
```

`armada:add` changes only the settings it receives. PHP versions are 8.3, 8.4, and 8.5.

## Settings in `.env`

Apply changes with `armada up -d`.

| Variable | Values |
| --- | --- |
| `ARMADA_SERVER` | `serve` or `surge` (`--watch` is added when `node_modules/chokidar` exists) |
| `ARMADA_UPDATES` | `polling` adds `--polling` to the server; `webhook` waits for deliveries |
| `ARMADA_PROCESSES` | Comma-separated: `queue`, `schedule`, `watchdog`, `mtproto`, `ssr`, `sentinel-queue`, `sentinel-check`, `vite` |
| `BOT_API_VERBOSITY` | Bot API log level; use `2` or higher when debugging logins |

Rules:

- Under Surge, do not add `mtproto`: the pump runs as a Surge process.
- Use `sentinel-queue` or `queue`, never both.
- MTProto sessions must be authorized first: `armada laragram client:auth`, then `armada restart`.

## Bot API Servers

- `--bot-api` is `cloud`, `official`, or `laragram`. Both containers listen on `8081` in local mode, need `API_ID` and `API_HASH`, and set `API_ENDPOINT=http://bot-api:8081`.
- A bot must be logged out of the cloud once before using a local server.
- With a local server, webhooks are delivered inside the Docker network (`BOT_URL=http://laragram.test`, or `http://app:8000` in production), so no tunnel or HTTPS is needed. With the cloud API, webhooks need a public HTTPS URL.
- `getFile` returns paths under `/var/lib/bot-api`, mounted at the same path in the application and `bot-api` containers. Read them directly.
- Statistics are on `bot-api:8082`, inside the Docker network only, because the page lists bot tokens.
- Images come from `ghcr.io/laraxgram/telegram-bot-api` and `ghcr.io/laraxgram/apiserver`, falling back to a source build.

## Production

```bash
php laragram armada:production
./vendor/bin/armada prod build app
./vendor/bin/armada prod up -d
```

- `--server` is `surge` or `fpm`. Each entry of `--roles` becomes its own container from the same image, selected by `ARMADA_ROLE`: `queue`, `sentinel-queue`, `schedule`, `poller`, `mtproto`, `ssr`, `sentinel-check`.
- `DB_HOST`, `REDIS_HOST`, `API_ENDPOINT`, and similar values are set in the `environment` of `compose.production.yaml`, overriding `.env`.
- Caches are built when a container starts. Set `ARMADA_MIGRATE=1` to migrate on start.
- Armada does not terminate TLS. Cloud webhooks and Mini Apps need a reverse proxy.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| `Armada is not running` | Run `armada up -d`, then `armada ps` |
| Bot silent on polling | `webhook:info`; a webhook blocks polling, so remove it with the user's approval |
| Bot API requests fail with 401 or login errors | The bot is still logged in to the cloud (`armada bot-api logout`), or `API_ID` / `API_HASH` are wrong; check `armada bot-api logs` |
| `bot-api` exits at start | `API_ID` or `API_HASH` is missing from `.env` |
| Webhook set but no updates | `BOT_URL` must be reachable from `bot-api`; check `webhook:info` |
| Process missing | Check `ARMADA_PROCESSES` spelling; supervisor logs skip unknown names |
| New code not picked up in production | Rebuild the image; OPcache does not check file changes |
