# MTProto Listening

## Listen Files

```php
// bootstrap/app.php
->withListener(
    bot: [
        __DIR__.'/../listens/bot.php' => 'bot',      // Bot API connection "bot"
        __DIR__.'/../listens/mybot.php' => 'mybot',  // MTProto *bot* session "mybot"
    ],
    client: [
        __DIR__.'/../listens/client.php',            // MTProto user sessions
    ],
    commands: __DIR__.'/../listens/console.php',
)
```

- Files under `client:` serve user sessions. A `bot:` entry whose name matches an MTProto session (not a `config/bot.php` connection) is served by that MTProto bot session.
- Every MTProto listen file uses the `Client` facade and `ClientRequest`, never `Bot::` and `LaraGram\Request\Request`.

## Verbs

The `LaraGram\MTProto\Facades\Client` verbs mirror the `Bot` facade and add MTProto-only updates. Actions are closures, `[Controller::class, 'method']`, or invokable classes.

```php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onCommand('echo', function (ClientRequest $request, $args) {
    $request->sendMessage(peer: $request->chatId(), message: $args ?? '(nothing to echo)');
});

Client::onText('rate {product} {stars}', [RatingController::class, 'store']);

Client::onPhoto(function (ClientRequest $request) {
    $request->markAsRead($request->chatId());
});
```

| Area | Verbs |
| --- | --- |
| Messages | `onText` (alias `on`), `onCommand` (captures `{args?}`), `onReferral`, `onMessageType`, `onMessage`, `onEditedMessage`, `onSentMessage`, `onDeletedMessages`, `onPinnedMessages`, `onScheduledMessage`, `onMessageViews`, `onTranscribedAudio` |
| Media | `onPhoto`, `onVideo`, `onAnimation`, `onSticker`, `onDocument`, `onAudio`, `onVoice`, `onVideoNote`, `onContact`, `onLocation`, `onVenue`, `onPoll`, `onPaidMedia`, `onDice` |
| Entities | `onHashtag`, `onCashtag`, `onMention`, `onUrl`, `onEmail`, `onBotCommandEntity` |
| Queries | `onCallbackQuery`, `onCallbackQueryData`, `onInlineQuery`, `onChosenInlineResult`, `onPreCheckoutQuery`, `onShippingQuery` |
| Chats | `onChat`, `onChannel`, `onChatParticipant`, `onChatParticipantAdd`, `onChatParticipantDelete`, `onChatParticipantAdmin`, `onChatJoinRequest`, `onChatBoost`, `onForumTopic` |
| Users and activity | `onUserStatus`, `onUserName`, `onUserPhone`, `onTyping`, `onReadHistory`, `onReactions`, `onPollVote`, `onPollResults` |
| Other | `onStory`, `onStarsBalance`, `onEncryptedMessage`, `onEncryption`, `onBusinessMessage`, `onBusinessConnect`, `onDraft`, `onPhoneCall`, `onGroupCall`, `onUpdate` (any update), `fallback` |

Many more update verbs exist; check the facade's docblock (`vendor/laraxgram/mtproto/src/Facades/Client.php`) before inventing a generic `onUpdate` handler that branches on `$request->type()`.

Pattern parameters, `where*` constraints, and optional parameters work exactly as for bot listens.

## Scoping

```php
// Only messages received by the account (not the ones it sends)
Client::incomming()->onText('ping', fn (ClientRequest $request) => $request->sendMessage(peer: $request->chatId(), message: 'pong'));

// Self-commands typed from the account itself
Client::outgoing()->onText('.note {text}', [NoteController::class, 'store']);

// Only for some sessions
Client::forSessions(['support', 'sales'])->onCommand('help', HelpController::class);

// Share attributes
Client::forSessions('support')->middleware(EnsureSupportAgent::class)->group([], function () {
    Client::onCommand('open', [TicketController::class, 'open']);
    Client::onCommand('close', [TicketController::class, 'close']);
});
```

- On user sessions, always scope reply listeners with `incomming()` (or check `$request->isOutgoing()`); otherwise the account answers its own messages and can loop.
- Unscoped listeners run for every session.
- Middleware, `onStep` (with the `Step` facade), and controllers work as in bot listens. MTProto listen files use their own `client` middleware group (model binding) on the client listener; attach extra middleware per listener or group with `middleware()`.

## Reading the Update

```php
Client::onMessage(function (ClientRequest $request) {
    $text = $request->text();
    $fromUserId = $request->message->from_id?->user_id;
    $peer = $request->message->peer_id;       // a peer object, usable as `peer:`
});
```

- Fields are read-only properties hydrated from the TL update (`message`, `peer_id`, `from_id`, `media`, `entities`, `date`, `query`, `query_id`, `data`, ...); missing fields are `null`.
- Helpers: `text()`, `callbackData()`, `inlineQuery()`, `chatId()`, `messageId()`, `entities()`, `type()` (e.g. `updateNewMessage`), `session()`, `isOutgoing()`, `toArray()`, `toJson()`, `client()` (the live session client).
- Media helpers on the request: `download()` (temp file path), `downloadMediaToFile($path)`, `getMediaInfo()`; `read()` marks the chat read and `seen()` marks media as consumed.
- MTProto handlers run concurrently (swoole driver with the pump). Don't share mutable state between handlers through static properties; use the cache or database.
