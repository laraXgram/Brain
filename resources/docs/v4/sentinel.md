# LaraGram Sentinel

- [Introduction](#introduction)
- [Installation](#installation)
    - [Configuration](#configuration)
    - [Data Retention](#data-retention)
    - [Keeping Sentinel Running](#keeping-sentinel-running)
- [Dashboard Authorization](#dashboard-authorization)
    - [Telegram Login](#telegram-login)
    - [Password Login](#password-login)
    - [Letting Your Own Users In](#letting-your-own-users-in)
- [The Dashboard](#the-dashboard)
    - [Updates](#updates)
    - [Chat Preview and Replay](#chat-preview-and-replay)
    - [The Playground](#the-playground)
    - [Webhooks](#webhooks)
    - [Commands Check](#commands-check)
    - [Users and Chats](#users-and-chats)
    - [Listeners and Conversations](#listeners-and-conversations)
    - [API Calls](#api-calls)
    - [Servers and Network](#servers-and-network)
- [Watchers](#watchers)
- [Filtering and Tagging](#filtering-and-tagging)
    - [Filtering](#filtering)
    - [Tagging](#tagging)
    - [Hiding Sensitive Data](#hiding-sensitive-data)
- [Custom Metrics](#custom-metrics)
- [Telegram Alerts](#telegram-alerts)
- [Sampling and Performance](#sampling-and-performance)

<a name="introduction"></a>
## Introduction

[LaraGram Sentinel](https://github.com/laraxgram/sentinel) watches over your bot. It is two tools in one dashboard:

<div class="content-list" markdown="1">

- A **debugger** that records every update your bot receives, together with everything that happened while it was handled: the Bot API calls it made, database queries, logs, cache operations, conversation steps, queued jobs and exceptions.
- A **monitor** that keeps cheap, time-bucketed metrics: traffic by update type, active and new users, the busiest commands, the slowest handlers, API latency, error rates and flood waits.

</div>

On top of that, Sentinel knows Telegram: it inspects your webhooks, compares the commands registered with BotFather to your listens, replays an update as a chat, and lets you run a made-up update through your real bot code without anybody receiving a message.

<a name="installation"></a>
## Installation

Sentinel is a package for a LaraGram application. Install it with Composer, run its installer, and migrate:

```shell
composer require laraxgram/sentinel

php laragram sentinel:install

php laragram migrate
```

The `sentinel:install` command publishes `config/sentinel.php`, Sentinel's migration and an `App\Providers\SentinelServiceProvider`, and registers that provider for you. The dashboard then lives at **`/sentinel`**.

> [!NOTE]
> Sentinel stores its entries in a database supported by LaraGram (MySQL, MariaDB, PostgreSQL or SQLite) and records Bot API calls through the `LaraGram\Request\Events\ApiCallSending` and `ApiCallCompleted` [events](/v4/events).

<a name="configuration"></a>
### Configuration

Everything is configured in `config/sentinel.php`, and the options you reach for most have environment variables:

```ini
SENTINEL_ENABLED=true
SENTINEL_PATH=sentinel
SENTINEL_DOMAIN=
SENTINEL_DB_CONNECTION=            # keep monitoring writes off your bot's database

SENTINEL_UPDATE_SAMPLE_RATE=1      # 0.1 keeps 10% of update entries; metrics stay complete

SENTINEL_PLAYGROUND=true
SENTINEL_PLAYGROUND_LIVE=false     # let the playground and replays really call Telegram
SENTINEL_WEBHOOK_CHANGES=true      # allow setting / deleting webhooks from the dashboard
```

Pointing `SENTINEL_DB_CONNECTION` at a second [database connection](/v4/database) is recommended in production: monitoring writes then never compete with your bot's own queries.

<a name="data-retention"></a>
### Data Retention

Entries are kept for `retention.entries` hours (48 by default) and metrics for `retention.metrics` days (7 by default). The `sentinel:prune` command removes what is older, and should be [scheduled](/v4/scheduling) daily:

```php
use LaraGram\Support\Facades\Schedule;

Schedule::command('sentinel:prune')->daily();
```

Both windows may be overridden per run, and exceptions may be kept while everything else goes:

```shell
php laragram sentinel:prune --hours=72 --keep-exceptions
```

`sentinel:clear` empties the entries immediately, and `--metrics` clears the recorded metrics as well.

<a name="keeping-sentinel-running"></a>
### Keeping Sentinel Running

One long-running command keeps the parts of the dashboard that are not driven by updates alive. It snapshots every webhook, the [proxy pool](/v4/requests#proxy-pool) and the server it runs on, and sends the webhook alerts:

```shell
php laragram sentinel:check
```

Run it under Supervisor (or any process manager) on each server, or take a single snapshot from the scheduler:

```shell
php laragram sentinel:check --once --interval=15
```

Recording may also be paused and resumed without deploying: `php laragram sentinel:pause` and `php laragram sentinel:resume`.

<a name="dashboard-authorization"></a>
## Dashboard Authorization

Sentinel has a login of its own, separate from your application's users. With no login method configured, the dashboard is only reachable in the `local` environment; as soon as one is configured, **every** visit needs a login, locally too.

<a name="telegram-login"></a>
### Telegram Login

List the Telegram user ids (or usernames) that may sign in. An admin types their id and the bot sends them a one-time code:

```ini
SENTINEL_ADMINS=123456789,987654321
SENTINEL_AUTH_CONNECTION=bot       # the bot connection that sends the codes
SENTINEL_SESSION_LIFETIME=720      # minutes
SENTINEL_LOGIN_NOTIFY=true         # message the admins about every login
```

Codes expire after five minutes and die after five wrong tries, each IP is limited to five attempts per minute, and the form answers the same way for unknown ids so it cannot be used to find out who the admins are. Every login is recorded and, with `SENTINEL_LOGIN_NOTIFY`, announced to the admins on Telegram.

<a name="password-login"></a>
### Password Login

A username and password may be used instead of, or alongside, Telegram login. The password may be plain text or a bcrypt / argon hash:

```ini
SENTINEL_USERNAME=admin
SENTINEL_PASSWORD=choose-a-long-password
```

<a name="letting-your-own-users-in"></a>
### Letting Your Own Users In

Users of your own application who are authenticated through the `web` guard may be let in with the `viewSentinel` [gate](/v4/authorization) in `App\Providers\SentinelServiceProvider`:

```php
use LaraGram\Support\Facades\Gate;

protected function gate(): void
{
    Gate::define('viewSentinel', function ($user = null) {
        return in_array($user?->email, [
            'you@example.com',
        ]);
    });
}
```

For a rule of your own — an IP allow list, a VPN header, anything — register a callback:

```php
use LaraGram\Sentinel\Sentinel;

Sentinel::auth(fn ($request) => in_array($request->ip(), ['203.0.113.7']));
```

<a name="the-dashboard"></a>
## The Dashboard

<a name="updates"></a>
### Updates

Every update your bot receives becomes one entry with everything that happened while it was handled. The entry is tagged automatically, so the search box is the fastest way through the data:

| Tag | Matches |
| --- | --- |
| `chat:<id>` / `user:<id>` | Everything one chat or one user did |
| `connection:<name>` | A single bot connection |
| `update:<type>` / `kind:<kind>` | A kind of update, such as `update:message` or `kind:photo` |
| `command:/start` | One command |
| `unhandled` | Updates no listen matched |
| `failed` | Updates whose handling threw |
| `slow` | Updates slower than the watcher's threshold |

The Bot API calls, logs, queries and exceptions an update caused carry the same `chat:` and `user:` tags, so typing `user:123456` shows everything that happened for that user.

<a name="chat-preview-and-replay"></a>
### Chat Preview and Replay

Each update is replayed as a Telegram chat: what the user sent and what the bot answered, with inline keyboards and formatting rendered the way Telegram renders them. Any recorded update may also be **replayed** through your current code to reproduce a bug — as a dry run, or live when `SENTINEL_PLAYGROUND_LIVE` allows it.

<a name="the-playground"></a>
### The Playground

The playground composes an update by hand — text, a command, a button press, an inline query, a location, or raw JSON — and runs it through your real bot. In dry-run mode every Bot API call is answered locally with `Request::interceptUsing()`, so nobody receives anything and the dashboard still shows exactly which calls your code made.

<a name="webhooks"></a>
### Webhooks

The webhook inspector keeps a live `getWebhookInfo` for every bot connection with a health score: delivery errors, pending updates, the IP Telegram connects from and the allowed update types. When `SENTINEL_WEBHOOK_CHANGES` is enabled, webhooks may also be set, deleted, or have their pending updates dropped from the dashboard.

<a name="commands-check"></a>
### Commands Check

Sentinel compares the commands registered with [@BotFather](https://t.me/BotFather) to your [listens](/v4/listening): commands users can see but nothing answers, and handlers that are missing from the menu.

<a name="users-and-chats"></a>
### Users and Chats

Who talks to your bot, who is new, which languages they use, and who **blocked** the bot.

<a name="listeners-and-conversations"></a>
### Listeners and Conversations

Every registered listen with its hit count and timings, plus the updates that matched nothing. [Conversations](/v4/conversations) are shown as a funnel: started, answered, invalid answers, completed and cancelled.

<a name="api-calls"></a>
### API Calls

Volume, latency and errors grouped by method and error code, plus the **flood waits** (`retry_after`) Telegram handed out per method — the fastest way to see whether [anti-flood](/v4/requests#smart-anti-flood) needs tuning.

<a name="servers-and-network"></a>
### Servers and Network

CPU, memory and disk of every server running `sentinel:check`, the latency of the Telegram API, and the health of the [proxy pool](/v4/requests#proxy-pool).

<a name="watchers"></a>
## Watchers

Watchers are what collect the data. Each one may be switched off or tuned under `watchers` in `config/sentinel.php`:

<div class="overflow-auto">

| Watcher | Records | Options |
| --- | --- | --- |
| `UpdateWatcher` | Every incoming update and how it was handled | `sample_rate`, `slow`, `ignore_types` |
| `ApiCallWatcher` | Bot API calls, their latency, errors and flood waits | `sample_rate`, `slow`, `ignore_methods`, `response_size_limit` |
| `ConversationWatcher` | Conversation steps, answers and completions | — |
| `ExceptionWatcher` | Exceptions, with their update and stack trace | — |
| `LogWatcher` | Log entries | `level` |
| `QueryWatcher` | Database queries | `slow`, `ignore_packages`, `ignore_paths` |
| `JobWatcher` | Queued jobs and their failures | — |
| `CacheWatcher` | Cache hits, misses and writes | `hidden`, `ignore` |
| `CommandWatcher` | Commander commands | — |
| `ScheduleWatcher` | Scheduled tasks | — |
| `RequestWatcher` | Web requests (Mini Apps, dashboards, APIs) | `slow`, `size_limit` |
| `EventWatcher` | Dispatched events (off by default) | `ignore` |

</div>

Bot tokens are always masked, and the parameters, headers and fields listed under `hidden` are redacted before anything is written.

<a name="filtering-and-tagging"></a>
## Filtering and Tagging

<a name="filtering"></a>
### Filtering

In development it is useful to keep everything. In production you usually want the interesting entries only, which is what a filter decides — register one in `App\Providers\SentinelServiceProvider`:

```php
use LaraGram\Sentinel\IncomingEntry;
use LaraGram\Sentinel\Sentinel;

Sentinel::filter(fn (IncomingEntry $entry) =>
    $entry->isException() || $entry->isFailedApiCall() || $entry->type === 'update'
);
```

`Sentinel::filterBatch` decides for a whole update at once, so you may keep every entry of an update that failed and nothing from the ones that went well.

> [!NOTE]
> Exceptions, failed API calls and failed jobs are always kept, whatever the filter and the sample rate say. Tags marked as *monitored* on the Settings page are always kept too, which is how you follow one user through production.

<a name="tagging"></a>
### Tagging

Add tags of your own to make entries searchable by what matters in your application:

```php
Sentinel::tag(fn (IncomingEntry $entry) => $entry->type === 'update'
    ? ['plan:'.auth()->user()?->plan]
    : []);
```

<a name="hiding-sensitive-data"></a>
### Hiding Sensitive Data

Parameters, fields and headers may be redacted before they are stored:

```php
Sentinel::hide('parameters', ['phone_number']);
```

<a name="custom-metrics"></a>
## Custom Metrics

Numbers of your own may be recorded and charted next to the built-in ones. Pass a type, a key, the value and the aggregates to keep:

```php
Sentinel::metric('payment', $plan, $amount, ['count', 'sum']);
```

<a name="telegram-alerts"></a>
## Telegram Alerts

Sentinel can message you on Telegram when something needs attention: an exception was thrown, the webhook started failing, updates are piling up, a job failed, or Telegram flood-limited the bot.

```ini
SENTINEL_ALERTS=true
SENTINEL_ALERTS_CHAT_IDS=123456789,-1001234567890
SENTINEL_ALERTS_CONNECTION=bot
```

<a name="sampling-and-performance"></a>
## Sampling and Performance

Each webhook delivery is handled in its own process, so Sentinel buffers what happens while an update is handled and writes it in a single batch when the process terminates — it adds almost nothing to your bot's response time. Long-running processes ([queue workers](/v4/queues), [Surge](/v4/surge)) flush after every job or request.

On a busy bot, keep every metric but only a slice of the entries:

```ini
SENTINEL_UPDATE_SAMPLE_RATE=0.1
```

Metrics are always complete, because they are counted before sampling is applied.

Recording may also be turned off around a block of code, which is handy in seeders, imports and tests:

```php
Sentinel::withoutRecording(function () {
    // ...
});
```
