# Listening

<a name="basic-listening"></a>
## Basic Listening

The most basic LaraGram listens accept a ?pattern and a closure, providing a very simple and expressive method of defining listens and behavior without complicated listening configuration files:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Request\Request;

Bot::onText('hello', function (Request $request) {
    $request->sendMessage(chat()->id, 'hi');
});
```

<a name="the-default-listen-files"></a>
### The Default Listen Files

All LaraGram listens are defined in your listen files, which are located in the `listens` directory. These files are automatically loaded by LaraGram using the configuration specified in your application's `bootstrap/app.php` file. The `listens/bot.php` file defines listens that are for your bot interface. These listens are assigned the `bot` [middleware group](/v4/middleware#laragrams-default-middleware-groups), which provides features like scope limiter.

For most applications, you will begin by defining listens in your `listens/bot.php` file. The listens defined in `listens/bot.php` may be accessed by entering the defined listen's update in your bot. For example, you may access the following listen by sending a `hello`:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Request\Request;

Bot::onText('hello', function (Request $request) {
    $request->sendMessage(chat()->id, 'hi');
});
```

<a name="available-listener-methods"></a>
#### Available Listener Methods

The listener allows you to register listens that respond to any Bot verb:

```php
Bot::on($message, $action)
Bot::onText($message, $action)
Bot::onCommand($command, $action)
Bot::onAnimation($action)
Bot::onAudio($action)
Bot::onDocument($action)
Bot::onPhoto($action)
Bot::onSticker($action)
Bot::onVideo($action)
Bot::onVideoNote($action)
Bot::onVoice($action)
Bot::onContact($action)
Bot::onDice($action, $emoji, $value)
Bot::onGame($action)
Bot::onPoll($action)
Bot::onVenue($action)
Bot::onLocation($action)
Bot::onNewChatMembers($action)
Bot::onLeftChatMember($action)
Bot::onNewChatTitle($action)
Bot::onNewChatPhoto($action)
Bot::onDeleteChatPhoto($action)
Bot::onGroupChatCreated($action)
Bot::onSuperGroupChatCreated($action)
Bot::onMessageAutoDeleteTimerChanged($action)
Bot::onMigrateToChatId($action)
Bot::onMigrateFromChatId($action)
Bot::onPinnedMessage($action)
Bot::onInvoice($action)
Bot::onSuccessfulPayment($action)
Bot::onConnectedWebsite($action)
Bot::onPassportData($action)
Bot::onProximityAlertTriggered($action)
Bot::onForumTopicCreated($action)
Bot::onForumTopicEdited($action)
Bot::onForumTopicClosed($action)
Bot::onForumTopicReopened($action)
Bot::onVideoChatScheduled($action)
Bot::onVideoChatStarted($action)
Bot::onVideoChatEnded($action)
Bot::onVideoChatParticipantsInvited($action)
Bot::onWebAppData($action)
Bot::onMessage($action)
Bot::onMessageType($type, $action)
Bot::onEditedMessage($action)
Bot::onChannelPost($action)
Bot::onEditedChannelPost($action)
Bot::onInlineQuery($action)
Bot::onChosenInlineResult($action)
Bot::onCallbackQuery($action)
Bot::onCallbackQueryData($pattern, $action)
Bot::onShippingQuery($action)
Bot::onPreCheckoutQuery($action)
Bot::onPollAnswer($action)
Bot::onMyChatMember($action)
Bot::onChatMember($action)
Bot::onChatJoinRequest($action)
Bot::onReferral($pattern, $action)
Bot::onAddMember($action)
Bot::onJoinMember($action)
Bot::onMention($action)
Bot::onHashtag($action)
Bot::onCashtag($action)
Bot::onUrl($action)
Bot::onEmail($action)
Bot::onPhoneNumber($action)
Bot::onTextLink($action)
Bot::onTextMention($action)
Bot::onCustomEmoji($action)
Bot::onSpoiler($action)
Bot::onBlockquote($action)
Bot::onExpandableBlockquote($action)
Bot::onBold($action)
Bot::onItalic($action)
Bot::onUnderline($action)
Bot::onStrikethrough($action)
Bot::onCode($action)
Bot::onPre($action)
Bot::onStory($action)
Bot::onPaidMedia($action)
Bot::onLivePhoto($action)
Bot::onAlbum($action)
Bot::onChecklist($action)
Bot::onChecklistTasksDone($action)
Bot::onChecklistTasksAdded($action)
Bot::onBoostAdded($action)
Bot::onChatBackgroundSet($action)
Bot::onChannelChatCreated($action)
Bot::onGift($action)
Bot::onUniqueGift($action)
Bot::onGiftUpgradeSent($action)
Bot::onRefundedPayment($action)
Bot::onUsersShared($action)
Bot::onChatShared($action)
Bot::onWriteAccessAllowed($action)
Bot::onGiveawayCreated($action)
Bot::onGiveaway($action)
Bot::onGiveawayWinners($action)
Bot::onGiveawayCompleted($action)
Bot::onGeneralForumTopicHidden($action)
Bot::onGeneralForumTopicUnhidden($action)
Bot::onDirectMessagePriceChanged($action)
Bot::onPaidMessagePriceChanged($action)
Bot::onPollOptionAdded($action)
Bot::onPollOptionDeleted($action)
Bot::onSuggestedPostApproved($action)
Bot::onSuggestedPostApprovalFailed($action)
Bot::onSuggestedPostDeclined($action)
Bot::onSuggestedPostPaid($action)
Bot::onSuggestedPostRefunded($action)
Bot::onManagedBotCreated($action)
Bot::onChatOwnerLeft($action)
Bot::onChatOwnerChanged($action)
Bot::onRichMessage($action)
Bot::onRichMessageType($type, $action)
Bot::onMessageReaction($action)
Bot::onMessageReactionEmoji($action, $emoji)
Bot::onMessageReactionCustomEmoji($action)
Bot::onMessageReactionType($type, $action)
Bot::onMessageReactionCount($action)
Bot::onBusinessConnection($action)
Bot::onBusinessMessage($action)
Bot::onBusinessMessageText($pattern, $action)
Bot::onEditedBusinessMessage($action)
Bot::onEditedBusinessMessageText($pattern, $action)
Bot::onDeletedBusinessMessages($action)
Bot::onGuestMessage($action)
Bot::onGuestMessageText($pattern, $action)
Bot::onEditedMessageText($pattern, $action)
Bot::onChannelPostText($pattern, $action)
Bot::onEditedChannelPostText($pattern, $action)
Bot::onInlineQueryQuery($pattern, $action)
Bot::onChosenInlineResultQuery($pattern, $action)
Bot::onPurchasedPaidMedia($action)
Bot::onChatBoost($action)
Bot::onRemovedChatBoost($action)
Bot::onManagedBot($action)
Bot::onPollUpdate($action)
```

