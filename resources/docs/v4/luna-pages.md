# Luna: Pages & Props

<a name="introduction"></a>
## Introduction

The heart of Luna is a simple contract: a controller returns the *name* of a page component and the *props* that component should receive. LaraGram resolves the data on the server; the frontend renders it. This chapter covers how to render pages, how to pass data to them, and the range of prop types Luna offers — from ordinary values to props that load lazily, merge across visits, or persist for a single visit only.

If you haven't yet, read the [Luna introduction](/v4/luna) first.

<a name="rendering-pages"></a>
## Rendering Pages

Return a Luna response from any [controller](/v4/controllers) with the `Luna` facade or the `luna()` helper. The first argument is the component name (relative to `resources/js/Pages`, without extension); the second is an array of props:

```php
use LaraGram\Luna\Luna;

class DashboardController extends Controller
{
    public function index()
    {
        return Luna::render('Dashboard', [
            'stats' => Stat::all(),
        ]);
    }
}
```

The `luna()` helper is equivalent and reads well in shorter actions:

```php
return luna('Dashboard', ['stats' => Stat::all()]);
```

<a name="the-render-response"></a>
### Working with the Response

`Luna::render()` returns a `LaraGram\Luna\Response` you can further configure before it is sent. The most common methods:

```php
return Luna::render('Users/Show', ['user' => $user])
    ->with('key', 'value')      // Add extra props fluently.
    ->rootView('layout')        // Use a non-default root Blade template.
    ->withViewData('meta', $m); // Pass data to the root Blade view (not the component).
```

`withViewData()` is for values your **root Blade template** needs (like an Open Graph tag rendered server-side) — they are not sent to the JS component.

<a name="props"></a>
## Props

Any JSON-serializable value can be a prop. [Eloquent models](/v4/eloquent), [collections](/v4/collections), and [API resources](/v4/eloquent-resources) all serialize automatically:

```php
return Luna::render('Users/Index', [
    'users' => User::query()
        ->paginate()
        ->through(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
        ]),
]);
```

