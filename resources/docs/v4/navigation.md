# Navigation

<a name="introduction"></a>
## Introduction

Most bots are menus: the user opens a list, picks an item, opens its settings, then wants to go back. Telegram has no history of its own, so every bot ends up carrying "where was this user" around by hand and writing one callback per back button.

LaraGram's navigation remembers the steps each user took and takes them back with one call:

```php
use LaraGram\Support\Facades\Bot;

Bot::onCommand('start', [MenuController::class, 'home'])->breadcrumb('home', 'Home');
Bot::onCallbackQueryData('settings', [MenuController::class, 'settings'])->breadcrumb('settings', 'Settings');
Bot::onCallbackQueryData('language', [MenuController::class, 'language'])->breadcrumb('language', 'Language');
```

```php
use LaraGram\Keyboard\Make;

$request->sendMessage(chat()->id, 'Pick a language', reply_markup: inlineKeyboardMarkup(
    Make::row(Make::callbackData('فارسی', 'lang:fa'), Make::callbackData('English', 'lang:en')),
    Make::row(Make::back('🔙 Back')),
)->get());
```

The `Make::back` button needs no listen of its own: pressing it runs the previous step again.

The trail of steps lives in your application's [cache](/v4/cache), keyed by the bot, the chat and the user, so two users in the same group, or one user in two chats, each have their own.

<a name="recording-steps"></a>
## Recording Steps

<a name="naming-a-step"></a>
### Naming a Step

Call `breadcrumb` on a listen to make it a step. The first argument is the name you can go back to, and the second is the title it is shown with:

```php
Bot::onCommand('start', $handler)->breadcrumb('home', 'Home');
```

Both are optional. Without a name the step takes the [name of the listen](/v4/listening#named-listens), and without a title it is shown with its name. A step with neither still counts when going back; it is only left out of the text of the trail.

Coming back to a named step cuts the trail back to it, so opening "Home" from a deep menu leaves `Home` on the trail, not `Home › Settings › Language › Home`. A step that repeats the last one takes its place.

<a name="recording-every-listen"></a>
### Recording Every Listen

Instead of marking listens one by one, you may record every bot listen with the `trackNavigation` method in your application's `bootstrap/app.php` file. It is off by default:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trackNavigation();
})
```

Leave out the listens that are not screens, such as a catch-all fallback, with `withoutBreadcrumb`:

```php
Bot::onText('ping', $handler)->withoutBreadcrumb();
```

A listen that only asks to go back does not count as a step, even when it is tracked.

<a name="recording-a-step-by-hand"></a>
### Recording a Step by Hand

When only a part of a handler is a screen of its own, record the step yourself:

```php
navigation()->push('receipt', 'Receipt');
```

<a name="going-back"></a>
## Going Back

The `back` helper and `redirect()->back()` take the user back while an update is being handled. In an HTTP request they keep their usual meaning:

```php
Bot::onText('🔙 Back', fn () => back());               // one step
Bot::onText('🔝 Top', fn () => back(2));                // two steps
Bot::onText('🏠 Home', fn () => back('home'));          // the step named "home"
Bot::onText('Back', fn () => redirect()->back());       // the same as back()
```

Going back further than the first step lands on the first step. Going back to a step that is not on the trail changes nothing.

You don't have to return the result, but it runs at the end of the statement when you don't. Return it when you chain onto it:

```php
return back()->with('notice', 'Saved.');
```

<a name="when-there-is-nothing-to-go-back-to"></a>
### When There Is Nothing to Go Back To

A user on the first step, or one whose trail expired, has nowhere to go. Nothing happens by default (a pressed button is still answered, so it stops spinning). Pass a fallback to do something else, either the name of a listen or a closure:

```php
return back(fallback: 'home');

