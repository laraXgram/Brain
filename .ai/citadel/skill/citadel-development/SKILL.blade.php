---
name: citadel-development
description: "Use this skill for API authentication with laraxgram/citadel in LaraGram. Trigger when issuing or checking personal access tokens, protecting routes with auth:citadel or token abilities, authenticating a first-party SPA or a Luna Telegram Mini App, running an OAuth2 server (authorization code + PKCE, client credentials, device flow, refresh tokens), signing users in with Telegram (Mini App init data, Login Widget), approving devices or issuing tokens from the bot, or securing MCP servers and AI agents with tokens. Covers: HasApiTokens, createToken, tokenCan, Citadel::tokensCan, citadel:* commands, abilities middleware, Citadel::botListeners, and Citadel::actingAs."
license: MIT
metadata:
  author: laraxgram
---
@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram Citadel

`laraxgram/citadel` is LaraGram's API authentication package: Laravel Sanctum (personal access tokens, SPA sessions) and Laravel Passport (an OAuth2 server) merged into one guard and one trait, with Telegram sign-in on top. Everything lives under `LaraGram\Citadel`; there is no `LaraGram\Sanctum` or `LaraGram\Passport`.

## Setup

```bash
{{ $assist->commanderCommand('citadel:install') }}          # config + migrations (+ OAuth keys when asked)
{{ $assist->commanderCommand('citadel:keys') }}             # RSA keys, only needed for OAuth
```

@brainsnippet("User Model", "php")
use LaraGram\Citadel\HasApiTokens;
use LaraGram\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens;
}
@endbrainsnippet

Citadel registers the `citadel` guard itself (driver `citadel`, any token owner). Protect HTTP routes with `auth:citadel`; never configure a `sanctum` or `passport` guard.

## One Guard, Four Sources

`auth:citadel` authenticates a request with the first source that knows the user:

1. the stateful guards in `config('citadel.guard')` (`web` sessions; add Luna's `telegram` guard for Mini Apps),
2. a personal access token (`Authorization: Bearer 12|abc...`),
3. an OAuth access token (a JWT in the same header),
4. the encrypted `laragram_token` cookie from `CreateFreshApiToken`.

`$request->user()->currentAccessToken()` is a `PersonalAccessToken`, an `AccessToken` (OAuth) or a `TransientToken` (session/cookie, which can do everything).

## Personal Access Tokens

@brainsnippet("Issue And Check Tokens", "php")
$token = $user->createToken('cli', ['orders:read'], now()->addMonth());

return $token->plainTextToken; // shown once

$request->user()->tokenCan('orders:read');
$user->tokens()->where('name', 'cli')->delete(); // revoke
@endbrainsnippet

## Abilities and Scopes

Abilities (personal tokens) and scopes (OAuth tokens) are the same thing to Citadel: `tokenCan()` and the middleware check both.

@brainsnippet("Ability Middleware", "php")
use LaraGram\Citadel\Http\Middleware\CheckAbilities;

Route::get('/orders', OrderController::class)->middleware(['auth:citadel', 'abilities:orders:read,orders:write']);
Route::get('/reports', ReportController::class)->middleware(['auth:citadel', 'ability:reports,admin']);
Route::get('/sync', SyncController::class)->middleware(CheckAbilities::using('orders:read'));
@endbrainsnippet

- `abilities` requires all, `ability` requires any; `#[AuthorizeToken([...])]` does the same on controllers.
- OAuth scopes must be defined with `Citadel::tokensCan([...])` in a service provider's `boot()`.
- `Citadel::$withInheritedScopes = true` makes `orders` grant `orders:read`.
- Machine-to-machine (client credentials) routes use `EnsureClientIsResourceOwner::using('scope')` without `auth:citadel`.

## OAuth2 Server

Only needed for third-party apps, MCP clients and devices; plain API tokens do not need it.

```bash
{{ $assist->commanderCommand('citadel:client') }}              # authorization code (+ PKCE with --public)
{{ $assist->commanderCommand('citadel:client --client') }}     # client credentials
{{ $assist->commanderCommand('citadel:client --device') }}     # device flow (CLIs, MCP clients)
{{ $assist->commanderCommand('citadel:client --telegram') }}   # Mini App / Login Widget token exchange
```

Routes live under `/oauth` and are named `citadel.*` (`citadel.token`, `citadel.authorizations.authorize`, `citadel.device.code`, `citadel.token.revoke`). Public clients must use PKCE. Refresh tokens rotate. Schedule `{{ $assist->commanderCommand('citadel:purge') }}` daily.

## Telegram

- **Mini App → token**: POST `/oauth/token` with `grant_type=telegram_init_data`, `client_id` and `init_data` (the raw `Telegram.WebApp.initData`).
- **Login Widget → token**: `grant_type=telegram_login` with the widget fields. `/oauth/telegram/login` signs users in on the web (for example before the consent screen).
- **Bot**: call `Citadel::botListeners()` in `listens/bot.php` (before any catch-all `/start` listener) to let users approve devices in the bot (`/start device-ABCDEFGH`, `/device ABCD-EFGH`) and issue tokens with `/token`.
- Users are found by Telegram id through the user provider; register unknown users with `Citadel::resolveTelegramUsersUsing(fn ($identity, $provider) => ...)`.

## MCP and AI

- MCP web servers: `Mcp::web(...)->middleware('auth:citadel')`; tool access with `#[RequiresAbility('...')]`.
- MCP clients that speak OAuth use the authorization code (PKCE) or device flow; the device response carries `verification_uri_telegram` so users approve in the bot.
- Gate agent tools on the caller's token with `Citadel::tokenCan('ability')`, which fails closed when there is no token.

## Testing

@brainsnippet("Acting As", "php")
use LaraGram\Citadel\Citadel;

Citadel::actingAs($user, ['orders:read']);
Citadel::actingAsClient($client, ['reports']);
@endbrainsnippet

## Common Pitfalls

- Missing `HasApiTokens` on the user model: tokens never authenticate.
- Using `auth:sanctum`, `auth:api` with a `passport` driver, or `Laravel\...` classes; use `auth:citadel` and `LaraGram\Citadel\...`.
- Forgetting `citadel:keys` before using OAuth (personal tokens do not need keys).
- OAuth scopes that were never registered with `Citadel::tokensCan()` are rejected as `invalid_scope`.
- In LaraGram the HTTP request is `request()` / `app('http.request')`; `app('request')` is the bot update.
