@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
use LaraGram\Brain\Discovery\Enums\Approach;
@endphp
# Do Things the LaraGram Way

- Use `{{ $assist->commanderCommand('make:') }}` commands to create new files (i.e. models, migrations, conversations, templates, controllers, middleware, jobs, commands, etc.). You can list the generators using `{{ $assist->commanderCommand('list make') }}` and check their parameters with `{{ $assist->commanderCommand('[command] --help') }}`.
- If you're creating a generic PHP class, use `{{ $assist->commanderCommand('make:class') }}`.
- `make:controller` and `make:middleware` generate **bot** classes (`app/Controllers`, `app/Middleware`, `LaraGram\Request\Request`) by default. Pass `--web` for HTTP controllers and middleware (`app/Http/Controllers`, `app/Http/Middleware`, `LaraGram\Http\Request`); `--resource`, `--api`, `--model`, `--singleton`, `--parent` and `--requests` imply `--web`.
- Pass `--no-interaction` to all Commander commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.
- LaraGram mirrors many Laravel APIs, but not all of them. Before using an API you know from Laravel, confirm it exists in LaraGram with `search-docs` or by reading the framework source in `vendor/laraxgram/core/src`.
- Telegram updates are handled by **listens** (`listens/bot.php`, registered through `withListener()` in `bootstrap/app.php`) and the `LaraGram\Request\Request` object. Browser requests are handled by **routes** (`routes/web.php`, registered through `withRouting()`) and `LaraGram\Http\Request`. Do not mix the two: never put Telegram handlers in `routes/` or web handlers in `listens/`.

@scoped(['listens/**', 'app/Controllers/**', 'app/Conversations/**', 'app/templates/**', 'app/Middleware/**'])
## Bot Development

- IMPORTANT: Activate `bot-development` whenever you write or change listens, bot controllers, keyboards, Temple8 templates, conversations, steps, or bot middleware.
- Reply through the request's Telegram methods (`$request->sendMessage(chat()->id, 'Hello')`) and read the current update with the `chat()`, `user()`, `sender()`, `message()`, and `callback_query()` helpers (`user()` is null for channel posts; `sender()` returns the sender chat for messages sent on behalf of a chat).
- Use Conversations (`{{ $assist->commanderCommand('make:conversation') }}`) or the Step Manager (`Step::set()` + `Bot::onStep()`) for multi-message flows instead of storing ad-hoc state in the cache.
@if($assist->bot()->usesMultipleBots())
- This application serves several bot connections ({{ implode(', ', $assist->bot()->connections()) }}; default: `{{ $assist->bot()->defaultConnection() }}`). Bind listen files to connections with `path => connection` pairs in `withListener(bot: [...])` (or `Bot::middleware(...)->forConnections([...])->group(...)`), and pick the connection explicitly for calls made outside an update (jobs, commands).
@endif
@if($assist->bot()->usesAntiFlood())
- Anti-flood pacing is enabled: never add `sleep()` between Bot API calls; broadcasts are paced automatically.
@endif
@if($assist->bot()->supportsBroadcasting())
- To send Bot API calls or a template to many chats (all users, all groups, the members of a group, a filtered segment), use the `Broadcast` facade (`Broadcast::users()->language('fa')->template('promo')->localized()->queue()`), never a loop over chat ids. Bot API methods take Laraquest's own parameters without the recipient. Define custom audiences in `listens/channels.php`, schedule with `later()` / `between()` / `Schedule::broadcast()`, and preview with `->count()` and `->test($chatId)` before sending.
@if(! $assist->bot()->tracksChats())
- Chat tracking is not set up yet: before relying on `Broadcast::users()` / `groups()`, run `{{ $assist->commanderCommand('install:broadcasting') }}` and migrate.
@endif
@endif
- Keep bot state per user in the cache or database. Never rely on PHP static properties or globals between updates.
@php
$conventions = array_values(array_filter([
    match (true) {
        $assist->usesApproach(Approach::ListenActionController) => 'Listens point to bot controllers (`[StartController::class, \'start\']` or invokable controllers in `app/Controllers`), not closures.',
        $assist->usesApproach(Approach::ListenActionClosure) => 'Listens are written as closures in the listen files.',
        default => null,
    },
    match (true) {
        $assist->usesApproach(Approach::UpdateAccessHelpers) => 'The current update is read with the `chat()`, `user()`, `message()`, and `callback_query()` helpers.',
        $assist->usesApproach(Approach::UpdateAccessRequest) => 'The current update is read from the request object (`$request->message->chat->id`), not the helpers.',
        default => null,
    },
    match (true) {
        $assist->usesApproach(Approach::KeyboardBuilder) => 'Keyboards are built with the `Keyboard` facade and `LaraGram\\Keyboard\\Make` (or Temple8 `@keyboard`), not raw `reply_markup` arrays.',
        $assist->usesApproach(Approach::KeyboardArray) => 'Keyboards are written as raw `inline_keyboard` / `keyboard` arrays.',
        default => null,
    },
    match (true) {
        $assist->usesApproach(Approach::ReplyTemplate) => 'Messages are rendered with Temple8 templates (`template(\'name\', $data)`) from `'.$assist->bot()->templatesPath().'`.',
        $assist->usesApproach(Approach::ReplyDirect) => 'Messages are sent directly with Bot API methods (`$request->sendMessage(...)`) rather than Temple8 templates.',
        default => null,
    },
    match (true) {
        $assist->usesApproach(Approach::MultiStepConversation) => 'Multi-step flows use Conversations in `'.$assist->bot()->conversationsPath().'`.',
        $assist->usesApproach(Approach::MultiStepStepManager) => 'Multi-step flows use the Step Manager (`Step::set()` + `Bot::onStep()`).',
        default => null,
    },
]));
@endphp
@if($conventions !== [])

### Conventions Detected in This Application

These were detected in the existing code; follow them unless the user asks otherwise:

@foreach($conventions as $convention)
- {!! $convention !!}
@endforeach
@endif
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

## Commander, Scheduling and Processes

- Activate `commander-development` when creating or changing console commands, closure commands in `listens/console.php`, prompts, scheduled tasks, or shell processes.
- Scheduled tasks, jobs, and commands have no incoming update: `chat()`, `user()`, and `Auth::user()` are `null` there, so pass chat ids and the bot connection explicitly.

## Vite Error

- If you receive a "Unable to locate file in Vite manifest" error, you can run `{{ $assist->nodePackageManagerCommand('run build') }}` or ask the user to run `{{ $assist->nodePackageManagerCommand('run dev') }}` or `{{ $assist->composerCommand('run dev') }}`.
