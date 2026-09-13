# MTProto: Listening

- [Introduction](#introduction)
- [Basic Listening](#basic-listening)
    - [Where Listeners Live](#where-listeners-live)
    - [The Client Facade](#the-client-facade)
- [Available Listener Verbs](#available-listener-verbs)
    - [Messages](#messages-verbs)
    - [Media](#media-verbs)
    - [Text Entities](#entity-verbs)
    - [Callback & Inline Queries](#callback-inline-verbs)
    - [Chats, Channels & Participants](#chat-verbs)
    - [Reactions & Polls](#reaction-verbs)
    - [Users](#user-verbs)
    - [Stories, Stars & More](#other-verbs)
    - [Catch-All & Fallback](#catch-all)
- [Pattern Parameters](#pattern-parameters)
- [Scoping Listeners](#scoping-listeners)
    - [Direction (Incoming / Outgoing)](#direction)
    - [Sessions](#session-scoping)
    - [Groups](#groups)
- [Middleware](#middleware)
- [Conversation Steps](#conversation-steps)
- [The Client Request](#the-client-request)
    - [Reading the Update](#reading-the-update)
    - [Helper Methods](#helper-methods)
    - [Replying](#replying)
- [Sending Outside a Handler](#sending-outside-a-handler)

<a name="introduction"></a>
## Introduction

Listening for MTProto updates works exactly like [listening for Bot updates](/v4/listening) — you register listeners with a facade and a closure, and each listener receives a `Request` object. The only differences are the facade (`Client` instead of `Bot`), the request type (`ClientRequest`), and a much larger catalog of update verbs, because MTProto exposes hundreds of update types the Bot API never sends.

```php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onText('hello', function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'hi 👋');
});
```

<a name="basic-listening"></a>
## Basic Listening

<a name="where-listeners-live"></a>
### Where Listeners Live

MTProto listeners are defined in listen files registered under the `client:` slot (for user clients) or bound to a session name under the `bot:` slot (for MTProto bots) in your `bootstrap/app.php`. See [Registering Listen Files](/v4/mtproto#registering-listen-files) for the wiring.

```php
// listens/client.php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onCommand('start', function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'Welcome!');
});
```

<a name="the-client-facade"></a>
### The Client Facade

The `LaraGram\MTProto\Facades\Client` facade is the entry point for registering listeners and for reaching a live session. Its verbs mirror the `Bot` facade wherever the concept overlaps (`onText`, `onCommand`, `onPhoto`, …) and add MTProto-only verbs on top.

<a name="available-listener-verbs"></a>
## Available Listener Verbs

Every verb accepts a closure, a `[Controller::class, 'method']` pair, or an invokable class string as its action — identical to Bot listeners. The most common verbs are grouped below; the full set covers well over two hundred update types.

<a name="messages-verbs"></a>
### Messages

```php
Client::on($pattern, $action);              // Alias of onText
Client::onText($pattern, $action);          // Message text pattern
Client::onCommand($command, $action);       // Slash command (captures {args?})
Client::onReferral($pattern, $action);      // /start deep-link payload
Client::onMessageType($type, $action);      // One or more content types
Client::onMessage($action);                 // All new messages
Client::onEditedMessage($action);           // Edited messages
Client::onSentMessage($action);             // Own sent-message confirmations
Client::onDeletedMessages($action);         // Deleted messages
Client::onPinnedMessages($action);          // Pinned-message events
Client::onScheduledMessage($action);        // New scheduled messages
Client::onMessageViews($action);            // Channel view-count changes
Client::onMessageForwards($action);         // Channel forward-count changes
Client::onTranscribedAudio($action);        // Audio transcription results
```

<a name="media-verbs"></a>
### Media

```php
Client::onPhoto($action);
Client::onVideo($action);
Client::onAnimation($action);               // GIFs
Client::onSticker($action);
Client::onDocument($action);
Client::onAudio($action);
Client::onVoice($action);
Client::onVideoNote($action);               // Round videos
Client::onContact($action);
Client::onLocation($action);                // And live locations
Client::onVenue($action);
Client::onGame($action);
Client::onInvoice($action);
Client::onPoll($action);
Client::onGiveaway($action);
Client::onPaidMedia($action);
Client::onDice($action, $emoji = 'any', $value = 0);
```

<a name="entity-verbs"></a>
### Text Entities

```php
Client::onHashtag($action);                 // #hashtag
Client::onCashtag($action);                 // $CASHTAG
Client::onMention($action);                 // @mention
Client::onUrl($action);
Client::onEmail($action);
Client::onBotCommandEntity($action);        // /command entity
```

<a name="callback-inline-verbs"></a>
### Callback & Inline Queries

```php
Client::onCallbackQuery($action);                    // All callback queries
Client::onCallbackQueryData($pattern, $action);      // Matching a data pattern
Client::onInlineQuery($action);
Client::onChosenInlineResult($action);
Client::onPreCheckoutQuery($action);
Client::onShippingQuery($action);
```

<a name="chat-verbs"></a>
### Chats, Channels & Participants

```php
Client::onChat($action);
Client::onChannel($action);
Client::onChatParticipant($action);          // join/leave/promote/ban
Client::onChatParticipantAdd($action);
Client::onChatParticipantDelete($action);
Client::onChatParticipantAdmin($action);     // Admin rights changed
Client::onChatJoinRequest($action);
Client::onChatBoost($action);
Client::onForumTopic($action);               // Forum topic pinned/unpinned
```

<a name="reaction-verbs"></a>
### Reactions & Polls

```php
Client::onReactions($action);                // Message reaction changes
Client::onBotReaction($action);
Client::onPollVote($action);
Client::onPollResults($action);
```

<a name="user-verbs"></a>
### Users

```php
Client::onUserStatus($action);               // Online/offline
Client::onUserName($action);                 // Username changes
Client::onUserPhone($action);
Client::onUserEmojiStatus($action);
Client::onTyping($action);                   // Typing indicators
Client::onReadHistory($action);              // Read receipts
```

<a name="other-verbs"></a>
### Stories, Stars & More

```php
Client::onStory($action);                    // New/updated stories
Client::onStoryReaction($action);
Client::onStarsBalance($action);             // Stars balance changes
Client::onEncryptedMessage($action);         // Secret-chat messages
Client::onEncryption($action);               // Secret-chat state
Client::onBusinessMessage($action);          // Telegram Business messages
Client::onDraft($action);                    // Draft changes
Client::onPhoneCall($action);
Client::onGroupCall($action);
```

> [!NOTE]
> This is a representative selection. Business connections, group calls, dialog filters (folders), sticker sets, quick replies, notify settings, privacy rules, bot management, and many more verbs are all available. Your IDE will autocomplete the full list from the `Client` facade.

<a name="catch-all"></a>
### Catch-All & Fallback

Handle *any* update that no other listener matched, or register a true catch-all:

```php
Client::onUpdate(function (ClientRequest $request) {
    logger()->info('Update: ' . $request->type());
});

Client::fallback(function (ClientRequest $request) {
    // Runs when nothing else matched
});
```

<a name="pattern-parameters"></a>
## Pattern Parameters

Text patterns support the same placeholder syntax as Bot listeners, and captured parameters are injected into your handler after the request. Command arguments are captured automatically:

```php
Client::onText('rate {product} {stars}', function (ClientRequest $request, $product, $stars) {
    $request->sendMessage(peer: $request->chatId(), message: "You rated {$product}: {$stars}★");
});

Client::onCommand('echo', function (ClientRequest $request, $args) {
    $request->sendMessage(peer: $request->chatId(), message: $args ?? '(nothing to echo)');
});
```

For the complete pattern syntax — optional parameters, constraints via `where`, and regular expressions — see the [Listening](/v4/listening) reference; it applies to MTProto listeners unchanged.

<a name="scoping-listeners"></a>
## Scoping Listeners

<a name="direction"></a>
### Direction (Incoming / Outgoing)

Because a user client sees its own outgoing messages as updates too, you can scope listeners to one direction. `incomming()` matches only messages *received* by the session; `outgoing()` matches only messages *sent* by it:

```php
Client::incomming()->onText('ping', function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'pong');
});

Client::outgoing()->onText('.note {text}', function (ClientRequest $request, $text) {
    // A self-command you type from your own account
});
```

You can also test direction inside a handler with `$request->isOutgoing()`.

<a name="session-scoping"></a>
### Sessions

In a [multi-account](/v4/mtproto-authentication#multi-account-sessions) setup, scope listeners to one or more sessions with `forSessions()`. Listeners not scoped run for every session:

```php
Client::forSessions('support')->onText('hours', function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'We are open 9–5.');
});

Client::forSessions(['support', 'sales'])->onCommand('help', HelpController::class);
```

<a name="groups"></a>
### Groups

Group listeners to share attributes — middleware, session scope, or direction — across many registrations at once:

```php
Client::forSessions('support')->group([], function () {
    Client::onCommand('open', [TicketController::class, 'open']);
    Client::onCommand('close', [TicketController::class, 'close']);
});
```

<a name="middleware"></a>
## Middleware

MTProto listeners run through the LaraGram [middleware](/v4/middleware) pipeline, so everything you know about middleware applies. Attach middleware to a listener or a group with `middleware()`:

```php
Client::middleware('throttle')->onCommand('report', ReportController::class);

Client::middleware(['auth.admin'])->group([], function () {
    Client::onCommand('ban', [ModController::class, 'ban']);
    Client::onCommand('unban', [ModController::class, 'unban']);
});
```

The `direction:in` / `direction:out` filters used by `incomming()` / `outgoing()` are themselves middleware, so they compose naturally with your own.

<a name="conversation-steps"></a>
## Conversation Steps

Multi-step conversations are supported through the same [Step](/v4/step) system as Bot listeners, via `onStep`:

```php
Client::onStep('await_name', function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: "Hi {$request->text()}!");
});
```

<a name="the-client-request"></a>
## The Client Request

Every listener receives a `LaraGram\MTProto\Foundation\ClientRequest`. It exposes the update's fields as read-only properties, a set of convenience helpers, and — by forwarding to the underlying client — the entire [high-level API](/v4/mtproto-requests) for replying.

<a name="reading-the-update"></a>
### Reading the Update

Access any field of the raw update as a property. Nested TL objects are hydrated into objects you can walk with `->`:

```php
Client::onMessage(function (ClientRequest $request) {
    $message = $request->message;                 // The Message object
    $fromId  = $request->message->peer_id->user_id;
    $date    = $request->date;
    $entities = $request->entities;               // MessageEntity[]
});
```

Commonly available properties include `message`, `peer_id`, `from_id`, `media`, `entities`, `date`, `user_id`, `chat_id`, `channel_id`, `query`, `query_id`, `data` (callback data), `reactions`, `story`, `poll`, and many more — one per update field. Missing fields simply return `null`.

<a name="helper-methods"></a>
### Helper Methods

The request also provides shortcut methods for the values you reach for most:

```php
$request->text();          // Message text (or null)
$request->callbackData();  // Callback query data (or null)
$request->inlineQuery();   // Inline query string (or null)
$request->chatId();        // Resolved chat/peer id
$request->messageId();     // Message id
$request->entities();      // Parsed entities
$request->type();          // Update type string, e.g. "updateNewMessage"
$request->session();       // Originating session name
$request->isOutgoing();    // Was this sent by our own account?
$request->toArray();       // The whole update as an array
$request->toJson();        // ...as JSON
```

Media helpers download the update's attachment directly (see [Media](/v4/mtproto-media)):

```php
$path = $request->download();            // To a temp file, returns the path
$request->downloadMediaToFile('img.jpg');
$info = $request->getMediaInfo();        // Size, mime, dimensions...
```

<a name="replying"></a>
### Replying

Because the request forwards unknown method calls to the client, the full high-level API is available directly on `$request`. Replies automatically go out through the **session that received the update**:

```php
Client::onPhoto(function (ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'Nice photo!');
    $request->markAsRead($request->chatId());
});
```

You can also reach a specific API namespace through the request — see [Requests](/v4/mtproto-requests#the-namespaced-api) for the full high-level catalog:

```php
$request->messages->sendMessage(
    peer: $request->message->peer_id->user_id,
    message: 'Sent via the messages namespace',
);
```

<a name="sending-outside-a-handler"></a>
## Sending Outside a Handler

To send from outside an update handler — a scheduled task, a queue job, a controller, or to a *different* account than the one that received the update — resolve a live session with `Client::session()`:

```php
use LaraGram\MTProto\Facades\Client;

// Default session
Client::session()->sendMessage(peer: '@durov', message: 'Hello from a job!');

// A specific account
Client::session('announcer')->sendMessage(peer: -1001234567890, message: 'Daily digest is out.');
```

The object returned by `Client::session()` is the live client, exposing the same high-level API documented in [Requests](/v4/mtproto-requests), [Chats & Channels](/v4/mtproto-chats), and [Media](/v4/mtproto-media).