Sometimes you may need to register a listen that responds to multiple Bot verbs. You may do so using the `match` method:

```php
Bot::match(['TEXT', 'COMMAND'], 'start', function () {
    // ...
});
```

> [!NOTE]
> When defining multiple listens that share the same Pattern, listens using the `TEXT`, `COMMAND`, `DICE`, `UPDATE`, `MESSAGE` methods should be defined before listens using the `match`, and `redirect` methods. This ensures the incoming request is matched with the correct listen.

<a name="dependency-injection"></a>
#### Dependency Injection

You may type-hint any dependencies required by your listen in your listen's callback signature. The declared dependencies will automatically be resolved and injected into the callback by the LaraGram [service container](/v4/container). For example, you may type-hint the `LaraGram\Request\Request` class to have the current Bot request automatically injected into your listen callback:

```php
use LaraGram\Request\Request;

Bot::onText('hello', function (Request $request) {
    // ...
});
```

<a name="redirect-listens"></a>
### Redirect Listens

If you are defining a listen that redirects to another listen, you may use the `Bot::redirect` method. This method provides a convenient shortcut so that you do not have to define a full listen or controller for performing a simple redirect:
The second parameter must be a destination listens name.

```php
Bot::redirect('hello', 'main');
```

> [!WARNING]
> When using listen parameters in redirect listens, the following parameters are reserved by LaraGram and cannot be used: `destination`.

<a name="template-listens"></a>
### Template Listens

If your listen only needs to return a [template](/v4/templates), you may use the `Bot::template` method. Like the `redirect` method, this method provides a simple shortcut so that you do not have to define a full listen or controller. The `template` method accepts a pattern as its first argument and a template name as its second argument and a update verbs as its third argument. In addition, you may provide an array of data to pass to the template as an optional fourth argument:

```php
Bot::template('hello', 'welcome');

Bot::template('/start', 'welcome', 'COMMAND', ['name' => 'LaraGram']);
```

> [!WARNING]
> When using listen parameters in template listens, the following parameters are reserved by LaraGram and cannot be used: `template`, `data`.

<a name="listing-your-listens"></a>
### Listing Your Listens

The `listen:list` Commander command can easily provide an overview of all of the listens that are defined by your application:

```shell
php laragram listen:list
```

By default, the listen middleware that are assigned to each listen will not be displayed in the `listen:list` output; however, you can instruct LaraGram to display the listen middleware and middleware group names by adding the `-v` option to the command:

```shell
php laragram listen:list -v

# Expand middleware groups...
php laragram listen:list -vv
```

You may also instruct LaraGram to only show listens that begin with a given Pattern:

```shell
php laragram listen:list --pattern=admin
```

In addition, you may instruct LaraGram to hide any listens that are defined by third-party packages by providing the `--except-vendor` option when executing the `listen:list` command:

```shell
php laragram listen:list --except-vendor
```

Likewise, you may also instruct LaraGram to only show listens that are defined by third-party packages by providing the `--only-vendor` option when executing the `listen:list` command:

```shell
php laragram listen:list --only-vendor
```

<a name="listening-customization"></a>
### Listening Customization

By default, your application's listens are configured and loaded by the `bootstrap/app.php` file:

```php
<?php

use LaraGram\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withListening(
        bot: __DIR__.'/../listens/bot.php',
        commands: __DIR__.'/../listens/console.php',
    )->create();
```

However, sometimes you may want to define an entirely new file to contain a subset of your application's listens. To accomplish this, you may provide a `then` closure to the `withListening` method. Within this closure, you may register any additional listens that are necessary for your application:

