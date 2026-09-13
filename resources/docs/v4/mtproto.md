# MTProto: Getting Started

- [Introduction](#introduction)
    - [What Is MTProto?](#what-is-mtproto)
    - [User Clients vs. Bots](#user-clients-vs-bots)
    - [Feature Overview](#feature-overview)
- [Installation](#installation)
    - [API Credentials](#api-credentials)
    - [Publishing the Configuration](#publishing-the-configuration)
- [Registering Listen Files](#registering-listen-files)
    - [The Client Listen File](#the-client-listen-file)
    - [Binding a Session to a Bot Listen File](#binding-a-session-to-a-bot-listen-file)
- [Your First Client](#your-first-client)
    - [Authenticating a Session](#authenticating-a-session)
    - [Writing a Listener](#writing-a-listener)
    - [Running the Client](#running-the-client)
- [Architecture Overview](#architecture-overview)
- [Where to Go Next](#where-to-go-next)

<a name="introduction"></a>
## Introduction

The MTProto component connects your LaraGram application directly to Telegram's core network protocol — the same protocol the official Telegram apps speak. Where the standard [Bot listener](/v4/listening) talks to the Telegram **Bot API** over HTTP, MTProto talks to Telegram's data centers over a raw, encrypted TCP connection. This unlocks the full Telegram surface: logging in as a **real user account** (a "userbot"), downloading any file, managing channels and supergroups, reading dialogs, secret chats, stories, stars, takeout exports, and hundreds of API methods the Bot API never exposes.

The best part is that none of the protocol complexity leaks into your application. You never open a socket, generate an auth key, or serialize a TL object by hand. Everything is expressed through the same LaraGram idioms you already use — facades, a listen file, a `Request` object, and configuration. Authentication is a single Commander command; listening for updates looks exactly like listening for Bot updates; sending messages is one expressive method call.

> [!NOTE]
> This documentation never shows "raw" MTProto usage. Every example runs through the LaraGram framework — facades, config, and the `ClientRequest` object. Low-level concerns such as creating a client instance, opening connections, and handshaking are handled by the framework for you.

<a name="what-is-mtproto"></a>
### What Is MTProto?

MTProto is Telegram's own transport and cryptographic protocol. Connecting through it (rather than the Bot API) means your application *is* a Telegram client, with the same capabilities a phone or desktop app has. In practice this gives you:

<div class="content-list" markdown="1">

- **Full account access** — log in with a phone number and act as a user, not just a bot.
- **No Bot API limits** — download files larger than 20&nbsp;MB, read full chat history, list every dialog, and call methods the Bot API doesn't offer.
- **Rich update stream** — receive typing indicators, read receipts, user status, reactions, stories, and hundreds of other update types.
- **Multiple accounts in one process** — run a fleet of user and bot sessions side by side.

</div>

<a name="user-clients-vs-bots"></a>
### User Clients vs. Bots

MTProto supports two kinds of login, and both are driven through the same API:

<div class="content-list" markdown="1">

- **User client (userbot)** — authenticated with a phone number (and 2FA password / QR code). Has the capabilities of a normal Telegram account.
- **Bot client** — authenticated with a bot token from [@BotFather](https://t.me/BotFather), but running over MTProto instead of the Bot API. This gives a bot access to MTProto-only features (large file downloads, richer updates) while keeping bot semantics.

</div>

You choose which one a session is when you [authenticate](/v4/mtproto-authentication) it. The listeners and high-level API you write are identical either way.

<a name="feature-overview"></a>
### Feature Overview

| Area | Highlights | Reference |
|------|-----------|-----------|
| Authentication | Phone, bot token, QR login, 2FA, sign-up; multi-account sessions | [Authentication](/v4/mtproto-authentication) |
| Listening | 200+ update verbs, `incomming()`/`outgoing()`, session scoping, groups, middleware, steps | [Listening](/v4/mtproto-listening) |
| Requests | Send messages, `parse_mode`/entities, rich messages, keyboards, namespaced API | [Requests](/v4/mtproto-requests) |
| Chats | Channel/supergroup management, participants, admin rights, invite links, forums, communities | [Chats & Channels](/v4/mtproto-chats) |
| Media | Upload/download, photos/video/documents/albums, `file_id` reuse, stories | [Media](/v4/mtproto-media) |
| Advanced | Secret chats, takeout export, stars & gifts, reactions, drafts, business | [Features](/v4/mtproto-features) |
| Configuration | Transport, proxy, stores, rate-limit, pacing, device fingerprint, ban-safety | [Configuration](/v4/mtproto-configuration) |

<a name="installation"></a>
## Installation

The MTProto component is a LaraGram package. Once it is required via Composer, its service provider is auto-discovered — no manual registration is needed.

```shell
composer require laraxgram/mtproto
```

<a name="api-credentials"></a>
### API Credentials

Every MTProto client — user *or* bot — needs a Telegram **API ID** and **API Hash**. These are not the same as a bot token; they identify the *application*, and you obtain them once from [my.telegram.org](https://my.telegram.org) → *API development tools*.

Add them to your application's `.env` file:

```ini
API_ID=1234567
API_HASH=0123456789abcdef0123456789abcdef
```

> [!WARNING]
> Treat your API ID/Hash and session files like credentials. They identify your application to Telegram and, together with a session, grant access to the logged-in account. Never commit them to source control.

<a name="publishing-the-configuration"></a>
### Publishing the Configuration

Publish the configuration file to `config/mtproto.php` so you can tune it for your application:

```shell
php laragram vendor:publish --tag=mtproto-config
```

The published file is heavily commented and covers every option — sessions, transport, stores, rate limiting, device fingerprint, and more. See the [Configuration](/v4/mtproto-configuration) reference for a full walkthrough. Sensible defaults ship out of the box, so for a first run you only need the `API_ID` and `API_HASH` values above.

<a name="registering-listen-files"></a>
## Registering Listen Files

Like the rest of LaraGram, MTProto is driven by **listen files**. You register them in your application's `bootstrap/app.php` using the `withListener()` method. MTProto adds a dedicated `client:` slot for user/bot MTProto sessions, alongside the standard `bot:` slot:

```php
use LaraGram\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withListener(
        bot: [
            __DIR__."/../listens/bot.php" => "bot1",     // Bot API listener
            __DIR__."/../listens/mybot.php" => "bot",    // MTProto bot session
        ],
        client: [
            __DIR__."/../listens/client.php",            // MTProto user client
        ],
        commands: __DIR__."/../listens/commands.php",
    )
    ->withMiddleware(function ($middleware) {
        //
    })
    ->create();
```

<a name="the-client-listen-file"></a>
### The Client Listen File

Listen files registered under `client:` define the listeners for your **user** MTProto sessions. This is where you register handlers using the `Client` facade — exactly the way you register bot listeners with the `Bot` facade.

```php
// listens/client.php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onText('hello', function (ClientRequest $request) {
    $request->sendMessage(
        peer: $request->message->peer_id->user_id,
        message: 'hi 👋',
    );
});
```

<a name="binding-a-session-to-a-bot-listen-file"></a>
### Binding a Session to a Bot Listen File

The `bot:` slot accepts a `path => name` pair. When the name matches an MTProto **session** (see [multi-account sessions](/v4/mtproto-authentication#multi-account-sessions)), that listen file is served by an MTProto **bot** client rather than the HTTP Bot API. The listener code is written identically — with the `Client` facade and a `ClientRequest`:

```php
// listens/mybot.php  (bound as the "bot" MTProto session)
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onText('hello', function (ClientRequest $request) {
    $request->sendMessage(
        peer: $request->message->peer_id->user_id,
        message: 'hi from bot',
    );
});
```

<a name="your-first-client"></a>
## Your First Client

<a name="authenticating-a-session"></a>
### Authenticating a Session

Before a session can receive updates it must be authenticated once. This is fully interactive and handled by the `client:auth` command — you never construct a client yourself:

```shell
# User login (interactive phone → code → optional 2FA)
php laragram client:auth

# Bot login
php laragram client:auth --bot=123456:ABC-DEF...

# QR-code login (scan from your Telegram app)
php laragram client:auth --qr
```

The command creates a **session** on disk (default name: `default`) that stores the auth key so you never have to log in again. See [Authentication](/v4/mtproto-authentication) for the full flow, 2FA, and multi-account setups.

<a name="writing-a-listener"></a>
### Writing a Listener

Listeners look and behave like Bot listeners. The `Client` facade exposes the same expressive verbs (`onText`, `onCommand`, `onPhoto`, …) plus hundreds of MTProto-only update types. Each handler receives a `ClientRequest` describing the update:

```php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onCommand('start', function (ClientRequest $request) {
    $request->sendMessage(
        peer: $request->chatId(),
        message: 'Welcome! Send me *hello*.',
        parse_mode: 'markdown',
    );
});
```

The complete verb catalog, the `ClientRequest` object, and update scoping live in the [Listening](/v4/mtproto-listening) reference.

<a name="running-the-client"></a>
### Running the Client

In production the client runs **inside** the [Surge](/v4/surge) server — booting Surge starts the update pump automatically for every authorized session, with no extra wiring:

```shell
php laragram surge:start
```

For local development or a standalone process, you can run a session (or several) directly:

```shell
# Start the default session
php laragram client:start

# Start specific sessions
php laragram client:start --session=default,support

# Start every configured session
php laragram client:start --all
```

<a name="architecture-overview"></a>
## Architecture Overview

At a high level, an incoming Telegram update flows through the same shape as a Bot update, so everything you know from [Listening](/v4/listening) carries over:

<div class="content-list" markdown="1">

- **Session** — a named, authenticated connection to Telegram (an account or a bot). Stored on disk; recovered automatically on start.
- **Pump** — a single-reader coroutine that streams updates off the socket and dispatches each one, non-blocking, in its own coroutine. Runs inside Surge automatically.
- **`Client` facade / listen files** — where you register listeners for updates.
- **`ClientRequest`** — the object handed to every listener, exposing the update's fields and the high-level API for replying.
- **High-level API** — expressive methods (`sendMessage`, `sendPhoto`, `banChatMember`, …) that hide the underlying TL method calls.

</div>

Replies automatically route back to the **session that received the update**, so a multi-account setup "just works" — you never track which account a message belongs to.

<a name="where-to-go-next"></a>
## Where to Go Next

<div class="content-list" markdown="1">

- [Authentication](/v4/mtproto-authentication) — log in (phone/bot/QR/2FA), multi-account sessions, importing and encrypting sessions.
- [Listening](/v4/mtproto-listening) — every update verb, the `ClientRequest` object, scoping, groups, and middleware.
- [Requests](/v4/mtproto-requests) — sending messages, formatting, keyboards, and the namespaced API.
- [Chats & Channels](/v4/mtproto-chats) — managing groups, channels, participants, forums, and communities.
- [Media](/v4/mtproto-media) — uploading and downloading files, albums, and stories.
- [Features](/v4/mtproto-features) — secret chats, takeout, stars, reactions, and more.
- [Configuration](/v4/mtproto-configuration) — the full config reference and ban-safety guidance.

</div>
