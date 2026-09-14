---
name: mtproto-development
description: "Develops Telegram MTProto clients with laraxgram/mtproto in LaraGram. Activate when writing or changing MTProto listeners (Client::onText, Client::onCommand, Client::incomming/outgoing, Client::forSessions, listens/client.php), ClientRequest replies (sendMessage with peer, sendPhoto, markAsRead, namespaced API like $request->messages->..., invoke()), sessions and authentication (client:auth, session:list, session:import, session:encrypt), running clients (client:start, the Surge-hosted pump), config/mtproto.php (sessions, stores, rate_limit, pacing, flood control), sending from jobs or controllers (Client::session, ClientManager::invoker), channels, participants, media downloads, or ban-safety for userbots."
license: MIT
metadata:
  author: laraxgram
---

# MTProto Development

MTProto lets the application act as a Telegram client: a user account ("userbot") or a bot over MTProto, with capabilities the Bot API lacks (full history, large files, dialogs, channel administration, hundreds of update types). Everything runs through LaraGram idioms — the `Client` facade, listen files, and `ClientRequest`. Use `search-docs` with `packages: ['laraxgram/mtproto']` for exact method names and parameters.

## Safety First

- A session is a logged-in account. Treat `storage` session files, `API_ID`, and `API_HASH` as secrets; never print, commit, or copy them.
- Never run `client:auth`, `session:import`, `session:encrypt`/`decrypt`, or log out a session without the user's explicit request.
- Userbots can be banned for automated behavior. Keep `rate_limit` enabled, consider `pacing` for long-lived userbots, and never write loops that message many users or join many chats without the user's explicit request.
- Two processes must not use the same session at once. When the pump runs (under Surge or `client:start`), other processes must go through the pump (see "Calling MTProto Outside Listeners").

## Setup

```php
// bootstrap/app.php
->withListener(
    bot: __DIR__.'/../listens/bot.php',
    client: __DIR__.'/../listens/client.php',   // MTProto user sessions
    commands: __DIR__.'/../listens/console.php',
)
```

- Credentials come from `.env` (`API_ID`, `API_HASH`); sessions are declared in `config/mtproto.php` (`sessions`).
- Authenticate once, interactively, with `php laragram client:auth` (`--session=support`, `--bot=TOKEN`, or `--qr`).
- Run sessions with `php laragram surge:start` (the pump starts automatically for authorized sessions) or `php laragram client:start --session=default,support` / `--all`.

## Listeners

```php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onCommand('start', function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'Welcome!');
});

Client::incomming()->onText('ping', function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'pong');
});

Client::forSessions('support')->onText('hours', [SupportController::class, 'hours']);
```

- The verbs mirror the Bot facade (`onText`, `onCommand`, `onPhoto`, `onCallbackQueryData`, `onStep`, `fallback`, ...) plus MTProto-only updates (`onEditedMessage`, `onDeletedMessages`, `onUpdate`, ...).
- A user account sees its own messages as updates: scope with `incomming()` or `outgoing()` so the account does not answer itself.
- Scope multi-account listeners with `forSessions()`. Replies go out through the session that received the update automatically.

## ClientRequest

- Read fields as properties (`$request->message`, `$request->peer_id`, `$request->media`) or helpers: `text()`, `callbackData()`, `chatId()`, `messageId()`, `entities()`, `type()`, `session()`, `isOutgoing()`, `toArray()`.
- Reply with the high-level API on the request: `sendMessage(peer:, message:, parse_mode:)`, `sendPhoto`, `sendDocument`, `sendAlbum`, `editMessage`, `forwardMessages`, `markAsRead`, `answerCallback`, `answerInlineQuery`.
- Peers accept `@username`, numeric ids, or `'me'`.
- Download media with `$request->download()` / `downloadMediaToFile($path)`; check `getMediaInfo()` for size before downloading large files.
- Reach any TL namespace with `$request->messages->...`, `$request->channels->...`, or call a raw method with `invoke('help.getConfig', [...])`.

## Calling MTProto Outside Listeners

- In jobs, commands, and scheduled tasks running in a process that owns the session, use `Client::session('support')->sendMessage(...)`.
- In web requests and Surge workers while the pump is running, use the invoker so the call is forwarded to the pump over its local RPC socket instead of opening a second connection:

```php
$invoker = app('mtproto.manager')->invoker('support');

$invoker->call('sendMessage', ['peer' => '@team', 'message' => 'Deploy finished']);
$invoker->invoke('messages.getHistory', ['peer' => '@team', 'limit' => 10]);
```

## Configuration Checklist

| Key | Guidance |
| --- | --- |
| `driver` / `use_pump` | `swoole` + pump for non-blocking, concurrent handlers (required under Surge) |
| `stores` | Where auth keys, peers, and update state live; use durable stores for sessions |
| `rate_limit`, `flood_sleep` | Keep enabled; they prevent `FLOOD_WAIT` and reduce ban risk |
| `pacing` | Adds human-like delays for long-lived userbots |
| `surge.sessions`, `surge.isolation` | Which sessions the Surge pump runs, and how state is reset between updates (`concurrent` or `sandbox`) |
| `rpc` | The pump's local socket used by `invoker()` |
