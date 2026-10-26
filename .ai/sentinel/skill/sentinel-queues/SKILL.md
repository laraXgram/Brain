---
name: sentinel-queues
description: "Runs and tunes Redis queue workers with Sentinel's queue supervisor (laraxgram/sentinel). Activate when the app uses QUEUE_CONNECTION=redis with Sentinel installed; when starting, deploying or restarting workers (sentinel:queue, sentinel:queue:terminate, pause / continue, purge); when configuring supervisors, processes, balancing, timeouts or tries in the queue section of config/sentinel.php; when jobs are slow, piling up or failing (failed jobs, retries, batches, wait times, long-wait alerts); or when adding job tags, monitoring tags, or silencing noisy jobs."
license: MIT
metadata:
  author: laraxgram
---

# Sentinel Queues

Sentinel's queue supervisor replaces `queue:work` for Redis queues: `php laragram sentinel:queue` starts a master supervisor that launches and balances the workers defined in the `queue` section of `config/sentinel.php`, and records jobs, failures, batches and metrics for the dashboard's Queues pages. Use `search-docs` with `packages: ['laraxgram/sentinel']` for exact options.

## Running

- Requires `QUEUE_CONNECTION=redis`. Other queue drivers keep using `queue:work`; never run both on the same Redis queue.
- Production: one `sentinel:queue` per server under Supervisor, with `stopwaitsecs` above the longest job. Do not add `queue:work` programs next to it.
- Deploy: `php laragram sentinel:queue:terminate` (or `php laragram reload`) after the new code is in place; the process manager restarts it. Running workers keep old code until then.
- Development: `sentinel:queue:listen` restarts on changes; `php laragram dev` runs the supervisor instead of `queue:listen` for Redis queues.
- Inspect with `sentinel:queue:status`, `sentinel:queue:supervisors`, `sentinel:queue:list`; pause with `sentinel:queue:pause` / `sentinel:queue:continue`.
- Metrics need the scheduler (`sentinel:queue:snapshot` runs every five minutes unless `SENTINEL_QUEUE_SNAPSHOTS=false`).

## Configuring Supervisors

```php
'queue' => [
    'defaults' => [
        'supervisor-1' => ['connection' => 'redis', 'queue' => ['default'], 'balance' => 'auto', 'maxProcesses' => 1, 'tries' => 1, 'timeout' => 60],
        // Give Telegram broadcasts their own supervisor so they never delay user-facing jobs.
        'broadcasts' => ['connection' => 'redis', 'queue' => ['broadcasts'], 'balance' => 'auto', 'maxProcesses' => 5, 'timeout' => 300],
    ],
    'environments' => [
        'production' => ['supervisor-1' => ['maxProcesses' => 10, 'balanceMaxShift' => 1, 'balanceCooldown' => 3]],
    ],
],
```

- `balance`: `auto` (scale by `autoScalingStrategy` `time` or `size`, between `minProcesses` and `maxProcesses`), `simple` (even split), `false` (queue order).
- Keep each worker `timeout` below the queue connection's `retry_after`, or jobs run twice.
- `waits` (`'redis:default' => 60`) sets the long-wait Telegram alert threshold per `connection:queue`; `0` disables it. Alerts need `sentinel.alerts`.

## Jobs

- Tag jobs the bot runs for a chat: `public function tags(): array { return ['chat:'.$this->chatId]; }`. Models passed to the job are tagged automatically (`App\Models\User:42`).
- Monitor a tag in the dashboard to keep all its jobs beyond the one-hour `trim.recent` window.
- Silence high-volume, uninteresting jobs with `queue.silenced` / `queue.silenced_tags` or the `LaraGram\Sentinel\Queue\Contracts\Silenced` interface; their failures are still recorded.
- Retry failed jobs from the dashboard only after fixing the cause; `sentinel:queue:forget` deletes one, `sentinel:queue:clear` empties a queue. Ask before clearing or retrying in production.
