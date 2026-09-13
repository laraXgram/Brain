# MTProto: Features

- [Introduction](#introduction)
- [Reactions](#reactions)
- [Polls](#polls)
- [Drafts & Checklists](#drafts-checklists)
- [Secret Chats](#secret-chats)
- [Takeout & Export](#takeout-export)
- [Stars & Gifts](#stars-gifts)
- [Ephemeral Messages](#ephemeral-messages)
- [Bot Controls](#bot-controls)
- [Business Messages](#business-messages)

<a name="introduction"></a>
## Introduction

Beyond messaging and chat management, MTProto exposes the account-level features that make Telegram Telegram — reactions, secret chats, data export, stars, and more. Each is a high-level method on the `ClientRequest` handed to your [listeners](/v4/mtproto-listening) and controllers, so you use them the same expressive way as everything else.

```php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onReactions(function (ClientRequest $request) {
    $request->sendReaction(peer: $request->chatId(), msgId: $request->messageId(), reaction: '🔥');
});
```

The examples below use `$request` — the object your handler or controller receives.

<a name="reactions"></a>
## Reactions

React to a message, read a message's reactions, or list who reacted:

```php
$request->sendReaction(peer: $chatId, msgId: 500, reaction: '🔥');
$request->sendReaction(peer: $chatId, msgId: 500, reaction: '❤️', big: true);
$request->sendReaction(peer: $chatId, msgId: 500, reaction: null);   // Remove your reaction

$reactions = $request->getMessageReactions(peer: $chatId, ids: 500);
$who       = $request->getMessageReactionsList(peer: $chatId, msgId: 500);
```

React to incoming messages from a handler with the [`onReactions`](/v4/mtproto-listening#reaction-verbs) verb.

<a name="polls"></a>
## Polls

Vote in polls and read live results:

```php
$request->voteInPoll(peer: $chatId, msgId: 500, options: 0);          // Vote option index
$results = $request->getPollResults(peer: $chatId, msgId: 500);
```

<a name="drafts-checklists"></a>
## Drafts & Checklists

Draft messages are saved per chat, exactly as the official apps do:

```php
$request->saveDraft(peer: $chatId, message: 'Unsent thought…');
$drafts = $request->getAllDrafts();
$request->clearAllDrafts();
```

Telegram checklists (interactive to-do messages) are first-class:

```php
$request->sendChecklist(
    peer: $chatId,
    title: 'Release checklist',
    items: ['Write docs', 'Tag release', 'Announce'],
);

$request->toggleChecklistItems(peer: $chatId, msgId: 600, completed: [1], incompleted: [2, 3]);
$request->appendChecklistItems(peer: $chatId, msgId: 600, items: ['Update changelog']);
```

<a name="secret-chats"></a>
## Secret Chats

MTProto supports end-to-end encrypted secret chats (MTProto 2.0). The framework handles the Diffie-Hellman key exchange and message encryption for you:

```php
// Start a secret chat with a user
$chat = $request->startSecretChat(userId: 123456789);

// Send an encrypted message
$request->sendSecretMessage(chatId: $chat['id'], text: 'This is end-to-end encrypted', ttl: 30);
```

Incoming encryption requests and messages arrive as updates — accept new chats and decrypt messages in your listeners:

```php
Client::onEncryption(function (ClientRequest $request) {
    // A secret-chat request/state change; accept it via the manager
    $request->client()->handleSecretEncryption($request->toArray());
});

Client::onEncryptedMessage(function (ClientRequest $request) {
    $decrypted = $request->client()->decryptSecret($request->toArray());
    // $decrypted['text'] ...
});
```

> [!NOTE]
> Secret chats are device-bound by design in Telegram — messages are encrypted for the specific session that created the chat. Keep the session stable to retain access to an ongoing secret chat.

<a name="takeout-export"></a>
## Takeout & Export

Telegram's *takeout* API lets an account export its own data at a controlled rate. The `client:export` command wraps the whole flow:

```shell
# Export every dialog to a timestamped folder
php laragram client:export --session=default

# Export one chat, with the contact list, capped at 1000 messages
php laragram client:export --peer=@saved --contacts --limit=1000 --into=storage/exports
```

Programmatically, run work inside a takeout session so it counts against the export budget rather than normal limits:

```php
$request->takeout(function ($takeoutId) use ($request) {
    foreach ($request->iterateTakeoutHistory($takeoutId, peer: '@group') as $message) {
        // Archive $message ...
    }
}, opts: ['message_users' => true, 'message_chats' => true]);
```

<a name="stars-gifts"></a>
## Stars & Gifts

Telegram Stars balances, gifts, and paid reactions are available on the client:

```php
$status = $request->getStarsStatus(peer: 'me');
$gifts  = $request->getStarGifts();

$request->saveStarGift(gift: $giftId);
$request->convertStarGift(gift: $giftId);
$request->transferStarGift(gift: $giftId, to: '@friend');

// Paid reactions and paid media
$request->sendPaidReaction(peer: '@channel', msgId: 500, count: 5);
```

<a name="ephemeral-messages"></a>
## Ephemeral Messages

Ephemeral (self-destructing service) messages are supported through their own namespace helpers:

```php
$sent = $request->sendEphemeral(peer: $chatId, receiver: '@user', message: 'Vanishes soon');
$request->editEphemeral(peer: $chatId, receiver: '@user', id: $sent['id'], params: ['message' => 'Edited']);
$request->deleteEphemeral(/* ... */);
```

<a name="bot-controls"></a>
## Bot Controls

When a session is a [bot](/v4/mtproto-authentication#bot-login), the bot-management surface is available:

```php
$request->setBotCommands(
    commands: [
        ['command' => 'start', 'description' => 'Start the bot'],
        ['command' => 'help',  'description' => 'Show help'],
    ],
);

$request->setBotMenuButton(user: '@user', button: 'commands');
$request->setBotInfo(params: ['name' => 'My Bot', 'about' => 'Built with LaraGram']);
$request->setDefaultAdminRights(rights: ['delete_messages' => true]);
```

Answer callback and inline queries with [`answerCallback` / `answerInlineQuery`](/v4/mtproto-requests#answering-queries).

<a name="business-messages"></a>
## Business Messages

Telegram Business connections deliver messages through dedicated verbs, so a bot connected to a business account can respond on the owner's behalf:

```php
Client::onBusinessMessage(function (ClientRequest $request) {
    $request->sendMessage(
        peer: $request->chatId(),
        message: 'Thanks for reaching out — we will reply shortly.',
    );
});

Client::onBusinessConnect(function (ClientRequest $request) {
    logger()->info('New business connection: ' . $request->connection_id);
});
```

Next: tune transport, stores, rate limiting, and ban-safety in the [Configuration](/v4/mtproto-configuration) reference.
