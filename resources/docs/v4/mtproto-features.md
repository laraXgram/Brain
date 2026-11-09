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
- [Using Other Bots](#using-other-bots)
- [Wallet & TON Connect](#wallet-ton-connect)
- [Widgets](#widgets)
    - [Answering Widget Queries](#widget-queries)
    - [Reading & Querying Widgets](#reading-widgets)
- [Share Themes](#share-themes)
- [Password-Protected Methods](#password-protected-methods)
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

$request->closePoll(peer: $chatId, msgId: 500);                       // Stop a poll you sent
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

A gift is addressed by the id of the message that delivered it, a collectible's slug, or a raw `InputSavedStarGift`. Collectibles can leave Telegram as TON NFTs; withdrawing needs the account's [2FA password](#password-protected-methods):

```php
$request->withdrawStarGift(gift: 'PlushPepe-42', password: 'my 2FA password', keepOnProfile: true);
$url = $request->getStarGiftWithdrawalUrl(gift: 'PlushPepe-42', password: 'my 2FA password');
$request->deleteStarGift(gift: $giftId);

$rates = $request->getCurrencyRates(); // ['USD' => 0.013, 'TON' => ..., ...]
```

Walk transaction history and saved gifts lazily:

```php
foreach ($request->iterateStarsTransactions(direction: 'in', limit: 100) as $transaction) {
    // ...
}

foreach ($request->iterateSavedStarGifts(peer: '@channel') as $gift) {
    // ...
}
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

// Additional (collectible) usernames of a bot you own
$request->checkBotUsername(username: 'shop_helper_bot', additional: true);
$request->addBotUsername(bot: '@shop_bot', username: 'shop_helper_bot');
$request->removeBotUsername(bot: '@shop_bot', username: 'shop_helper_bot');

// Verifier bots: verify a peer with a custom description, or remove it
$request->setCustomVerification(peer: '@partner_channel', description: 'Official partner');
$request->setCustomVerification(peer: '@partner_channel', enabled: false);
```

Answer callback and inline queries with [`answerCallback` / `answerInlineQuery`](/v4/mtproto-requests#answering-queries).

<a name="using-other-bots"></a>
## Using Other Bots

A user session can use other bots like a person would: query them inline and press their inline buttons.

```php
// Type "@gif cats" in a chat and send the first result
$request->sendInlineQuery(peer: $chatId, bot: '@gif', query: 'cats');

// Or pick a result yourself
$results = $request->inlineQuery(bot: '@gif', query: 'cats', peer: $chatId);
$request->sendInlineResult(peer: $chatId, queryId: $results['query_id'], resultId: $results['results'][2]['id']);

// Press an inline button: pass its callback data
$answer = $request->getCallbackAnswer(peer: '@shop_bot', msgId: 120, data: 'buy:42');
$answer['message'] ?? null; // the bot's toast or alert text
```

Buttons that ask for the account password, such as a bot ownership transfer, take it as `password:`. To start a bot with a deep-link parameter, call the raw method: `$request->startBot(bot: '@shop_bot', peer: '@shop_bot', start_param: 'ref42')`.

<a name="wallet-ton-connect"></a>
## Wallet & TON Connect

The account's built-in TON wallet is available on the client. Amounts are in nanotons:

```php
$wallet = $request->getWallet(); // walletState, or walletStateEmpty when there is none

foreach ($request->iterateWalletTransactions(limit: 100, direction: 'in') as $transaction) {
    // $transaction['amount'], $transaction['peer'], $transaction['comment'] ...
}

$nfts      = $request->getWalletNfts();
$addresses = $request->getWalletAddresses(users: ['@alice', '@bob']);

// Connected dapps
foreach ($request->getTonConnectSessions() as $session) {
    $request->closeTonConnectSession(sessionId: $session['id']);
}

// The toncenter HTTP API, proxied through Telegram; the JSON response is decoded
$balance = $request->toncenter('getAddressBalance', query: ['address' => $wallet['address']]);
```

Listen for wallet changes with `Client::onWalletState()`, `onWalletTransaction()`, `onTonConnectSession()` and `onTonConnectDisconnect()`. Every other `wallet.*` method (backups, secret phrase export, TON Connect requests) is on the [namespaced API](/v4/mtproto-requests#the-namespaced-api), for example `$request->wallet->getGaslessInfo()`.

<a name="widgets"></a>
## Widgets

A bot can send a message that renders an interactive HTML widget. Only bot sessions can send, edit and answer widgets; any session can read one and send it queries.

```php
$request->sendWidget(
    peer: $chatId,
    html: '<button id="vote">Vote</button>',
    caption: 'Live poll',
    params: ['height' => 240, 'public_data' => '{"votes":0}', 'allow_queries' => true],
);
```

The HTML can be up to 32768 bytes, and up to 4096 bytes once compressed with zlib. `params` accepts:

| Option | Description |
| --- | --- |
| `width`, `height` | The size in pixels. `0`, the default, lets the client size it. |
| `public_data` | Data that stays with the widget in copies and forwards. |
| `chat_data` | Data specific to this chat. |
| `allow_queries` | Let the widget send queries to the bot. |
| `shared` | Make the widget resendable by its `widget_id`. |

Any other `messages.sendMedia` parameter, such as `silent` or `invert_media`, passes through. A shared widget's id is in the sent message's `media.widget_id`. To send it again:

```php
$request->sendSharedWidget(peer: $otherChatId, widgetId: $widgetId, params: ['chat_data' => 'room-2']);
```

Update a widget's data without resending it:

```php
$request->editWidget(peer: $chatId, msgId: 120, publicData: '{"votes":1}');
$request->editInlineWidget(id: $request->inline_message_id, chatData: 'room-2');
$request->editSharedWidget(widgetId: $widgetId, publicData: '{"votes":1}', cacheTime: 60);
```

<a name="widget-queries"></a>
### Answering Widget Queries

`Client::onWidgetQuery()` receives every query sent to the bot, whether from a message, an inline message or a copy of a shared widget. Answer with the query id:

```php
Client::onWidgetQuery(function (ClientRequest $request) {
    $result = match ($request->query_type['_']) {
        'widgetQueryTypeData' => handleVote($request->data),
        'widgetQueryTypeSignData' => 'signature-request-received',
        'widgetQueryTypeSentTransaction' => 'transaction-recorded',
    };

    $request->answerWidgetQuery(queryId: $request->query_id, result: $result);
});
```

The update also carries `user_id`, `widget_id` and, when the user shared it, `wallet_card`. `inline_message_id` is set for queries from inline messages. Queries from a copy of a shared widget have no chat, so `chatId()` returns null for them.

<a name="reading-widgets"></a>
### Reading & Querying Widgets

`Client::onWidget()` fires for messages containing a widget. Use `widgetHtml()` to get its HTML, which Telegram delivers compressed:

```php
Client::onWidget(function (ClientRequest $request) {
    $html = $request->widgetHtml($request->message);
    $data = $request->message['media']['public_data'] ?? null;
});
```

A session can query a widget's bot and read its current public data:

```php
use LaraGram\MTProto\Foundation\WidgetQuery;

$answer = $request->queryWidget(peer: $chatId, msgId: 120, data: '{"action":"vote"}');

$answer = $request->queryWidget(
    peer: $chatId,
    msgId: 120,
    queryType: WidgetQuery::data(withWallet: true),
);

$public = $request->getWidgetPublicData(peer: $chatId, msgId: 120);
```

`queryWidget()` throws `BOT_RESPONSE_TIMEOUT` when the bot does not answer in time. `getWidgetPublicData()` accepts the last `hash:` you received and returns `messages.widgetPublicDataNotModified` when nothing changed; wait the response's `period`, in seconds, before polling again. `WidgetQuery` also builds `signData()` and `sentTransaction()` queries, using a wallet from `WidgetQuery::wallet()`. `Client::onWidgetData()` reports data changes for widgets already on screen.

To post a widget from an inline query, use `InputMedia::inlineWidget()` or `InputMedia::inlineSharedWidget()` as the result's `send_message`:

```php
use LaraGram\MTProto\Foundation\InputMedia;

$request->answerInlineQuery(queryId: $request->query_id, results: [[
    '_' => 'inputBotInlineResult',
    'id' => '1',
    'type' => 'article',
    'title' => 'Live poll',
    'send_message' => InputMedia::inlineWidget(html: $html, message: 'Live poll', height: 240),
]]);
```

<a name="share-themes"></a>
## Share Themes

Choose how a profile looks when it is shared: a backdrop with an emoji pattern, a collectible gift, or the default:

```php
$backdrops = $request->messages->getShareThemeBackdrops(hash: 0);

$request->setShareThemeBackdrop(peer: 'me', backdropId: $backdrops['backdrops'][0]['backdrop_id'], emojis: ['🔥', '✨']);
$request->setShareThemeGift(peer: 'me', gift: 'PlushPepe-42');
$request->resetShareTheme(peer: 'me');
```

<a name="password-protected-methods"></a>
## Password-Protected Methods

Some methods require the account's two-step verification password. The helpers above take it as `password:` and build the proof for you; for raw calls, build it yourself:

```php
// inputCheckPasswordSRP, for methods with a `password` parameter
$request->invoke('wallet.exportSecretPhrase', ['password' => $request->passwordCheck('my 2FA password')]);

// A temporary password, for methods with a `tmp_password` parameter
$tmp = $request->tmpPassword('my 2FA password', period: 60);
```

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
