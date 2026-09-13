# Luna: Routing & Visits

<a name="introduction"></a>
## Introduction

In a Luna application there is only *one* router — LaraGram's. You never define client-side routes. Instead, you navigate by telling Luna to *visit* a URL: it issues an XHR, receives the new [page object](/v4/luna#the-protocol), swaps the component, and updates the browser history. This chapter covers the two ways to trigger a visit — the `<Link>` component and the programmatic router — plus redirects, prefetching, polling, and history management.

<a name="defining-routes"></a>
## Defining Routes

Routes are ordinary LaraGram [routes](/v4/routing). Any route whose controller returns a [Luna response](/v4/luna-pages#rendering-pages) is a Luna page. For pages that need no controller logic, the `Route::luna()` macro is a shortcut:

```php
use LaraGram\Support\Facades\Route;

Route::luna('/about', 'About');
Route::luna('/pricing', 'Pricing', ['plans' => $plans]);
```

`Route::luna($uri, $component, $props)` registers a `GET`/`HEAD` route that renders `$component` with static `$props`. For anything dynamic, use a normal controller route.

<a name="links"></a>
## The Link Component

The `<Link>` component renders an anchor that performs a Luna visit instead of a full page load. Import it from your framework adapter:

```jsx
import { Link } from '@laraxgram/react'

<Link href="/users">Users</Link>
<Link href="/users/1">View user</Link>
```

By default `<Link>` issues a `GET` visit. Change the method and send data for non-GET actions:

```jsx
<Link href="/logout" method="post" as="button">Log out</Link>
<Link href="/users/1" method="delete" as="button">Delete</Link>
```

> [!NOTE]
> When `method` is anything other than `get`, render the link `as="button"` — an anchor performing a destructive action is a browser accessibility and prefetch hazard.

<a name="link-options"></a>
### Link Options

`<Link>` accepts the full range of visit options as props:

| Prop | Description |
|------|-------------|
| `href` | The target URL (or a `{ url, method }` pair). |
| `method` | HTTP verb: `get` (default), `post`, `put`, `patch`, `delete`. |
| `data` | Data to send with the request. |
| `only` / `except` | Perform a [partial reload](/v4/luna-pages#partial-reloads) of specific props. |
| `preserveScroll` | Keep the current scroll position after the visit. |
| `preserveState` | Keep the current page component's local state. |
| `replace` | Replace the current history entry instead of pushing a new one. |
| `headers` | Extra request headers. |
| `async` | Fire the visit without blocking navigation. |
| `prefetch` | [Prefetch](#prefetching) the page (`true`, `'hover'`, `'mount'`, `'click'`). |
| `cacheFor` | How long a prefetched response stays fresh. |

```jsx
<Link
    href="/users"
    only={['users']}
    preserveScroll
    prefetch="hover"
>
    Users
</Link>
```

The Vue and Svelte adapters export an equivalent `<Link>` component with the same options (bound the idiomatic way for each framework).

<a name="manual-visits"></a>
## Manual Visits

Import the `router` to navigate programmatically — from event handlers, effects, or anywhere in your app:

```js
import { router } from '@laraxgram/react'

router.visit('/users')
router.get('/users', { page: 2 })
router.post('/users', { name: 'Ada' })
router.put('/users/1', { name: 'Ada' })
router.patch('/users/1', { name: 'Ada' })
router.delete('/users/1')
```

`router.visit(url, options)` is the general form; `get`/`post`/`put`/`patch`/`delete` are convenience wrappers. All accept the same options object:

```js
router.post('/users', data, {
    only: ['users', 'flash'],   // Partial reload.
    preserveScroll: true,
    preserveState: true,
    replace: false,
    headers: { 'X-Custom': '1' },
    onBefore: (visit) => { /* return false to cancel */ },
    onStart: (visit) => {},
    onProgress: (event) => {},   // Upload progress.
    onSuccess: (page) => {},
    onError: (errors) => {},     // Validation errors.
    onFinish: (visit) => {},
    onCancel: () => {},
})
```

<a name="reloading"></a>
### Reloading the Current Page

`router.reload()` re-requests the current URL — commonly with `only` for a [partial reload](/v4/luna-pages#partial-reloads):

```js
router.reload()
router.reload({ only: ['notifications'] })
```

<a name="preserving-scroll-and-state"></a>
### Preserving Scroll & State

By default a visit resets scroll to the top and replaces the page component (destroying its local state). Opt out with `preserveScroll` and `preserveState`. Both accept a boolean or a callback that decides based on the response:

```js
router.get('/search', { q }, {
    preserveScroll: true,
    preserveState: true,   // Keep the search box focused & filled.
})
```

`preserveState` defaults to `true` for non-GET visits so form fields survive a validation error.

<a name="redirects"></a>
## Redirects

Server-side redirects work exactly as they do in any LaraGram app — return a redirect and Luna follows it, swapping to the destination page:

```php
public function store(Request $request)
{
    User::create($request->validated());

    return redirect()->route('users.index');
}
```

Luna automatically converts a redirect after a `PUT`/`PATCH`/`DELETE` into a `GET` for the destination (via the bundled `EnsureGetOnRedirect` middleware), so you never get a stale method on the follow-up visit.

<a name="external-redirects"></a>
### External Redirects

To send the user to a non-Luna URL (an external site, or a route outside your SPA), use `Luna::location()`. It responds with a header that tells the client to perform a full browser navigation:

```php
return Luna::location('https://billing.example.com/checkout');
```

The `luna_location()` helper is a shorthand for the same thing.

<a name="the-page-object"></a>
## The Page Object

Every page component can read the current [page object](/v4/luna#the-protocol) — its props, URL, component name, and version — via `usePage`:

```jsx
import { usePage } from '@laraxgram/react'

function Nav() {
    const { auth, url } = usePage().props
    return <span>{auth.user.name}</span>
}
```

Vue exposes `usePage()` from `@laraxgram/vue3`; Svelte exposes both `usePage()` and a `page` store from `@laraxgram/svelte`. This is how you access [shared props](/v4/luna-pages#shared-data) — including the [Telegram context](/v4/luna-tma#shared-context) — anywhere in the tree.

<a name="prefetching"></a>
## Prefetching

Prefetching fetches a page's data *before* the user navigates, so the visit feels instant. Enable it per-link with the `prefetch` prop:

```jsx
<Link href="/users" prefetch>Users</Link>              {/* on hover (default) */}
<Link href="/users" prefetch="mount" cacheFor="30s">…</Link>
<Link href="/users" prefetch={['hover', 'click']}>…</Link>
```

| Strategy | When the fetch fires |
|----------|----------------------|
| `hover` | When the pointer hovers the link (default when `prefetch` is `true`). |
| `mount` | As soon as the link mounts. |
| `click` | On mouse-down, slightly ahead of the click. |

`cacheFor` controls how long a prefetched response is considered fresh (e.g. `'30s'`, `'5m'`, or milliseconds). Prefetch programmatically with `router.prefetch(url, options, { cacheFor })`, and clear the cache with `router.flushAll()`.

The `usePrefetch()` hook exposes the prefetch state for a given set of options — `isPrefetching`, `isPrefetched`, `lastUpdatedAt`, and a `flush()` — so you can reflect it in the UI.

<a name="polling"></a>
## Polling

To keep a page fresh, poll the server on an interval. The `usePoll` hook wraps a repeating [partial reload](/v4/luna-pages#partial-reloads):

```jsx
import { usePoll } from '@laraxgram/react'

// Re-fetch `notifications` every 5 seconds.
const { stop, start } = usePoll(5000, { only: ['notifications'] })
```

Pass `{ keepAlive: true }` to keep polling while the tab is in the background, and `{ autoStart: false }` to start it manually via the returned `start()`. Under the hood this is `router.poll(interval, options)`, which you can also call directly for non-component code. Vue and Svelte export the same `usePoll` helper.

<a name="infinite-scroll"></a>
## Infinite Scroll

The `<InfiniteScroll>` component pairs with [merge props](/v4/luna-pages#merging-props) or [scroll props](/v4/luna-pages#scroll-props) to load more items as the user reaches the end of a list — no manual page tracking:

```jsx
import { InfiniteScroll } from '@laraxgram/react'

<InfiniteScroll data="posts">
    {posts.map((post) => <PostCard key={post.id} post={post} />)}
</InfiniteScroll>
```

Each time the sentinel scrolls into view, Luna requests the next page and merges it into the `posts` prop. The core also exports a headless `useInfiniteScroll` for custom UIs. Vue and Svelte export the same component.

<a name="remembering-state"></a>
## Remembering Local State

Luna can persist component-local state (form drafts, scroll positions, open panels) in the browser history, so it is restored when the user navigates back. Use the framework's remember hook:

```jsx
import { useRemember } from '@laraxgram/react'

// Restored automatically on back-navigation, keyed by 'filters'.
const [filters, setFilters] = useRemember({ search: '' }, 'filters')
```

For lower-level control, `router.remember(data, key)` and `router.restore(key)` read and write the history state directly. Vue and Svelte export `useRemember` as well.

<a name="events"></a>
## Global Events

Subscribe to the visit lifecycle globally — useful for [progress bars](/v4/luna-frontend#progress-indicator), analytics, or logging. `router.on()` returns an unsubscribe function:

```js
const off = router.on('start', (event) => {
    console.log('Visiting', event.detail.visit.url.href)
})

// Later:
off()
```

Available events include `before`, `start`, `progress`, `success`, `error`, `invalid`, `exception`, `finish`, `navigate`, `prefetching`, and `prefetched`.

<a name="history-encryption"></a>
## History Encryption

Page data is stored in the browser's history state so back/forward navigation is instant. For sensitive pages you can encrypt that data so it is unreadable after logout. Enable it globally in `config/luna.php` (`history.encrypt`), per-response, or via middleware:

```php
// Per-response.
return Luna::render('Account', $props)->encryptHistory();

// Clear all encrypted history (e.g. on logout).
Luna::clearHistory();
```

Or apply the `luna.encrypt` middleware to a route group. See [config/luna.php](/v4/luna#server-side-installation) for the global switch.

<a name="next"></a>
## Next Steps

With navigation covered, move on to sending data to the server with [Forms](/v4/luna-forms), or wiring up your framework and layouts in [Frontend Setup](/v4/luna-frontend).