```php
use LaraGram\Support\Facades\Bot;

->withListening(
    bot: __DIR__.'/../listens/bot.php',
    commands: __DIR__.'/../listens/console.php',
    then: function () {
        Bot::middleware('scope:groups')
            ->name('group.')
            ->group(base_path('listens/group.php'));
    },
)
```

Or, you may even take complete control over listen registration by providing a `using` closure to the `withListening` method. When this argument is passed, no Bot listens will be registered by the framework and you are responsible for manually registering all listens:

```php
use LaraGram\Support\Facades\Bot;

->withListening(
    commands: __DIR__.'/../listens/console.php',
    using: function () {
        Bot::middleware('bot')
            ->group(base_path('listens/bot.php'));
    },
)
```

<a name="assigning-listen-files-to-connections"></a>
### Assigning Listen Files to Connections

If your application uses multiple bot connections, you may assign each listen file to one or more specific connections. To do so, pass an array to the `bot` argument of the `withListening` method. Each element may be either a plain file path, which registers the file for all connections, or a `path => connection` pair, which limits the file to the given connection(s):

```php
<?php

use LaraGram\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withListening(
        bot: [
            __DIR__.'/../listens/bot.php', // All connections
            __DIR__.'/../listens/first.php' => 'first-bot', // A specific connection
            __DIR__.'/../listens/multi.php' => ['first-bot', 'second-bot'], // Multiple connections
            __DIR__.'/../listens/all.php' => '*', // All connections
        ],
        commands: __DIR__.'/../listens/console.php',
    )->create();
```

The connection names refer to the connections defined in your application's bot configuration. The values you may assign are:

<div class="overflow-x-auto">

| Value | Description |
| --- | --- |
| A plain path (no key) | The listen file is registered for **all** connections. |
| `'first-bot'` | The listen file is registered only for the `first-bot` connection. |
| `['first-bot', 'second-bot']` | The listen file is registered for each of the listed connections. |
| `'*'` | The listen file is registered for **all** connections. |

</div>

<a name="listen-parameters"></a>
## Listen Parameters

<a name="required-parameters"></a>
### Required Parameters

Sometimes you will need to capture segments of the Pattern within your listen. For example, you may need to capture a user's ID from the update. You may do so by defining listen parameters:

```php
use LaraGram\Request\Request;

Bot::onText('user {id}', function (Request $request, string $id) {
    $request->sendMessage(chat()->id, 'User ID: ' . $id);
});
```

You may define as many listen parameters as required by your listen:

```php
Bot::onText('set {id} {role}', function (string $id, string $role) {
    // ...
});
```

Listen parameters are always encased within `{}` braces and should consist of alphabetic characters. Underscores (`_`) are also acceptable within listen parameter names. Listen parameters are injected into listen callbacks / controllers based on their order - the names of the listen callback / controller arguments do not matter.

<a name="parameters-and-dependency-injection"></a>
#### Parameters and Dependency Injection

If your listen has dependencies that you would like the LaraGram service container to automatically inject into your listen's callback, you should list your listen parameters after your dependencies:

```php
use LaraGram\Request\Request;

Bot::onText('user {id}', function (Request $request, string $id) {
    $request->sendMessage(chat()->id, 'User ID: ' . $id);
});
```

<a name="parameters-optional-parameters"></a>
### Optional Parameters

Occasionally you may need to specify a listen parameter that may not always be present in the message. You may do so by placing a `?` mark after the parameter name. Make sure to give the listen's corresponding variable a default value:

```php
Bot::onText('user {id?}', function (Request $request, ?string $id = 'Unknown') {
    $request->sendMessage(chat()->id, 'User ID: ' . $id);
});
```

<a name="parameters-regular-expression-constraints"></a>
### Regular Expression Constraints

You may constrain the format of your listen parameters using the `where` method on a listen instance. The `where` method accepts the name of the parameter and a regular expression defining how the parameter should be constrained:

```php
Bot::onText('user {name}', function (string $name) {
    // ...
})->where('name', '[A-Za-z]+');

Bot::onText('user {id}', function (string $id) {
    // ...
})->where('id', '[0-9]+');

Bot::onText('user {id} {name}', function (string $id, string $name) {
    // ...
})->where(['id' => '[0-9]+', 'name' => '[a-z]+']);
```

For convenience, some commonly used regular expression patterns have helper methods that allow you to quickly add pattern constraints to your listens:

```php
Bot::onText('user {id} {name}', function (string $id, string $name) {
    // ...
})->whereNumber('id')->whereAlpha('name');

Bot::onText('user {name}', function (string $name) {
    // ...
})->whereAlphaNumeric('name');

Bot::onText('user {id}', function (string $id) {
    // ...
})->whereUuid('id');

Bot::onText('user {id}', function (string $id) {
    // ...
})->whereUlid('id');

Bot::onText('category {slug}', function (string $id) {
    // ...
})->whereIn('slug', ['movie', 'song', 'painting']);

Bot::onText('category {slug}', function (string $category) {
    // ...
})->whereIn('slug', CategoryEnum::cases());
```

<a name="parameters-global-constraints"></a>
#### Global Constraints

