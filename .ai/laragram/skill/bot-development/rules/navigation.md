# Navigation

Navigation remembers the steps each user took, in the cache per bot, chat and user, and takes them back. Use it for every menu and back button instead of one callback per screen or a "previous menu" kept by hand.

## Recording Steps

Mark the listens that are screens:

```php
Bot::onCommand('start', [MenuController::class, 'home'])->breadcrumb('home', 'Home');
Bot::onCallbackQueryData('settings', [MenuController::class, 'settings'])->breadcrumb('settings', 'Settings');
```

- `->breadcrumb(?$name, ?$title)`: the name defaults to the listen name, the title to the name.
- Reaching a named step already on the trail cuts the trail back to it; repeating the last step replaces it.
- `$middleware->trackNavigation()` in `bootstrap/app.php` records every bot listen (off by default). Exclude non-screens with `->withoutBreadcrumb()`.
- `navigation()->push($name, $title)` records a step from inside a handler.
- Do not use `->step()` for this: that is the Step Manager filter.

## Going Back

```php
Bot::onText('🔙 Back', fn () => back());      // one step, same as redirect()->back()
Bot::onText('🔝 Top', fn () => back(2));
Bot::onText('🏠 Home', fn () => back('home'));

return back(fallback: 'home');                 // a listen name or a closure, when there is nothing to go back to
```

- Inline buttons need no listen: `Make::back('🔙 Back')`, `Make::back('🏠 Home', 'home')`, `Make::back('🔙 Back', fallback: 'home')`. The step and fallback must fit Telegram's 64-byte callback data. `Make::breadcrumbs()` is a row of buttons for the named steps before the current one.
- Reply keyboards send text, so they need `Bot::onText('🔙 Back', fn () => back())`.
- In HTTP requests `back()` and `redirect()->back()` keep their usual redirect meaning.

## What It Does

Going back runs the stored update of that step through its listen again, with middleware and bindings, so handlers need no special code:

- A button press going back to a step that was a press keeps the current `callback_query()->id` and `->message`, so `answerCallbackQuery` and `editMessageText` act on the tapped message. A press going back to a non-press step is answered automatically, and so is one with nowhere to go.
- The Step Manager step is restored.
- Branch on `navigation()->isGoingBack()` only when a handler must behave differently, such as editing instead of sending.

## Reading the Trail

`navigation()` or the `Navigation` facade: `breadcrumbs(' › ')` (text), `trail()`, `current()`, `previous()`, `canGoBack($to)`, `clear()`. In Temple8: `@breadcrumbs(' / ')` and, inside `@row`, `@back('🔙 Back', 'home')`.

## With Other Components

- Conversations: `Back::make(onFirst: 'navigate')` (or `->back(onFirst: 'navigate')` on an inline conversation) leaves the conversation on the first question and goes back along the trail. `Flow::back('home')` does it from a callback or hook. Pressing `Make::back()` also leaves an active conversation.
- Telegram pagination: `$users->withBackButton('🔙 Back', 'home')`.
- Config: `config/navigation.php` (`store`, `prefix`, `lifetime`, `limit`). The trail must outlive the request, so never the `array` store.
- `bot_simulate_update` records steps like a real update: use the same `from.id` and `chat.id` across calls to walk a trail.
