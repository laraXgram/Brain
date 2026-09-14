---
name: surge-development
description: "Writes LaraGram code that is safe and fast under LaraGram Surge (Swoole/OpenSwoole long-running server). Activate when creating or reviewing service providers, singletons, static properties, or objects that hold the container, request, or config; when using Surge::concurrently, Surge::tick, Swoole tables (Surge::table), the surge cache store, Surge::process background processes, or Surge::route / Surge::listen fast paths; when configuring config/surge.php, workers, task workers, or max requests; or when debugging leaked state, memory growth, or behavior that differs between php laragram serve and surge:start."
license: MIT
metadata:
  author: laraxgram
---

# Surge Development

Surge serves the application from memory: service providers run once per worker, and the same process handles many bot updates and web requests. Surge resets framework state between requests (it clones the application and flushes first-party services), but it cannot reset state your code keeps elsewhere. Use `search-docs` with `packages: ['laraxgram/surge']`.

## How Requests Flow

- One server handles both kinds of requests: JSON bodies containing an `update_id` become bot requests (`LaraGram\Request\Request`, listens), everything else becomes web requests (`LaraGram\Http\Request`, routes).
- With task workers enabled, Telegram updates are acknowledged immediately and handled by a task worker, so the webhook never waits for listeners.
- Each request runs in a fresh sandbox of the application; `app()`, facades, `request()`, `config()`, and helpers always point at the current sandbox.

## Rules

- **Do not store the container, request, or config in singletons or constructors of long-lived objects.** Resolve them when needed (`app()`, `request()`, `config()`), or inject a resolver closure:

```php
// Leaks the first request's container into every later request
$this->app->singleton(ReportService::class, fn ($app) => new ReportService($app['request']));

// Resolves the current request each time
$this->app->singleton(ReportService::class, fn () => new ReportService(fn () => app('request')));
```

- **Do not accumulate data in static properties or global arrays.** Every value added lives until the worker restarts. Keep per-user state in the cache or database.
- **Type-hinting `Request` in listens, routes, and controller methods is safe** — the method receives the current request.
- **Reset package state you own.** When a package keeps static caches, flush them with a listener on Surge's `RequestReceived` / `RequestTerminated` events or list the bindings under `flush` in `config/surge.php`.
- **Close what you open.** Streams, file handles, and long-lived connections opened per request must be closed in the same request.
- Workers restart after `--max-requests` (500 by default) as a safety net, not as a fix for leaks.

## Concurrency and Swoole Features

These require the Swoole or OpenSwoole extension:

```php
use LaraGram\Surge\Facades\Surge;

[$users, $orders] = Surge::concurrently([
    fn () => User::count(),
    fn () => Order::where('status', 'pending')->count(),
]);

Surge::tick('refresh-rates', fn () => Rates::refresh())->seconds(60);

Surge::table('presence')->set((string) $userId, ['online' => 1]);   // tables are declared in config/surge.php

Cache::store('surge')->put('hot-key', $value, 30);                  // shared across workers, cleared on restart
```

- Concurrent task closures are serialized: capture only serializable values, and write them as normal multi-line closures.
- Declare Swoole tables in `config/surge.php` under `tables` (`'presence:10000' => ['online' => 'int']`). Data is lost when the server restarts; never treat tables or the `surge` cache as durable storage.
- Register long-lived background processes (for example a queue-like consumer) with `Surge::process(Handler::class, 'name')` in a service provider's `boot()`; the MTProto pump runs this way.
- `Surge::route('GET', '/health', fn ($request) => ...)` and `Surge::listen('COMMAND', 'ping', fn ($request) => ...)` register fast paths that skip the full kernel; use them only for trivial, stateless endpoints.

## Operations

- Start: `php laragram surge:start` (`--workers`, `--task-workers`, `--max-requests`, `--watch` for development).
- Reload after deploying: `php laragram surge:reload`; status: `surge:status`; stop: `surge:stop`.
- Serve behind Nginx for TLS and static files, and set `SURGE_HTTPS=true` when TLS terminates at the proxy.
- Long-lived streams (SSE, MCP subscriptions) hold a worker until they finish; keep them shorter than `max_execution_time`.
