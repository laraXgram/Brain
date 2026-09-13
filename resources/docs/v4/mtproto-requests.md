# MTProto: Requests

- [Introduction](#introduction)
- [Addressing Peers](#addressing-peers)
- [Sending Messages](#sending-messages)
    - [Plain Text](#plain-text)
    - [Formatting with parse_mode](#parse-mode)
    - [Rich Messages](#rich-messages)
    - [Replies and Silent Messages](#replies-silent)
- [Keyboards](#keyboards)
- [Editing, Forwarding & Copying](#editing-forwarding)
- [Reading & Housekeeping](#reading-housekeeping)
- [Searching Messages](#searching-messages)
- [Answering Queries](#answering-queries)
- [The Namespaced API](#the-namespaced-api)
- [Calling Any Method](#calling-any-method)

<a name="introduction"></a>
## Introduction

Sending requests to Telegram is done through the same high-level API whether you are inside an update handler (via the `ClientRequest`) or outside one (via `Client::session()`). Both expose expressive methods — `sendMessage`, `sendPhoto`, `editMessage`, `forwardMessages`, and so on — that hide the underlying TL method calls. You never assemble a raw TL request yourself.

```php
use LaraGram\MTProto\Facades\Client;

Client::session()->sendMessage(peer: '@durov', message: 'Hello!');
```

Inside a handler, the same methods are available directly on the request, and replies route back to the originating session automatically:

```php
Client::onText('ping', function (\LaraGram\MTProto\Foundation\ClientRequest $request) {
    $request->sendMessage(peer: $request->chatId(), message: 'pong');
});
```

<a name="addressing-peers"></a>
## Addressing Peers

Almost every method takes a `peer` (the chat, channel, or user to act on). LaraGram resolves peers for you from several convenient forms:

<div class="content-list" markdown="1">

- **Username** — `'@durov'`
- **Numeric id** — a user id `123456789`, or a channel/supergroup id like `-1001234567890`
- **`'me'`** — the logged-in account (Saved Messages)
- **A peer object** — e.g. `$request->message->peer_id`

</div>

```php
$client->sendMessage(peer: '@durov', message: 'By username');
$client->sendMessage(peer: 123456789, message: 'By id');
$client->sendMessage(peer: 'me', message: 'Note to self');
```

> [!NOTE]
> The first time you address a peer by username or id, LaraGram resolves it and caches the access hash, so subsequent calls are instant. Peer caching is backed by the configurable [peer store](/v4/mtproto-configuration#state-stores).

<a name="sending-messages"></a>
## Sending Messages

<a name="plain-text"></a>
### Plain Text

```php
$client->sendMessage(
    peer: '@durov',
    message: 'A plain text message.',
);
```

<a name="parse-mode"></a>
### Formatting with parse_mode

Pass `parse_mode` as `markdown` or `html` and LaraGram converts the formatting into Telegram message entities for you:

```php
$client->sendMessage(
    peer: $chatId,
    message: 'Hello *world*, visit [our site](https://laragram.dev).',
    parse_mode: 'markdown',
);

$client->sendMessage(
    peer: $chatId,
    message: '<b>bold</b> <i>italic</i> <code>code</code> '
        . '<a href="tg://user?id=123456789">mention</a>',
    parse_mode: 'html',
);
```

The HTML mode supports the full Telegram entity set — `b`, `i`, `u`, `s`, `code`, `pre` (with `language`), `blockquote` (and `expandable`), spoilers via `<span class="tg-spoiler">`, `text_link`, `text_mention`, and custom emoji via `<tg-emoji emoji-id="...">`. If you supply your own `entities`, they take precedence and `parse_mode` is ignored.

<a name="rich-messages"></a>
### Rich Messages

Beyond `parse_mode`, MTProto supports Telegram's *rich message* format (headings, fenced code blocks, blockquotes) through `sendRichMessage`. This uses Telegram's native `InputRichMessage`, not `parse_mode`:

```php
$client->sendRichMessage(
    peer: $chatId,
    content: "# Rich heading\n\n"
        . "**bold**, *italic*, `code`, and a [link](https://telegram.org).\n\n"
        . "> a blockquote\n\n"
        . "```php\necho 'fenced block';\n```",
    format: 'markdown',   // or 'html'
);
```

<a name="replies-silent"></a>
### Replies and Silent Messages

Additional parameters map straight to Telegram's options — reply to a message, send silently, disable the link preview, and so on:

```php
$client->sendMessage(
    peer: $chatId,
    message: 'This is a reply, sent silently.',
    reply_to_msg_id: $request->messageId(),
    silent: true,
    no_webpage: true,
);
```

<a name="keyboards"></a>
## Keyboards

Attach a keyboard by passing `reply_markup`. It accepts the exact same array shape produced by LaraGram's [Keyboard Builder](/v4/keyboards), so you build keyboards the way you already do for bots:

```php
use LaraGram\Support\Facades\Keyboard;
use LaraGram\Keyboard\Make;

$keyboard = Keyboard::inlineKeyboardMarkup(
    Make::row(
        Make::callbackData('👍 Yes', 'vote_yes'),
        Make::callbackData('👎 No', 'vote_no'),
    ),
    Make::row(
        Make::url('Open site', 'https://laragram.dev'),
    ),
)->get();

$client->sendMessage(
    peer: $chatId,
    message: 'Do you agree?',
    reply_markup: $keyboard,
);
```

Reply keyboards, force-reply, and remove-keyboard markups are all supported the same way — build them with the [Keyboard Builder](/v4/keyboards) and pass the result as `reply_markup`. Handle button presses with [`onCallbackQuery` / `onCallbackQueryData`](/v4/mtproto-listening#callback-inline-verbs) and answer them with [`answerCallback`](#answering-queries).

<a name="editing-forwarding"></a>
## Editing, Forwarding & Copying

```php
// Edit a message you sent
$client->editMessage(peer: $chatId, id: $messageId, message: 'Updated text', parse_mode: 'markdown');

// Forward (keeps the "forwarded from" header)
$client->forwardMessages(fromPeer: $sourceChat, toPeer: $targetChat, ids: [101, 102, 103]);

// Copy (no forward header — re-sends the content)
$client->copyMessages(fromPeer: $sourceChat, toPeer: $targetChat, ids: 101);

// Fetch specific messages
$messages = $client->getMessages(peer: $chatId, ids: [101, 102]);

// Delete
$client->deleteChatMessages(peer: $chatId, ids: [101, 102], revoke: true);
```

<a name="reading-housekeeping"></a>
## Reading & Housekeeping

```php
$client->markAsRead(peer: $chatId);                 // Mark history read
$client->readMessageContents(peer: $chatId, ids: 101); // Mark media/voice as consumed
$client->sendChatAction(peer: $chatId, action: 'typing');
$client->sendTyping(peer: $chatId);                 // Shortcut for the typing action
$client->deleteHistory(peer: $chatId, forEveryone: false);
```

From inside a handler you can also use the request shortcuts:

```php
$request->read();   // Mark the incoming message's chat as read
$request->seen();   // Mark its contents as consumed
```

<a name="searching-messages"></a>
## Searching Messages

```php
// Within a chat
$results = $client->searchMessages(peer: $chatId, query: 'invoice');

// Across all chats
$results = $client->searchGlobal(query: 'meeting notes');
```

For scheduled messages, see `getScheduledMessages`, `sendScheduledMessages`, and `deleteScheduledMessages` — each takes a `peer` and message ids.

<a name="answering-queries"></a>
## Answering Queries

Bots answer callback and inline queries through the high-level API:

```php
Client::onCallbackQueryData('vote_yes', function (ClientRequest $request) {
    $request->answerCallback(
        queryId: $request->query_id,
        text: 'Thanks for voting!',
        alert: false,
    );
});

Client::onInlineQuery(function (ClientRequest $request) {
    $request->answerInlineQuery(
        queryId: $request->query_id,
        results: [ /* inline result objects */ ],
    );
});
```

Bot management (`setBotCommands`, `setBotMenuButton`, `setBotInfo`, `setDefaultAdminRights`) is available on the same client — see the [Chats & Channels](/v4/mtproto-chats) and [Features](/v4/mtproto-features) references for related surfaces.

<a name="the-namespaced-api"></a>
## The Namespaced API

The high-level helpers cover the common cases, but Telegram's API is organized into **namespaces** (`messages`, `channels`, `contacts`, `account`, `photos`, `stories`, `payments`, …), and every method in them is reachable — with named parameters and IDE autocompletion — through the matching property on the client or request:

```php
// On the request (inside a handler)
$request->messages->sendMessage(
    peer: $request->message->peer_id->user_id,
    message: 'Via the messages namespace',
);

// On a live client (outside a handler)
Client::session()->contacts->getContacts();
Client::session()->channels->getFullChannel(channel: '@laragram');
Client::session()->account->updateProfile(about: 'Building bots with LaraGram');
```

Peers, `parse_mode`, and `reply_markup` are pre-processed for namespaced calls exactly as they are for the high-level helpers, so `'@username'` peers and Bot-API keyboards work everywhere.

<a name="calling-any-method"></a>
## Calling Any Method

For anything not surfaced elsewhere, `invoke()` calls any TL method by name with an associative array of parameters, while still resolving peers and formatting for you:

```php
$result = Client::session()->invoke('help.getConfig');

$result = Client::session()->invoke('messages.sendMessage', [
    'peer'    => '@durov',
    'message' => 'Low-level, still framework-managed.',
]);
```

This is the escape hatch for brand-new API methods — you stay inside LaraGram's peer resolution, rate limiting, and pacing, without touching the raw protocol.
