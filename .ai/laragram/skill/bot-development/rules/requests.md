# Requests and Replies

## Reading the Update

Type-hint `LaraGram\Request\Request` in listen closures and controller methods. Read update fields as dynamic properties or with dot notation:

```php
$text = $request->message->text;
$text = $request->input('message.text', '');

if ($request->has('callback_query')) {
    // ...
}

$validated = $request->validate([
    'message.text' => 'required|string|max:255',
]);
```

Use the global helpers for the common parts of any update. They work across messages, edited messages, callback queries, and other update types:

| Helper | Returns |
| --- | --- |
| `chat()` | The chat the update belongs to (`chat()->id`, `chat()->type`) |
| `user()` | The user who caused the update (`user()->id`, `user()->first_name`); `null` for channel posts and anonymous reactions or votes |
| `sender()` | The actual sender: a user, or the chat a message was sent on behalf of (`sender_chat`, `actor_chat`, `voter_chat`) |
| `message()` | The message object, when the update carries one (`null` for callback queries from inline-mode messages) |
| `callback_query()` | The callback query, when a button was pressed |
| `text()` | The message text, or the caption of a media message |
| `bot_connection()` | The bot connection handling the update |

These helpers read the update currently being handled. They return `null` in jobs, commands, and scheduled tasks — pass ids explicitly there. Fields Telegram marks optional are `null` when absent, so use `user()?->id` in code that can run for channel posts. A message sent on behalf of a chat in a group (an anonymous admin, a linked channel) carries a placeholder `from` user, so use `sender()` to identify who sent it.

`$request->scope()` returns the chat type (`private`, `group`, `supergroup`, `channel`) and `$request->listenIs('orders.*')` checks the matched listen name.

## Replying

Every Telegram Bot API method is available on the request, with the method's parameters as arguments. Prefer named arguments for optional parameters:

```php
$request->sendMessage(chat()->id, 'Order confirmed.', parse_mode: 'html');

$request->editMessageText(
    text: 'Updated!',
    chat_id: chat()->id,
    message_id: callback_query()->message->message_id,
);

$request->answerCallbackQuery(callback_query()->id, text: 'Saved');
```

`$request->call('methodName', [...])` calls a method by name. Use `search-docs` with `packages: ['laraxgram/laraquest']` to look up a method's parameters and return type instead of guessing.

## Responses and Errors

A call gives back a response that reads as the array Telegram sent, as the object it describes, or through its own helpers. A refused call does not throw by default; check it when the result matters.

```php
$response = $request->sendMessage(chat()->id, 'Hello');

$response['result']['message_id'];  // the raw array
$response->message_id;              // the result's fields, as properties
$response->result();                // the Message object

if ($response->failed()) {
    logger()->warning($response->description(), ['code' => $response->errorCode()]);
}
```

- Response helpers: `isOk()`, `failed()`, `result(bool $raw = false)`, `errorCode()`, `description()`, `parameters()`, `retryAfter()`, `migrateToChatId()`, `toArray($full = false)`, `toJson($full = false)`, `throw()`. Fields of the result are properties (`getMe()->first_name`), everything around it is a method.

- `$request->throw()->sendMessage(...)` turns a failure into the exception that matches it; `silent()` opts a single call out when `laraquest.throw_exceptions` is on. Catch the specific one you can handle: `BotBlockedException`, `ChatNotFoundException`, `MessageNotModifiedException`, `NotEnoughRightsException`, `FloodException` (`retryAfter()`), `ChatMigratedException` (`migrateToChatId()`), `ConnectionException`, or `TelegramApiException` for the rest (all in `LaraGram\Laraquest\Exceptions`).
- Never parse `description` strings by hand to detect a blocked user or a migrated group: catch the exception, or read `error_code` and `parameters`.
- Build payload objects with the generated types instead of nested arrays when it helps: `InlineKeyboardMarkup::init(inline_keyboard: [[InlineKeyboardButton::init(text: 'Open', url: $url)]])`. `Message::from($response['result'])` reads a payload back as objects (`$message->chat->id`, `$message->get('from.username')`).

- Always answer callback queries, even when there is nothing to show.
- Escape user-provided text for the chosen `parse_mode`, or send it without a parse mode. The helpers `bold()`, `italic()`, `code()`, `inline_url()`, `mention_user_by_id()` and friends build MarkdownV2 fragments.
- Configure repeated options (such as `parse_mode`) once under `default_parameters` in `config/laraquest.php` instead of passing them on every call.
- When no response from Telegram is needed, `$request->mode(Mode::NO_RESPONSE_CURL)` sends the call without waiting for it.

## Sending Outside an Update

In jobs, event listeners, commands, and scheduled tasks there is no incoming update. Resolve the request from the container and pass the chat id explicitly:

```php
app('request')->sendMessage($user->chat_id, 'Your report is ready.');
```

Store Telegram ids (`user_id`, `chat_id`) on your models when you need to message users later. Use `bigInteger` columns for them.

## Multiple Bots

Bot connections live in `config/bot.php`. Send through a specific bot with `$request->connection('shop-bot')->sendMessage(...)`, or bind a listen group with `Bot::connection('shop-bot')->group(...)`. To receive updates from several bots in one application, set `default` to `auto` and give every connection a unique `secret_token` (or a unique webhook `url`), then run `webhook:set` for each connection. LaraGram detects each update's connection before any listen is matched and binds it to that update only, so replies, `forConnections()` listens, steps, and conversations all stay with the right bot; read it with `bot_connection()`. Updates no connection can claim are rejected with an `UnresolvableConnectionException`.

## Anti-Flood and Proxies

Telegram limits outgoing calls (about 30 per second overall, 1 message per second per private chat, 20 per minute per group). Enable anti-flood in `config/bot.php` (`ANTI_FLOOD=true`, with a shared store such as `redis` for webhook bots) instead of adding `sleep()` calls. For bulk sends, use the `Broadcast` facade (see [`broadcasting.md`](broadcasting.md)); it queues the work and paces it with the `broadcast` scope:

```php
Broadcast::users()->sendMessage($announcement)->queue();
```

`antiFloodWith('broadcast')` applies the same scope to a single call you make yourself.

`withoutAntiFlood()` skips pacing for a single urgent call. When the server cannot reach Telegram directly, configure the proxy pool (`bot.proxy`) rather than custom HTTP code; `withoutProxy()` and `withProxy('name')` override it per call.

## Files

```php
Bot::onPhoto(function (Request $request) {
    $photo = $request->file()->last(); // the largest photo size

    $path = $photo->download('photos/'.$photo->fileUniqueId().'.jpg', 'public');
});
```

`$request->file()` returns a `FileBag` (albums, sizes, `downloadAll()`), and each `MediaFile` exposes `fileId()`, `fileSize()`, `fileName()`, `isPhoto()`, `largest()`, and `download($path, $disk)`. Validate file size and type before downloading, and reuse `fileId()` to resend a file instead of uploading it again.

## Webhooks

`php laragram webhook:set` registers the connection's `url` and `secret_token`; `webhook:info` shows the current state. Only run webhook commands when the user asks, because they change the live bot. Each webhook update should finish quickly — reply first and queue slow work.
