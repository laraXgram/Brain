# MTProto Requests and Media

The same high-level API is available on `$request` inside a handler (replies go through the session that received the update) and on `Client::session('name')` outside one. Methods take named parameters; check the exact names with `search-docs` (`packages: ['laraxgram/mtproto']`) — they differ from the Bot API (`peer`/`message`, not `chat_id`/`text`).

## Peers

`peer` accepts `'@username'`, a numeric user id, a channel/supergroup id like `-1001234567890`, `'me'` (Saved Messages), or a peer object such as `$request->message->peer_id`. Resolved access hashes are cached in the peer store, so resolve peers by id or username rather than storing raw TL objects.

## Messages

```php
$client->sendMessage(peer: $chatId, message: 'Hello *world*', parse_mode: 'markdown');

$client->sendMessage(
    peer: $chatId,
    message: '<b>Report</b> is ready',
    parse_mode: 'html',
    reply_to_msg_id: $request->messageId(),
    silent: true,
    no_webpage: true,
);

$client->sendRichMessage(peer: $chatId, content: "# Heading\n\n**bold** and `code`", format: 'markdown');
```

- `parse_mode` is `markdown` or `html` and is converted to entities; explicit `entities` override it. Escape user content before interpolating it into formatted text.
- Keyboards (for bot sessions): build them with `Keyboard` / `Make` exactly like Bot API keyboards and pass `reply_markup: $keyboard->get()`.
- Post into a forum topic with `top_msg_id: $topicId`.

## Editing, Forwarding and Housekeeping

```php
$client->editMessage(peer: $chatId, id: $messageId, message: 'Updated', parse_mode: 'markdown');
$client->forwardMessages(fromPeer: $source, toPeer: $target, ids: [101, 102]);
$client->copyMessages(fromPeer: $source, toPeer: $target, ids: 101); // no "forwarded from" header
$client->getMessages(peer: $chatId, ids: [101, 102]);
$client->deleteChatMessages(peer: $chatId, ids: [101], revoke: true);

$client->markAsRead(peer: $chatId);
$client->sendTyping(peer: $chatId);
$client->searchMessages(peer: $chatId, query: 'invoice');
$client->searchGlobal(query: 'meeting notes');
```

Scheduled messages: `getScheduledMessages`, `sendScheduledMessages`, `deleteScheduledMessages`.

## Answering Queries (Bot Sessions)

```php
Client::onCallbackQueryData('vote_yes', function (ClientRequest $request) {
    $request->answerCallback(queryId: $request->query_id, text: 'Thanks for voting!', alert: false);
});

Client::onInlineQuery(function (ClientRequest $request) {
    $request->answerInlineQuery(queryId: $request->query_id, results: [/* ... */]);
});
```

## Namespaced API and invoke()

When no high-level method exists, use the TL namespace property, then raw `invoke()`. Both still resolve peers, `parse_mode`, and `reply_markup`, and go through rate limiting and pacing:

```php
$request->messages->sendMessage(peer: $request->message->peer_id, message: 'Via the messages namespace');
Client::session()->channels->getFullChannel(channel: '@laragram');
Client::session()->account->updateProfile(about: 'Built with LaraGram');

Client::session()->invoke('help.getConfig');
```

Never bypass the client with hand-built TL frames or a second MTProto library on the same session.

## Sending Media

Media senders take a `peer`, a local `path`, an optional caption `message`, and `params` for extra options:

```php
$request->sendPhoto(peer: $chatId, path: storage_path('app/banner.jpg'), message: 'A *captioned* photo', params: ['parse_mode' => 'markdown']);
$request->sendDocument(peer: $chatId, path: storage_path('app/report.pdf'), message: 'Quarterly report');
$request->sendVideo(peer: $chatId, path: $video);
$request->sendVoice(peer: $chatId, path: $voice);
$request->sendLocation(peer: $chatId, lat: 35.6892, long: 51.3890);
$request->sendPoll(peer: $chatId, question: 'Pick one', answers: ['A', 'B']);

$request->sendAlbum(peer: $chatId, items: [
    ['type' => 'photo', 'path' => '/img/1.jpg', 'caption' => 'First'],
    ['type' => 'video', 'path' => '/vid/clip.mp4'],
]);
```

- Reuse uploads: `$fileId = $request->fileId($sent)` after sending, then `sendMediaById(peer: ..., fileId: $fileId)`.
- Large files: `uploadFile(path: ..., progress: fn (int $uploaded, int $total) => ...)` returns an input file for `sendMedia(peer:, media:, message:)`; `uploadBytes($contents, $fileName)` uploads from memory.
- MTProto has no Bot API size limits, so validate size and type yourself before uploading user-provided files.

## Downloading Media

```php
Client::onDocument(function (ClientRequest $request) {
    $info = $request->getMediaInfo(); // size, mime type, dimensions

    if (($info['size'] ?? 0) > 50 * 1024 * 1024) {
        return;
    }

    $request->downloadMediaToFile(storage_path('app/incoming/'.$request->messageId()));
});

$message = $request->getMessages(peer: $chatId, ids: 500)[0] ?? null;
$bytes = $request->downloadMedia($message->media);
$request->downloadMediaToFile($message->media, storage_path('app/out.dat'));
```

Downloads follow media to other data centers through the connection pool automatically. Write to `storage/` paths, never to paths derived from user input.

## Stories

`sendStory(peer: 'me', media: $path, params: ['caption' => ...])`, `editStory`, `pinStory`, `deleteStories`, `getPeerStories(peer:)`, `readStories(peer:, maxId:)`, and `downloadStory(...)`; react to new stories with `Client::onStory`.