return redirect()->back(1, fn () => $request->sendMessage(chat()->id, 'Send /start to begin.'));
```

<a name="what-going-back-does"></a>
### What Going Back Does

Going back runs the update of that step through its listen again, with its middleware, parameters and model bindings, so the handler builds the screen as it did the first time. Going back is therefore free of any special code in your handlers.

A few things are kept up to date on the way:

<div class="content-list" markdown="1">

- When a button press takes the user back to a step that was also a button press, the handler sees the press being handled. `callback_query()->id` and `callback_query()->message` are the current ones, so `answerCallbackQuery` and `editMessageText` act on the message the user just tapped.
- When a button press takes the user back to a step that was not a button press, the press is answered for you.
- The [Step Manager](/v4/step) step the user was on is restored, so listens scoped with `->step()` match again.

</div>

To tell the two ways of reaching a handler apart, such as editing a message instead of sending a new one:

```php
if (navigation()->isGoingBack()) {
    // ...
}
```

<a name="back-buttons"></a>
## Back Buttons

`Make::back` creates an inline button that goes back without a listen. It takes the same target as the `back` helper:

```php
use LaraGram\Keyboard\Make;

Make::back('🔙 Back');                       // one step
Make::back('⏫ Two steps', 2);
Make::back('🏠 Home', 'home');
Make::back('🔙 Back', style: 'primary');
Make::back('🔙 Back', fallback: 'home');     // the listen to run when there is nothing to go back to
```

The name of the step and the fallback travel in the button's callback data, which Telegram limits to 64 bytes, so an `InvalidArgumentException` is thrown for longer ones. Pressing the button also leaves an active [conversation](/v4/conversations).

`Make::breadcrumbs` creates a row with a button for every named step before the current one:

```php
inlineKeyboardMarkup(
    Make::breadcrumbs(),
    Make::row(Make::callbackData('Save', 'save')),
);
```

Reply keyboards send plain text, so give them a listen:

```php
replyKeyboardMarkup(Make::row(Make::text('🔙 Back')));

Bot::onText('🔙 Back', fn () => back());
```

<a name="showing-the-trail"></a>
## Showing the Trail

The `breadcrumbs` method returns the trail as text, and the `navigation` helper or the `Navigation` facade give you the rest:

```php
navigation()->breadcrumbs();          // Home › Settings › Language
navigation()->breadcrumbs(' / ');     // Home / Settings / Language
```

| Method | Description |
| --- | --- |
| `trail()` | The steps of the trail, the first one first |
| `current()` | The step the user is on |
| `previous()` | The step `back()` would go to |
| `canGoBack($to = 1)` | Whether going back would land anywhere |
| `isGoingBack()` | Whether the handler was reached by going back |
| `push($name, $title)` | Record the current update as a step |
| `clear()` | Forget the trail |

Each step is a `LaraGram\Listening\Navigation\Breadcrumb` with `name`, `title`, `update`, `state` and `time` properties, and a `label()` method.

<a name="in-templates"></a>
## In Templates

[Temple8](/v4/temple8) has a directive for the text and one for the button:

```blade
@text()
    @breadcrumbs(' / ')

    Pick a language.
@endText

@keyboard()
    @row()
        @col('فارسی', callback_data: 'lang:fa')
        @back('🔙 Back')
    @endRow
@endKeyboard()
```

<a name="other-components"></a>
## Other Components

<a name="in-conversations"></a>
### In Conversations

A [conversation](/v4/conversations#back-navigation) keeps its own back button between questions. With `onFirst: 'navigate'`, pressing it on the first question leaves the conversation and goes back along the trail:

```php
Back::make(label: '🔙 Back', onFirst: 'navigate');
```

A callback or a hook may do the same at any point with `Flow::back`:

```php
return Flow::back('home');
```

<a name="in-paginated-lists"></a>
### In Paginated Lists

The keyboard of a [Telegram paginator](/v4/pagination#telegram-bot-pagination) can carry a back button under its page buttons:

```php
User::telegramPaginate(10)->withBackButton('🔙 Back');
```

<a name="configuration"></a>
## Configuration

Publish the `config/navigation.php` file with `php laragram config:publish navigation` to change the defaults:

| Option | Description | Default |
| --- | --- | --- |
| `store` | The cache store of the trails (`NAVIGATION_STORE`) | The default store |
| `prefix` | The prefix of the cache keys | `navigation` |
| `lifetime` | The seconds a trail is remembered after its last step | `604800` |
| `limit` | The steps remembered per user; older ones are forgotten first | `50` |

The trail has to outlive the request, so don't use the `array` store: every step keeps a copy of its update.