> [!NOTE]
> Props are resolved eagerly by default — every closure and query runs on every visit to the page. To defer expensive work, use [lazy props](#lazy-evaluation), [deferred props](#deferred-props), or [partial reloads](#partial-reloads).

<a name="lazy-evaluation"></a>
### Lazy (Closure) Props

Wrap a prop in a closure and it is only evaluated when the prop is actually included in the response. On a [partial reload](#partial-reloads) that excludes it, the closure never runs:

```php
return Luna::render('Users/Index', [
    // Always evaluated.
    'users' => User::all(),

    // Only evaluated when this prop is requested.
    'stats' => fn () => Stat::expensiveAggregate(),
]);
```

<a name="optional-props"></a>
### Optional Props

An **optional** prop is never included on the first visit or on a full reload — it is included *only* when explicitly requested by a [partial reload](#partial-reloads). Use it for data that is expensive and not needed for the initial render:

```php
use LaraGram\Luna\Luna;

return Luna::render('Users/Index', [
    'users' => User::all(),
    'stats' => Luna::optional(fn () => Stat::expensiveAggregate()),
]);
```

<a name="deferred-props"></a>
### Deferred Props

A **deferred** prop renders the page immediately without the data, then Luna automatically fetches it in a follow-up request once the page has mounted. This is the easiest way to keep a page fast while still loading secondary data:

```php
return Luna::render('Users/Show', [
    'user' => $user,
    'posts' => Luna::defer(fn () => $user->posts),
    'activity' => Luna::defer(fn () => $user->activity, group: 'secondary'),
]);
```

Props sharing a `group` are fetched together in a single request; different groups are fetched in parallel requests. Pass `rescue: true` to keep the page alive if the deferred callback throws, resolving the prop to `null` instead of failing the visit.

On the client, wrap deferred props in the `<Deferred>` component to show a fallback while they load:

```jsx
import { Deferred } from '@laraxgram/react'

<Deferred data="posts" fallback={<p>Loading posts…</p>}>
    <PostList posts={posts} />
</Deferred>
```

`data` may be an array to wait on several deferred props at once. Provide a `rescue` slot to render when a rescued deferred prop failed. The Vue and Svelte adapters export an equivalent `<Deferred>` component.

<a name="merging-props"></a>
### Merging Props

Normally new prop values *replace* the old ones. A **merge** prop instead appends/merges into the existing client value — ideal for "load more" and [infinite scroll](/v4/luna-routing#infinite-scroll) patterns where each visit brings the next page of items:

```php
return Luna::render('Feed', [
    'posts' => Luna::merge($nextPage),      // Shallow merge (append arrays).
    'meta' => Luna::deepMerge($metaChunk),  // Recursive deep merge.
]);
```

<a name="always-props"></a>
### Always Props

An **always** prop is included in *every* response, even in a [partial reload](#partial-reloads) that would otherwise exclude it. Use it for values that must never go stale, such as a flash message or CSRF token:

```php
return Luna::render('Users/Index', [
    'users' => User::all(),
    'flash' => Luna::always(fn () => session('flash')),
]);
```

<a name="once-props"></a>
### Once Props

A **once** prop is resolved a single time and cached for the duration of the request, so multiple resolvers referencing it don't recompute. `shareOnce` combines this with [shared props](#shared-data):

```php
Luna::shareOnce('permissions', fn () => auth()->user()->permissions());
```

<a name="scroll-props"></a>
### Scroll Props (Paginated Data)

A **scroll** prop wraps paginated data together with the metadata Luna's [infinite scroll](/v4/luna-routing#infinite-scroll) needs — page numbers, cursors, and whether more data exists:

```php
return Luna::render('Feed', [
    'posts' => Luna::scroll($query->paginate(), wrapper: 'data'),
]);
```

The `wrapper` names the key that holds the items; Luna manages the surrounding scroll metadata for you.

<a name="shared-data"></a>
## Shared Data

Often several pages need the same props — the authenticated user, flash messages, unread counts. Rather than repeating them in every controller, **share** them once, typically from a [middleware](/v4/middleware) or [service provider](/v4/providers). Shared props are merged into every Luna response:

```php
use LaraGram\Luna\Luna;

class HandleLunaRequests
{
    public function handle($request, Closure $next)
    {
        Luna::share([
            'auth' => [
                'user' => $request->user()?->only('id', 'name'),
            ],
            'flash' => fn () => session('status'),
        ]);

        return $next($request);
    }
}
```

`Luna::share()` accepts a key/value pair, an associative array, or an object implementing `ProvidesLunaProperties`. Values may be closures (evaluated lazily per request).

Read shared props back on the server with `Luna::getShared('auth')`, and clear them with `Luna::flushShared()`. On the client, shared props arrive merged into every page's `props` and are accessible via the page object — see [`usePage`](/v4/luna-routing#the-page-object).

> [!NOTE]
> Telegram Mini Apps use exactly this mechanism: the `telegram` middleware shares a validated `telegram` prop on every response. See [Telegram Mini Apps](/v4/luna-tma#shared-context).

<a name="partial-reloads"></a>
## Partial Reloads

A **partial reload** re-requests the *current* page but asks the server for only a subset of its props. Because [lazy](#lazy-evaluation) and [optional](#optional-props) props are only evaluated when included, partial reloads are how you selectively refresh data without recomputing everything.

Trigger one from the [router](/v4/luna-routing#manual-visits) with `only` or `except`:

```js
import { router } from '@laraxgram/react'

// Re-fetch only the `stats` prop; everything else is left untouched.
router.reload({ only: ['stats'] })

// Re-fetch everything except the heavy `report` prop.
router.reload({ except: ['report'] })
```

The `<Link>` component accepts the same `only` / `except` options, and the [`usefetch`/poll](/v4/luna-routing#polling) helpers build on partial reloads under the hood.

<a name="loading-when-visible"></a>
### Loading Props When Visible

The `<WhenVisible>` component triggers a partial reload for the named prop(s) when the element scrolls into view — perfect for below-the-fold data that pairs with [optional props](#optional-props):

```jsx
import { WhenVisible } from '@laraxgram/react'

<WhenVisible data="stats" fallback={<Spinner />}>
    <Stats stats={stats} />
</WhenVisible>
```

Options: `buffer` (pixels before the element enters the viewport to start loading), `as` (the wrapper element/tag), `always` (re-fetch every time it becomes visible, not just once), and `params` (extra [visit options](/v4/luna-routing#manual-visits)). Vue and Svelte export the same component.

<a name="resetting-props"></a>
### Resetting Merged Props

When paginating with [merge props](#merging-props), you sometimes need to discard the accumulated client state (e.g. when a filter changes) and start fresh. Pass `reset` with the prop keys to clear before merging:

```js
router.reload({ only: ['posts'], reset: ['posts'] })
```

<a name="next"></a>
## Next Steps

Now that data flows from server to component, learn how to move *between* pages in [Routing & Visits](/v4/luna-routing), and how to send data back with [Forms](/v4/luna-forms).
