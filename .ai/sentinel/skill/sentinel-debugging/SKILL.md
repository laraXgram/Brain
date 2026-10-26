---
name: sentinel-debugging
description: "Debugs and monitors a LaraGram bot with laraxgram/sentinel. Activate when the user reports that the bot misbehaved, failed, or was slow in production; when asked why an update was not handled, which Bot API calls an update made, why Telegram returned an error or a flood wait, who blocked the bot, whether the webhook is healthy, how the local Bot API server is doing, or which commands have no handler; when reading the Sentinel dashboard, replaying or composing updates in the playground; and when changing config/sentinel.php - watchers, sampling, retention, filters, tags, hidden fields, alerts, dashboard logins, sentinel:install / check / prune / clear / pause / resume."
license: MIT
metadata:
  author: laraxgram
---

# Debugging With Sentinel

Sentinel is the bot's recorder: every update it handled, with the Bot API calls, queries, logs, cache operations, conversation steps, jobs and exceptions that happened while handling it — plus time-bucketed metrics for traffic, users, commands, latency, errors and flood waits. Use `search-docs` with `packages: ['laraxgram/sentinel']` for exact options.

## Safety First

- Never set, delete, or drop pending updates of a webhook from the dashboard, and never run the playground or a replay in **live** mode, unless the user explicitly asks: these reach Telegram and real users.
- Never run `sentinel:clear` (or `--metrics`) to "start fresh" while investigating; it destroys the evidence you are looking at.
- Session data, tokens and parameters listed under `hidden` are redacted before storage. Do not widen what is recorded to debug something; narrow it with tags instead.
- Recording may be paused (`sentinel:pause`) and resumed (`sentinel:resume`), which is for maintenance, not for hiding a problem.

## How to Investigate

1. Start from the entry, not the code: find the update in the dashboard, or ask for the `chat:` / `user:` tag. Every entry an update caused carries the same tags, so `user:123456` shows one user's whole story.
2. Read the automatic tags before anything else: `unhandled` (no listen matched), `failed` (handling threw), `slow` (over the watcher's threshold), `command:/start`, `update:<type>`, `connection:<name>`.
3. For a failure, read the exception entry and the Bot API call that preceded it; for a Telegram error, read the call's `error_code` and `description`, and check **flood waits** per method before blaming the code.
4. For "the bot did nothing", check `unhandled` first, then the listens page (a listen with zero hits is usually ordered after a broader one), then the conversation funnel if a flow is involved.
5. To reproduce, replay the recorded update in **dry** mode, or compose one in the playground; the dashboard then shows exactly which calls your current code makes.
6. When the dashboard is missing webhook history, server charts or alerts, `sentinel:check` is not running — say so instead of hunting for a bug.
7. With a local Bot API server, open the **API Server** page (its `--http-stat-port` statistics: requests, updates and errors per second, pending updates per bot, CPU, memory) before blaming Telegram for slow or missing updates. It reads `bot.api_server.stat` or `SENTINEL_API_SERVER_STATS_URL`; never paste the raw statistics page anywhere, it contains bot tokens.

## What to Change, and Where

- Everything is configured in `config/sentinel.php`: `watchers` (each one may be disabled or tuned: `sample_rate`, `slow`, `ignore_*`), `retention`, `storage.database.connection`, `playground`, `webhook`, `alerts`, `auth`.
- On a busy bot keep the metrics complete and sample the entries (`SENTINEL_UPDATE_SAMPLE_RATE=0.1`); exceptions, failed API calls and failed jobs are always kept.
- Point `SENTINEL_DB_CONNECTION` at a second database connection in production so monitoring writes never compete with the bot's own queries.
- Keep the entries from growing: `Schedule::command('sentinel:prune')->daily()`.
- To follow one user in production, mark their tag as *monitored* on the Settings page rather than dropping the filter for everyone.
- Register a filter (`Sentinel::filter`), tags (`Sentinel::tag`) and redaction (`Sentinel::hide`) in `App\Providers\SentinelServiceProvider`, and record your own numbers with `Sentinel::metric($type, $key, $value, ['count', 'sum'])`.
- Dashboard access is its own login (`SENTINEL_ADMINS` for Telegram codes, `SENTINEL_USERNAME` / `SENTINEL_PASSWORD`, or the `viewSentinel` gate). Never commit credentials, and never widen access to debug something.

## Telegram Alerts

`SENTINEL_ALERTS=true` with `SENTINEL_ALERTS_CHAT_IDS` messages you when an exception is thrown, the webhook fails, updates pile up, a job fails or Telegram flood-limits the bot. Suggest it when the user says they found out about an outage from their users.
