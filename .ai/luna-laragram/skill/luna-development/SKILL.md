---
name: luna-development
description: "Develops the server side of Luna single-page apps in LaraGram (laraxgram/luna). Activate when writing web routes or controllers that return Luna::render() / luna(), Route::luna(), props (Luna::defer, optional, merge, deepMerge, always, once, scroll), shared data and the HandleLunaRequests middleware (luna:middleware, share, shareOnce, version, rootView), partial reloads, redirects and Luna::location(), flash data, validation errors, the root Blade template (@luna, @lunaHead), asset versioning, history encryption (luna.encrypt), server-side rendering (config/luna.php ssr, luna:start-ssr), or config/luna.php. Use together with the React, Vue, or Svelte Luna skill for client code, and luna-tma-development for Telegram Mini Apps."
license: MIT
metadata:
  author: laraxgram
---

# Luna Server-Side Development

Luna lets LaraGram web routes drive a React, Vue, or Svelte frontend without an API or a client-side router. A controller returns a component name and props; the first visit renders the root Blade template with the page object embedded, and later visits (XHR with the `X-Luna` header) return the page object as JSON. Use `search-docs` with `packages: ['laraxgram/luna']`.

Luna is web-only: it uses `routes/web.php`, `LaraGram\Http\Request`, and controllers in `app/Http/Controllers` (`php laragram make:controller --web`). Never return Luna responses from bot listens.

## Setup Checklist

- `config/luna.php` exists (`php laragram vendor:publish --provider="LaraGram\Luna\ServiceProvider"`).
- A root template, by convention `resources/views/app.blade.php`, containing `@lunaHead`, `@vite([...])`, and `@luna`.
- The request middleware, generated with `php laragram luna:middleware HandleLunaRequests` (`app/Http/Middleware/HandleLunaRequests.php`), is appended to the web group: `$middleware->web(append: [HandleLunaRequests::class])`.
- The client entry calls `createLunaApp()`; pages live in `resources/js/Pages` (see the framework skill).

## Rendering Pages

```php
use LaraGram\Luna\Luna;

class UserController extends Controller
{
    public function show(User $user)
    {
        return Luna::render('Users/Show', [
            'user' => $user->only('id', 'name', 'email'),
            'posts' => Luna::defer(fn () => $user->posts()->latest()->take(10)->get()),
        ]);
    }
}

Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
Route::luna('/about', 'About');                 // no controller needed
```

- `luna('Users/Show', $props)` is the helper form. The component name maps to the page file path (`Users/Show` → `resources/js/Pages/Users/Show.tsx`); create the page whenever you add a render call.
- Configure the response fluently: `->with('key', $value)`, `->rootView('layout')`, `->withViewData('meta', $meta)` (root Blade view only), `->encryptHistory()`.
- Props are serialized to JSON and visible in the page source. Send only the fields the page needs (`only()`, API resources, `through()` on paginators); never send hidden attributes, tokens, or other users' data.

## Prop Types

| Prop | Use |
| --- | --- |
| `fn () => ...` | Lazy: evaluated only when included (skipped by partial reloads that exclude it) |
| `Luna::optional(fn () => ...)` | Never on first load; only when a partial reload asks for it (`router.reload({ only: [...] })`, `<WhenVisible>`) |
| `Luna::defer(fn () => ..., group: 'secondary', rescue: true)` | Page renders first, then the client fetches it; groups load in parallel; `rescue` turns failures into `null` |
| `Luna::merge($items)` / `Luna::deepMerge($data)` | Append to (or deep-merge with) the client's existing value — "load more" |
| `Luna::scroll($query->paginate(), wrapper: 'data')` | Paginated data plus the metadata `<InfiniteScroll>` needs |
| `Luna::always(fn () => ...)` | Included even in partial reloads that exclude it (flash, errors) |
| `Luna::once(fn () => ...)` | Resolved once per request even when referenced several times |

Wrap expensive queries in closures or deferred props; eager props run on every visit, including partial reloads of other props.

## Shared Data

Share props every page needs in the Luna middleware:

```php
class HandleLunaRequests extends \LaraGram\Luna\Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => fn () => $request->user()?->only('id', 'name')],
            'flash' => fn () => session('status'),
        ];
    }
}
```

- Keep `...parent::share($request)`: it shares the validation `errors`.
- Use closures so values are only computed when needed, `shareOnce()` for data remembered across visits, and `Luna::share()` for values added from controllers or other middleware.
- Override `version()` only when assets are not built by Vite into `public/build/manifest.json`; the default hashes the manifest so clients reload after a deploy.

## Forms, Validation and Redirects

```php
public function store(StoreUserRequest $request)
{
    $user = User::create($request->validated());

    Luna::flash('status', 'User created.');

    return to_route('users.show', $user);
}
```

- Validate with `$request->validate()` or form requests. Failed validation redirects back and the errors reach `form.errors` / `usePage().props.errors` automatically.
- After `post`/`put`/`patch`/`delete`, redirect (`back()`, `to_route()`, `redirect()->route()`). Don't return JSON to a Luna visit; Luna converts redirects after PUT/PATCH/DELETE into GET visits.
- Use `Luna::location($url)` (or `luna_location()`) for external URLs and non-Luna pages, including file downloads, so the browser does a full navigation.
- Authorization works as usual (`Gate::authorize()`, policies, the `can` middleware); a 403/404 renders the error page, so render a Luna error page from the exception handler (`Luna::handleExceptionsUsing(...)`) when the app needs styled errors.

## History Encryption

Page props are stored in browser history. For sensitive pages enable `history.encrypt` in `config/luna.php`, call `->encryptHistory()` per response, or apply the `luna.encrypt` middleware, and call `Luna::clearHistory()` on logout.

## Server-Side Rendering

- Configure under `ssr` in `config/luna.php` (`enabled`, `runtime`, the render service URL, the bundle). The SSR entry is `resources/js/ssr.(jsx|tsx|ts|js)` using the adapter's `/server` export.
- Production: `npm run build` builds the client and SSR bundles; run `php laragram luna:start-ssr` under a supervisor (`luna:stop-ssr`, `luna:check-ssr`). During development `npm run dev` serves SSR in-process.
- SSR failures fall back to client rendering. Exclude paths with `Luna::withoutSsr([...])` or the middleware's `$withoutSsr`; Telegram Mini Apps usually leave SSR off.
- Code that runs during SSR must not touch `window`, `document`, or the Telegram SDK at module load.

## Common Pitfalls

- Rendering a component without creating its page file.
- Forgetting the Luna middleware in the `web` group (no shared errors, no asset versioning).
- Sharing large or sensitive data globally.
- Using Luna responses for API routes (`routes/api.php`) or bot listens.
- Returning JSON or `response()->json()` from actions that Luna forms submit to.
