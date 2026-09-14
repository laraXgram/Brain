# Users, Permissions and Localization

## The Bot Guard

While an update is handled, the `bot` guard identifies the sender by their Telegram id and loads the matching record through the `users` provider (`'column' => 'user_id'` in `config/auth.php`). There is no login step:

```php
use LaraGram\Support\Facades\Auth;

Bot::onCommand('profile', function (Request $request) {
    $user = Auth::user(); // or $request->user(); null when the sender has no users row

    // ...
});
```

- `Auth::user()` only returns a model for Telegram users stored in the `users` table. Register them first, typically on `/start`:

```php
Bot::onCommand('start', function (Request $request) {
    User::updateOrCreate(
        ['user_id' => user()->id],
        ['first_name' => user()->first_name, 'last_name' => user()->last_name, 'chat_id' => chat()->id],
    );

    return template('welcome');
});
```

- `user()` (the Telegram sender object from the update) and `Auth::user()` (your Eloquent model) are different things. Use `user()->id` to identify the sender and `Auth::user()` for application data.
- Web requests use the `web` (session) guard instead; Telegram Mini Apps use Luna's `telegram` guard. Do not use the `bot` guard in routes.

## Authorizing Listens

Gates and policies work the same as on the web side. Attach them to listens with `can`:

```php
Bot::onText('edit {post}', [PostController::class, 'edit'])->can('update', 'post');
Bot::onCommand('newpost', [PostController::class, 'create'])->can('create', Post::class);
```

Inside handlers use `Gate::allows()`, `$request->user()->can()`, or `Gate::authorize()`. Temple8 templates support `@can`, `@cannot`, and `@canany`. When authorization fails in a bot, reply with an explanation (or answer the callback query) instead of relying on a web-style 403 page.

## Chat-Member Status

LaraGram registers the Telegram chat-member statuses (`creator`, `administrator`, `member`, `restricted`, `left`, `kicked`) as abilities, so group admin commands need no custom code:

```php
Bot::scope(['group', 'supergroup'])->group(function () {
    Bot::onCommand('ban', [ModerationController::class, 'ban'])->can(['administrator', 'creator']);
});
```

- The status driver is configured under `status` in `config/auth.php`: `live` (default, calls `getChatMember` once per update), `eloquent`, `database`, or `cache`. Writable drivers observe `chat_member` / `my_chat_member` updates to stay in sync; those updates are only delivered when the bot is a chat administrator and the update types are in `allowed_updates`.
- Outside a listen, use `app('auth.status')->statusFor($userId, $chatId)` or `->is('administrator')`.
- Remember that the bot itself needs admin rights for moderation methods (`banChatMember`, `restrictChatMember`, `deleteMessage` in groups).

## Localization

Translation files live in `lang/` (publish the defaults with `php laragram lang:publish`). Use `__('messages.welcome', ['name' => user()->first_name])` and `trans_choice('messages.orders', $count)` in listens, controllers, and templates.

Set the locale per update in a bot middleware, because every update may come from a user with a different language:

```php
class SetLocaleFromTelegram
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Auth::user()?->locale ?? user()?->language_code;

        if (in_array($locale, ['en', 'fa'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
```

Register it with `$middleware->bot(append: [SetLocaleFromTelegram::class])`. Under Surge the locale is reset between updates, but queued jobs and scheduled messages must set the locale of the recipient themselves (`App::setLocale()` or `__('...', [], $user->locale)`).
