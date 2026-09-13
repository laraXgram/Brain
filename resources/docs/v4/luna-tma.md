# Luna: Telegram Mini Apps

<a name="introduction"></a>
## Introduction

A **Telegram Mini App (TMA)** is a web application that runs inside the Telegram client. It opens in a WebView, can read the user's Telegram identity, adopts Telegram's theme, and drives native UI like the back button and the MainButton. Luna is built to make Mini Apps first-class: a Mini App is a [Luna SPA](/v4/luna) that additionally speaks the Telegram WebApp protocol.

This chapter covers the Mini App essentials — validating who the user is, sharing that identity with the frontend, and wiring the reactive theme, viewport, and native buttons. For the full device-feature surface (CloudStorage, biometrics, location, sensors, popups, sharing, invoices), see [Telegram Features](/v4/luna-tma-features).

> [!NOTE]
> Everything in the SPA chapters ([Pages](/v4/luna-pages), [Routing](/v4/luna-routing), [Forms](/v4/luna-forms), [Frontend](/v4/luna-frontend)) applies to Mini Apps unchanged. This chapter only adds the Telegram layer.

<a name="how-a-mini-app-works"></a>
### How a Mini App Works

When Telegram launches your Mini App it appends signed **init data** — a query string containing the user, chat, and a cryptographic hash — to the WebView URL. Your job:

<div class="content-list" markdown="1">

1. **Validate the init data server-side** before trusting any identity. Luna's `telegram` middleware does this on every request, failing closed on tampered or expired data.
2. **Share the validated user** with the frontend as a prop, so the client never has to trust the unsigned `initDataUnsafe`.
3. **Bootstrap the WebApp SDK** on the client: signal readiness, expand the viewport, and wire the reactive theme/viewport store and the native back button.

</div>

<a name="configuration"></a>
## Configuration

Mini App options live under the `telegram` key of `config/luna.php`. The one required value is the bot token, which must stay server-side:

```php
// config/luna.php
'telegram' => [
    'bot_token' => env('BOT_TOKEN'),
    'validate' => (bool) env('LUNA_TG_VALIDATE', true),
    'scheme' => env('LUNA_TG_SCHEME', 'hmac'),   // 'hmac' | 'signature' | 'both'
    'auth_ttl' => (int) env('LUNA_TG_AUTH_TTL', 86400),
    'init_data_header' => env('LUNA_TG_INIT_DATA_HEADER', 'X-Telegram-Init-Data'),
    'share_props' => (bool) env('LUNA_TG_SHARE_PROPS', true),
    'sdk_url' => env('LUNA_TG_SDK_URL', 'https://telegram.org/js/telegram-web-app.js'),
    // ...
],
```

Set the bot token in your `.env`:

```ini
BOT_TOKEN=123456:AA...your-bot-token
```

| Option | Purpose |
|--------|---------|
| `bot_token` | Validates init data via HMAC-SHA256. **Never expose it to the frontend bundle.** |
| `validate` | Master switch. Disable only for local testing — never in production. |
| `scheme` | `hmac` (default), Ed25519 `signature`, or `both`. |
| `auth_ttl` | Max age (seconds) of init data before it's treated as replayed/expired. `0` disables the check. |
| `share_props` | Auto-share the validated context as a `telegram` prop on every response. |
| `sdk_url` | The `telegram-web-app.js` script injected by `@telegramWebApp`. |
| `public_keys` / `test_env` | Ed25519 keys for the `signature` scheme; `test_env` selects the test key. |

> [!WARNING]
> The bot token is a secret. It is used server-side to validate the HMAC of every request. If it reaches the browser bundle, anyone can forge init data. Keep it in `.env`, never in a shared prop, and never in `resources/js`.

<a name="the-root-template"></a>
## The Root Template

