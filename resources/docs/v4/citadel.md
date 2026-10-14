# LaraGram Citadel

- [Introduction](#introduction)
    - [How It Works](#how-it-works)
    - [Do I Need OAuth?](#do-i-need-oauth)
- [Installation](#installation)
    - [Preparing the User Model](#preparing-the-user-model)
    - [Deploying Citadel](#deploying-citadel)
- [Configuration](#configuration)
    - [Token Lifetimes](#token-lifetimes)
    - [Overriding Default Models](#overriding-default-models)
    - [Overriding Routes](#overriding-routes)
- [Personal Access Tokens](#personal-access-tokens)
    - [Issuing Tokens](#issuing-tokens)
    - [Token Abilities](#token-abilities)
    - [Revoking Tokens](#revoking-tokens)
    - [Token Expiration](#token-expiration)
    - [Issuing Tokens From the Bot](#issuing-tokens-from-the-bot)
- [Protecting Routes](#protecting-routes)
    - [Checking Abilities](#checking-abilities)
    - [Passing the Access Token](#passing-the-access-token)
- [SPA and Mini App Authentication](#spa-and-mini-app-authentication)
    - [First-Party SPAs](#first-party-spas)
    - [Telegram Mini Apps](#telegram-mini-apps)
    - [Consuming Your API With JavaScript](#consuming-your-api-with-javascript)
- [OAuth2 Server](#oauth2-server)
    - [Defining Scopes](#defining-scopes)
    - [Managing Clients](#managing-clients)
    - [Authorization Code Grant With PKCE](#authorization-code-grant)
    - [Refreshing Tokens](#refreshing-tokens)
    - [Revoking OAuth Tokens](#revoking-oauth-tokens)
    - [Client Credentials Grant](#client-credentials-grant)
    - [Device Authorization Grant](#device-authorization-grant)
    - [Password and Implicit Grants](#password-and-implicit-grants)
    - [Authorization Views](#authorization-views)
    - [Server Metadata](#server-metadata)
    - [Purging Tokens](#purging-tokens)
- [Telegram Sign-In](#telegram-sign-in)
    - [Mini App Init Data](#mini-app-init-data)
    - [Telegram Login Widget](#telegram-login-widget)
    - [Approving Devices in the Bot](#approving-devices-in-the-bot)
    - [Registering New Users](#registering-new-users)
- [MCP Servers and AI Agents](#mcp-servers-and-ai-agents)
- [Events](#events)
- [Testing](#testing)

<a name="introduction"></a>
## Introduction

[LaraGram Citadel](https://github.com/laraxgram/citadel) protects your application's HTTP API. It combines everything you would otherwise find in two separate packages, a lightweight token system and a full OAuth2 server, behind **one guard** (`citadel`) and **one trait** (`HasApiTokens`):

<div class="content-list" markdown="1">

- **Personal access tokens** for scripts, mobile apps, CLIs and MCP clients, with abilities and expiry.
- **Session authentication** for your own SPA, and **init data authentication** for Telegram Mini Apps built with [Luna](/v4/luna), with no tokens at all.
- **A complete OAuth2 server**: authorization code with PKCE, client credentials, rotating refresh tokens, the device flow, token revocation and server metadata.
- **Telegram sign-in**: exchange Mini App init data or Login Widget data for OAuth tokens, approve devices inside your bot, and let users issue tokens with a bot command.

</div>

Citadel has no third-party dependencies: its OAuth2 engine, JWT signing and key generation are built on PHP's own `openssl` and `sodium` extensions.

<a name="how-it-works"></a>
### How It Works

When a route uses the `auth:citadel` middleware, Citadel authenticates the request with the first source that knows the user:

1. **The stateful guards** listed in the `guard` option of `config/citadel.php`: a `web` session for your SPA, or Luna's `telegram` guard for Mini Apps. The user receives a "transient" token that is allowed to do everything.
2. **A personal access token** in the `Authorization` header (`Bearer 12|a3c...`).
3. **An OAuth access token** (a signed JWT) in the same header.
4. **The API token cookie** set by the `CreateFreshApiToken` middleware.

Whichever source matched, `$request->user()` is the user and `$request->user()->currentAccessToken()` is the token that authenticated it, so abilities are checked the same way everywhere.

<a name="do-i-need-oauth"></a>
### Do I Need OAuth?

Most applications don't. Personal access tokens, sessions and Mini App init data cover your own frontends, scripts and integrations. Enable the OAuth2 server when **other parties** need access to your users' accounts: third-party applications, MCP clients that only speak OAuth, smart devices and CLIs that sign in with the device flow, or machine-to-machine integrations. You can start with tokens and turn OAuth on later; both live in the same guard.

<a name="installation"></a>
## Installation

Install Citadel with Composer, then run the `citadel:install` command. It publishes the configuration file and migrations, offers to generate the OAuth keys, and runs the migrations:

```shell
composer require laraxgram/citadel

php laragram citadel:install
```

The OAuth2 server signs access tokens with an RSA key pair. Personal access tokens do not need it. If you skipped the keys during installation, generate them whenever you enable OAuth:

```shell
php laragram citadel:keys
```

<a name="preparing-the-user-model"></a>
### Preparing the User Model

Add the `LaraGram\Citadel\HasApiTokens` trait to your user model:

```php
<?php

namespace App\Models;

use LaraGram\Citadel\HasApiTokens;
use LaraGram\Database\Eloquent\Factories\HasFactory;
use LaraGram\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;
}
```

Citadel registers the `citadel` guard for you, so there is nothing to add to `config/auth.php`. To restrict the guard to a single user provider, define it yourself:

```php
'guards' => [
    'citadel' => [
        'driver' => 'citadel',
        'provider' => 'users',
    ],
],
```

OAuth tokens identify users by their auth identifier, which for LaraGram users is the Telegram user id (`user_id`).

<a name="deploying-citadel"></a>
### Deploying Citadel

The keys are stored in `storage/oauth-private.key` and `storage/oauth-public.key`. They should not be committed to source control; run `citadel:keys` on your server, or load them from environment variables instead:

```ini
CITADEL_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
<private key here>
-----END RSA PRIVATE KEY-----"

CITADEL_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
<public key here>
-----END PUBLIC KEY-----"
```

You may also load the key files from another directory with `Citadel::loadKeysFrom(__DIR__.'/../secrets/oauth')` in the `boot` method of your `AppServiceProvider`.

<a name="configuration"></a>
## Configuration

<a name="token-lifetimes"></a>
### Token Lifetimes

By default, OAuth access and refresh tokens live for one year. Shorten them in the `boot` method of your `AppServiceProvider`:

```php
use LaraGram\Citadel\Citadel;

public function boot(): void
{
    Citadel::tokensExpireIn(now()->addDays(15));
    Citadel::refreshTokensExpireIn(now()->addDays(30));
    Citadel::clientCredentialsTokensExpireIn(now()->addHours(6));
}
```

Personal access tokens never expire unless you give them an expiry date or set the `expiration` option, see [Token Expiration](#token-expiration).

<a name="overriding-default-models"></a>
### Overriding Default Models

Every model Citadel uses may be extended and swapped:

```php
use App\Models\Citadel\Client;
use App\Models\Citadel\PersonalAccessToken;
use LaraGram\Citadel\Citadel;

public function boot(): void
{
    Citadel::usePersonalAccessTokenModel(PersonalAccessToken::class);
    Citadel::useClientModel(Client::class);
    Citadel::useTokenModel(Token::class);
    Citadel::useRefreshTokenModel(RefreshToken::class);
    Citadel::useAuthCodeModel(AuthCode::class);
    Citadel::useDeviceCodeModel(DeviceCode::class);
}
```

<a name="overriding-routes"></a>
### Overriding Routes

The OAuth routes are registered under the `/oauth` prefix (the `oauth.path` option) with names starting with `citadel.`. To define them yourself, call `Citadel::ignoreRoutes()` in the `register` method of your `AppServiceProvider` and copy the routes from `vendor/laraxgram/citadel/routes/web.php` into your own routes file.

<a name="personal-access-tokens"></a>
## Personal Access Tokens

<a name="issuing-tokens"></a>
### Issuing Tokens

Use the `createToken` method to issue a token. The plain-text token is only available on the returned object, right after it is created, so show it to the user immediately:

```php
use LaraGram\Http\Request;

Route::post('/tokens/create', function (Request $request) {
    $token = $request->user()->createToken($request->token_name);

    return ['token' => $token->plainTextToken];
});
```

Tokens are stored as SHA-256 hashes and have the format `{id}|{secret}`. Set the `token_prefix` option (for example `myapp_`) to make leaked tokens recognizable by secret scanners.

Access all of a user's tokens through the `tokens` relationship:

```php
foreach ($user->tokens as $token) {
    // $token->name, $token->abilities, $token->last_used_at...
}
```

<a name="token-abilities"></a>
### Token Abilities

Give a token "abilities" to limit what it can do. Abilities are plain strings; the default, `*`, allows everything:

```php
return $user->createToken('orders-sync', ['orders:read', 'orders:write'])->plainTextToken;
```

Check an ability of the authenticated request's token with `tokenCan`:

```php
if ($request->user()->tokenCan('orders:write')) {
    // ...
}
```

Abilities and OAuth scopes are the same thing to Citadel, so `tokenCan` and the [ability middleware](#checking-abilities) work for every token type. Users authenticated by a session or Mini App init data hold a transient token, and `tokenCan` always returns `true` for them; authorize their actions with [policies](/v4/authorization#creating-policies) as usual.

To let a broader ability grant narrower ones (`orders` grants `orders:read` and `orders:write:refunds`), enable inherited scopes:

```php
Citadel::$withInheritedScopes = true;
```

<a name="revoking-tokens"></a>
### Revoking Tokens

Revoke tokens by deleting them:

```php
// Revoke all tokens...
$user->tokens()->delete();

// Revoke the token that was used to authenticate the current request...
$request->user()->currentAccessToken()->delete();

// Revoke a specific token...
$user->tokens()->where('id', $tokenId)->delete();
```

<a name="token-expiration"></a>
### Token Expiration

Give a single token an expiry date with the third argument of `createToken`:

```php
return $user->createToken('ci', ['*'], now()->addWeek())->plainTextToken;
```

Or expire every personal access token after a number of minutes with the `expiration` option of `config/citadel.php`. Expired tokens are removed by the [`citadel:purge`](#purging-tokens) command.

<a name="issuing-tokens-from-the-bot"></a>
### Issuing Tokens From the Bot

Your users already proved who they are by talking to your bot, so the bot can hand out tokens. Register Citadel's bot listeners in `listens/bot.php`:

```php
use LaraGram\Citadel\Citadel;

Citadel::botListeners(tokenCommand: 'token', abilities: ['orders:read'], expiresInMinutes: 60 * 24 * 30);
```

In a private chat, `/token` issues a new personal access token (protected from forwarding) and `/token revoke` deletes the tokens issued this way. The account is resolved through the `bot` guard. The same call also registers the [device approval](#approving-devices-in-the-bot) listeners; pass `tokenCommand: null` to skip the token command.

> [!NOTE]
> Register Citadel's listeners before any catch-all `/start` listener, so `/start device-...` deep links reach Citadel.

<a name="protecting-routes"></a>
## Protecting Routes

Attach the `auth:citadel` middleware to every route that requires authentication:

```php
use LaraGram\Http\Request;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:citadel');
```

<a name="checking-abilities"></a>
### Checking Abilities

Citadel registers two route middleware aliases. `abilities` requires **all** of the listed abilities, `ability` requires **at least one**:

```php
Route::get('/orders', function () {
    // The token has both "orders:read" and "orders:write"...
})->middleware(['auth:citadel', 'abilities:orders:read,orders:write']);

Route::get('/reports', function () {
    // The token has "reports" or "admin"...
})->middleware(['auth:citadel', 'ability:reports,admin']);
```

A failed check throws `LaraGram\Citadel\Exceptions\MissingAbilityException`, rendered as a `403` response. You may also build the middleware from its class, or use the `AuthorizeToken` attribute on a controller:

```php
use LaraGram\Citadel\Attributes\AuthorizeToken;
use LaraGram\Citadel\Http\Middleware\CheckAbilities;

Route::get('/orders', ListOrders::class)->middleware(['auth:citadel', CheckAbilities::using('orders:read')]);

#[AuthorizeToken(['orders:read'])]
class OrderController
{
    #[AuthorizeToken(['orders:write', 'admin'], anyAbility: true)]
    public function store() { /* ... */ }
}
```

<a name="passing-the-access-token"></a>
### Passing the Access Token

Clients send their token as a bearer token:

```php
$response = Http::withToken($token)->acceptJson()->get('https://example.com/api/user');
```

To read the token from somewhere else, register a callback:

```php
Citadel::getAccessTokenFromRequestUsing(fn (Request $request) => $request->header('X-Api-Token'));
```

<a name="spa-and-mini-app-authentication"></a>
## SPA and Mini App Authentication

<a name="first-party-spas"></a>
### First-Party SPAs

Your own SPA should not handle tokens at all. When it runs on a domain listed in the `stateful` option, Citadel starts a regular cookie session for its API requests, with CSRF protection. Enable the stateful middleware for the `api` group in `bootstrap/app.php`:

```php
use LaraGram\Citadel\Http\Middleware\EnsureFrontendRequestsAreStateful;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        EnsureFrontendRequestsAreStateful::class,
    ]);
})
```

Then configure the domains of your frontend with the `CITADEL_STATEFUL_DOMAINS` environment variable, and make sure your [CORS configuration](/v4/routing#cors) allows credentials. The SPA first requests `/citadel/csrf-cookie` to initialize CSRF protection, then logs in through your normal `web` login route; after that, every API request is authenticated by the session.

<a name="telegram-mini-apps"></a>
### Telegram Mini Apps

Mini Apps built with [Luna](/v4/luna) are already authenticated: Luna's `telegram` guard verifies the init data Telegram signs for every launch. Add that guard to Citadel's `guard` option and your API routes accept Mini App requests directly:

```php
// config/citadel.php
'guard' => ['web', 'telegram'],
```

```php
Route::middleware(['telegram', 'auth:citadel'])->group(function () {
    Route::get('/orders', ListOrders::class);
});
```

When the Mini App needs a token instead, for example to call another service or an MCP server, exchange its init data for OAuth tokens with the [`telegram_init_data` grant](#mini-app-init-data).

<a name="consuming-your-api-with-javascript"></a>
### Consuming Your API With JavaScript

Pages rendered by your own application can call the API without a session cookie on the API domain by adding the `CreateFreshApiToken` middleware to the `web` group. It attaches an encrypted, CSRF-bound `laragram_token` cookie to responses, which the `citadel` guard accepts:

```php
use LaraGram\Citadel\Http\Middleware\CreateFreshApiToken;

$middleware->web(append: [
    CreateFreshApiToken::class,
]);
```

<a name="oauth2-server"></a>
## OAuth2 Server

<a name="defining-scopes"></a>
### Defining Scopes

OAuth clients request "scopes". Define the scopes of your application in the `boot` method of your `AppServiceProvider`; requests for other scopes fail with `invalid_scope`:

```php
use LaraGram\Citadel\Citadel;

Citadel::tokensCan([
    'orders:read' => 'View your orders',
    'orders:write' => 'Place and cancel orders',
    'profile' => 'Read your Telegram profile',
]);

Citadel::defaultScopes(['profile']);
```

The descriptions are shown on the consent screen and in the bot. A client may be limited to a subset of scopes with its `scopes` column; `null` allows them all.

<a name="managing-clients"></a>
### Managing Clients

Create clients with the `citadel:client` command. Confidential clients receive a secret, which is hashed and shown only once:

```shell
# Authorization code (a web application with a backend)...
php laragram citadel:client

# Authorization code with PKCE (an SPA, mobile app or MCP client)...
php laragram citadel:client --public

# Client credentials (machine-to-machine)...
php laragram citadel:client --client

# Device authorization (CLIs, TVs, MCP clients)...
php laragram citadel:client --device

# Telegram (Mini App and Login Widget token exchange)...
php laragram citadel:client --telegram
```

Clients can also be created in code with `LaraGram\Citadel\ClientRepository`, for example to let your users register their own applications:

```php
use LaraGram\Citadel\ClientRepository;

$client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
    name: 'Shop Dashboard',
    redirectUris: ['https://dashboard.example.com/callback'],
    confidential: false,
    user: $user,
);
```

Clients created for a user are available through `$user->oauthApps`. Call `Citadel::withJsonApiRoutes()` to register JSON endpoints for managing clients, OAuth tokens and personal access tokens under `/oauth`.

<a name="authorization-code-grant"></a>
### Authorization Code Grant With PKCE

The consuming application redirects the user to your `/oauth/authorize` route. Public clients must send a PKCE code challenge:

```php
use LaraGram\Support\Str;

Route::get('/redirect', function (Request $request) {
    $request->session()->put('state', $state = Str::random(40));
    $request->session()->put('code_verifier', $verifier = Str::random(128));

    $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');

    return redirect('https://example.com/oauth/authorize?'.http_build_query([
        'client_id' => 'your-client-id',
        'redirect_uri' => 'https://third-party.com/callback',
        'response_type' => 'code',
        'scope' => 'orders:read',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));
});
```

If the user is not logged in, Citadel throws an `AuthenticationException` so your application redirects them to its login page; [Telegram Login](#telegram-login-widget) works well here. The user then approves or denies the request on the [consent screen](#authorization-views). On approval they return to the client with a `code`, which the client exchanges for tokens:

```php
$response = Http::asForm()->post('https://example.com/oauth/token', [
    'grant_type' => 'authorization_code',
    'client_id' => 'your-client-id',
    'redirect_uri' => 'https://third-party.com/callback',
    'code_verifier' => $request->session()->pull('code_verifier'),
    'code' => $request->code,
]);

return $response->json(); // token_type, expires_in, access_token, refresh_token
```

Loopback redirect URIs (`http://127.0.0.1/callback`) match on any port, as native apps and CLIs choose one at runtime. Requests with `prompt=none`, `prompt=login` and `prompt=consent` are supported, and consent is skipped when the user already granted the requested scopes, or when the client's `skipsAuthorization` method returns `true`.

<a name="refreshing-tokens"></a>
### Refreshing Tokens

```php
$response = Http::asForm()->post('https://example.com/oauth/token', [
    'grant_type' => 'refresh_token',
    'refresh_token' => 'the-refresh-token',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret', // Confidential clients only...
    'scope' => 'orders:read',               // Optional: a subset of the original scopes...
]);
```

Refresh tokens rotate: each use returns a new refresh token and revokes the previous one along with its access token. Set `Citadel::$revokeRefreshTokenAfterUse = false` to keep refresh tokens.

<a name="revoking-oauth-tokens"></a>
### Revoking OAuth Tokens

Clients revoke their access or refresh tokens through the `/oauth/revoke` endpoint ([RFC 7009](https://datatracker.ietf.org/doc/html/rfc7009)). Revoking either one also revokes the other:

```php
Http::asForm()->post('https://example.com/oauth/revoke', [
    'token' => $refreshToken,
    'client_id' => 'your-client-id',
]);
```

On the server, use the `TokenRepository` or the user's `oauthTokens` relationship:

```php
use LaraGram\Citadel\TokenRepository;

app(TokenRepository::class)->revoke($user->oauthTokens()->findOrFail($tokenId));
```

<a name="client-credentials-grant"></a>
### Client Credentials Grant

Machine-to-machine clients authenticate with their own credentials, without a user:

```php
$response = Http::asForm()->post('https://example.com/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'scope' => 'reports',
]);
```

These tokens have no user, so protect their routes with the `EnsureClientIsResourceOwner` middleware instead of `auth:citadel`:

```php
use LaraGram\Citadel\Http\Middleware\EnsureClientIsResourceOwner;

Route::get('/reports', ExportReports::class)->middleware(EnsureClientIsResourceOwner::using('reports'));
```

<a name="device-authorization-grant"></a>
### Device Authorization Grant

The device flow ([RFC 8628](https://datatracker.ietf.org/doc/html/rfc8628)) signs in devices that can't open a browser comfortably: CLIs, TVs and MCP clients. The device requests a code:

```php
$response = Http::asForm()->post('https://example.com/oauth/device/code', [
    'client_id' => 'your-client-id',
    'scope' => 'orders:read',
]);

// device_code, user_code ("BRPL-WTBH"), verification_uri, verification_uri_complete,
// verification_uri_telegram, expires_in, interval
```

It shows the user code and the verification URL, then polls the token endpoint every `interval` seconds while the user approves it:

```php
$response = Http::asForm()->post('https://example.com/oauth/token', [
    'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
    'client_id' => 'your-client-id',
    'device_code' => $deviceCode,
]);

// "authorization_pending" until the user decides, then the tokens
// (or "access_denied", "slow_down", "expired_token").
```

Users approve the device on the `/oauth/device` page, or [in your bot](#approving-devices-in-the-bot) through the `verification_uri_telegram` link. Call `Citadel::disableDeviceCodeGrant()` in the `register` method of a service provider to turn the flow off.

<a name="password-and-implicit-grants"></a>
### Password and Implicit Grants

The password and implicit grants are legacy flows that the OAuth 2.1 draft removes. They are disabled by default; enable them in the `boot` method of your `AppServiceProvider` only for existing clients that need them:

```php
Citadel::enablePasswordGrant();
Citadel::enableImplicitGrant();
```

The password grant looks users up by `email`; define `findForCitadel($username)` and `validateForCitadelPasswordGrant($password)` on your user model to customize it.

<a name="authorization-views"></a>
### Authorization Views

Citadel ships simple, responsive consent screens that follow the user's light or dark theme. Publish them to customize their look:

```shell
php laragram vendor:publish --tag=citadel-views
```

Or render your own views, including [Luna](/v4/luna) pages:

```php
use LaraGram\Citadel\Citadel;
use LaraGram\Luna\Luna;

Citadel::authorizationView('auth.oauth.authorize');
Citadel::deviceUserCodeView('auth.oauth.device.user-code');
Citadel::deviceAuthorizationView('auth.oauth.device.authorize');

// A closure receives the view parameters: client, user, scopes, request and authToken...
Citadel::authorizationView(fn (array $parameters) => Luna::render('OAuth/Authorize', [
    'client' => $parameters['client']->name,
    'scopes' => $parameters['scopes'],
    'authToken' => $parameters['authToken'],
]));
```

The forms post to the `citadel.authorizations.approve` route (and `DELETE` to `citadel.authorizations.deny`) with the `auth_token` field; the device screens use the `citadel.device.authorizations.*` routes.

<a name="server-metadata"></a>
### Server Metadata

Call `Citadel::withAuthorizationServerMetadata()` to publish your endpoints, scopes and grants at `/.well-known/oauth-authorization-server` ([RFC 8414](https://datatracker.ietf.org/doc/html/rfc8414)), so clients can configure themselves. Add your own values with `Citadel::extendAuthorizationServerMetadata(fn (array $metadata) => [...$metadata, 'service_documentation' => url('/docs')])`.

<a name="purging-tokens"></a>
### Purging Tokens

Revoked and expired OAuth tokens, codes and expired personal access tokens stay in the database until they are purged. Schedule the `citadel:purge` command:

```php
use LaraGram\Support\Facades\Schedule;

Schedule::command('citadel:purge')->daily();
```

It accepts `--revoked`, `--expired` and `--hours=168` (how long to keep expired items).

<a name="telegram-sign-in"></a>
## Telegram Sign-In

Telegram signs the user data it gives to your bot's Mini Apps and Login Widget with your bot token. Citadel verifies these signatures, so a Telegram identity can be turned into tokens or a web session without passwords. Create a client for it:

```shell
php laragram citadel:client --telegram
```

Payloads are verified with the token of the bot connection in the `telegram.connection` option (the default connection when empty) and are accepted for `telegram.auth_ttl` seconds after Telegram signed them.

<a name="mini-app-init-data"></a>
### Mini App Init Data

Send the raw `Telegram.WebApp.initData` string to the token endpoint:

```js
const response = await fetch('/oauth/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({
        grant_type: 'telegram_init_data',
        client_id: 'your-telegram-client-id',
        init_data: window.Telegram.WebApp.initData,
        scope: 'orders:read',
    }),
});

const { access_token, refresh_token } = await response.json();
```

Init data is verified with the bot token by default. If you can't share the bot token with the server that verifies it, set `CITADEL_TELEGRAM_SCHEME=signature` to verify Telegram's Ed25519 signature instead.

<a name="telegram-login-widget"></a>
### Telegram Login Widget

The [Telegram Login Widget](https://core.telegram.org/widgets/login) signs users in on regular websites. Citadel handles it in two ways:

<div class="content-list" markdown="1">

- **Web sessions**: `/oauth/telegram/login` shows the widget and logs the user in with the `telegram.login_guard` guard, then redirects to the page they wanted. Point guests there, and users can sign in before the consent screen with one tap: `$middleware->redirectGuestsTo(fn () => route('citadel.telegram.login'))`. Set your domain with BotFather's `/setdomain` command first. The same URL works as the target of a bot's `login_url` button.
- **Tokens**: post the widget's fields (`id`, `first_name`, `auth_date`, `hash`...) to the token endpoint with `grant_type=telegram_login`, or send them as a JSON string in `login_data`.

</div>

<a name="approving-devices-in-the-bot"></a>
### Approving Devices in the Bot

When the bot username is configured (`BOT_USERNAME`), every device authorization response includes a `verification_uri_telegram` link such as `https://t.me/your_bot?start=device-BRPLWTBH`. CLIs can print it or render it as a QR code. After you register `Citadel::botListeners()`, opening the link shows the device's name and requested scopes with **Approve** and **Deny** buttons, and the device receives its tokens as soon as the user taps one. Users may also type `/device BRPL-WTBH`. The device approval web page shows the same link.

Devices are only approved in private chats, by the account the `bot` guard resolves for the Telegram user. Set `CITADEL_TELEGRAM_DEVICE_APPROVAL=false` to leave the link out.

<a name="registering-new-users"></a>
### Registering New Users

By default, a Telegram identity must belong to an existing user (found by Telegram id through the user provider). To register users on their first sign-in, provide a resolver:

```php
use App\Models\User;
use LaraGram\Citadel\Citadel;
use LaraGram\Citadel\Telegram\TelegramIdentity;

Citadel::resolveTelegramUsersUsing(function (TelegramIdentity $identity, $provider) {
    return User::firstOrCreate(['user_id' => $identity->id], [
        'first_name' => $identity->firstName,
        'last_name' => $identity->lastName,
        'chat_id' => $identity->id,
    ]);
});
```

<a name="mcp-servers-and-ai-agents"></a>
## MCP Servers and AI Agents

Citadel is the authentication layer of [LaraGram MCP](/v4/mcp):

<div class="content-list" markdown="1">

- Protect web MCP servers with `Mcp::web('/mcp/shop', ShopServer::class)->middleware('auth:citadel')`. Clients send a personal access token, which users can issue with the bot's `/token` command.
- Restrict tools to tokens with an ability using MCP's `#[RequiresAbility('orders:write')]` attribute; it reads the same abilities and scopes as `tokenCan`.
- MCP clients that only speak OAuth use the authorization code flow with PKCE (registered dynamically by MCP's OAuth routes), or the device flow, where users approve the client right in your bot.

</div>

AI agents that act for a user can check the caller's token without knowing which guard authenticated it. `Citadel::tokenCan` fails closed when there is no token:

```php
use LaraGram\Citadel\Citadel;

if (! Citadel::tokenCan('orders:write', guard: 'citadel')) {
    return 'You are not allowed to place orders.';
}
```

For agents that call your API on their own (scheduled jobs, other services), use the [client credentials grant](#client-credentials-grant) with narrow scopes.

<a name="events"></a>
## Events

Citadel dispatches these events from the `LaraGram\Citadel\Events` namespace:

<div class="overflow-auto">

| Event | When |
| --- | --- |
| `TokenAuthenticated` | A personal access token authenticated a request. |
| `AccessTokenCreated` | An OAuth access token was issued. |
| `AccessTokenRevoked` | An OAuth access token was revoked. |
| `RefreshTokenCreated` | A refresh token was issued. |
| `DeviceAuthorizationRequested` | A device asked for a code. |
| `DeviceAuthorizationCompleted` | A user approved or denied a device, on the web or in the bot. |

</div>

For example, listen to `DeviceAuthorizationRequested` to message the user, or `AccessTokenCreated` to audit new grants.

<a name="testing"></a>
## Testing

Authenticate as a user with the given abilities, or as a client with the given scopes, without issuing real tokens:

```php
use App\Models\User;
use LaraGram\Citadel\Citadel;

Citadel::actingAs(User::factory()->create(), ['orders:read']);

Citadel::actingAsClient(Client::factory()->asClientCredentials()->create(), ['reports']);
```