If you would like a listen parameter to always be constrained by a given regular expression, you may use the `pattern` method. You should define these patterns in the `boot` method of your application's `App\Providers\AppServiceProvider` class:

```php
use LaraGram\Support\Facades\Bot;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Bot::pattern('id', '[0-9]+');
}
```

Once the pattern has been defined, it is automatically applied to all listens using that parameter name:

```php
Bot::onText('user {id}', function (string $id) {
    // Only executed if {id} is numeric...
});
```

<a name="named-listens"></a>
## Named Listens

Named listens allow the redirects for specific listens or ... . You may specify a name for a listen by chaining the `name` method onto the listen definition:

```php
Bot::onCommand('panel', function () {
    // ...
})->name('profile');
```

You may also specify listen names for controller actions:

```php
Bot::onCommand(
    'panel',
    [UserProfileController::class, 'show']
)->name('profile');
```

> [!WARNING]
> Listen names should always be unique.

<a name="generating-pattern-to-named-listens"></a>
#### Generating Pattern to Named Listens

Once you have assigned a name to a given listen, you may use the listen's name when generating Pattern or redirects via LaraGram's `listen` and `redirect` helper functions:

```php
// Generating Pattern...
$pattern = listen('profile');

// Generating Redirects...
return redirect()->listen('profile');

return to_listen('profile');
```

If the named listen defines parameters, you may pass the parameters as the second argument to the `listen` function. The given parameters will automatically be inserted into the generated Pattern in their correct positions:

```php
Bot::onText('user {id}', function ($id) {
    // ...
})->name('user');

$pattern = listen('user', ['id' => 1]);
```

<a name="inspecting-the-current-listen"></a>
#### Inspecting the Current Listen

If you would like to determine if the current request was listend to a given named listen, you may use the `named` method on a Listen instance. For example, you may check the current listen name from a listen middleware:

```php
use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

/**
 * Handle an incoming request.
 *
 * @param  \Closure(\LaraGram\Request\Request): (\LaraGram\Request\Response)  $next
 */
public function handle(Request $request, Closure $next): Response
{
    if ($request->listen()->named('profile')) {
        // ...
    }

    return $next($request);
}
```

<a name="step-listeners"></a>
## Step Listeners

While you can branch on the current step manually using `is`, LaraGram provides a dedicated `onStep` listener that registers a handler bound to a specific step. The listener only fires when the user's current step matches the given name, so you can split each stage of a conversation into its own clean handler instead of branching inside a single one.

<a name="defining-step-listeners"></a>
### Defining Step Listeners

The `onStep` method accepts the step name as its first argument and the handler as its second. When an update arrives, the listener runs only if `Step::is()` returns `true` for that step:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Support\Facades\Step;
use LaraGram\Request\Request;

// Begin the conversation...
Bot::onText('start', function (Request $request) {
    Step::set('awaiting_name');

    $request->sendMessage(chat()->id, 'What is your name?');
});

// Only runs while the user's step is "awaiting_name"...
Bot::onStep('awaiting_name', function (Request $request, string $name) {
    Step::set('awaiting_email');

    $request->sendMessage(chat()->id, "Nice to meet you, {$name}! What is your email?");
});

// Only runs while the user's step is "awaiting_email"...
Bot::onStep('awaiting_email', function (Request $request, string $email) {
    Step::forget();

    $request->sendMessage(chat()->id, 'Thanks, you are all set!');
});
```

By default, a step listener matches incoming text updates and captures the entire message text, passing it to your handler as a listen parameter. As with any listen, parameters are injected after your type-hinted dependencies and bound by position, so you may name the argument whatever you like.

<a name="customizing-the-match"></a>
### Customizing the Match

The `onStep` method accepts two optional arguments — a `pattern` and a `method` — for finer control over what the listener matches:

```php
Bot::onStep(
    step: 'awaiting_age',
    action: function (Request $request, string $age) {
        // ...
    },
    pattern: '{age}',
    method: 'TEXT'
);
```

The `pattern` argument lets you supply an explicit [listen pattern](listening.md#required-parameters) instead of capturing the whole message, while the `method` argument controls which update types the listener responds to. You may pass a single type, an array of types, or `'*'` to match every supported update verb. When omitted, the listener defaults to capturing all text updates.

<a name="step-listener-priority"></a>
### Step Listener Priority

By default, step listeners are evaluated after your regular listens. If you would like step listeners to be matched in definition order, mixed in with your other listens, you may enable priority registration:

```php
Bot::enableStepListensPriorityRegister();
```

<a name="listen-groups"></a>
## Listen Groups

Listen groups allow you to share listen attributes, such as middleware, across a large number of listens without needing to define those attributes on each individual listen.

Nested groups attempt to intelligently "merge" attributes with their parent group. Middleware and `where` conditions are merged while names and prefixes are appended. Namespace delimiters and slashes in Pattern prefixes are automatically added where appropriate.

<a name="listen-group-middleware"></a>
### Middleware

To assign [middleware](/v4/middleware) to all listens within a group, you may use the `middleware` method before defining the group. Middleware are executed in the order they are listed in the array:

```php
Bot::middleware(['first', 'second'])->group(function () {
    Bot::onText('hello', function () {
        // Uses first & second middleware...
    });

    Bot::onText('bye', function () {
        // Uses first & second middleware...
    });
});
```

<a name="listen-group-controllers"></a>
### Controllers

If a group of listens all utilize the same [controller](/v4/controllers), you may use the `controller` method to define the common controller for all of the listens within the group. Then, when defining the listens, you only need to provide the controller method that they invoke:

```php
use App\Controllers\OrderController;

