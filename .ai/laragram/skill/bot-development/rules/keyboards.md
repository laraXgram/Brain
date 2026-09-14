# Keyboards

## Build Keyboards With the Builder

Use the `Keyboard` facade and the `LaraGram\Keyboard\Make` button factory instead of hand-writing `reply_markup` arrays:

```php
use LaraGram\Keyboard\Make;
use LaraGram\Support\Facades\Keyboard;

$keyboard = Keyboard::inlineKeyboardMarkup(
    Make::row(
        Make::callbackData('Confirm', 'order:confirm:'.$order->id),
        Make::callbackData('Cancel', 'order:cancel:'.$order->id),
    ),
    Make::row(
        Make::url('Open website', route('orders.show', $order)),
    ),
);

$request->sendMessage(chat()->id, 'Confirm your order?', reply_markup: $keyboard->get());
```

`get()` returns the JSON the Bot API expects (`get(true)` returns an array).

## Keyboard Types

| Type | Builder |
| --- | --- |
| Inline buttons attached to a message | `Keyboard::inlineKeyboardMarkup(...$rows)` |
| Reply keyboard under the input field | `Keyboard::replyKeyboardMarkup(...$rows)` |
| Remove the reply keyboard | `Keyboard::replyKeyboardRemove()` |
| Ask the user to reply | `Keyboard::forceReply($placeholder)` |

Common buttons: `Make::callbackData($text, $data)`, `Make::url($text, $url)`, `Make::webApp($text, $url)` (opens a Mini App), `Make::copyText($text, $copy)`, `Make::switchInlineQuery($text, $query)`, `Make::text($text)`, `Make::requestContact($text)`, `Make::requestLocation($text)`, `Make::requestUsers($text, ...)`, `Make::requestChat($text, ...)`, `Make::pay($text)`.

Set options with `$keyboard->setOptions(['resize_keyboard' => true, 'one_time_keyboard' => true])`, and edit rows with `appendRow()`, `prependRow()`, `editRow()`, `removeRow()`.

## Callback Data

- Telegram limits `callback_data` to 64 bytes. Encode a short action prefix and ids (`order:cancel:42`), not serialized payloads.
- Handle presses with a matching listen, constrain the parameters, and verify ownership before acting:

```php
Bot::onCallbackQueryData('order:cancel:{order}', function (Request $request, Order $order) {
    abort_unless($order->user_id === user()->id, 403);

    $order->cancel();

    $request->answerCallbackQuery(callback_query()->id, text: 'Order cancelled');
})->whereNumber('order');
```

- Edit the message that contains the keyboard (`editMessageText`, `editMessageReplyMarkup`) instead of sending a new message on every press.
- Do not use the `paginate:` prefix for your own buttons; Telegram pagination uses `paginate:<key>:<page>`.

## Keyboards in Templates

When a message is rendered with Temple8, build its keyboard with the `@keyboard`, `@row`, and `@col` directives instead (see the templates rule).