A Mini App's [root Blade template](/v4/luna#root-template) adds the `@telegramWebApp` directive, which injects `telegram-web-app.js` from the configured `sdk_url`. It must load **before** your app bundle so `window.Telegram.WebApp` exists when the app boots:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    {{-- Injects telegram-web-app.js from config('luna.telegram.sdk_url'). --}}
    @telegramWebApp

    @lunaHead

    {{-- Theme the shell from Telegram's palette; the JS layer keeps these live. --}}
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            background: var(--tg-theme-bg-color, #fff);
            color: var(--tg-theme-text-color, #000);
            padding:
                var(--tg-safe-area-inset-top, 0)
                var(--tg-safe-area-inset-right, 0)
                var(--tg-safe-area-inset-bottom, 0)
                var(--tg-safe-area-inset-left, 0);
        }
    </style>

    @vite(['resources/js/app.tsx'])
</head>
<body>
    @luna
</body>
</html>
```

<a name="authentication"></a>
## Authenticating the Request

<a name="the-telegram-middleware"></a>
### The `telegram` Middleware

Apply the `telegram` middleware to every Mini App route. It reads the init data (from the `X-Telegram-Init-Data` header on visits, or the `tgWebAppData` parameter on the initial load), validates it, and populates the request-scoped Telegram context — **failing closed** on tampered or expired data:

```php
use LaraGram\Support\Facades\Route;

Route::middleware('telegram')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update']);
});
```

Two modes:

<div class="content-list" markdown="1">

- **`telegram`** (required) — reject any request without valid init data (`401`).
- **`telegram:optional`** — allow anonymous access when init data is absent, but still reject *tampered* data (`403`). Use for pages that work both inside and outside Telegram.

</div>

In both modes, tampered data (`403`) and expired data (`401`) are always rejected — there is no way to opt out of the security check while keeping the middleware.

<a name="reading-the-user"></a>
### Reading the Validated User

Inside a `telegram`-protected route, read the trusted, validated user with the `tg_user()` helper or the `telegram()` context:

```php
public function show()
{
    $user = tg_user(); // WebAppUser|null

    return Luna::render('Profile', [
        'displayName' => $user?->firstName ?? 'Guest',
    ]);
}
```

The `WebAppUser` exposes `id`, `firstName`, `lastName`, `username`, `languageCode`, `isPremium`, `photoUrl`, `fullName()`, and the raw payload. The full context (`telegram()`) also offers `chat()`, `queryId()`, and `startParam()`:

```php
telegram()->user();       // WebAppUser|null
telegram()->chat();       // WebAppChat|null
telegram()->startParam(); // deep-link start_param
telegram()->queryId();    // one-time query id (for answerQuery)
telegram()->has();        // was the request authenticated?
```

> [!WARNING]
> Always key your database writes to `tg_user()->id`, never to an id sent in the request body. The client can put any number in a form field; only the middleware-validated id is trustworthy.

<a name="the-auth-guard"></a>
### The `telegram` Auth Guard

Luna registers a `telegram` [authentication guard](/v4/authentication) that resolves your application user from the validated Telegram id. Configure it in `config/auth.php`:

```php
'guards' => [
    'telegram' => [
        'driver' => 'telegram',
        'provider' => 'users',
    ],
],
```

Now the standard auth APIs resolve the Telegram user against your `users` table:

```php
auth('telegram')->user();   // Your App\Models\User, matched from the Telegram id.
auth('telegram')->check();
```

Point the guard's user provider at a column that stores the Telegram id (e.g. `telegram_id`) so the validated identity maps to your own user records.

<a name="shared-context"></a>
## The Shared Telegram Context

When `telegram.share_props` is enabled (the default), the middleware automatically [shares](/v4/luna-pages#shared-data) a `telegram` prop on every response, carrying the **server-validated** context:

```php
// Shared automatically — you don't write this:
Luna::share('telegram', [
    'user' => $initData->user?->toArray(),
    'chat' => $initData->chat?->toArray(),
    'startParam' => $initData->startParam,
    'chatType' => $initData->chatType,
    'chatInstance' => $initData->chatInstance,
]);
```

> [!NOTE]
> The one-time `queryId` is deliberately **not** shared — it's a server-side secret used to answer Web App queries. Everything shared here is safe to expose to the client.

On the client, read this trusted context with the adapter's Telegram bindings — prefer these over the SDK's unsigned `initDataUnsafe`:

```jsx
// React
import { useTelegramUser, useTelegramSharedContext } from '@laraxgram/react'

const user = useTelegramUser()          // server-validated WebAppUser | undefined
const ctx = useTelegramSharedContext()  // { user, chat, startParam, … }
```

```js
// Vue — reactive refs
import { useTelegramUser } from '@laraxgram/vue3'
const user = useTelegramUser()  // ComputedRef<WebAppUser | undefined>
```

```js
// Svelte
import { getTelegramUser } from '@laraxgram/svelte'
const user = getTelegramUser()
```

<a name="bootstrapping"></a>
## Bootstrapping the Client

Call `bootstrapTelegram()` once, before mounting your app. It signals readiness, expands the viewport, wires the reactive theme/viewport CSS variables, and syncs the native back button with browser history. It is a **no-op outside Telegram**, so it's always safe to call — even in a browser during development:

```tsx
import { bootstrapTelegram } from '@laraxgram/luna'
import { createLunaApp } from '@laraxgram/react'
import { createRoot } from 'react-dom/client'

// WebApp.ready() + expand() + reactive CSS vars + BackButton↔history sync.
bootstrapTelegram()

createLunaApp({
    pages: './Pages',
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
})
```

Bootstrap options:

| Option | Default | Effect |
|--------|---------|--------|
| `ready` | `true` | Call `WebApp.ready()`. |
| `expand` | `true` | Call `WebApp.expand()` to fill the viewport. |
| `cssVariables` | `true` | Mirror theme/viewport/safe-area onto `--tg-*` CSS vars, kept reactive. |
| `backButton` | `true` | Sync the native BackButton with Luna history (`history.back()` on tap). |
| `closingConfirmation` | `false` | Enable the "are you sure?" close dialog globally. |
| `disableVerticalSwipes` | `false` | Disable swipe-to-close. |

<a name="theme-and-viewport"></a>
## Reactive Theme & Viewport

`bootstrapTelegram()` writes Telegram's palette, viewport height, and safe-area insets to `--tg-*` CSS variables and keeps them live as the user switches theme or the keyboard opens. Style directly against them — no JavaScript needed:

```css
.card {
    background: var(--tg-theme-secondary-bg-color);
    color: var(--tg-theme-text-color);
}
.hint { color: var(--tg-theme-hint-color); }
```

When you need the values in JavaScript, read the reactive store via the adapter hook. It updates on every theme/viewport change:

```jsx
// React
import { useTelegram, useTelegramTheme, useTelegramViewport } from '@laraxgram/react'

function Header() {
    const tg = useTelegram()
    // tg.colorScheme, tg.themeParams, tg.viewportHeight,
    // tg.viewportStableHeight, tg.isExpanded, tg.isFullscreen,
    // tg.safeAreaInset, tg.contentSafeAreaInset
    return <p>{tg.colorScheme} · {tg.viewportHeight}px</p>
}
```

`useTelegramTheme()` and `useTelegramViewport()` return just the theme or viewport slice. Vue returns reactive refs; Svelte exposes a runes-backed accessor:

```js
// Vue
import { useTelegram } from '@laraxgram/vue3'
const tg = useTelegram() // Readonly<Ref<TelegramState>>
```

```js
// Svelte
import { useTelegram } from '@laraxgram/svelte'
const tg = useTelegram()
// tg.colorScheme, tg.viewportHeight, tg.isExpanded, …
```

<a name="back-button"></a>
## The Native Back Button

By default `bootstrapTelegram()` already syncs Telegram's native BackButton with browser history: it shows the button when there's somewhere to go back to, hides it on the entry page, and runs `history.back()` on tap. For per-page control — intercepting back, or a custom "can go back" rule — use the back-button hook:

```jsx
import { useTelegramBackButton } from '@laraxgram/react'

useTelegramBackButton({
    onBack: () => {
        if (hasUnsavedChanges) { confirmLeave(); return false } // Cancel default back.
    },
    canGoBack: () => historyDepth > 0,
})
```

Vue and Svelte export `useTelegramBackButton` with the same options, scoped to the component's lifetime.

<a name="main-button-and-forms"></a>
## The MainButton & Forms

Telegram's MainButton (and SecondaryButton) is the primary call-to-action at the bottom of a Mini App. Luna binds it directly to a [Luna form](/v4/luna-forms): the button submits the form on tap, shows a progress spinner while submitting, and stays disabled until the form is dirty (and valid):

```jsx
import { useForm, useTelegramFormButton } from '@laraxgram/react'
import { telegramHaptic } from '@laraxgram/luna'

function Profile({ bio }) {
    const form = useForm('post', '/profile', { bio })

    useTelegramFormButton(form, {
        text: 'Save',
        submit: () => {
            telegramHaptic.impact('light')
            form.submit()
        },
    })

    return (
        <textarea
            value={form.data.bio}
            onChange={(e) => form.setData('bio', e.target.value)}
        />
    )
}
```

Options: `text` (label), `kind` (`'main'` default or `'secondary'`), `disableUntilDirty` (default `true`), `disableOnErrors` (default `true`), and `textColor`. Vue passes a reactive form; Svelte passes a getter:

```js
// Svelte — pass a getter so the reactive form state is tracked.
useTelegramFormButton(() => form, { text: 'Save', submit: () => form.submit() })
```

For a button not tied to a form, drop to the core `telegramButton('main')` controller, which exposes `set()`, `onClick()`, and `destroy()`.

<a name="closing-confirmation"></a>
## Closing Confirmation

Warn the user before they close the Mini App while there are unsaved changes. Drive it from your form's `isDirty`:

```jsx
import { useTelegramClosingConfirmation } from '@laraxgram/react'

useTelegramClosingConfirmation(form.isDirty)
```

Vue accepts a ref or getter; Svelte accepts a getter. When `dirty` is `true`, Telegram shows its native "Close anyway?" prompt; when it clears, the prompt is disabled again.

<a name="answering-queries"></a>
## Answering Web App Queries

When a Mini App is opened from an inline keyboard button with a `query_id`, you can send an inline result back into the chat with `Luna::answerQuery()`. It defaults the query id to the current request's validated context:

```php
public function submit()
{
    return Luna::answerQuery([
        'type' => 'article',
        'id' => 'result-1',
        'title' => 'My result',
        'input_message_content' => ['message_text' => 'Sent from my Mini App!'],
    ]);
}
```

<a name="bot-actions"></a>
## Bot Actions

Luna exposes a handful of Bot API actions on the facade for common Mini App flows:

```php
// Prepare an inline message the user can share from inside the app.
$prepared = Luna::shareMessage([
    'type' => 'article',
    'id' => 'profile-card',
    'title' => 'My profile',
    'input_message_content' => ['message_text' => 'Check out my profile!'],
]);
// Return $prepared['id']; the client opens the share sheet with telegramShare.message(id).

// Create an invoice link for Telegram payments.
$url = Luna::invoiceLink([/* Bot API createInvoiceLink params */]);

// Configure the chat menu button.
Luna::setMenuButton(['type' => 'commands']);
Luna::miniAppMenuButton('Open App', 'https://t.me/yourbot/app');
```

`shareMessage` returns a Bot API `PreparedInlineMessage` (with an `id`); hand that id to the client, which calls [`telegramShare`](/v4/luna-tma-features#sharing) to open the native share sheet.

<a name="js-backends"></a>
## JavaScript Backends (tgcloud)

If your backend is JavaScript instead of PHP — for example a [Telegram Serverless (tgcloud)](https://core.telegram.org/bots/serverless) handler running in a V8 isolate — validate init data with the core's Web-Crypto validator, mirroring what the PHP middleware does:

```ts
import { createInitDataValidator, type ValidatedInitData } from '@laraxgram/luna'

// tgcloud exposes the bot token to the isolate.
declare const env: { BOT_TOKEN: string }

const tg = createInitDataValidator({ botToken: env.BOT_TOKEN, ttl: 3600 })

export default async function handler(request: Request): Promise<Response> {
    let user: ValidatedInitData
    try {
        // Reads X-Telegram-Init-Data (or the tgWebAppData query param) and
        // validates it — throws on missing / tampered / expired (fails closed).
        user = await tg.fromRequest(request)
    } catch {
        return new Response(JSON.stringify({ error: 'unauthorized' }), { status: 401 })
    }

    // Trusted. Key identity to user.user!.id.
    return Response.json({ id: user.user?.id, startParam: user.start_param ?? null })
}
```

The core also exports the lower-level `validateInitData()` / `tryValidateInitData()` and the header/request helpers `initDataFromHeaders()` and `initDataFromRequest()` for custom runtimes.

<a name="next"></a>
## Next Steps

You now have an authenticated, themed, native-integrated Mini App. Add device features — CloudStorage, biometrics, location, sensors, haptics, popups, sharing, invoices, and the test mock — in [Telegram Features](/v4/luna-tma-features).