Bot::controller(OrderController::class)->group(function () {
    Bot::onText('get', 'show');
    Bot::onText('set', 'store');
});
```

<a name="listen-group-prefixes"></a>
### Listen Prefixes

The `prefix` method may be used to prefix each listen in the group with a given Pattern. For example, you may want to prefix all listen Patterns within the group with `set`:

```php
Bot::prefix('set ')->group(function () {
    Bot::onText('admin', function () {
        // Matches The "set admin" text messages
    });
});
```

<a name="listen-group-name-prefixes"></a>
### Listen Name Prefixes

The `name` method may be used to prefix each listen name in the group with a given string. For example, you may want to prefix the names of all of the listens in the group with `admin`. The given string is prefixed to the listen name exactly as it is specified, so we will be sure to provide the trailing `.` character in the prefix:

```php
Bot::name('admin.')->group(function () {
    Bot::onText('users', function () {
        // Listen assigned name "admin.users"...
    })->name('users');
});
```

<a name="listen-group-scopes"></a>
### Chat Scopes

Telegram delivers updates from several chat types: `private`, `group`, `supergroup`, and `channel`. The `scope` method limits the listens within a group so they only run when the incoming update originates from one of the given chat types. This is convenient when, for example, certain listens should only respond inside private chats:

```php
Bot::scope('private')->group(function () {
    Bot::onText('hello', function () {
        // Only runs in private chats...
    });
});
```

You may pass an array of chat types to match any one of them:

```php
Bot::scope(['private', 'channel', 'group', 'supergroup'])->group(function () {
    // ...
});
```

The available chat scopes are:

<div class="overflow-x-auto">

| Scope | Description |
| --- | --- |
| `private` | One-on-one private chats. |
| `group` | Basic groups. |
| `supergroup` | Supergroups. |
| `channel` | Channels. |

</div>

<a name="listen-group-out-of-scope"></a>
#### Inverting the Scope

The `outOfScope` method is the opposite of `scope`. The listens within the group will run for every chat type **except** the ones you provide:

```php
Bot::outOfScope('private')->group(function () {
    Bot::onText('hello', function () {
        // Runs everywhere except private chats...
    });
});
```

Like `scope`, the `outOfScope` method also accepts an array of chat types:

```php
Bot::outOfScope(['group', 'supergroup'])->group(function () {
    // Runs everywhere except groups and supergroups...
});
```

<a name="listen-group-reply"></a>
### Reply Constraints

Sometimes you only want a listen to run when the incoming message is (or is not) a reply to another message. The `hasReply` and `hasNotReply` methods constrain a group based on the reply status of the incoming update:

```php
Bot::hasReply()->group(function () {
    Bot::onText('delete', function () {
        // Only runs when the message is a reply to another message...
    });
});

Bot::hasNotReply()->group(function () {
    Bot::onText('delete', function () {
        // Only runs when the message is NOT a reply...
    });
});
```

<a name="listen-group-applying-to-single-listens"></a>
### Applying Constraints to a Single Listen

The `scope`, `outOfScope`, `hasReply`, and `hasNotReply` methods are not limited to groups. You may chain them directly onto an individual listen to apply the same constraint to just that listen:

```php
// Applied to a whole group...
Bot::scope('private')->group(function () {
    Bot::onText('hello', function () {
        // ...
    });
});

// Applied to a single listen...
Bot::scope('private')->onText('hello', function () {
    // ...
});
```

These constraints may also be combined to express more specific conditions. For example, the following listen only runs in groups when the message is a reply:

```php
Bot::scope(['group', 'supergroup'])->hasReply()->onText('ban', function () {
    // ...
});
```

<a name="overlapping-listens"></a>
## Overlapping Listens

By default, dispatch is exclusive: the first listen that matches an incoming update handles it, and no other listen runs. Occasionally a single update should trigger more than one handler. For example, a message that contains both text and a URL might need to run `onText` *and* `onUrl`. Marking a listen as **overlapping** lets several matching listens run for the same update instead of competing for the match.

Use the `overlap` method to opt a listen into overlapping dispatch:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Request\Request;

Bot::onText('{text}', function (Request $request, string $text) {
    $request->sendMessage(chat()->id, 'onText');
})->where('text', '.*')->overlap();

Bot::onUrl(function (Request $request) {
    $request->sendMessage(chat()->id, 'onUrl');
})->overlap();
```

When the update matches both listens, both run. The first matched listen is the *primary* and produces the response; overlapping listens run afterwards, each within its own middleware stack, so they cannot interfere with one another.

> [!NOTE]
> "Overlap" describes co-running handlers for one update, not concurrency. Handlers run sequentially within the same request.

<a name="overlap-groups"></a>
### Overlap Groups

