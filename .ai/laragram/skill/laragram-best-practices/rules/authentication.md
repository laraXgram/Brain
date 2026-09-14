# Authentication Best Practices

## Pick the Guard for the Entry Point

LaraGram authenticates different entry points with different guards. Use the one that matches where the code runs:

| Entry point | Guard | Identity comes from |
| --- | --- | --- |
| Bot listens (`listens/*.php`) | `bot` | The Telegram `user_id` of the update, looked up through the `users` provider (`column => user_id`) |
| Web routes (`routes/web.php`) | `web` (session) | A login form and the session |
| Telegram Mini Apps (Luna) | `telegram` | Init data validated by the `telegram` middleware |
| API tokens (`routes/api.php`, MCP servers) | `citadel` (when `laraxgram/citadel` is installed) | Personal access tokens |
| Jobs, commands, scheduled tasks | none | Pass the user explicitly |

- `Auth::user()` in a listen is `null` for Telegram users without a `users` row; create the row (for example on `/start`) instead of assuming it exists.
- Never authenticate a web or API request from a Telegram id sent in the request body. Only the bot guard (signed webhook updates) and the `telegram` guard (validated init data) may trust Telegram identities.
- Keep `APP_KEY` stable across deploys; rotating it logs out web users and invalidates encrypted values.

## Web Login

```php
public function store(LoginRequest $request): RedirectResponse
{
    if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
}
```

- Regenerate the session after login and invalidate it on logout (`Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()`).
- Protect routes with `auth` (or `auth:guard`), `guest`, `verified`, and `password.confirm`, and configure redirects with `$middleware->redirectGuestsTo(...)` / `redirectUsersTo(...)`. Guests are sent to the `login` route when it exists.
- These are web route middleware (registered as `route.auth`, `route.guest`, ... and exposed to routes under the short names). They don't exist for bot listens: there, check `Auth::user()` in a bot middleware, or use `can(...)`.
- Throttle login attempts by email and IP (`RateLimiter` or the `throttle` middleware on the route).
- Hash passwords with the `hashed` cast or `Hash::make()`; never store or log plain passwords.
- Starter kits (React, Vue, Svelte with Luna) already contain the auth flows. Extend them instead of writing a second login system.

## Linking Telegram and Web Accounts

When the same person uses the bot and the web app, link accounts by the Telegram id verified on each side (the bot guard or the Mini App `telegram` guard). Don't accept a Telegram id typed into a web form; use a one-time token sent through the bot, or the Mini App's validated init data.
