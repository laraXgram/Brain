# MTProto Sessions, Configuration and Running

## Sessions

A session is a named, authenticated account or bot. Its auth key, salt, data center, and peer cache live in the configured stores (session files default to `storage/app/clients/sessions`, `CLIENT_SESSION_PATH`). The default session is `default`; every session command accepts `--session=name`.

Declare accounts in `config/mtproto.php`; each entry deep-merges over the global options:

```php
'api_id' => env('API_ID', ''),
'api_hash' => env('API_HASH', ''),

'sessions' => [
    'default' => [],
    'support' => [
        'api_id' => env('SUPPORT_API_ID'),
        'api_hash' => env('SUPPORT_API_HASH'),
    ],
    'announcer' => ['device' => ['preset' => 'android']],
],
```

Publish the file with `php laragram vendor:publish --tag=mtproto-config` when it doesn't exist.

## Authentication Commands (User-Run Only)

These are interactive and act on real accounts. Tell the user which command to run instead of running it yourself:

| Command | Purpose |
| --- | --- |
| `php laragram client:auth [--session=] [--bot=TOKEN] [--qr]` | Log in by phone (code and 2FA prompts), as a bot, or by QR code |
| `php laragram session:list` | List sessions, data center, auth key presence, encryption |
| `php laragram session:import <source> --from=pyrogram\|telethon\|madeline --session=` | Import an existing session (`--dry-run` to inspect) |
| `php laragram session:encrypt [--session=] [--key=] [--prune]` / `session:decrypt` | Encrypt session files at rest; keep the key out of the repository |
| `Client::session('support')->logOut()` | Invalidate the auth key on Telegram's side |

## Ban-Safety Configuration

| Option | Guidance |
| --- | --- |
| `transport` | Keep `obfuscated` (plain framings are fingerprintable). An MTProxy (`proxy.*`) forces obfuscation. |
| `device.preset` | A realistic, stable official-client preset (`tdesktop`, `android`, `ios`, `macos`, `web`) matching the registered `api_id`. Never rotate it. |
| `flood_sleep`, `flood_sleep_limit` | Keep enabled so `FLOOD_WAIT` is waited out instead of retried aggressively. |
| `rate_limit` | Keep enabled (`global` and `per_peer` token buckets). |
| `pacing` | Enable for long-lived userbots to add human-like random delays. |
| `pool.max_connections` | Keep small; many sockets are a signal. |
| `layer` | Managed by the package; never change it by hand. |

## Stores

`stores.peer`, `stores.session`, `stores.state`, and `stores.limit` pick a driver each (`file`, `database`, `redis`, `cache`, `array`, `swoole-table`). Switching drivers is read-through (`migrate_from` lists older sources), so live sessions migrate without re-login. Use durable storage (files, database, or Redis) for `session`; `swoole-table` state only lives as long as the server.

## Running Sessions

- **With Surge (production):** `php laragram surge:start` starts the MTProto pump as a Surge process for every authorized session (`mtproto.surge.autostart`, `mtproto.surge.sessions`). There is no interactive login inside the server; unauthorized sessions are skipped and re-checked every `session_check_interval` seconds.
- **Standalone:** `php laragram client:start`, `--session=default,support`, or `--all`. It blocks and requires the `swoole` driver. Keep it running under a process supervisor, and set `mtproto.surge.autostart` to `false` if Surge runs too.
- **Driver and pump:** non-blocking concurrent handlers need `driver => 'swoole'` and `use_pump => true`; the `sync` driver handles one update at a time.
- **Isolation:** `mtproto.surge.isolation` is `concurrent` (updates run concurrently, application state is flushed when idle) or `sandbox` (one update at a time, each in a fresh application sandbox like Surge requests). Choose `sandbox` when handlers rely on per-update singletons, auth, or scoped state.
- Restart the pump after deploying. `surge:reload` only reloads HTTP workers, not Surge processes such as the pump, so restart the whole server (`surge:stop` then `surge:start`, or restart it through the supervisor), or restart `client:start`.

## Calling Sessions Outside Handlers

Only one process may hold a session's connection. Choose the call style by where the code runs:

| Code runs in | Use |
| --- | --- |
| A listener or a controller bound to a client listen | `$request->...` |
| The pump process, or a command/job in a process that owns the session (no pump running elsewhere) | `Client::session('support')->...` |
| Surge HTTP workers, queue workers, scheduled commands, MCP servers — any process while the pump runs elsewhere | `app('mtproto.manager')->invoker('support')` |

```php
$invoker = app('mtproto.manager')->invoker('support');

$invoker->call('sendMessage', ['peer' => '@team', 'message' => 'Deploy finished']);
$history = $invoker->invoke('messages.getHistory', ['peer' => '@team', 'limit' => 10]);
```

- The invoker forwards to the pump's local RPC socket (`mtproto.rpc`) when it is running and falls back to a local client otherwise; `isRemote()` tells which. Arguments and results must be serializable; generators are collected into arrays.
- Never call `Client::session()` from web requests or queue workers while the pump runs: it opens a second connection on the same session, which breaks update state and increases ban risk.
- Queue slow or bulk MTProto work in jobs that use the invoker, and keep them paced.
