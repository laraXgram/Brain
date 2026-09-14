---
name: surge-development
description: "Writes LaraGram code that is safe and fast under LaraGram Surge (the long-running Swoole / Open Swoole / RoadRunner / FrankenPHP server). Activate when creating or reviewing service providers, singletons, static properties, or objects that hold the container, request, or config; when using Surge::concurrently, Surge::tick, Swoole tables (Surge::table), the surge cache store and cache intervals, Surge::process background processes, or Surge::route / Surge::listen fast paths; when configuring config/surge.php (listeners, warm, flush, processes, tables, watch, max_execution_time), workers, task workers, or max requests; when bot updates are handled by task workers; or when debugging leaked state, memory growth, stale code, or behavior that differs between php laragram serve and surge:start."
license: MIT
metadata:
  author: laraxgram
---

# Surge Development

Surge boots the application once per worker and keeps it in memory: service providers run once, and the same process serves many bot updates and web requests. Surge resets framework state between operations (each request, task, and tick runs in a sandbox clone of the application, and first-party services are flushed), but it cannot reset state your code keeps elsewhere. Use `search-docs` with `packages: ['laraxgram/surge']`.

## How Requests Flow

- One server handles both kinds of traffic: a JSON body containing `update_id` is a Telegram update (bot kernel, listens, `LaraGram\Request\Request`); everything else is a web request (HTTP kernel, routes, `LaraGram\Http\Request`).
- With Swoole task workers (`--task-workers`, `surge.task_workers`, `auto` by default), Telegram updates are acknowledged with an empty 200 immediately and handled by a task worker. Consequences:
  - As with the PHP-FPM entrypoint (which backgrounds each update), Telegram's request is already answered when listens run, so handler errors never show up in `webhook:info`; check the logs.
  - Task workers are shared with `Surge::concurrently()` and ticks; size them for both.
- Without task workers, updates are handled inline by the HTTP worker like any request.
- Each operation runs in a fresh sandbox: `app()`, facades, `request()`, `config()`, and helpers always point at the current sandbox.

## Server Capabilities

| Feature | Swoole / Open Swoole | RoadRunner / FrankenPHP |
| --- | --- | --- |
| Web requests and bot updates | Yes | Yes |
| Task-worker bot updates, `Surge::concurrently()`, ticks | Yes | No |
| `Surge::table()`, the `surge` cache store, cache intervals | Yes | No |
| `Surge::process()` background processes (MTProto pump) | Yes | No |

Guard Swoole-only code (`extension_loaded('swoole') || extension_loaded('openswoole')`, or `config('surge.server')`) when the project may run on other servers.

## State Rules

- **Never capture the container, request, or config repository in long-lived objects.** Singletons and objects built in `register()` / `boot()` outlive the request:

```php
// Leaks the first request into every later request
$this->app->singleton(ReportService::class, fn ($app) => new ReportService($app['request']));

// Resolves the current request when needed
$this->app->singleton(ReportService::class, fn () => new ReportService(fn () => app('request')));
```

- Prefer `bind()` / `scoped()` over `singleton()` for services that hold per-request or per-user data. Type-hinting `Request` in listens, routes, controller methods, and jobs is safe.
- **No accumulating statics or globals.** `static $cache[] = ...` grows until the worker restarts. Keep per-user state in the cache or database.
- **Flush what you own.** List bindings to re-resolve under `flush` in `config/surge.php`, pre-resolve expensive shared services under `warm`, and reset package statics with a listener on `RequestReceived` / `TaskReceived` / `TickReceived` or `OperationTerminated`.
- **Close what you open.** Streams, file handles, sockets, and database transactions opened in a request must be closed in it. Consider enabling `DisconnectFromDatabases` / `CollectGarbage` under `OperationTerminated` for apps with many idle connections.
- `--max-requests` (500 by default) restarts workers as a safety net against leaks, not a fix for them. Monitor worker memory (for example with `ps` or `memory_get_usage()` logs) during development.
- Locale, auth, and other framework state are reset per operation; code that changes global PHP state (`date_default_timezone_set`, `setlocale`, `ini_set`) must restore it.

## Concurrency and Swoole Features

```php
use LaraGram\Surge\Facades\Surge;
use LaraGram\Support\Facades\Cache;

[$users, $orders] = Surge::concurrently([
    fn () => User::count(),
    fn () => Order::where('status', 'pending')->count(),
], waitMilliseconds: 3000);

// In a service provider's boot()
Surge::tick('refresh-rates', fn () => Rates::refresh())->seconds(60)->immediate();

Cache::store('surge')->interval('exchange-rates', fn () => Rates::fetch(), seconds: 30);

Surge::table('presence')->set((string) $userId, ['online' => 1]);
Cache::store('surge')->put('hot-key', $value, 30);
```

- Concurrent tasks run in other processes: closures are serialized, so capture only serializable values, and keep them to at most 1024 tasks.
- Declare tables in `config/surge.php` (`'presence:10000' => ['online' => 'int', 'name' => 'string:64']`; types `string`, `int`, `float`). Tables and the `surge` cache are shared by all workers and lost on restart: never use them as durable storage.
- Ticks run on task workers in a sandbox; keep them short and idempotent, and don't register them per request.

## Background Processes

```php
// In a service provider's boot(), before the server starts
Surge::process(PriceFeedListener::class, 'price-feed');

class PriceFeedListener
{
    public function __invoke($application, $process, $server): void
    {
        // Runs for the life of the server with its own booted application
    }
}
```

Processes are supervised (restarted with backoff when they exit) and get a freshly booted application. `surge:reload` does **not** restart them; restart the server after changing their code. The MTProto pump is registered this way.

## Fast Paths

`Surge::route('GET', '/health', fn (Request $request) => response('ok'))` and `Surge::listen('COMMAND', 'ping', fn (Request $request) => new Response)` bypass the kernel, middleware, and sessions. Use them only for trivial, stateless endpoints.

## Operations

- Start: `php laragram surge:start` (`--server`, `--workers`, `--task-workers`, `--max-requests`, `--watch`); `surge:status`; `surge:stop`.
- Development: `surge:start --watch` restarts on file changes (needs Node and `chokidar`; paths under `watch` in `config/surge.php`).
- Deploy: `surge:reload` reloads HTTP (and task) workers. Restart the whole server when processes (such as the MTProto pump), `config/surge.php`, or the server binary changed.
- Behind Nginx: proxy to the Surge port (9000), serve static files from Nginx, and set `SURGE_HTTPS=true` when TLS terminates at the proxy.
- Long-lived responses (SSE, streamed downloads, MCP `subscriptions/listen`) hold a worker; keep them below `max_execution_time` and plan worker counts accordingly.
