@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# Do Things the LaraGram Way

- Use `{{ $assist->commanderCommand('make:') }}` commands to create new files (i.e. models, migrations, conversations, templates, controllers, middleware, jobs, commands, etc.). You can list the generators using `{{ $assist->commanderCommand('list make') }}` and check their parameters with `{{ $assist->commanderCommand('[command] --help') }}`.
- If you're creating a generic PHP class, use `{{ $assist->commanderCommand('make:class') }}`.
- Pass `--no-interaction` to all Commander commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.
- LaraGram mirrors many Laravel APIs, but not all of them. Before using an API you know from Laravel, confirm it exists in LaraGram with `search-docs` or by reading the framework source in `vendor/laraxgram/core/src`.
- Telegram updates are handled by **listens** (`listens/bot.php`, registered through `withListener()` in `bootstrap/app.php`) and the `LaraGram\Request\Request` object. Browser requests are handled by **routes** (`routes/web.php`, registered through `withRouting()`) and `LaraGram\Http\Request`. Do not mix the two: never put Telegram handlers in `routes/` or web handlers in `listens/`.

@scoped(['listens/**', 'app/Controllers/**', 'app/Conversations/**', 'app/templates/**', 'app/Middleware/**'])
## Bot Development

- IMPORTANT: Activate `bot-development` whenever you write or change listens, bot controllers, keyboards, Temple8 templates, conversations, steps, or bot middleware.
- Reply through the request's Telegram methods (`$request->sendMessage(chat()->id, 'Hello')`) and use the `chat()`, `user()`, `message()`, and `callback_query()` helpers to read the current update.
- Prefer Temple8 templates (`template('name', $data)`) for messages with formatting or keyboards, and the `Keyboard`/`Make` builder over hand-written `reply_markup` arrays, when the project already uses them.
- Use Conversations (`{{ $assist->commanderCommand('make:conversation') }}`) or the Step Manager (`Step::set()` + `Bot::onStep()`) for multi-message flows instead of storing ad-hoc state in the cache.
- Keep bot state per user in the cache or database. Never rely on PHP static properties or globals between updates.
@endscoped

@scoped(['app/Models/**'])
### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `{{ $assist->commanderCommand('make:model --help') }}` to check the available options.
- Telegram identifiers (`user_id`, `chat_id`) can exceed 32-bit integers. Store them in `bigInteger` columns.
@endscoped

@scoped(['app/Http/**', 'routes/**'])
## Web Routes & APIs

- For JSON APIs, default to Eloquent API Resources unless existing routes do not use them, then follow the existing application convention.
- When generating links to other pages, prefer named routes and the `route()` function. For bot listens, name them (`->name()`) and redirect between them with `to_listen()`.
@endscoped

## Vite Error

- If you receive a "Unable to locate file in Vite manifest" error, you can run `{{ $assist->nodePackageManagerCommand('run build') }}` or ask the user to run `{{ $assist->nodePackageManagerCommand('run dev') }}` or `{{ $assist->composerCommand('run dev') }}`.
