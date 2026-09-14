---
name: mtproto-development
description: "Develops Telegram MTProto clients with laraxgram/mtproto in LaraGram. Activate when writing or changing MTProto listeners (Client::onText, Client::onCommand, Client::incomming/outgoing, Client::forSessions, listens/client.php, session-bound bot listen files), ClientRequest replies (sendMessage with peer, sendPhoto with path, sendAlbum, editMessage, markAsRead, the namespaced API like $request->messages->..., invoke()), chats and channels (banChatMember, promoteChatMember, iterateDialogs, iterateHistory, iterateParticipants, invite links, forum topics), media uploads and downloads, stories, reactions, secret chats, takeout, sessions and authentication (client:auth, session:list, session:import, session:encrypt), running clients (client:start, the Surge-hosted pump), config/mtproto.php (sessions, stores, transport, device, rate_limit, pacing, surge.isolation, rpc), sending from jobs, controllers or web requests (Client::session, ClientManager::invoker), or ban-safety for userbots."
license: MIT
metadata:
  author: laraxgram
---

# MTProto Development

MTProto lets the application act as a real Telegram client: a user account ("userbot") or a bot over MTProto, with capabilities the Bot API lacks (full history, dialogs, large files, channel administration, stories, secret chats, hundreds of update types). It is written with LaraGram idioms — the `Client` facade, listen files, middleware, steps, and `LaraGram\MTProto\Foundation\ClientRequest`. Use `search-docs` with `packages: ['laraxgram/mtproto']` for exact method names and parameters.

## Safety First

- A session is a logged-in account. Session files (`storage/app/clients/sessions`), `API_ID`, `API_HASH`, and session encryption keys are secrets: never print, log, commit, or copy them.
- Never run `client:auth`, `session:import`, `session:encrypt` / `session:decrypt`, `client:export`, or `logOut()` without the user's explicit request. Authentication is interactive; do not try to automate it.
- Userbots can be banned for automated behavior. Keep `rate_limit`, `flood_sleep`, the `obfuscated` transport, and a stable realistic `device` preset. Never write loops that message many users, join many chats, add members, or scrape participants unless the user explicitly asks, and then pace them.
- One session must only be connected from one process. When the pump runs (under Surge or `client:start`), every other process reaches the session through the pump's RPC (see the sessions rule). Never run the same bot token over the Bot API webhook and an MTProto session at the same time.

## How to Apply

1. Check `bootstrap/app.php` (`withListener(client: ...)` and session-bound `bot:` entries), `config/mtproto.php` (`sessions`), and the existing listen files before adding listeners.
2. Map the task to the rule index below and read each mapped rule before editing.
3. Remember that a user account sees its own messages: scope listeners with `incomming()` / `outgoing()`.
4. Handler exceptions are logged to `mtproto.log_channel` instead of crashing the pump — check `read-log-entries` / `last-error` when a listener seems to do nothing.
5. After changing listeners, restart the process that runs the sessions: `surge:reload` only reloads Surge's HTTP workers, so restart the Surge server (`surge:stop` + `surge:start`, or the supervisor) or the `client:start` process; a running pump keeps the old code.

## Rule Index

| Concern | Read |
| --- | --- |
| Listen files, the `Client` facade verbs, patterns, direction and session scoping, groups, middleware, steps, reading `ClientRequest` | [`rules/listening.md`](rules/listening.md) |
| Peers, sending and formatting messages, keyboards, editing / forwarding / deleting, searching, answering queries, the namespaced API and `invoke()`, media uploads, albums, `file_id` reuse, downloads, stories | [`rules/requests-and-media.md`](rules/requests-and-media.md) |
| Reading and iterating chats, creating chats, bans / restrictions / admins, invite links, pinning, forum topics, profile and contacts, reactions, polls, secret chats, takeout, stars and gifts, bot controls, business messages | [`rules/chats-and-features.md`](rules/chats-and-features.md) |
| Sessions and multi-account setup, authentication commands, importing and encrypting sessions, `config/mtproto.php`, ban-safety settings, stores, the Surge-hosted pump, `client:start`, calling sessions from jobs / controllers / web requests | [`rules/sessions-and-running.md`](rules/sessions-and-running.md) |

## Decision Rules

- Use the Bot API (`Bot::` listens, `LaraGram\Request\Request`) for normal bots. Reach for MTProto only when the task needs a user account or MTProto-only capabilities.
- Prefer the high-level methods (`sendMessage`, `banChatMember`, `iterateHistory`) over the namespaced API, and the namespaced API over raw `invoke()`.
- Use the generator iterators (`iterateDialogs`, `iterateHistory`, `iterateParticipants`) for large collections instead of manual offset loops.
- Store and reuse `file_id`s instead of uploading the same file repeatedly.
- Inside a handler reply through `$request` (the originating session); outside a handler use `Client::session($name)` in the pump process and `app('mtproto.manager')->invoker($name)` in any other process.
