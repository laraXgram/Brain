# Listening

## Where Listens Live

Bot listens are defined in the files registered with `withListener()` in `bootstrap/app.php` (by default `listens/bot.php`, which uses the `bot` middleware group). Closure console commands and schedules live in `listens/console.php`.

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withListener(
        bot: __DIR__.'/../listens/bot.php',
        commands: __DIR__.'/../listens/console.php',
    )
    ->create();
```

For several bots, bind listen files to connections from `config/bot.php`:

```php
->withListener(
    bot: [
        __DIR__.'/../listens/bot.php',                        // all connections
        __DIR__.'/../listens/shop.php' => 'shop-bot',         // one connection
        __DIR__.'/../listens/admin.php' => ['shop-bot', 'support-bot'],
    ],
    commands: __DIR__.'/../listens/console.php',
)
```

## Choose the Most Specific Verb

The `Bot` facade has a method per update kind. Use the narrowest one instead of a generic `onMessage`/`onUpdate` listen that branches internally.

```php
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Bot;

Bot::onCommand('start', function (Request $request) {
    $request->sendMessage(chat()->id, 'Welcome!');
});

Bot::onText('hello', fn (Request $request) => $request->sendMessage(chat()->id, 'Hi!'));

Bot::onPhoto(function (Request $request) {
    // ...
});

Bot::onCallbackQueryData('order:cancel:{id}', [OrderController::class, 'cancel']);

Bot::match(['TEXT', 'COMMAND'], 'help', [HelpController::class, 'show']);
```

Other useful verbs include `onReferral` (deep-link `/start` payloads), `onPaginate` (taps on a Telegram paginator's keyboard, see [`templates.md`](templates.md)), `onInlineQuery`, `onPreCheckoutQuery`, `onSuccessfulPayment`, `onMyChatMember`, `onChatJoinRequest`, `onWebAppData`, and entity listens such as `onUrl` or `onMention`. Search the docs (`listening` page) for the full list.

## Listen Parameters

Capture parts of the text or callback data with `{param}` segments (`{param?}` for optional ones). Parameters are injected by position after type-hinted dependencies. Constrain them instead of validating by hand:

```php
Bot::onText('user {id}', function (Request $request, string $id) {
    // ...
})->whereNumber('id');

Bot::onCallbackQueryData('plan:{plan}', [PlanController::class, 'choose'])
    ->whereIn('plan', ['free', 'pro']);
```

Define global constraints with `Bot::pattern('id', '[0-9]+')` in `AppServiceProvider::boot()`.

## Listen Model Binding

Type-hint a model whose variable name matches the parameter to resolve it automatically (`{order}` → `Order $order`). Use `{post:slug}` for another column, `scopeBindings()` for nested models, `withTrashed()` for soft-deleted records, and enums (`{status}` → `OrderStatus $status`) for backed enum cases. When no record matches, the listen does not run and nothing is sent to the user, so attach `->missing(fn (Request $request) => ...)` to reply (for example with `answerCallbackQuery`). Always check that the resolved model belongs to the current user before acting on it.

## Named Listens and Redirects

Name listens with `->name('orders.show')`. Build patterns with `listen('orders.show', ['id' => 1])` and redirect to another listen with `to_listen('orders.show')` or `redirect()->listen(...)`. `Bot::redirect('menu', 'main')` and `Bot::template('help', 'help')` are shortcuts for listens that only redirect or render a template.

## Groups, Scopes and Constraints

Share middleware, controllers, name prefixes, and pattern prefixes with groups. Limit listens to chat types with `scope()` / `outOfScope()` and to replies with `hasReply()` / `hasNotReply()`:

```php
Bot::scope('private')->middleware('throttle:bot')->group(function () {
    Bot::onCommand('settings', [SettingsController::class, 'show']);
});

Bot::scope(['group', 'supergroup'])->hasReply()->onText('ban', [ModerationController::class, 'ban']);
```

## Overlapping Listens

Dispatch is exclusive by default: the first matching listen wins. Mark listens with `->overlap()` (optionally with group names) only when several handlers must run for the same update, for example logging plus replying. Overlapping handlers run sequentially after the primary listen.

## Step Listens and Fallback

`Bot::onStep('awaiting_email', ...)` runs only while the user's current step matches (see the conversations and steps rule). `Bot::fallback(...)` handles updates no listen matched; keep it cheap and avoid replying to every message in groups.

## Rate Limiting

Define limiters in `AppServiceProvider::boot()` with `RateLimiter::for('bot', fn (Request $request) => Limit::perMinute(30)->by(user()->id))` and attach them with the `throttle:bot` middleware. Rate limiting protects your bot from abusive users; it is different from anti-flood, which paces your outgoing Telegram API calls.

## Listen Caching

Run `php laragram listen:cache` during deployment and `php laragram listen:clear` when listens change locally. Remember to clear the cache after editing listen files if it is active.
