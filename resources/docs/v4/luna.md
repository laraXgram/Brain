# Luna

<a name="introduction"></a>
## Introduction

Luna is the official adapter that lets you build modern, single-page style frontends for your LaraGram application using React, Vue, or Svelte — without building an API, without client-side routing configuration, and without losing any of the server-side conveniences you already know: [routing](/v4/routing), [controllers](/v4/controllers), [middleware](/v4/middleware), [validation](/v4/validation), and [authorization](/v4/authorization).

Luna is a LaraGram port of the ideas behind [Luna.js](https://laraxgram.github.io/v4/luna). You write your routes and controllers exactly as you always have. Instead of returning a Blade [view](/v4/views) or JSON, a controller returns a Luna *response*, naming a JavaScript page component and the props it should receive:

```php
use LaraGram\Luna\Luna;

class UserController extends Controller
{
    public function show(User $user)
    {
        return Luna::render('Users/Show', [
            'user' => $user,
        ]);
    }
}
```

On the first visit, LaraGram returns a full HTML document with your page component embedded. On every visit after that, Luna intercepts the navigation, fetches only the new page's props over XHR, and swaps the component client-side — giving you the feel of a SPA while keeping all of your application logic on the server.

> [!NOTE]
> Luna ships in two halves: the **`laraxgram/luna`** PHP package (the server-side adapter for LaraGram) and the **`@laraxgram/luna`** family of npm packages (the client-side runtime plus the `@laraxgram/react`, `@laraxgram/vue3`, and `@laraxgram/svelte` adapters). You install both.

<a name="use-cases"></a>
### When to Use Luna

Luna serves two related purposes, and this documentation covers both:

<div class="content-list" markdown="1">

- **Building SPAs for LaraGram.** Use Luna as a drop-in replacement for a hand-rolled API + client-side router. Your team writes controllers and page components; Luna wires them together. This is the "classic" Luna experience, and everything in [Pages & Props](/v4/luna-pages), [Routing & Visits](/v4/luna-routing), [Forms](/v4/luna-forms), and [Frontend Setup](/v4/luna-frontend) applies.

- **Building Telegram Mini Apps (TMA).** Luna's primary reason for existing. A Telegram Mini App is a web app that runs inside the Telegram client. Luna adds cryptographic init-data validation, a `telegram` auth guard, a reactive theme/viewport store, native-button ↔ form integration, and promise-based wrappers for the entire Telegram WebApp SDK. See [Telegram Mini Apps](/v4/luna-tma) and [Telegram Features](/v4/luna-tma-features).

</div>

The two use cases share one runtime. A Mini App *is* a Luna SPA that additionally speaks Telegram; read the SPA chapters first, then layer the Telegram chapters on top.

<a name="how-it-works"></a>
## How Luna Works

Luna sits between LaraGram's [router](/v4/routing) and your JavaScript framework. The lifecycle of a request looks like this:

<div class="content-list" markdown="1">

1. **First visit (full page load).** The browser requests a URL normally. Your route resolves to a controller that returns `Luna::render('Component', $props)`. Luna renders your root Blade template (`app.blade.php` by default), embedding the page object — component name, props, URL, and asset version — as JSON in a `data-page` attribute on the root element. The browser boots your JS framework, which reads that object and mounts the named component.

2. **Subsequent visits (Luna visits).** When the user clicks a Luna [`<Link>`](/v4/luna-routing#links) or you call the [router](/v4/luna-routing#manual-visits), Luna issues an XHR request carrying the `X-Luna: true` header. LaraGram sees the header and — instead of returning HTML — returns the page object as JSON. Luna swaps the page component and props client-side, updates the browser history, and the user never sees a full reload.

</div>

Because the server decides which component renders, you keep a single source of truth for routing, authorization, and validation. There is no second router to keep in sync.

<a name="the-protocol"></a>
### The Luna Protocol

Every Luna response — whether HTML or JSON — carries the same **page object**:

| Key | Description |
|-----|-------------|
| `component` | The name of the JS page component to render, e.g. `Users/Show`. |
| `props` | The data passed to the component. |
| `url` | The URL of the current page. |
| `version` | The current [asset version](/v4/luna-frontend#asset-versioning), used to force a full reload when assets change. |

You will rarely interact with the protocol directly — the PHP adapter and the JS runtime handle it — but understanding it makes the rest of the documentation click into place.

<a name="server-side-installation"></a>
## Server-Side Installation

Install the PHP adapter with Composer:

```shell
composer require laraxgram/luna
```

Publish the configuration file. This creates `config/luna.php`, where you configure [server-side rendering](/v4/luna-frontend#server-side-rendering), page paths, history encryption, and [Telegram](/v4/luna-tma#configuration) options:

```shell
php laragram vendor:publish --provider="LaraGram\Luna\ServiceProvider"
```

<a name="root-template"></a>
### The Root Blade Template

Luna needs a single root [Blade](/v4/blade) template that loads your compiled assets and provides the mounting point. By convention it lives at `resources/views/app.blade.php`. Use the `@luna` directive for the root element and `@lunaHead` for server-rendered `<head>` content:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @lunaHead

    @vite(['resources/js/app.js'])
</head>
<body>
    @luna
</body>
</html>
```

<div class="content-list" markdown="1">

- `@luna` renders the root `<div>` and embeds the initial page object.
- `@lunaHead` renders any `<title>`, `<meta>`, and other head elements produced by [server-side rendering](/v4/luna-frontend#server-side-rendering).
- `@vite` loads your compiled JS/CSS via the [Vite](/v4/vite) plugin.

</div>

If you name your root template something other than `app`, tell Luna about it globally in a [service provider](/v4/providers):

```php
use LaraGram\Luna\Luna;

Luna::setRootView('layout');
```

Or per-response:

```php
return Luna::render('Users/Show', ['user' => $user])
    ->rootView('layout');
```

<a name="client-side-installation"></a>
## Client-Side Installation

Install the core runtime plus the adapter for your framework of choice:

```shell
# React
npm install @laraxgram/luna @laraxgram/react

# Vue 3
npm install @laraxgram/luna @laraxgram/vue3

# Svelte
npm install @laraxgram/luna @laraxgram/svelte
```

Install the [Vite](/v4/vite) plugin as a dev dependency — it wires up page auto-resolution and the SSR dev server:

```shell
npm install -D @laraxgram/vite
```

<a name="initializing-the-app"></a>
### Initializing the App

Create your app entry point (e.g. `resources/js/app.js`) and boot Luna with `createLunaApp`. The `resolve` callback maps a component name to a component module; the `setup` callback mounts the resolved app. Here is the React version:

```js
import { createLunaApp } from '@laraxgram/react'
import { createRoot } from 'react-dom/client'

createLunaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true })
        return pages[`./Pages/${name}.jsx`]
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
})
```

The equivalent Vue and Svelte entry points — plus the Vite plugin's `pages` shorthand that removes the `import.meta.glob` boilerplate — are covered in [Frontend Setup](/v4/luna-frontend). Configure the Vite plugin in `vite.config.js`:

```js
import luna from '@laraxgram/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
    plugins: [
        react(),
        luna(),
    ],
})
```

<a name="your-first-page"></a>
## Your First Page

Page components are ordinary framework components stored under `resources/js/Pages`. They receive their props from the controller. A React page:

```jsx
// resources/js/Pages/Users/Show.jsx
import { Head, Link } from '@laraxgram/react'

