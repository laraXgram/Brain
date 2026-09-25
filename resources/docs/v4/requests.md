# HTTP Requests

<a name="introduction"></a>
## Introduction

LaraGram's `LaraGram\Request\Request` class provides an object-oriented way to interact with the current Bot request being handled by your application as well as retrieve the updates that were submitted with the request and work with API methods.

<a name="interacting-with-the-request"></a>
## Interacting With The Request

<a name="accessing-the-request"></a>
### Accessing the Request

To obtain an instance of the current Bot request via dependency injection, you should type-hint the `LaraGram\Request\Request` class on your listen closure or controller method. The incoming request instance will automatically be injected by the LaraGram [service container](/v4/container):

```php
<?php

namespace App\Request\Controllers;

use LaraGram\Request\Request;

class UserController extends Controller
{
    /**
     * Store a new user.
     */
    public function store(Request $request)
    {
        $name = $request->message->user->first_name;

        // Store the user...

        $request->sendMessage($request->message->chat->id, 'User stored!')
    }
}
```

As mentioned, you may also type-hint the `LaraGram\Request\Request` class on a listen closure. The service container will automatically inject the incoming request into the closure when it is executed:

```php
use LaraGram\Request\Request;

Bot::onText('hello', function (Request $request) {
    // ...
});
```

<a name="dependency-injection-listen-parameters"></a>
#### Dependency Injection and Listen Parameters

If your controller method is also expecting input from a listen parameter you should list your listen parameters after your other dependencies. For example, if your listen is defined like so:

```php
use App\Request\Controllers\UserController;

Bot::onText('user {id}', [UserController::class, 'update']);
```

You may still type-hint the `LaraGram\Request\Request` and access your `id` listen parameter by defining your controller method as follows:

```php
<?php

namespace App\Request\Controllers;

use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;

class UserController extends Controller
{
    /**
     * Update the specified user.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        // Update the user...

        return to_listen('users');
    }
}
```

<a name="request-scope-and-method"></a>
### Request Scope, and Method

<a name="retrieving-the-request-scope"></a>
#### Retrieving the Request Scope

The `scope` method returns the request's Scope such as `group`, `supergroup`, `private`, `channel`. So, if the incoming request is from a private chat, the `scope` method will return `private`:

```php
$scope = $request->scope();
```

<a name="inspecting-the-request-listen"></a>
#### Inspecting the Request Listen

