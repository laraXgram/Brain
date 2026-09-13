# MTProto: Media

- [Introduction](#introduction)
- [Sending Media](#sending-media)
    - [Photos](#photos)
    - [Documents & Files](#documents)
    - [Video, Audio & Voice](#video-audio-voice)
    - [Location, Venue, Contact & Dice](#other-media)
    - [Polls](#polls)
- [Albums](#albums)
- [Reusing Uploads with file_id](#file-id)
- [Upload Progress](#upload-progress)
- [Downloading Media](#downloading-media)
    - [From an Update](#download-from-update)
    - [From the High-Level API](#download-high-level)
    - [File Info](#file-info)
- [Stories](#stories)

<a name="introduction"></a>
## Introduction

Because MTProto talks to Telegram's data centers directly, it has no Bot-API file-size ceiling — you can upload and download large files, and downloads transparently follow media to whatever data center it lives on via the [connection pool](/v4/mtproto-configuration#connection-pool). Every method here is called on the `ClientRequest` handed to your [listeners](/v4/mtproto-listening) and controllers.

```php
use LaraGram\MTProto\Facades\Client;
use LaraGram\MTProto\Foundation\ClientRequest;

Client::onPhoto(function (ClientRequest $request) {
    $request->sendPhoto(peer: $request->chatId(), path: '/path/to/photo.jpg');
});
```

The examples below use `$request` — the object your handler or controller receives.

<a name="sending-media"></a>
## Sending Media

All media senders share a shape: a `peer`, a local file path, an optional caption `message` (which accepts `parse_mode`), and a `params` array for extra options.

<a name="photos"></a>
### Photos

```php
$request->sendPhoto(
    peer: '@durov',
    path: '/path/to/photo.jpg',
    message: 'A *captioned* photo',
    params: ['parse_mode' => 'markdown'],
);
```

<a name="documents"></a>
### Documents & Files

```php
$request->sendDocument(
    peer: $chatId,
    path: '/path/to/report.pdf',
    message: 'Quarterly report',
);
```

<a name="video-audio-voice"></a>
### Video, Audio & Voice

```php
$request->sendVideo(peer: $chatId, path: '/path/to/clip.mp4', message: 'Demo');
$request->sendAudio(peer: $chatId, path: '/path/to/song.mp3');
$request->sendVoice(peer: $chatId, path: '/path/to/note.ogg');
```

<a name="other-media"></a>
### Location, Venue, Contact & Dice

```php
$request->sendLocation(peer: $chatId, lat: 35.6892, long: 51.3890);
$request->sendVenue(peer: $chatId, lat: 35.6892, long: 51.3890, title: 'Azadi Tower', address: 'Tehran');
$request->sendContact(peer: $chatId, phoneNumber: '+12025550123', firstName: 'Alice');
$request->sendDice(peer: $chatId, emoticon: '🎲');
```

<a name="polls"></a>
### Polls

```php
$request->sendPoll(
    peer: $chatId,
    question: 'Pick a framework',
    answers: ['LaraGram', 'Something else'],
);
```

<a name="albums"></a>
## Albums

Send several media as a single grouped album with `sendAlbum`. Each item describes one attachment:

```php
$request->sendAlbum(
    peer: $chatId,
    items: [
        ['type' => 'photo', 'path' => '/img/1.jpg', 'caption' => 'First'],
        ['type' => 'photo', 'path' => '/img/2.jpg'],
        ['type' => 'video', 'path' => '/vid/clip.mp4'],
    ],
);
```

<a name="file-id"></a>
## Reusing Uploads with file_id

Uploading the same file repeatedly is wasteful. After sending media, capture a reusable `file_id` and re-send it any number of times without re-uploading the bytes:

```php
$sent   = $request->sendPhoto(peer: 'me', path: '/img/banner.jpg');
$fileId = $request->fileId($sent);   // A portable file_id string

// Later, or to many chats — no re-upload
$request->sendMediaById(peer: '@channelA', fileId: $fileId, message: 'Reused');
$request->sendMediaById(peer: '@channelB', fileId: $fileId);
```

<a name="upload-progress"></a>
## Upload Progress

For large files, upload the file yourself and track progress with a callback. `uploadFile` returns an input-file reference you can hand to `sendMedia`:

```php
$input = $request->uploadFile(
    path: '/path/to/large.zip',
    progress: function (int $uploaded, int $total) {
        $pct = $total > 0 ? round($uploaded / $total * 100) : 0;
        logger()->info("Upload: {$pct}%");
    },
);

$request->sendMedia(peer: $chatId, media: $input, message: 'Here you go');
```

You can also upload from an in-memory string with `uploadBytes($contents, $fileName)`.

<a name="downloading-media"></a>
## Downloading Media

<a name="download-from-update"></a>
### From an Update

Inside a handler, download the incoming message's attachment directly from the request:

```php
Client::onPhoto(function (ClientRequest $request) {
    // To a temp file, returns the path
    $path = $request->download();

    // To a specific path, returns bytes written
    $request->downloadMediaToFile(storage_path('app/incoming.jpg'));
});
```

<a name="download-high-level"></a>
### From the High-Level API

Given any media object (for example from `getMessages`), download it to memory or to a file:

```php
$message = $request->getMessages(peer: $chatId, ids: 500)[0] ?? null;

$bytes = $request->downloadMedia($message->media);                 // Returns the file contents
$request->downloadMediaToFile($message->media, '/path/out.dat');   // Writes to disk
```

You can request a specific thumbnail size by passing `thumbSize` (e.g. `'m'`, `'x'`) where the method accepts it.

<a name="file-info"></a>
### File Info

Inspect a media object without downloading it — size, mime type, and dimensions:

```php
$info = $request->getMediaInfo();          // In a handler
$info = $request->getFileInfo($message->media);
```

<a name="stories"></a>
## Stories

Post, edit, and read stories through the high-level API. `peer` is where the story is posted (`'me'` for your own account, or a channel you manage):

```php
$story = $request->sendStory(peer: 'me', media: '/path/to/story.jpg', params: ['caption' => 'Hello!']);

$request->editStory(peer: 'me', id: $story['id'], params: ['caption' => 'Edited']);
$request->pinStory(peer: 'me', ids: $story['id']);
$request->deleteStories(peer: 'me', ids: $story['id']);

$peerStories = $request->getPeerStories(peer: '@durov');
$request->readStories(peer: '@durov', maxId: 999999);
```

To download a story's media, use `downloadStory(peerOrStory, id, path)` or react to the [`onStory`](/v4/mtproto-listening#other-verbs) verb.

Next: secret chats, takeout, stars, and more in the [Features](/v4/mtproto-features) reference.