export default function Show({ user }) {
    return (
        <>
            <Head title={user.name} />

            <h1>{user.name}</h1>
            <p>{user.email}</p>

            <Link href="/users">Back to users</Link>
        </>
    )
}
```

Wire a [route](/v4/routing) to it. Any controller returning `Luna::render()` works, but for prop-less pages Luna adds a `Route::luna()` shortcut that renders a component directly:

```php
use LaraGram\Support\Facades\Route;

// Full control via a controller...
Route::get('/users/{user}', [UserController::class, 'show']);

// ...or the shorthand for a component with static props.
Route::luna('/about', 'About');
```

That's a complete round trip: a route, a controller returning a named component with props, and a page component that renders them. From here, add [links and visits](/v4/luna-routing), [forms](/v4/luna-forms), and richer [props](/v4/luna-pages).

<a name="where-to-go-next"></a>
## Where to Go Next

<div class="content-list" markdown="1">

- **[Pages & Props](/v4/luna-pages)** — rendering components, shared props, deferred/optional/merged props, and partial reloads.
- **[Routing & Visits](/v4/luna-routing)** — the `<Link>` component, manual router visits, redirects, prefetching, polling, and history.
- **[Forms](/v4/luna-forms)** — the `useForm` helper, the `<Form>` component, validation errors, file uploads, and [Precognition](/v4/precognition).
- **[Frontend Setup](/v4/luna-frontend)** — per-framework setup, persistent layouts, `<Head>`, title/meta, code splitting, the [Vite](/v4/vite) plugin, and server-side rendering.
- **[Telegram Mini Apps](/v4/luna-tma)** — building TMAs: init-data validation, the `telegram` guard, the reactive store, and native-button integration.
- **[Telegram Features](/v4/luna-tma-features)** — CloudStorage, biometrics, location, sensors, haptics, popups, sharing, invoices, and the test mock.

</div>
