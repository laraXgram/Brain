# Luna: Frontend Setup

<a name="introduction"></a>
## Introduction

Luna is framework-agnostic: the same PHP adapter and core runtime power React, Vue 3, and Svelte apps. This chapter covers the client-side setup for each — booting the app, resolving page components, persistent layouts, managing the document `<head>`, code splitting, the [Vite](/v4/vite) plugin, progress indicators, asset versioning, and server-side rendering.

Read the [installation section](/v4/luna#client-side-installation) of the introduction first for package names.

<a name="creating-the-app"></a>
## Creating the App

Every Luna frontend boots through `createLunaApp`. It takes a `resolve` callback (name → component) and a `setup` callback (mount the app). The three adapters differ only in how they mount.

<a name="react-setup"></a>
### React

```jsx
// resources/js/app.jsx
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

<a name="vue-setup"></a>
### Vue 3

```js
// resources/js/app.js
import { createLunaApp } from '@laraxgram/vue3'
import { createApp, h } from 'vue'

createLunaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
        return pages[`./Pages/${name}.vue`]
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
```

<a name="svelte-setup"></a>
### Svelte

```js
// resources/js/app.js
import { createLunaApp } from '@laraxgram/svelte'
import { mount } from 'svelte'

createLunaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true })
        return pages[`./Pages/${name}.svelte`]
    },
    setup({ el, App, props }) {
        mount(App, { target: el, props })
    },
})
```

<a name="createlunaapp-options"></a>
### App Options

`createLunaApp` accepts more than `resolve` and `setup`:

| Option | Description |
|--------|-------------|
| `resolve` | Maps a component name to its module (required). |
| `setup` | Mounts the resolved `App` into `el` (required). |
| `id` | The root element id (default `app`). |
| `title` | A callback `(title, page) => string` to template every page title — e.g. append `· MyApp`. |
| `progress` | [Progress bar](#progress-indicator) options, or `false` to disable. |
| `page` | A pre-resolved page object (used in [SSR](#server-side-rendering)). |
| `layout` | A default [layout](#persistent-layouts) resolver applied to every page. |

<a name="code-splitting"></a>
### Code Splitting

The `import.meta.glob` above with `{ eager: true }` bundles all pages together. Drop `eager` (and make `resolve` async) to split each page into its own chunk, loaded on demand:

```js
resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.jsx')
    return pages[`./Pages/${name}.jsx`]()
},
```

<a name="vite-plugin"></a>
## The Vite Plugin

The `@laraxgram/vite` plugin removes boilerplate and powers dev-time SSR. Register it in `vite.config.js` alongside your framework plugin:

```js
import luna from '@laraxgram/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
    plugins: [react(), luna()],
})
```

<a name="pages-shorthand"></a>
### Pages Shorthand

Instead of writing `import.meta.glob` in every project, pass a `pages` directory to `createLunaApp` and let the plugin generate the resolver for you:

```js
createLunaApp({
    pages: './Pages',
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
})
```

The plugin detects your adapter (Vue/React/Svelte) from your imports and transforms `pages` into the correct glob. Add support for other frameworks with the plugin's `frameworks` option.

<a name="persistent-layouts"></a>
## Persistent Layouts

By default each Luna visit swaps the entire page component, which unmounts and remounts your layout — resetting its state (scroll position, open menus, media playback). A **persistent layout** stays mounted across visits. Assign one by setting `layout` on the page component:

```jsx
// resources/js/Pages/Dashboard.jsx
import AppLayout from '@/Layouts/AppLayout'

function Dashboard({ stats }) {
    return <StatsGrid stats={stats} />
}

Dashboard.layout = (page) => <AppLayout>{page}</AppLayout>

