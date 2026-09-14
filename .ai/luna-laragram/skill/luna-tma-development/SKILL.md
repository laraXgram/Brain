---
name: luna-tma-development
description: "Builds Telegram Mini Apps (TMA) with Luna in a LaraGram application. Activate when creating or changing a Mini App: routes protected by the telegram middleware, init data validation (config/luna.php telegram options, X-Telegram-Init-Data), tg_user() and telegram() helpers, the telegram auth guard, the @telegramWebApp root template directive, bootstrapTelegram(), Telegram theme and viewport CSS variables, the MainButton/BackButton hooks (useTelegramFormButton, useTelegramBackButton), CloudStorage, haptics, popups, sharing, invoices, Luna::answerQuery, Luna::shareMessage, Luna::invoiceLink, menu buttons, or opening the Mini App from a bot keyboard (Make::webApp)."
license: MIT
metadata:
  author: laraxgram
---

# Luna Telegram Mini Apps

A Telegram Mini App is a Luna single-page app that runs inside the Telegram client and speaks the Telegram WebApp protocol. Everything from the framework skill (`luna-react-development`, `luna-vue-development`, or `luna-svelte-development`) applies; this skill adds the Telegram layer. Use `search-docs` (`luna-tma`, `luna-tma-features`) for exact APIs.

## Security Model

1. Telegram opens the app with signed **init data**. Validate it on the server before trusting any identity — the `telegram` middleware does this on every request and fails closed.
2. Share only the **validated** identity with the frontend. Never use the SDK's unsigned `initDataUnsafe` for authorization.
3. The bot token validates the signature. Keep it in `.env` (`BOT_TOKEN`) and `config/luna.php`; never put it in shared props or `resources/js`.

## Server

```php
// config/luna.php
'telegram' => [
    'bot_token' => env('BOT_TOKEN'),
    'validate' => (bool) env('LUNA_TG_VALIDATE', true),
    'auth_ttl' => (int) env('LUNA_TG_AUTH_TTL', 86400),
    'share_props' => (bool) env('LUNA_TG_SHARE_PROPS', true),
],
```

```php
use LaraGram\Luna\Luna;
use LaraGram\Support\Facades\Route;

Route::middleware('telegram')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update']);
});

class ProfileController extends Controller
{
    public function show()
    {
        $user = tg_user(); // WebAppUser|null — validated by the middleware

        return Luna::render('Profile', [
            'displayName' => $user?->firstName ?? 'Guest',
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['bio' => 'nullable|string|max:500']);

        Profile::updateOrCreate(['telegram_id' => tg_user()->id], $data);

        return back();
    }
}
```

- `telegram` rejects missing (401), tampered (403), and expired (401) init data; `telegram:optional` allows anonymous access but still rejects tampered data.
- Key every write to `tg_user()->id`, never to an id from the request body.
- `telegram()` exposes `user()`, `chat()`, `startParam()`, `queryId()`, and `has()`.
- For Eloquent users, configure the `telegram` guard (`'driver' => 'telegram'`) in `config/auth.php` and use `auth('telegram')->user()`.
- `validate => false` is only for local development outside Telegram — never in production.

## Root Template

Load the SDK before the app bundle with `@telegramWebApp`, theme the shell with Telegram's CSS variables, and respect safe areas:

```blade
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @telegramWebApp
    @lunaHead
    <style>
        body {
            margin: 0;
            background: var(--tg-theme-bg-color, #fff);
            color: var(--tg-theme-text-color, #000);
        }
    </style>
    @vite(['resources/js/app.tsx'])
</head>
<body>
    @luna
</body>
```

## Client

Call `bootstrapTelegram()` once before mounting the app. It calls `ready()` and `expand()`, keeps `--tg-*` theme/viewport CSS variables live, and syncs the native BackButton with history. It is a no-op outside Telegram, so it is safe in a normal browser.

```tsx
import { bootstrapTelegram } from '@laraxgram/luna'
import { createLunaApp } from '@laraxgram/react'

bootstrapTelegram()

createLunaApp({ /* resolve, setup */ })
```

Framework bindings (React shown; Vue and Svelte export the same names, Svelte uses `getTelegramUser()` / `getTelegramSharedContext()`):

| Need | API |
| --- | --- |
| Validated user / shared context | `useTelegramUser()`, `useTelegramSharedContext()` |
| Theme, viewport, safe areas | `useTelegram()`, `useTelegramTheme()`, `useTelegramViewport()` or `--tg-*` CSS variables |
| MainButton bound to a Luna form | `useTelegramFormButton(form, { text: 'Save', submit: () => form.submit() })` |
| Custom back behavior | `useTelegramBackButton({ onBack, canGoBack })` |
| "Close anyway?" prompt for unsaved changes | `useTelegramClosingConfirmation(form.isDirty)` |

Device features from `@laraxgram/luna`: `telegramCloudStorage`, `telegramDeviceStorage`, `telegramSecureStorage`, `telegramBiometric`, `telegramLocation`, `telegramHaptic`, `telegramPopup`, `telegramScanQr`, `telegramShare`, `telegramRequest`, `telegramOpenInvoice`, `telegramDownloadFile`, `telegramCloseMiniApp`. Check `isTelegram()` or the feature's availability before relying on it, and use `installTelegramMock()` to develop outside Telegram.

## Bot Integration

- Open the Mini App from the bot with a `Make::webApp('Open', $url)` keyboard button or `Luna::miniAppMenuButton('Open App', $url)`. The URL must be public HTTPS; use `get-absolute-url` to build it.
- Answer an inline-button Web App query with `Luna::answerQuery([...])` (the query id comes from the validated context).
- Prepare a shareable message with `Luna::shareMessage([...])`, return its `id`, and open the share sheet with `telegramShare.message(id)`.
- Create payment links with `Luna::invoiceLink([...])`.
- Browser errors inside the Mini App are available through the `browser-logs` MCP tool.
