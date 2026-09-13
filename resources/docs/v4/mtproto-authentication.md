# MTProto: Authentication

- [Introduction](#introduction)
- [Sessions](#sessions)
    - [What a Session Is](#what-a-session-is)
    - [Multi-Account Sessions](#multi-account-sessions)
- [Logging In](#logging-in)
    - [User Login (Phone)](#user-login-phone)
    - [Two-Factor Authentication](#two-factor-authentication)
    - [Signing Up](#signing-up)
    - [Bot Login](#bot-login)
    - [QR Code Login](#qr-code-login)
- [Managing Sessions](#managing-sessions)
    - [Listing Sessions](#listing-sessions)
    - [Logging Out](#logging-out)
- [Importing Sessions](#importing-sessions)
- [Encrypting Sessions at Rest](#encrypting-sessions-at-rest)

<a name="introduction"></a>
## Introduction

Before an MTProto client can send or receive anything, its **session** must be authenticated once. Authentication is fully interactive and handled by the `client:auth` Commander command — you never build a client, generate an auth key, or manage the login handshake yourself. Once a session is authenticated, its auth key is persisted to disk and reused on every subsequent run, so you only log in a single time.

```shell
php laragram client:auth
```

<a name="sessions"></a>
## Sessions

<a name="what-a-session-is"></a>
### What a Session Is

A **session** is a named, authenticated connection to Telegram — either a user account or a bot. Each session stores its auth key, server salt, current data center, and a cache of resolved peers. Sessions are stored in `storage/app/clients/sessions` by default (configurable via `CLIENT_SESSION_PATH`).

The default session is named `default`. Every command that operates on a session accepts a `--session` option to target a specific one:

```shell
php laragram client:auth --session=support
```

<a name="multi-account-sessions"></a>
### Multi-Account Sessions

You can run several accounts — any mix of user clients and bots — from a single process. Declare each one in the `sessions` array of `config/mtproto.php`. Each entry is a session name mapped to per-session overrides that are deep-merged over the global options:

```php
// config/mtproto.php
'sessions' => [
    'default'   => [],  // empty = use the global options as-is

    'support' => [
        'api_id'   => env('SUPPORT_API_ID'),
        'api_hash' => env('SUPPORT_API_HASH'),
    ],

    'announcer' => [
        'dc_id'  => 4,
        'device' => ['preset' => 'android'],
    ],
],
```

Anything you omit falls back to the global value. Listen files are bound to a session name in `bootstrap/app.php` (see [Registering Listen Files](/v4/mtproto#registering-listen-files)), and replies automatically route back to the session that received the update — you never have to track which account a message belongs to.

> [!NOTE]
> When a listen file is bound to a session name under the `bot:` slot, that file is served by an MTProto client instead of the HTTP Bot API. This lets a bot access MTProto-only capabilities while keeping bot semantics.

<a name="logging-in"></a>
## Logging In

Running `client:auth` with no options starts an interactive flow and asks which method you want to use — `account` (phone), `bot`, or `qr`. You can skip the prompt by passing the method up front.

<a name="user-login-phone"></a>
### User Login (Phone)

The default flow logs in as a user account. You are prompted for your phone number (in international format), Telegram sends a login code to your other devices, and you enter it:

```shell
php laragram client:auth --session=default
```

```
 Session name: default
 How do you want to log in? account
 Phone number: +12025550123
 Enter the code you received: 12345
 ✓ Logged in as John Doe
```

The session is now authenticated and saved. You will not be asked again unless you [log out](#logging-out) or delete the session.

<a name="two-factor-authentication"></a>
### Two-Factor Authentication

If the account has a Two-Factor Authentication (cloud) password enabled, the flow automatically detects it and prompts for the password after the code step. Nothing extra is required from you — enter the password when asked and login continues.

<a name="signing-up"></a>
### Signing Up

If the phone number is not yet registered on Telegram, `client:auth` offers to create a new account, prompting for a first name (and optional last name) to complete sign-up.

<a name="bot-login"></a>
### Bot Login

To authenticate a bot, pass its token from [@BotFather](https://t.me/BotFather):

```shell
php laragram client:auth --session=mybot --bot=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
```

Or choose `bot` in the interactive menu and paste the token when prompted.

> [!WARNING]
> A bot's auth key is tied to that bot token. Do not run the **same** token over the Bot API webhook and MTProto simultaneously — keep the MTProto session single-instance. If a session already holds a different authorization, `client:auth` will offer to reset it onto a fresh key.

<a name="qr-code-login"></a>
### QR Code Login

You can log in by scanning a QR code from an already-authorized Telegram app (Settings → Devices → Link Desktop Device). Pass `--qr`:

```shell
php laragram client:auth --qr
```

A QR code is rendered in your terminal. Scan it with your phone and confirm the login. If the account has 2FA, you are prompted for the password to finish. The QR refreshes automatically until it is scanned.

<a name="managing-sessions"></a>
## Managing Sessions

<a name="listing-sessions"></a>
### Listing Sessions

List the sessions on disk, along with their data center, whether an auth key is present, whether the files are encrypted, and the cached peer count:

```shell
php laragram session:list
```

<a name="logging-out"></a>
### Logging Out

Logging out invalidates the auth key on Telegram's side and removes the session. This is exposed on the client via the high-level API — for example from a listener or a custom command using the `Client` facade:

```php
use LaraGram\MTProto\Facades\Client;

Client::session('support')->logOut();
```

To simply start fresh without contacting Telegram, delete the session files from the sessions directory and re-run `client:auth`.

<a name="importing-sessions"></a>
## Importing Sessions

If you already have a session from another MTProto library, you can import it into LaraGram format instead of logging in again. Pyrogram, Telethon, and MadelineProto are supported:

```shell
# Auto-detect the source format
php laragram session:import path/to/account.session --session=default

# From a Pyrogram/Telethon session string
php laragram session:import "1BVtsOK8B..." --from=pyrogram --session=default

# From a MadelineProto session folder
php laragram session:import path/to/madeline/session --from=madeline --dc=2 --session=default
```

Useful options:

<div class="content-list" markdown="1">

- `--from` — source format: `pyrogram`, `telethon`, `madeline`, or `auto` (default).
- `--session` — target LaraGram session name (prompted if omitted).
- `--dc` — home data center id, used to disambiguate MadelineProto sessions.
- `--force` — overwrite an existing session that already holds an auth key.
- `--dry-run` — decode and report the source session without writing anything.

</div>

<a name="encrypting-sessions-at-rest"></a>
## Encrypting Sessions at Rest

Session files contain the auth key and cached peer access hashes — everything needed to act as the account. For servers where the disk is not fully trusted, you can encrypt session files at rest:

```shell
# Encrypt every session in the directory (a key is generated and printed)
php laragram session:encrypt

# Encrypt one session with your own key, then remove the plaintext originals
php laragram session:encrypt --session=default --key=base64:... --prune
```

Decrypt them again when needed:

```shell
php laragram session:decrypt --session=default --key=base64:...
```

Store the encryption key outside the repository (for example in a secret manager or an environment variable), never alongside the encrypted files.

Next, learn how to react to updates in the [Listening](/v4/mtproto-listening) reference.