Using the `listenIs` method, you may determine if the incoming request has matched a [named listen](/v4/listening#named-listens):

```php
if ($request->listenIs('admin.*')) {
    // ...
}
```

<a name="retrieving-the-request-method"></a>
#### Retrieving the Request Method

The `method` method will return the Bot verb for the request. You may use the `isMethod` method to verify that the Bot verb matches a given string:

```php
$method = $request->method();

if ($request->isMethod('text')) {
    // ...
}
```

<a name="retrieving-the-request-secret-toek"></a>
#### Retrieving the Request Secret Token

For convenience, the `secretToken` method may be used to retrieve a secret_token from the webhook. If no such secret_token is present, an empty string will be returned:

```php
$token = $request->secretToken();
```

<a name="updates"></a>
## Updates

<a name="retrieving-updates-via-dynamic-properties"></a>
#### Retrieving Updates via Dynamic Properties

Access updates using dynamic properties on the `LaraGram\Request\Request` instance. For example, if you need the incoming text, you may access the value of the field like so:

```php
$text = $request->message->text;
```
You can receive all updates according to the official Telegram documentation, with full support for syntax highlighting in your editor or IDE.

<a name="update-helpers"></a>
#### Update Helpers

LaraGram provides global helpers that read the common parts of any update, whatever its type:

<div class="overflow-auto">

| Helper | Returns |
| --- | --- |
| `chat()` | The chat the update happened in. |
| `user()` | The user who caused the update. `null` for channel posts and anonymous reactions or poll votes. |
| `sender()` | The actual sender: a user, or the chat a message was sent on behalf of. |
| `message()` | The message of the update. For a callback query, the message its button is attached to, which is `null` for inline-mode messages. |
| `text()` | The text of the message, or the caption of a media message. |
| `bot_connection()` | The name of the bot connection handling the update. |

</div>

Each helper returns `null` when the current update does not carry that value, so fields Telegram marks as optional never cause an error:

```php
$request->sendMessage(chat()->id, 'Hello '.(user()?->first_name ?? 'there'));
```

A message may be sent on behalf of a chat: a post in a channel, a message from an anonymous group admin, or a channel post automatically forwarded to its discussion group. Telegram then puts the real sender in `sender_chat`, leaves `from` empty in channels, and fills `from` with a placeholder user in groups. The `sender` helper returns the sender chat for these messages, as well as the `actor_chat` of an anonymous reaction and the `voter_chat` of an anonymous poll vote:

```php
$sender = sender();

if (isset($sender->title)) {
    // Sent on behalf of a chat...
}
```

> [!WARNING]
> Never authorize an action by `user()->id` for messages sent on behalf of a chat, since every such message shares the same placeholder user. Check `sender()` instead.

<a name="retrieving-all-input-data"></a>
### Retrieving All Input Data

You may retrieve all of the incoming update's data as a nested `array` using the `all` method. The whole update tree is normalized to arrays, so nested fields are available as nested arrays:

```php
$update = $request->all();
```

<a name="retrieving-an-input-value"></a>
#### Retrieving an Input Value

Using the `input` method, you may retrieve any value from the update using "dot" notation, regardless of how deeply nested the field is:

```php
$text = $request->input('message.text');
```

You may pass a default value as the second argument. It will be returned if the requested field is not present on the update:

```php
$text = $request->input('message.text', 'default');
```

<a name="determining-if-input-is-present"></a>
#### Determining if Input Is Present

You may use the `has` method to determine if a value is present on the update. The `has` method returns `true` if the value is present:

```php
if ($request->has('message.text')) {
    // ...
}
```

The `missing` method is the inverse of `has`:

```php
if ($request->missing('message.text')) {
    // ...
}
```

<a name="retrieving-a-portion-of-the-input-data"></a>
#### Retrieving a Portion of the Input Data

If you need to retrieve a subset of the update data, you may use the `only` and `except` methods. Both accept a single `array` or a dynamic list of arguments:

```php
$input = $request->only(['message.text', 'message.chat.id']);

$input = $request->except(['message.entities']);
```

<a name="validating-updates"></a>
### Validating Updates

You may validate the incoming update directly on the request using the `validate` method. Rules target update fields using "dot" notation, and the validated data is returned as a `LaraGram\Request\ValidatedInput` instance:

```php
$validated = $request->validate([
    'message.text' => 'required|string|max:255',
]);

$text = $validated->message->text;
```

For more information, check out the complete [validation documentation](/v4/validation).

<a name="merging-additional-input"></a>
### Merging Additional Input

Sometimes you may need to manually merge additional input into the request's existing input data. To accomplish this, you may use the `merge` method. If a given input key already exists on the request, it will be overwritten by the data provided to the `merge` method:

```php
$request->merge(['votes' => 0]);
```

The `mergeIfMissing` method may be used to merge input into the request if the corresponding keys do not already exist within the request's input data:

```php
$request->mergeIfMissing(['votes' => 0]);
```

<a name="methods"></a>
## Methods

<a name="work-with-api-methods"></a>
### Work with API methods

Through the Request class, you have access to all Telegram methods according to the latest version of the Bot API.

```php
$request->sendMessage();
$request->sendAnimation();
$request->deleteMessage();
// ...
```

Every method takes the parameters the Bot API documents, in the order that reads best, and carries its own documentation — the description of each parameter and the shape of the response — so your editor can complete the call and what comes back.

<a name="responses"></a>
### Responses

A call gives back a response that may be read three ways, whichever suits the code around it:

```php
$message = $request->sendMessage($chatId, 'Hello!');

$message['result']['message_id'];  // the array Telegram sent
$message->message_id;              // the object it describes
$message->result();                // that object, in full
```

Reading a field as a property reaches into the result, so `$request->getMe()->first_name` is the bot's name and `$request->getChat($chatId)->title` is the chat's title. Everything around the result is a method, so a field never shadows it:

<div class="content-list" markdown="1">

- `isOk()` and `failed()` — whether Telegram accepted the call.
- `result()` — the result as the object the method returns (`result(true)` for the raw array).
- `errorCode()`, `description()` and `parameters()` — the failure, when there is one.
- `retryAfter()` and `migrateToChatId()` — the extras Telegram attaches to a failure.
- `toArray()` and `toJson()` — the result; pass `true` for the whole response, envelope included.
- `throw()` — raise the exception matching a failure, and do nothing otherwise.

</div>

```php
$response = $request->sendMessage($chatId, 'Hello!');

if ($response->failed()) {
    logger()->warning($response->description(), ['code' => $response->errorCode()]);
}

$response->toArray();      // ['message_id' => 42, 'chat' => [...], ...]
$response->toArray(true);  // ['ok' => true, 'result' => [...]]
```

The response is also countable, iterable and JSON-serializable, and `json_encode` writes exactly what Telegram sent.

<a name="api-errors"></a>
### Handling Errors

A call Telegram refuses does not throw by default: it returns the failure, and your code decides what to do with it.

```php
$response = $request->sendMessage($chatId, 'Hello!');

if ($response->failed()) {
    logger()->warning($response->description(), ['code' => $response->errorCode()]);
}
```

When a failure is exceptional in your application, the `throw` method turns the response into the exception that matches it, so you may catch exactly what you know how to handle. It reads well at the end of a call, too: `$request->sendMessage(...)->throw()`.

```php
use LaraGram\Laraquest\Exceptions\BotBlockedException;
use LaraGram\Laraquest\Exceptions\FloodException;
use LaraGram\Laraquest\Exceptions\TelegramApiException;

try {
    $request->throw()->sendMessage($chatId, 'Hello!');
} catch (BotBlockedException) {
    $user->update(['blocked_at' => now()]);
} catch (FloodException $e) {
    ReleaseQueue::dispatch()->delay($e->retryAfter());
} catch (TelegramApiException $e) {
    report($e);
}
```

To make every call throw, set `throw_exceptions` in your `config/laraquest.php` file (or the `LARAQUEST_THROW_EXCEPTIONS` environment variable), and opt a single call back out with `silent`:

```php
$response = $request->silent()->getChatMember($chatId, $userId);
```

Every exception extends `LaraGram\Laraquest\Exceptions\TelegramApiException` and exposes the failure: `method()`, `parameters()`, `errorCode()`, `description()`, `response()`, `retryAfter()`, `migrateToChatId()` and `isRetryable()`.

<div class="overflow-auto">

| Exception | Raised when |
| --- | --- |
| `BadRequestException` | Telegram refused the request itself (400) |
| `ChatNotFoundException` | The chat does not exist, or the bot has never met it |
| `UserNotFoundException` | The user does not exist, or the bot has never met them |
| `MessageNotFoundException` | The message to edit, delete, forward, copy, pin or reply to is gone |
| `MessageNotModifiedException` | An edit would leave the message exactly as it is |
| `ChatMigratedException` | The group became a supergroup; follow it with `migrateToChatId()` |
| `InvalidFileException` | The file identifier, URL or type was not accepted |
| `UnauthorizedException` / `InvalidTokenException` | The bot token was not accepted (401) |
| `ForbiddenException` | The bot is not allowed to do this (403) |
| `BotBlockedException` | The user blocked the bot |
| `BotKickedException` | The bot was removed from the chat, or may not write in it |
| `UserDeactivatedException` | The account was deleted or deactivated |
| `NotEnoughRightsException` | The bot lacks the administrator rights the call needs |
| `NotFoundException` | The method or the resource does not exist (404) |
| `ConflictException` | A webhook and `getUpdates` are competing (409) |
| `RequestEntityTooLargeException` | The uploaded file was too large (413) |
| `FloodException` | The bot is sending too fast (429); wait `retryAfter()` seconds |
| `InternalServerErrorException` | Telegram failed to handle the call (5xx) |
| `ConnectionException` | The call never reached Telegram |

</div>

> [!NOTE]
> [Broadcasts](/v4/broadcasting) and [anti-flood](#smart-anti-flood) keep working the same way whether calls throw or not: a 429 is still retried, a blocked chat is still marked unreachable, and a migrated group is still followed.

<a name="update-objects"></a>
### Update Objects

Every Bot API type has a class of its own in `LaraGram\Laraquest\Updates`, with one `init` parameter per field. It is the readable way to build the smaller structures a call takes, and the editor lists the fields for you:

```php
use LaraGram\Laraquest\Updates\LinkPreviewOptions;
use LaraGram\Laraquest\Updates\ReplyParameters;

$request->sendMessage($chatId, 'Have a look at this',
    link_preview_options: LinkPreviewOptions::init(url: $url, prefer_large_media: true),
    reply_parameters: ReplyParameters::init(message_id: $messageId, allow_sending_without_reply: true),
);
```

> [!NOTE]
> Keyboards are the exception: build them with the [keyboard builder](/v4/keyboards) (`Keyboard::inlineKeyboardMarkup(Make::row(...))`), which is shorter, validates the buttons, and handles right-to-left layouts for you.

The same classes read what Telegram sent. `from` accepts an array, a decoded object or a JSON string, and every field that is an object of its own comes back as one:

```php
use LaraGram\Laraquest\Updates\Message;

$message = Message::from($response['result']);

$message->chat->id;                  // objects all the way down
$message->entities[0]->type;         // lists of objects too
$message->get('from.username');      // or a dotted path
$message->has('photo');
$message->only(['message_id', 'text'])->toArray();
count($message);
```

An update object is countable, iterable, may be read as an array (`$message['text']`), and serializes back to exactly what Telegram expects with `toArray` and `toJson`.

<a name="request-mode"></a>
### Request mode

You can also configure how requests are sent. This can easily be done using the `mode` method.

In some cases, you might not need a response from Telegram — so you can use the no-response mode to improve performance and achieve faster execution.

```php
use LaraGram\Laraquest\Mode;

$request->mode(Mode::NO_RESPONSE_CURL)->sendMessage();
```

Or, you can use its integer equivalent instead:

```php
$request->mode(64)->sendMessage();
```

<a name="available-request-modes"></a>
### Available Request Modes

| Enum Value               | Int  |
|--------------------------|------|
| `Mode::CURL` (default)   | `32` |
| `Mode::NO_RESPONSE_CURL` | `64` |

### Default API Parameters

LaraGram allows you to specify global default values for optional Telegram Bot API parameters. This eliminates the need to repeatedly pass identical options (such as `parse_mode` or `disable_web_page_preview`) across all your API requests.

These default parameters only apply to optional arguments and are defined under the `default_parameters` array within your `config/laraquest.php` configuration file.

#### Configuring Method Defaults

You can set default parameters for specific methods individually, or define groups to apply defaults to multiple methods simultaneously.

```php
// config/laraquest.php

return [
    // ...

    'default_parameters' => [
        // Set defaults for groups of methods
        'groups' => [
            [
                'methods' => ['sendPhoto', 'sendVideo'],
                'defaults' => [
                    'parse_mode' => 'markdown',
                ]
            ]
        ],
        
        // Set defaults for specific individual methods
        'sendMessage' => [
            'parse_mode' => 'html',
        ]
    ]
];
```

With the configuration above, any call to `sendMessage` will automatically include `'parse_mode' => 'html'` under the hood, and calls to `sendPhoto` or `sendVideo` will fallback to using `'markdown'` formatting unless you explicitly override them at runtime:

```php
// Uses HTML parse_mode automatically based on your config
$request->sendMessage($chatId, 'Hello <b>World</b>');

// Explicitly overrides the default configuration
$request->sendMessage($chatId, 'Hello *World*', parse_mode: 'markdown');
```

<a name="file-downloads"></a>

## File Downloads

### Determining If File Is Present

To check if the incoming request contains any downloadable media or files (such as photos, videos, documents, etc.), you can use the `hasFile` method on the `Request` instance:

```php
Bot::onPhoto(function (Request $request) {
    if ($request->hasFile()) {
        // The request contains one or more files...
    }
});

```

### Retrieving Files

To retrieve the files attached to the current request, you may use the `file` method. This method returns an instance of `LaraGram\Request\Files\FileBag` if files are present, or `null` if the request contains no media:

```php
$fileBag = $request->file();
```

Alternatively, if you want to extract a `FileBag` from a specific Telegram message object manually, you may use the `fileFrom` method:

```php
$fileBag = $request->fileFrom($request->message);
```

### Working With File Bags

The `LaraGram\Request\Files\FileBag` class wraps all files associated with a single request or message, allowing you to seamlessly handle albums, various photo sizes, or video qualities.

#### Inspecting the File Bag

The `FileBag` provides several convenient helper methods to inspect the content:

```php
// Check if the bag is empty or not
if ($fileBag->isNotEmpty()) {
    $count = $fileBag->count();
    $mediaType = $fileBag->type(); // e.g., 'photo', 'video', 'document'
    $mimeType = $fileBag->mimeType(); // Returns the MIME type of the first file
}

// Check if the files are part of a media group (album)
if ($fileBag->isAlbum()) {
    $albumId = $fileBag->mediaGroupId();
}

// Check if the files require Telegram Stars (Paid Media)
if ($fileBag->isPaidMedia()) {
    // ...
}
```

#### Accessing Individual Files

You can easily pull specific files out of the `FileBag`:

```php
// Get the first file (for photos, this is the smallest available size)
$smallestFile = $fileBag->first();

// Get the last file (for photos, this is the largest available size)
$largestFile = $fileBag->last();

// Get all files as an array of MediaFile objects
$allFiles = $fileBag->all();

// Get a file by its specific index
$file = $fileBag->get(0);
```

#### Downloading All Files

The `downloadAll` method downloads all files inside the bag sequentially. You can pass a string directory path (where files will automatically be saved using their `file_unique_id`), or an array of explicit paths mapped to each file index:

```php
// Download all files to a specific directory automatically using unique IDs
$fileBag->downloadAll('downloads/photos');

// Download specifying custom names per file index
$fileBag->downloadAll([
    0 => 'downloads/thumb.jpg',
    1 => 'downloads/full.jpg'
]);

// You can also specify a custom storage disk as the second argument
$fileBag->downloadAll('downloads/photos', 's3');
```

### Working With Media Files

Each item inside a `FileBag` is represented by a `LaraGram\Request\Files\MediaFile` instance. This class gives you access to full file metadata and direct actions.

#### Downloading a Single File

To download an individual `MediaFile`, use the `download` method. LaraGram automatically calls Telegram's `getFile` API behind the scenes to resolve the absolute path, downloads the raw stream, and stores it using the integrated `Storage` system:

```php
// Download to default storage disk
$file->download('avatars/user_profile.jpg');

// Download to a specific configured storage disk (e.g., public, s3)
$file->download('avatars/user_profile.jpg', 'public');
```

If you only need the absolute Telegram download URL (or local server path), you may call the url method:

```php
$url = $file->url();
```

#### Retrieving File Metadata

```php
$fileId = $file->fileId();
$uniqueId = $file->fileUniqueId();
$sizeInBytes = $file->fileSize();
$fileName = $file->fileName(); // Only available for documents

// Dimensions and durations
$width = $file->width();
$height = $file->height();
$duration = $file->duration(); // For video/audio
```

#### Verifying Media Types

You can evaluate the specific nature of a `MediaFile` using fluent boolean checkers:

```php
if ($file->isPhoto()) { /* ... */ }
if ($file->isVideo()) { /* ... */ }
if ($file->isDocument()) { /* ... */ }
if ($file->isSticker()) { /* ... */ }
if ($file->isAudio()) { /* ... */ }
if ($file->isVoice()) { /* ... */ }
if ($file->isVideoNote()) { /* ... */ }
if ($file->isAnimation()) { /* ... */ }
if ($file->isLivePhoto()) { /* ... */ }
```

#### Handling Sizes and Qualities

If a file has multiple sizes (like Telegram Photos) or different quality options (like Videos in newer Bot API versions), you can navigate through those variants directly from the file instance:

```php
if ($file->hasSizes()) {
    $allVariants = $file->sizes();
    $lowestQuality = $file->smallest();
    $highestQuality = $file->largest();
    
    // Get variant at specific index
    $mediumQuality = $file->size(1);
}
```

<a name="multi-connections"></a>
## Multi connections

You can easily create multiple bot connections and use different connections for different types of requests.

To do this, simply define a new connection with a custom name in the `connections` section of the `config/bot.php` file.

After that, you can specify which connection to use for sending a request using the connection method.

```php
$request->connection('connection_name')->sendMessage();
```

You can also apply a specific connection to a group of listeners.

```php
Bot::connection('connection_name')->group(function (){
    // ...
});
```

<a name="receiving-updates-from-multiple-bots"></a>
### Receiving Updates From Multiple Bots

Sometimes you want a single application to receive updates from several different bots at once. To do this, set the `default` connection to `auto` in the `config/bot.php` file and give each connection its own `secret_token`:

```php
'default' => 'auto',

'connections' => [
    'first' => [
        'token' => env('FIRST_BOT_TOKEN'),
        'url' => 'https://example.com/webhook',
        'secret_token' => 'first-bot-secret',
    ],
    'second' => [
        'token' => env('SECOND_BOT_TOKEN'),
        'url' => 'https://example.com/webhook',
        'secret_token' => 'second-bot-secret',
    ],
],
```

Then register the webhook of every bot, so Telegram sends its secret token with each update:

```shell
php laragram webhook:set --connection=first
php laragram webhook:set --connection=second
```

When the `default` connection is `auto`, LaraGram detects which connection every incoming update belongs to **before** any middleware runs or any listener is matched. The detected connection is bound to that update's request only, so every call you send back uses the correct bot, listeners limited with `forConnections` only match their own bot, and updates of different bots never affect each other—even when a long-running server such as Surge handles many updates in one process.

The connection is detected from, in order:

<div class="content-list" markdown="1">

- The `LARAGRAM_BOT_CONNECTION` server variable, when the web server or the process feeding the update sets it.
- The webhook secret token. A connection with a `secret_token` only claims updates that carry that exact token, and a connection without one only claims updates that carry no token.
- The webhook `url`. If several connections are still candidates, the update is claimed by the connection whose `url` path (and query string) matches the requested URI—for example `https://example.com/webhook?bot=first` and `https://example.com/webhook?bot=second`.

</div>

An application with a single connection always uses it. If no single connection can be told apart, the update is not handled and an `UnresolvableConnectionException` is reported, rather than risking a reply from the wrong bot.

> [!NOTE]
> The `secret_token` (or `url`) of each connection must be unique. After changing a secret token, run `webhook:set` again for that connection so Telegram starts sending the new token.

You may retrieve the connection handling the current update using the `botConnection` method or the `bot_connection` helper:

```php
$connection = $request->botConnection();

$connection = bot_connection();
```

Steps and conversations are stored per bot when the `default` connection is `auto`, so a user talking to two of your bots keeps a separate step and conversation in each.

<a name="custom-connection-detection"></a>
#### Custom Connection Detection

If your bots need a different way to be told apart, register a resolver in the `boot` method of your `AppServiceProvider`. It receives the request and the configured connections, and returns the connection name. Returning `null` falls back to the built-in detection:

```php
use LaraGram\Request\ConnectionResolver;
use LaraGram\Request\Request;

ConnectionResolver::resolveUsing(function (Request $request, array $connections) {
    return $request->server()->get('HTTP_X_BOT_NAME');
});
```

<a name="smart-anti-flood"></a>
## Smart Anti-Flood

Telegram enforces flood limits on outgoing API calls — roughly 30 calls per second overall, 1 message per second to an individual chat, and about 20 messages per minute to a group. Exceed them and Telegram replies with a `429 Too Many Requests` error. Sprinkling `sleep()` calls throughout your code to avoid this is tedious and error-prone.

LaraGram's **Smart Anti-Flood** paces every outgoing Bot API call for you automatically. Naturally spaced calls and short bursts pay **zero delay**; only a sustained loop — a bulk send, mass delete, or broadcast — is slowed, and only exactly when a flood would otherwise occur. You never write a `sleep` by hand again.

Every Telegram method you call through the `Request` class flows through the anti-flood gate transparently, so there is nothing to change in your listens.

<a name="anti-flood-enabling"></a>
### Enabling Anti-Flood

Anti-flood is configured in the `anti_flood` section of your `config/bot.php` file and is toggled with the `ANTI_FLOOD` environment variable:

```ini
ANTI_FLOOD=true
ANTI_FLOOD_STORE=redis
```

> [!WARNING]
> On a webhook bot, each incoming update is handled by a **separate PHP process**. For per-chat pacing to hold across those processes, anti-flood state must live in a **shared** cache store. Use `redis` (or another shared driver) for webhooks. The `array` store is only correct for a single long-running process such as [Surge](/v4/surge). Durable stores like `database` and `file` are not recommended for this hot, ephemeral state.

<a name="anti-flood-configuration"></a>
### Configuration

Each limit allows a `burst` of instant, back-to-back calls, then paces to `rate` calls per `per` seconds. The default configuration defines a global limit and per-chat limits for private chats and groups:

```php
'anti_flood' => [

    'enabled' => env('ANTI_FLOOD', false),

    'store' => env('ANTI_FLOOD_STORE', 'redis'),

    'global' => [
        'rate' => 30,
        'per' => 1,
        'burst' => 30,
    ],

    'chat' => [
        'private' => [
            'rate' => 1,
            'per' => 1,
            'burst' => 5,
        ],
        'group' => [
            'rate' => 20,
            'per' => 60,
            'burst' => 5,
        ],
    ],

    'custom' => [
        'broadcast' => [
            'rate' => 4,
            'per' => 1,
            'every' => 100,
            'pause' => 10,
            'shared' => false,
        ],
    ],

    'reactive' => [
        'enabled' => true,
        'cooldown_margin' => 0.5,
        'default_cooldown' => 1.0,
    ],

    'sleep' => [
        'driver' => 'auto', // auto | usleep | coroutine (needs Swoole)
        'max_delay' => 5.0,
    ],
],
```

When more than one limit applies to a call (for example the global limit and a per-chat limit), the **larger** delay wins.

Each limit accepts the following keys:

<div class="overflow-auto">

| Key | Description |
| --- | ----------- |
| `rate` | The sustained number of calls allowed per `per` seconds. |
| `per` | The window, in seconds, over which `rate` is measured. |
| `burst` | How many back-to-back calls are allowed before pacing kicks in (default `rate * per`). |
| `every` | Rest after every N calls (`0` disables). Useful for broadcast safety. |
| `pause` | The number of seconds to pause when the `every` threshold is reached. |
| `shared` | When `true` (default), the limit is coordinated across all requests/processes via the store. When `false`, it applies only within the current process. |

</div>

<a name="anti-flood-reactive"></a>
### Reactive 429 Handling

Beyond proactively pacing calls, anti-flood also reacts to any `429 Too Many Requests` that slips through: when Telegram returns a `retry_after`, the affected limit is pushed forward so subsequent calls respect the cooldown Telegram asked for. This is configured under the `reactive` key.

<a name="anti-flood-per-call"></a>
### Per-Call Control

You may adjust anti-flood behavior for a single call using fluent methods on the `Request` instance.

Skip anti-flood entirely for the next call with `withoutAntiFlood`:

```php
$request->withoutAntiFlood()->sendMessage($chatId, 'Urgent!');
```

When sending a broadcast, pace it by a named custom limit instead of the automatic per-chat limit using `antiFloodWith`. This lets a broadcast run at its own throttle rather than being bottlenecked to one message per second per chat (the global ceiling still applies):

```php
foreach ($chatIds as $chatId) {
    $request->antiFloodWith('broadcast')->sendMessage($chatId, $announcement);
}
```

> [!TIP]
> To message all of your users or groups, prefer [Telegram broadcasts](/v4/broadcasting#telegram-broadcasts). They use this scope automatically and also queue the work, skip chats that blocked the bot, retry rate limited calls, and track progress.

<a name="anti-flood-eta"></a>
### Inspecting the Delay

You may peek at how long the next call to a limit would be delayed — without actually consuming a slot — using the `availableIn` method on the resolved engine. This is handy for estimating how long a broadcast will take:

```php
use LaraGram\Support\Facades\Request;

$seconds = app('antiflood')->availableIn('broadcast');
```

<a name="proxy-pool"></a>
## Proxy Pool

If your server cannot reach the Telegram Bot API directly, LaraGram can route every outgoing call through a **proxy pool**. Provide a list of proxies and the bot automatically fails over to the next healthy proxy when the active one stops responding.

Like anti-flood, the proxy layer hooks into the `Request` class transparently — every Telegram method you call is routed through the active proxy with no changes to your listens.

<a name="proxy-enabling"></a>
### Enabling the Proxy

The proxy pool is configured in the `proxy` section of `config/bot.php` and toggled with the `BOT_PROXY` environment variable:

```ini
BOT_PROXY=true
```

<a name="proxy-configuration"></a>
### Configuration

```php
'proxy' => [

    'enabled' => env('BOT_PROXY', false),

    'connect_timeout' => 5,

    'timeout' => 10,

    'retry' => 2,

    'strategy' => 'failover', // 'failover' | 'round_robin' | 'random'

    'retry_after' => 60,

    'store' => env('BOT_PROXY_STORE', null),

    'health' => [
        'url' => env('API_ENDPOINT', 'https://api.telegram.org'),
        'timeout' => 5,
    ],

    'list' => [
        'primary'  => 'socks5://user:pass@127.0.0.1:1080',
        'backup'   => 'http://10.0.0.2:8080',
        'explicit' => ['type' => 'socks5', 'host' => '1.2.3.4', 'port' => 1080],
    ],
],
```

Each entry in the `list` may be written as a connection string (`type://user:pass@host:port`) or as an explicit array. The following configuration keys are available:

<div class="overflow-auto">

| Key | Description |
| --- | ----------- |
| `connect_timeout` | Seconds to wait while establishing the proxy connection. |
| `timeout` | Seconds to wait for the whole request. |
| `retry` | Additional attempts before giving up (the bot fails over between attempts). |
| `strategy` | How the active proxy is chosen: `failover` (first healthy), `round_robin`, or `random`. |
| `retry_after` | Seconds a proxy is considered "down" after a failure before it is retried. |
| `store` | Cache store used to share down-state across processes (`null` keeps it in-memory). |
| `health` | The URL and timeout used by the `ping` health checks. |

</div>

> [!NOTE]
> Supported proxy types are `http`, `https`, `socks4`, `socks4a`, `socks5`, and `socks5h`. MTProto proxies cannot be used here — they are a client-protocol proxy and cannot tunnel Bot API HTTP traffic.

<a name="proxy-failover"></a>
### Failover and Recovery

When a call fails with a connection-level error, the proxy manager marks the current proxy as **down**, rotates to the next healthy one, and retries. A downed proxy is automatically retried again after `retry_after` seconds. If every proxy is down, the pool resets and reuses the first proxy so the bot never goes completely dark.

<a name="proxy-facade"></a>
### The Proxy Facade

You may inspect and manage the pool at runtime through the `Proxy` facade:

```php
use LaraGram\Support\Facades\Proxy;

// The currently active proxy...
$current = Proxy::current();

// Manually rotate to the next healthy proxy...
Proxy::rotate();

// Health-check a proxy, or all of them...
Proxy::ping('primary');
Proxy::pingAll();

// Mark a proxy up or down...
Proxy::markDown('backup');
Proxy::markUp('backup');

// Inspect pool statistics...
$stats = Proxy::stats();
```

<a name="proxy-per-call"></a>
### Per-Call Control

You may override the proxy for a single call using the fluent methods on the `Request` instance:

```php
// Send this one call directly, bypassing the proxy...
$request->withoutProxy()->sendMessage($chatId, 'Hello');

// Force this call through a specific proxy...
$request->withProxy('backup')->sendMessage($chatId, 'Hello');
```
