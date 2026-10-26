# LaraGram Sentinel

- [Introduction](#introduction)
- [Installation](#installation)
    - [Configuration](#configuration)
    - [Storage Drivers](#storage-drivers)
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
    - [API Server](#api-server)
- [Queues](#queues)
    - [Running the Queue Supervisor](#running-the-queue-supervisor)
    - [Supervisors and Balancing](#supervisors-and-balancing)
    - [Deploying](#deploying-queues)
    - [The Queue Pages](#the-queue-pages)
    - [Tags and Monitoring](#queue-tags)
    - [Silenced Jobs](#silenced-jobs)
    - [Long Wait Alerts](#long-wait-alerts)
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
SENTINEL_STORAGE_DRIVER=database   # or "redis" to buffer writes, see "Storage Drivers"

SENTINEL_UPDATE_SAMPLE_RATE=1      # 0.1 keeps 10% of update entries; metrics stay complete

SENTINEL_PLAYGROUND=true
SENTINEL_PLAYGROUND_LIVE=false     # let the playground and replays really call Telegram
SENTINEL_WEBHOOK_CHANGES=true      # allow setting / deleting webhooks from the dashboard
```

Pointing `SENTINEL_DB_CONNECTION` at a second [database connection](/v4/database) is recommended in production: monitoring writes then never compete with your bot's own queries.

<a name="storage-drivers"></a>
### Storage Drivers

Entries and metrics always end up in the database. The storage driver decides how they get there.

With the `database` driver (the default), every update writes its batch when its process terminates. That is the simplest setup and fine for most bots. Under a burst, though, such as thousands of users pressing `/start` at the same moment, hundreds of processes write to the same metric rows at once and the database spends its time waiting on locks. Sentinel writes those rows in a fixed order and retries a write that loses a deadlock or a lock wait, so nothing breaks, but the database is still busy with monitoring when your bot needs it most.

The `redis` driver takes those writes out of the update. Each update pushes its batch to a Redis list, which is a single command that never waits on a lock, and the `sentinel:flush` command writes the buffered batches to the database together:

```ini
SENTINEL_STORAGE_DRIVER=redis
SENTINEL_REDIS_CONNECTION=default   # a connection from config/database.php "redis"
SENTINEL_REDIS_MAX_BATCHES=100000   # older batches are dropped past this many
```

`sentinel:flush` is added to your [scheduler](/v4/scheduling) automatically and runs every minute, but it keeps flushing for most of that minute, so the dashboard stays only a few seconds behind. All you need is the scheduler's cron entry. To run the flusher under Supervisor instead, turn the schedule off and keep it running:

```ini
SENTINEL_REDIS_SCHEDULE=false
```

```shell
php laragram sentinel:flush --seconds=0
```

`sentinel:flush --once` writes whatever is buffered and exits.

The driver never loses data to a Redis outage: when the list cannot be reached, the batch is written to the database directly. If the flusher stops running, the list keeps only the newest `SENTINEL_REDIS_MAX_BATCHES` batches instead of filling Redis. [Playground](#the-playground) runs and replays are always written directly, because the dashboard reads them back as soon as they finish.

Entries are inserted in batches of at most 1MB (`storage.database.chunk_bytes`), so one statement stays under MySQL and MariaDB's `max_allowed_packet`. An entry larger than 256KB (`storage.database.entry_bytes`) has its long strings cut. If it is still too large, only its size is stored. Queries that touch Sentinel's own tables, such as the deletes `sentinel:prune` runs, are never recorded.

> [!NOTE]
> If you published `config/sentinel.php` before the `redis` driver existed, copy the `storage` section from the package's config into yours. A published config replaces the whole `storage` array, so new keys are not picked up otherwise.

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

<a name="api-server"></a>
### API Server

When your bots talk to a local Bot API server — the official `telegram-bot-api` or the [LaraGram API Server](/v4/apiserver) — started with a [statistics port](/v4/apiserver#statistics), the **API Server** page shows what it reports: bots and active bots, requests, updates and errors per second over the last 5 seconds, minute and hour, active requests and webhook connections, CPU and memory, and every bot with its delivery method and pending updates. Bot tokens are masked, and each bot is matched to its bot connection.

Sentinel reads the address from the `stat` option of `api_server` in `config/bot.php`. Point it elsewhere, for example at a server on another machine, with:

```ini
SENTINEL_API_SERVER_STATS_URL=http://10.0.0.5:8082
```

`sentinel:check` records the statistics with each webhook snapshot, so traffic, CPU, memory and per-bot updates are charted over the selected period, and the last snapshot stays visible while the server is unreachable.

<a name="queues"></a>
## Queues

Sentinel also runs your Redis queue. `sentinel:queue` starts a master supervisor that launches, watches and balances your queue workers, and records every job it handles: pending, completed and failed jobs, their payloads and exceptions, job batches, throughput and runtime per job and per queue, and how long each queue waits. All of it shows up in the **Queues** section of the dashboard.

Use the `redis` queue connection:

```ini
QUEUE_CONNECTION=redis
```

The jobs, metrics and supervisors are kept on the Redis connection named by `sentinel.queue.use` (`default`), under the `sentinel.queue.prefix` key prefix (`SENTINEL_QUEUE_PREFIX`). Apps that do not use Redis are not affected; the Queues pages simply stay empty.

<a name="running-the-queue-supervisor"></a>
### Running the Queue Supervisor

Start the supervisor instead of `queue:work`:

```shell
php laragram sentinel:queue
```

It runs in the foreground, so in production keep it alive with Supervisor (or any process manager), with a stop wait time longer than your longest job:

```ini
[program:sentinel-queue]
process_name=%(program_name)s
command=php /home/forge/app.com/laragram sentinel:queue
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/app.com/storage/logs/sentinel-queue.log
stopwaitsecs=3600
```

While developing, `php laragram sentinel:queue:listen` restarts the workers whenever a file listed in `sentinel.queue.watch` changes, and with a Redis queue `php laragram dev` runs the supervisor instead of `queue:listen`.

Pause and resume processing with `sentinel:queue:pause` and `sentinel:queue:continue` (or a single supervisor with `sentinel:queue:pause-supervisor` and `sentinel:queue:continue-supervisor`), and check on it with `sentinel:queue:status`, `sentinel:queue:supervisors` and `sentinel:queue:list`.

<a name="supervisors-and-balancing"></a>
### Supervisors and Balancing

The supervisors of each environment are defined in the `queue` section of `config/sentinel.php`. `defaults` holds the options shared by every environment, and `environments` overrides them per `APP_ENV`:

```php
'queue' => [
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'tries' => 1,
            'timeout' => 60,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],
    ],
],
```

Each supervisor runs up to `maxProcesses` workers for its queues. `balance` decides how they are spread:

<div class="content-list" markdown="1">

- `auto` moves workers to the busiest queues, judged by the time it would take to clear them (`autoScalingStrategy: 'time'`) or by the number of jobs waiting (`'size'`), between `minProcesses` and `maxProcesses`, shifting `balanceMaxShift` workers every `balanceCooldown` seconds.
- `simple` splits the workers evenly between the queues.
- `false` works the queues in the order they are listed, like `queue:work`.

</div>

A broadcast to thousands of chats is a good reason for its own supervisor, so it never delays the jobs your users are waiting for:

```php
'broadcasts' => [
    'connection' => 'redis',
    'queue' => ['broadcasts'],
    'balance' => 'auto',
    'maxProcesses' => 5,
    'timeout' => 300,
],
```

<a name="deploying-queues"></a>
### Deploying

Workers keep the code they started with. After deploying, terminate the supervisor; your process manager starts it again with the new code, and the running jobs are allowed to finish first:

```shell
php laragram sentinel:queue:terminate
```

`php laragram reload` does this for you. `sentinel:queue:purge` stops worker processes that lost their supervisor.

<a name="the-queue-pages"></a>
### The Queue Pages

<div class="content-list" markdown="1">

- **Queues** — whether the supervisor is running, jobs per minute, recent and failed jobs, the current workload per queue with its wait time, and every master supervisor with its supervisors, queues and worker count.
- **Queued Jobs** — pending, completed and silenced jobs with their tags, queue and runtime; open a job to see its payload.
- **Failed Jobs** — the jobs that ran out of attempts, with their exception and context. Retry them one by one once the cause is fixed, filter them by tag, and follow each retry. `sentinel:queue:forget` deletes a failed job, and `sentinel:queue:clear` empties a queue.
- **Batches** — `Bus::batch()` batches with their progress and failed jobs, which you may retry together. Batches need the `job_batches` table (`php laragram make:queue-batches-table`).
- **Queue Metrics** — throughput and runtime per job class and per queue over time.
- **Monitored Tags** — the tags you follow (see below).

</div>

The metrics come from snapshots Sentinel's scheduler takes every five minutes (`sentinel:queue:snapshot`), so make sure the scheduler runs. Turn it off with `SENTINEL_QUEUE_SNAPSHOTS=false` to schedule the command yourself. How long jobs are kept is set in minutes by `sentinel.queue.trim`.

<a name="queue-tags"></a>
### Tags and Monitoring

Every job is tagged with the Eloquent models it carries, such as `App\Models\User:42`. Add your own tags with a `tags` method; for a Telegram bot, the chat a job works for is usually the most useful one:

```php
class SendDailyDigest implements ShouldQueue
{
    public function __construct(public int $chatId) {}

    public function tags(): array
    {
        return ['chat:'.$this->chatId, 'digest'];
    }
}
```

Recent jobs are trimmed after an hour. To keep every job of a chat, a user or a model — completed or failed — monitor its tag from the **Monitored Tags** page. Monitored jobs are kept for `sentinel.queue.trim.monitored` minutes.

<a name="silenced-jobs"></a>
### Silenced Jobs

Jobs that run constantly and are not worth looking at can be kept out of the completed list. List their classes or tags in `sentinel.queue.silenced` and `sentinel.queue.silenced_tags`, or implement the `LaraGram\Sentinel\Queue\Contracts\Silenced` interface on the job. Silenced jobs are still listed on the **Silenced** tab, and they still fail loudly.

<a name="long-wait-alerts"></a>
### Long Wait Alerts

When a queue waits longer than its threshold in `sentinel.queue.waits` (seconds, keyed by `connection:queue`), Sentinel sends a [Telegram alert](#telegram-alerts):

```php
'waits' => [
    'redis:default' => 60,
    'redis:broadcasts' => 600,
],
```

Set a queue to `0` to never alert on it. Turn these alerts off with `sentinel.alerts.on.long_waits`.

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

Sentinel can message you on Telegram when something needs attention: an exception was thrown, the webhook started failing, updates are piling up, a job failed, a queue is waiting too long, or Telegram flood-limited the bot.

```ini
SENTINEL_ALERTS=true
SENTINEL_ALERTS_CHAT_IDS=123456789,-1001234567890
SENTINEL_ALERTS_CONNECTION=bot
```

<a name="sampling-and-performance"></a>
## Sampling and Performance

Each webhook delivery is handled in its own process, so Sentinel buffers what happens while an update is handled and writes it in a single batch when the process terminates — it adds almost nothing to your bot's response time. Long-running processes ([queue workers](/v4/queues), [Surge](/v4/surge)) flush after every job or request.

If your bot gets bursts of updates, use the [`redis` storage driver](#storage-drivers): the batch then costs each update one Redis command instead of database writes.

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
