@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
@scoped(['app/Http/**', 'routes/**', 'resources/js/**', 'resources/views/**'])
# Luna

- Luna builds single-page frontends (React, Vue, or Svelte) on top of LaraGram's web routes and controllers, and is the way to build Telegram Mini Apps. There is one router (LaraGram's): controllers return `Luna::render('Users/Show', $props)` instead of a Blade view, and pages live in `{{ $assist->luna()->pagesDirectory() }}`.
- ALWAYS use `search-docs` for Luna APIs (`packages: ['laraxgram/luna']`); Luna is a LaraGram package and its API differs from other SPA adapters you may know.
- Share global props in the Luna middleware (`{{ $assist->commanderCommand('luna:middleware') }}` generates it) or with `Luna::share()`. Use `Luna::defer()`, `Luna::optional()`, `Luna::merge()`, `Luna::always()`, `Luna::once()`, and `Luna::scroll()` for lazy or partial props, and `Route::luna('/about', 'About')` for pages without a controller.
- Validation errors from `$request->validate()` and form requests are shared with the page automatically; redirect back after a successful `post`/`put`/`delete` instead of returning JSON.
@if($assist->hasPackage(\LaraGram\Brain\Support\PackageRegistry::LUNA_REACT))
- IMPORTANT: Activate `luna-react-development` when working with Luna React pages, forms, or navigation.
@elseif($assist->hasPackage(\LaraGram\Brain\Support\PackageRegistry::LUNA_VUE))
- IMPORTANT: Activate `luna-vue-development` when working with Luna Vue pages, forms, or navigation.
@elseif($assist->hasPackage(\LaraGram\Brain\Support\PackageRegistry::LUNA_SVELTE))
- IMPORTANT: Activate `luna-svelte-development` when working with Luna Svelte pages, forms, or navigation.
@endif

## Telegram Mini Apps

- IMPORTANT: Activate `luna-tma-development` whenever a page runs inside Telegram as a Mini App: the `telegram` middleware, init data, `tg_user()`, the `telegram` auth guard, `@telegramWebApp`, `bootstrapTelegram()`, native buttons, or `Luna::answerQuery()` / `Luna::shareMessage()`.
- Protect Mini App routes with the `telegram` middleware and read the user with `tg_user()`. Never trust user ids sent by the client, and never expose the bot token to the frontend bundle.
@endscoped
