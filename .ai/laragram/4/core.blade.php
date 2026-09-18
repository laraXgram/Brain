@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram 4

- CRITICAL: ALWAYS use `search-docs` to find the version-specific documentation needed to implement features.
- LaraGram 4 requires PHP 8.5 and keeps the LaraGram 3 bot layer largely intact. Its additions are a web layer (routing, HTTP requests and responses, HTTP client, Blade, views, Vite, sessions), Luna frontends and Telegram Mini Apps, MTProto user and bot clients, Conversations, Eloquent API Resources, pagination (including Telegram pagination), and Precognition.

## Application Structure

- The application is configured in `bootstrap/app.php` with `Application::configure()`:
  - `withListener(bot: ..., client: ..., commands: ...)` registers the bot listen files (`listens/bot.php`), MTProto client listen files, and closure console commands (`listens/console.php`). A listen file may be bound to specific bot connections with `path => 'connection'` pairs.
  - `withRouting(web: ..., api: ..., health: ...)` registers web routes, only when the application serves HTTP.
  - `withBroadcasting(__DIR__.'/../listens/channels.php')` loads broadcast audiences and WebSocket channel authorization (for every entry point, including queue workers).
  - `withMiddleware()` and `withExceptions()` configure middleware and exception handling.
- Bot connections (tokens, webhook URLs, secret tokens) live in `config/bot.php`; Bot API request defaults live in `config/laraquest.php`; anti-flood throttling is configured under `bot.anti_flood`; broadcast connections, the broadcast store (`database`, `redis` or `null`), chat tracking and progress storage live in `config/broadcasting.php`.
- `public/index.php` sends Telegram updates (JSON bodies with an `update_id`) to the bot kernel and every other request to the web kernel.
- Templates live in `app/templates` (`*.t8.php`, Temple8), conversations in `app/Conversations`, bot controllers in `app/Controllers`, and Blade views in `resources/views`.
- Service providers are listed in `bootstrap/providers.php`. Command classes live in `app/Console/Commands`; closure commands and schedules live in `listens/console.php`.

## Running the Bot

- Local development: `{{ $assist->commanderCommand('serve') }}` serves the application (default port 9000). Point the webhook at a public tunnel URL with `{{ $assist->commanderCommand('webhook:set') }}` only when the user asks.
- Production: deploy behind a web server (or LaraGram Surge), run `{{ $assist->commanderCommand('optimize') }}`, and keep queue workers and the scheduler running.
- A self-hosted Bot API server can be started with `{{ $assist->commanderCommand('start:apiserver') }}` when `api_id` and `api_hash` are configured.

## Telegram Pagination

- Paginate bot screens with `Model::telegramPaginate(perPage: 10, key: 'users')` (numbered) or `simpleTelegramPaginate()` (previous / next). Handle navigation taps with a `Bot::onPaginate('users', fn (Request $request, int $page) => ...)` listen that re-renders the same template with `page: $page`. In the template, `@paginate($paginator)` attaches the keyboard and edits the message in place while the reader moves between pages.