To control exactly which listens may run together, assign one or more **groups**. Overlapping listens run together when they share a group with the matched listen — directly, or transitively through other group members. A listen may belong to several groups:

```php
Bot::onCashtag(function (Request $request) {
    $request->sendMessage(chat()->id, 'onCashtag');
})->overlap(['group-1', 'group-2']);

Bot::onHashtag(function (Request $request) {
    $request->sendMessage(chat()->id, 'onHashtag');
})->overlap('group-2');

Bot::onText('{text}', function (Request $request, string $text) {
    $request->sendMessage(chat()->id, 'onText');
})->where('text', '.*')->overlap('group-1');
```

In the example above `onText` and `onCashtag` share `group-1`, while `onCashtag` and `onHashtag` share `group-2`. Because `onCashtag` bridges the two groups, an update that matches all three runs every handler. Group membership is order-independent: starting from any matched listen, the full connected set of group members runs.

An `overlap()` call without a group always co-runs with the primary listen, regardless of which groups the primary belongs to.

<a name="overlap-groups-as-a-group"></a>
### Marking a Whole Group as Overlapping

Just like `middleware`, `prefix`, or `scope`, you may apply `overlap` to an entire [listen group](#listen-groups). Every listen defined within the closure becomes part of the given overlap group:

```php
Bot::overlap('group-1')->group(function () {
    Bot::onText('{text}', function (Request $request, string $text) {
        // ...
    })->where('text', '.*');

    Bot::onCashtag(function (Request $request) {
        // ...
    });
});
```

<a name="listen-model-binding"></a>
## Listen Model Binding

When injecting a model ID to a listen or controller action, you will often query the database to retrieve the model that corresponds to that ID. LaraGram listen model binding provides a convenient way to automatically inject the model instances directly into your listens. For example, instead of injecting a user's ID, you can inject the entire `User` model instance that matches the given ID.

<a name="implicit-binding"></a>
### Implicit Binding

LaraGram automatically resolves Eloquent models defined in listens or controller actions whose type-hinted variable names match a listen segment name. For example:

```php
use App\Models\User;
use LaraGram\Request\Request;

Bot::onText('/users/{user}', function (Request $request, User $user) {
    $request->sendMessage(chat()->id, 'User ID: ' . $user->user_id)
});
```

Since the `$user` variable is type-hinted as the `App\Models\User` Eloquent model and the variable name matches the `{user}` text segment, LaraGram will automatically inject the model instance that has an ID matching the corresponding value from the request pattern. If a matching model instance is not found in the database, null returned.

Of course, implicit binding is also possible when using controller methods. Again, note the `{user}` Pattern segment matches the `$user` variable in the controller which contains an `App\Models\User` type-hint:

```php
use App\Controllers\UserController;
use App\Models\User;

// Listen definition...
Bot::onText('user {user}', [UserController::class, 'show']);

// Controller method definition...
public function show(User $user)
{
    template('user.profile', ['user' => $user]);
}
```

<a name="implicit-soft-deleted-models"></a>
#### Soft Deleted Models

Typically, implicit model binding will not retrieve models that have been [soft deleted](/v4/eloquent#soft-deleting). However, you may instruct the implicit binding to retrieve these models by chaining the `withTrashed` method onto your listen's definition:

```php
use App\Models\User;
use LaraGram\Request\Request;

Bot::onText('/users/{user}', function (Request $request, User $user) {
    $request->sendMessage(chat()->id, 'User ID: ' . $user->user_id)
})->withTrashed();
```

<a name="customizing-the-default-key-name"></a>
#### Customizing the Key

Sometimes you may wish to resolve Eloquent models using a column other than `id`. To do so, you may specify the column in the listen parameter definition:

```php
use App\Models\Post;

Bot::onText('post {post:slug}', function (Post $post) {
    // ..
});
```

If you would like model binding to always use a database column other than `id` when retrieving a given model class, you may override the `getListenKeyName` method on the Eloquent model:

```php
/**
 * Get the listen key for the model.
 */
public function getListenKeyName(): string
{
    return 'slug';
}
```

<a name="implicit-model-binding-scoping"></a>
#### Custom Keys and Scoping

When implicitly binding multiple Eloquent models in a single listen definition, you may wish to scope the second Eloquent model such that it must be a child of the previous Eloquent model. For example, consider this listen definition that retrieves a blog post by slug for a specific user:

```php
use App\Models\Post;
use App\Models\User;

Bot::onText('user {user} posts {post:slug}', function (User $user, Post $post) {
    // ...
});
```

When using a custom keyed implicit binding as a nested listen parameter, LaraGram will automatically scope the query to retrieve the nested model by its parent using conventions to guess the relationship name on the parent. In this case, it will be assumed that the `User` model has a relationship named `posts` (the plural form of the listen parameter name) which can be used to retrieve the `Post` model.

If you wish, you may instruct LaraGram to scope "child" bindings even when a custom key is not provided. To do so, you may invoke the `scopeBindings` method when defining your listen:

```php
use App\Models\Post;
use App\Models\User;

Bot::onText('users {user} posts {post}', function (User $user, Post $post) {
   // ..
})->scopeBindings();
```

Or, you may instruct an entire group of listen definitions to use scoped bindings:

```php
Bot::scopeBindings()->group(function () {
    Bot::onText('users {user} posts {post}', function (User $user, Post $post) {
        // ...
    });
});
```

Similarly, you may explicitly instruct LaraGram to not scope bindings by invoking the `withoutScopedBindings` method:

```php
Bot::onText('user {user} posts {post:slug}', function (User $user, Post $post) {
    // ...
})->withoutScopedBindings();
```

<a name="customizing-missing-model-behavior"></a>
#### Customizing Missing Model Behavior

Typically, null data will be generated if an implicitly bound model is not found. However, you may customize this behavior by calling the `missing` method when defining your listen. The `missing` method accepts a closure that will be invoked if an implicitly bound model cannot be found:

```php
use App\Controllers\LocationsController;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Redirect;

Bot::onText(' location {location:slug}', [LocationsController::class, 'show'])
    ->name('locations.view')
    ->missing(function (Request $request) {
        return Redirect::listen('locations.index');
    });
```

<a name="implicit-enum-binding"></a>
### Implicit Enum Binding

PHP 8.1 introduced support for [Enums](https://www.php.net/manual/en/language.enumerations.backed.php). To complement this feature, LaraGram allows you to type-hint a [string-backed Enum](https://www.php.net/manual/en/language.enumerations.backed.php) on your listen definition and LaraGram will only invoke the listen if that listen segment corresponds to a valid Enum value. Otherwise, a null data will be returned automatically. For example, given the following Enum:

```php
<?php

namespace App\Enums;

enum Category: string
{
    case Fruits = 'fruits';
    case People = 'people';
}
```

You may define a listen that will only be invoked if the `{category}` listen segment is `fruits` or `people`. Otherwise, LaraGram will return null:

```php
use App\Enums\Category;
use LaraGram\Support\Facades\Bot;

Bot::onText('category {category}', function (Category $category) {
    // $category->value;
});
```

<a name="explicit-binding"></a>
### Explicit Binding

You are not required to use LaraGram's implicit, convention based model resolution in order to use model binding. You can also explicitly define how listen parameters correspond to models. To register an explicit binding, use the listener's `model` method to specify the class for a given parameter. You should define your explicit model bindings at the beginning of the `boot` method of your `AppServiceProvider` class:

```php
use App\Models\User;
use LaraGram\Support\Facades\Bot;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Bot::model('user', User::class);
}
```

Next, define a listen that contains a `{user}` parameter:

```php
use App\Models\User;

Bot::onText('users {user}', function (User $user) {
    // ...
});
```

Since we have bound all `{user}` parameters to the `App\Models\User` model, an instance of that class will be injected into the listen. So, for example, a message send like `users 1` will inject the `User` instance from the database which has an ID of `1`.

If a matching model instance is not found in the database, a null response will be automatically generated.

<a name="customizing-the-resolution-logic"></a>
#### Customizing the Resolution Logic

If you wish to define your own model binding resolution logic, you may use the `Bot::bind` method. The closure you pass to the `bind` method will receive the value of the pattern segment and should return the instance of the class that should be injected into the listen. Again, this customization should take place in the `boot` method of your application's `AppServiceProvider`:

```php
use App\Models\User;
use LaraGram\Support\Facades\Bot;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Bot::bind('user', function (string $value) {
        return User::where('name', $value)->firstOrFail();
    });
}
```

Alternatively, you may override the `resolveListenBinding` method on your Eloquent model. This method will receive the value of the pattern segment and should return the instance of the class that should be injected into the listen:

```php
/**
 * Retrieve the model for a bound value.
 *
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \LaraGram\Database\Eloquent\Model|null
 */
public function resolveListenBinding($value, $field = null)
{
    return $this->where('name', $value)->firstOrFail();
}
```

If a listen is utilizing [implicit binding scoping](#implicit-model-binding-scoping), the `resolveChildListenBinding` method will be used to resolve the child binding of the parent model:

```php
/**
 * Retrieve the child model for a bound value.
 *
 * @param  string  $childType
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \LaraGram\Database\Eloquent\Model|null
 */
public function resolveChildListenBinding($childType, $value, $field)
{
    return parent::resolveChildListenBinding($childType, $value, $field);
}
```

<a name="fallback-listens"></a>
## Fallback Listens

Using the `Bot::fallback` method, you may define a listen that will be executed when no other listen matches the incoming request. since you would typically define the `fallback` listen within your `listens/bot.php` file, all middleware in the `bot` middleware group will apply to the listen. You are free to add additional middleware to this listen as needed:

```php
Bot::fallback(function () {
    // ...
});
```

<a name="rate-limiting"></a>
## Rate Limiting

<a name="defining-rate-limiters"></a>
### Defining Rate Limiters

LaraGram includes powerful and customizable rate limiting services that you may utilize to restrict the amount of traffic for a given listen or group of listens. To get started, you should define rate limiter configurations that meet your application's needs.

Rate limiters may be defined within the `boot` method of your application's `App\Providers\AppServiceProvider` class:

```php
use LaraGram\Cache\RateLimiting\Limit;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\RateLimiter;

/**
 * Bootstrap any application services.
 */
protected function boot(): void
{
    RateLimiter::for('bot', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: user()->id);
    });
}
```

Rate limiters are defined using the `RateLimiter` facade's `for` method. The `for` method accepts a rate limiter name and a closure that returns the limit configuration that should apply to listens that are assigned to the rate limiter. Limit configuration are instances of the `LaraGram\Cache\RateLimiting\Limit` class. This class contains helpful "builder" methods so that you can quickly define your limit. The rate limiter name may be any string you wish:

```php
use LaraGram\Cache\RateLimiting\Limit;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\RateLimiter;

/**
 * Bootstrap any application services.
 */
protected function boot(): void
{
    RateLimiter::for('global', function (Request $request) {
        return Limit::perMinute(1000);
    });
}
```

If the incoming request exceeds the specified rate limit, a response will automatically be returned by LaraGram. If you would like to define your own response that should be returned by a rate limit, you may use the `response` method:

```php
RateLimiter::for('global', function (Request $request) {
    return Limit::perMinute(1000)->response(function (Request $request, array $headers) {
        $request->sendMessage(chat()->id, 'Too many attempts!')
    });
});
```

Since rate limiter callbacks receive the incoming Bot request instance, you may build the appropriate rate limit dynamically based on the incoming request or authenticated user:

```php
RateLimiter::for('uploads', function (Request $request) {
    return $request->user()->vipCustomer()
        ? Limit::none()
        : Limit::perMinute(100);
});
```

<a name="segmenting-rate-limits"></a>
#### Segmenting Rate Limits

Sometimes you may wish to segment rate limits by some arbitrary value. For example, you may wish to allow users to access a given listen 100 times per minute per user_id. To accomplish this, you may use the `by` method when building your rate limit:

```php
RateLimiter::for('uploads', function (Request $request) {
    return $request->user()->vipCustomer()
        ? Limit::none()
        : Limit::perMinute(100)->by($request->user()->user_id);
});
```

To illustrate this feature using another example, we can limit access to the listen to 100 times per minute per authenticated user ID or 10 times per minute per user_id for guests:

```php
RateLimiter::for('uploads', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(100)->by($request->user()->id)
        : Limit::perMinute(10)->by($request->user()->user_id);
});
```

<a name="multiple-rate-limits"></a>
#### Multiple Rate Limits

If needed, you may return an array of rate limits for a given rate limiter configuration. Each rate limit will be evaluated for the listen based on the order they are placed within the array:

```php
RateLimiter::for('login', function () {
    return [
        Limit::perMinute(500),
        Limit::perMinute(3)->by(user()->id),
    ];
});
```

If you're assigning multiple rate limits segmented by identical `by` values, you should ensure that each `by` value is unique. The easiest way to achieve this is to prefix the values given to the `by` method:

```php
RateLimiter::for('uploads', function (Request $request) {
    return [
        Limit::perMinute(10)->by('minute:'.$request->user()->id),
        Limit::perDay(1000)->by('day:'.$request->user()->id),
    ];
});
```

<a name="attaching-rate-limiters-to-listens"></a>
### Attaching Rate Limiters to Listens

Rate limiters may be attached to listens or listen groups using the `throttle` [middleware](/v4/middleware). The throttle middleware accepts the name of the rate limiter you wish to assign to the listen:

```php
Bot::middleware(['throttle:uploads'])->group(function () {
    Bot::onCommand('audio', function () {
        // ...
    });

    Bot::onCommand('video', function () {
        // ...
    });
});
```

<a name="throttling-with-redis"></a>
#### Throttling With Redis

By default, the `throttle` middleware is mapped to the `LaraGram\Listening\Middleware\ThrottleRequests` class. However, if you are using Redis as your application's cache driver, you may wish to instruct LaraGram to use Redis to manage rate limiting. To do so, you should use the `throttleWithRedis` method in your application's `bootstrap/app.php` file. This method maps the `throttle` middleware to the `LaraGram\Listening\Middleware\ThrottleRequestsWithRedis` middleware class:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->throttleWithRedis();
    // ...
})
```

<a name="accessing-the-current-listen"></a>
## Accessing the Current Listen

You may use the `current`, `currentListenName`, and `currentListenAction` methods on the `Listen` facade to access information about the listen handling the incoming request:

```php
use LaraGram\Support\Facades\Bot;

$listen = Bot::current(); // LaraGram\Listening\Listen
$name = Bot::currentListenName(); // string
$action = Bot::currentListenAction(); // string
```

<a name="listen-caching"></a>
## Listen Caching

When deploying your application to production, you should take advantage of LaraGram's listen cache. Using the listen cache will drastically decrease the amount of time it takes to register all of your application's listens. To generate a listen cache, execute the `listen:cache` Commander command:

```shell
php laragram listen:cache
```

After running this command, your cached listens file will be loaded on every request. Remember, if you add any new listens you will need to generate a fresh listen cache. Because of this, you should only run the `listen:cache` command during your project's deployment.

> [!NOTE]
> The listen cache preserves every listener type and option, including service-field listeners, entity listeners, and [overlap groups](#overlapping-listens). Cached dispatch behaves identically to uncached dispatch.

You may use the `listen:clear` command to clear the listen cache:

```shell
php laragram listen:clear
```
