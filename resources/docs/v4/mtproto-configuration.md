# MTProto: Configuration & Deployment

- [Introduction](#introduction)
- [Credentials & Sessions](#credentials-sessions)
- [Connection](#connection)
    - [Data Center](#data-center)
    - [Driver & Pump](#driver-pump)
    - [Connection Settings](#connection-settings)
- [Transport & Anonymity](#transport-anonymity)
    - [Transport Protocol](#transport-protocol)
    - [MTProxy Relay](#mtproxy-relay)
    - [Device Fingerprint](#device-fingerprint)
- [Ban-Safety](#ban-safety)
    - [Flood Control](#flood-control)
    - [Rate Limiting](#rate-limiting)
    - [Human-Like Pacing](#human-pacing)
- [State Stores](#state-stores)
- [Connection Pool](#connection-pool)
- [Logging](#logging)
- [API Layer](#api-layer)
- [Running in Production](#running-in-production)
    - [The Surge-Hosted Pump](#surge-pump)
    - [The client:start Command](#client-start)

<a name="introduction"></a>
## Introduction

All MTProto configuration lives in `config/mtproto.php`, published with:

```shell
php laragram vendor:publish --tag=mtproto-config
```

The file ships with safe defaults and is heavily commented. This reference walks through every block. Most options also read from the environment, so you can tune them per-deployment via `.env` without editing the file.

> [!IMPORTANT]
> Several defaults are chosen specifically to reduce ban risk on user accounts — the obfuscated transport, a realistic device fingerprint, and rate limiting. Read [Ban-Safety](#ban-safety) before changing them.

<a name="credentials-sessions"></a>
## Credentials & Sessions

```php
'api_id'   => env('API_ID', ''),
'api_hash' => env('API_HASH', ''),

'sessions' => [
    // 'default'  => [],
    // 'support'  => ['api_id' => env('SUPPORT_API_ID'), 'api_hash' => env('SUPPORT_API_HASH')],
],
```

`api_id` / `api_hash` identify your application to Telegram (from [my.telegram.org](https://my.telegram.org)). The `sessions` array declares your accounts; each entry deep-merges over the global options. See [Multi-Account Sessions](/v4/mtproto-authentication#multi-account-sessions).

The session storage backend and directory are configured separately:

```php
'session' => [
    'driver' => env('CLIENT_SESSION_DRIVER', 'swoole-table'),
    'path'   => env('CLIENT_SESSION_PATH', storage_path('app/clients/sessions')),
    'name'   => env('CLIENT_SESSION_NAME', 'default'),
],
```

<a name="connection"></a>
## Connection

<a name="data-center"></a>
### Data Center

```php
'dc_id'     => env('CLIENT_DC_ID', 2),
'test_mode' => env('CLIENT_TEST_MODE', false),
```

`dc_id` is the default data center (1–5) to connect to; Telegram redirects you to the correct one during login. Enable `test_mode` to use Telegram's test servers.

<a name="driver-pump"></a>
### Driver & Pump

```php
'driver'   => env('CLIENT_LOOP_DRIVER', 'swoole'),
'use_pump' => env('CLIENT_USE_PUMP', true),
```

- **`driver`** — `swoole` (non-blocking, coroutine-based) or `sync`. Use `swoole` for anything long-running; it is **required** under [Surge](/v4/surge).
- **`use_pump`** — the single-reader coroutine core that routes every RPC and pushed update through one socket reader so handlers run non-blocking, each in its own coroutine. Requires the `swoole` driver.

> [!WARNING]
> Non-blocking handlers (where a slow reply or `sleep` doesn't stall other updates) require **both** `driver => 'swoole'` **and** `use_pump => true`. With the `sync` driver, handlers run one at a time.

<a name="connection-settings"></a>
### Connection Settings

```php
'connection' => [
    'timeout'     => 10,
    'retry_count' => 3,
    'retry_delay' => 1,
],
```

Socket timeout (seconds), the number of retries on a failed connection, and the delay between them.

<a name="transport-anonymity"></a>
## Transport & Anonymity

<a name="transport-protocol"></a>
### Transport Protocol

```php
'transport' => env('CLIENT_TRANSPORT', 'obfuscated'),
```

| Value | Framing | Notes |
|-------|---------|-------|
| `abridged`, `intermediate`, `intermediate_padded`, `full` | Plain | Fixed, fingerprintable byte pattern |
| `obfuscated` | Obfuscated2 (abridged inner) | **Recommended default** |
| `obfuscated_intermediate` | Obfuscated2 (intermediate inner) | |

Obfuscated2 turns the whole TCP stream into AES-CTR ciphertext after a 64-byte random handshake — the traffic shape official clients and MTProxy use. Plain framing exposes a fixed, fingerprintable pattern, so `obfuscated` is the ban-safe default for userbots.

<a name="mtproxy-relay"></a>
### MTProxy Relay

```php
'proxy' => [
    'enabled' => env('CLIENT_PROXY_ENABLED', false),
    'host'    => env('CLIENT_PROXY_HOST', ''),
    'port'    => (int) env('CLIENT_PROXY_PORT', 443),
    'secret'  => env('CLIENT_PROXY_SECRET', ''),
],
```

Route the obfuscated stream through an MTProxy server instead of a direct data-center socket. The client's TCP connection only ever touches the proxy, which relays the still-end-to-end-encrypted stream to Telegram. Use this in censored or high-risk networks where reaching a data-center IP directly is itself a signal. Enabling a proxy forces obfuscated2 regardless of `transport`.

<a name="device-fingerprint"></a>
### Device Fingerprint

```php
'device' => [
    'preset' => env('CLIENT_DEVICE_PRESET', 'tdesktop'),
    // 'device_model'   => 'My Device',
    // 'system_version' => 'Windows 10',
    // 'app_version'    => '5.7.3 x64',
    // 'lang_code'      => 'en',
],
```

The device/system/app strings sent at connection init. Telegram fingerprints clients by these — a `php_uname()`-derived value reveals a PHP server and is a ban risk. Pick a realistic official-client `preset`: `tdesktop`, `android`, `ios`, `macos`, or `web`. Choose the platform that matches the `api_id` you registered, and keep it **stable** — a rotating fingerprint is itself a flag. You may override individual fields on top of the preset.

<a name="ban-safety"></a>
## Ban-Safety

> [!NOTE]
> Ban risk is the top priority of the MTProto component. The defaults are tuned to make a userbot's traffic and behavior indistinguishable from an official client. Nothing else — performance, resource use, or features — is worth trading against it.

<a name="flood-control"></a>
### Flood Control

```php
'flood_sleep'       => true,
'flood_sleep_limit' => 60,
```

When Telegram returns a `FLOOD_WAIT` error, the client automatically sleeps for the requested time (up to `flood_sleep_limit` seconds) and retries, instead of surfacing the error.

<a name="rate-limiting"></a>
### Rate Limiting

```php
'rate_limit' => [
    'enabled'  => env('CLIENT_RATE_LIMIT', true),
    'global'   => ['rate' => 30.0, 'capacity' => 30.0],
    'per_peer' => ['rate' => 1.0, 'capacity' => 5.0],
],
```

Proactively paces outgoing calls *before* they hit Telegram's flood limits, avoiding reactive `FLOOD_WAIT` and lowering ban risk. It uses a token bucket per scope — `global` (overall messages/second for the account) and `per_peer` (per chat/user; Telegram allows about one message per second to a given peer). Rates are tokens-per-second.

<a name="human-pacing"></a>
### Human-Like Pacing

```php
'pacing' => [
    'enabled'          => env('CLIENT_HUMAN_PACING', false),
    'min_think'        => 0.3,
    'max_think'        => 1.5,
    'chars_per_second' => 18.0,
    'max_typing'       => 4.0,
    'jitter'           => 0.4,
],
```

Where rate limiting caps throughput, pacing adds the opposite signal: a small **random** delay before user-facing actions (send/edit/forward/read) so the account's cadence never looks machine-regular. A bot firing sends exactly back-to-back — even under the flood limit — is itself a detectable pattern. Off by default (it adds latency); turn it on for long-lived userbots where the extra realism is worth it. Ban-safety is never traded — pacing only ever *adds* delay.

<a name="state-stores"></a>
## State Stores

```php
'stores' => [
    'peer'    => ['driver' => env('CLIENT_PEER_STORE', 'swoole-table')],
    'session' => ['driver' => env('CLIENT_SESSION_STORE', 'swoole-table')],
    'state'   => ['driver' => env('CLIENT_STATE_STORE', 'swoole-table')],
    'limit'   => ['driver' => env('CLIENT_LIMIT_STORE', 'swoole-table')],
],
```

Each piece of MTProto state can pick its own storage backend, swapped with one line. Drivers: `file`, `database`, `redis`, `cache`, `array`, `swoole-table`.

| Store | Holds |
|-------|-------|
| `peer` | Peer cache (id/username/phone → access hash) |
| `session` | Auth key, server salt, session id, DC, time delta |
| `state` | Update pts/qts/seq/date (+ per-channel pts) |
| `limit` | Rate-limit token buckets |

Switching a driver is zero-downtime: the new backend is read-through over the old one, so a live session is recovered and copied across on first access — no re-login. `file` is an implicit fallback for any non-file driver. For other chains (e.g. `redis` → `swoole-table`), list earlier sources in `migrate_from`, highest priority first:

```php
'session' => [
    'driver'       => 'swoole-table',
    'migrate_from' => ['redis', 'file'],   // file is added anyway
],
```

<a name="connection-pool"></a>
## Connection Pool

```php
'pool' => [
    'max_connections' => (int)   env('CLIENT_POOL_MAX', 4),
    'idle_timeout'    => (float) env('CLIENT_POOL_IDLE', 300),
],
```

Telegram media often lives on a different data center than the account is logged in on. The pool keeps the home connection live and opens secondary connections to other data centers on demand — authorizing each by transferring the home session — then reuses and closes them once idle. This is what lets [downloads](/v4/mtproto-media#downloading-media) follow media across data centers transparently.

Every secondary connection shares the home device fingerprint, rate limiter, and pacer, so a fan of connections never bypasses the global throttle. Keep `max_connections` small — a wide spread of live sockets is itself a signal.

<a name="logging"></a>
## Logging

```php
'log_channel' => env('CLIENT_LOG_CHANNEL', 'stack'),
```

The [log channel](/v4/logging) used for MTProto output. Because handlers run inside the pump, exceptions thrown in a handler are logged here rather than crashing the process — check this channel when a listener seems to do nothing.

<a name="api-layer"></a>
## API Layer

```php
'layer' => 228,
```

The Telegram API layer version. It **must** match the compiled TL schema and generated types shipped with the package. Do not raise this by hand — the deserializer would receive constructors it cannot parse. It is managed for you by the package.

<a name="running-in-production"></a>
## Running in Production

<a name="surge-pump"></a>
### The Surge-Hosted Pump

In production the update pump runs **inside** the [Surge](/v4/surge) server. Booting Surge starts the HTTP surface and the MTProto pump together — the app is booted once, non-blocking, with no manual wiring:

```shell
php laragram surge:start
```

```php
'surge' => [
    'autostart' => true,
    'sessions'  => [],   // empty = every configured session; only authorized ones start
],
```

Set `autostart` to `false` to opt out (for example to run the pump via `client:start` instead). The `sessions` list controls which sessions the pump boots — one reader coroutine each, on one shared runtime. Only already-authorized sessions start; there is no interactive login inside a server.

<a name="client-start"></a>
### The client:start Command

For local development or a standalone worker process, run sessions directly:

```shell
# The default session
php laragram client:start

# Specific sessions (comma-separated)
php laragram client:start --session=default,support

# Every session declared in config('mtproto.sessions')
php laragram client:start --all
```

This starts one reader per session and blocks, dispatching updates to your listen files until interrupted. It requires the `swoole` [driver](#driver-pump).

That completes the MTProto reference. Head back to [Getting Started](/v4/mtproto) for the overview, or jump to [Listening](/v4/mtproto-listening) and [Requests](/v4/mtproto-requests) to build.