export default Dashboard
```

Nest multiple layouts by returning an array, and set a default layout for every page via the `layout` option on `createLunaApp`. The `setLayoutProps` / `resetLayoutProps` helpers let you pass data into a persistent layout from the active page. Vue and Svelte use their own idiomatic layout conventions exported from the adapter.

<a name="the-head-component"></a>
## Managing the Head

Use the `<Head>` component to set the document title and inject `<meta>` / `<link>` tags per page:

```jsx
import { Head } from '@laraxgram/react'

<Head title="Users">
    <meta name="description" content="Manage your users" />
</Head>
```

Set a global title template with the `title` option on `createLunaApp` so every `<Head title>` gets a suffix:

```js
createLunaApp({
    title: (title) => (title ? `${title} · MyApp` : 'MyApp'),
    // ...
})
```

For [server-side rendering](#server-side-rendering) to emit these tags into the initial HTML, keep the `@lunaHead` directive in your [root template](/v4/luna#root-template). Vue and Svelte export the same `<Head>` component.

<a name="progress-indicator"></a>
## Progress Indicator

Luna ships a top-of-page loading bar that appears during visits taking longer than a short delay. It's enabled by default; configure or disable it via the `progress` option:

```js
createLunaApp({
    progress: {
        color: '#29d',
        delay: 250,
        showSpinner: false,
    },
    // progress: false, // to disable entirely
})
```

For custom indicators, subscribe to the [router's `progress` events](/v4/luna-routing#events).

<a name="asset-versioning"></a>
## Asset Versioning

When you deploy new frontend assets, browsers with the old bundle cached could render a stale page against new server props. Luna guards against this with an **asset version**: each response carries a version string, and when the client detects a mismatch on a visit, it performs a full page reload to fetch the new assets.

Set the version from a [service provider](/v4/providers) — typically a hash of your manifest:

```php
use LaraGram\Luna\Luna;

Luna::version(fn () => md5_file(public_path('build/manifest.json')));
```

The [Vite](/v4/vite) integration wires a sensible default, so most apps never set this manually.

<a name="server-side-rendering"></a>
## Server-Side Rendering

Server-side rendering (SSR) pre-renders the initial page to HTML on the server, improving first-paint and making pages crawlable. Luna renders through a small Node (or Bun) service.

<a name="ssr-entry"></a>
### The SSR Entry Point

Create `resources/js/ssr.jsx` (auto-detected by the Vite plugin) that renders the page to a string:

```jsx
// resources/js/ssr.jsx
import { createLunaApp } from '@laraxgram/react'
import createServer from '@laraxgram/react/server'
import { renderToString } from 'react-dom/server'

createServer((page) =>
    createLunaApp({
        page,
        render: renderToString,
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true })
            return pages[`./Pages/${name}.jsx`]
        },
        setup: ({ App, props }) => <App {...props} />,
    }),
)
```

Each adapter exports its own `server` entry (`@laraxgram/react/server`, `@laraxgram/vue3/server`, `@laraxgram/svelte/server`).

<a name="ssr-config-and-commands"></a>
### Configuration & Commands

Enable and configure SSR in `config/luna.php` under the `ssr` key — the runtime (`node`/`bun`), the render service `url`, the bundle path, and error handling. In production, build the SSR bundle and run the service:

```shell
# Build the client and SSR bundles.
npm run build

# Manage the SSR render service.
php laragram luna:start-ssr
php laragram luna:stop-ssr
php laragram luna:check-ssr
```

During development the Vite plugin runs an in-process SSR endpoint, so you don't need a separate service — just `npm run dev`. When SSR rendering fails, Luna gracefully falls back to client-side rendering; set `ssr.throw_on_error` to fail loudly in E2E tests, or listen for the `SsrRenderFailed` event.

> [!NOTE]
> Telegram Mini Apps can use SSR too, but because they only ever run inside the authenticated Telegram client, first-paint SEO is irrelevant — most TMAs leave SSR off. See [Telegram Mini Apps](/v4/luna-tma).

<a name="next"></a>
## Next Steps

Your SPA is fully configured. To build a Telegram Mini App on top of it, continue to [Telegram Mini Apps](/v4/luna-tma).
