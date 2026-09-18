# Broadcasting

<a name="introduction"></a>
## Introduction

Sooner or later, every bot needs to reach many chats at once. You may want to announce a new feature to all of your users, pin a message in every group your bot manages, remind the members of a community about an upcoming event, or change the permissions of dozens of supergroups. Sending these messages from a `foreach` loop quickly becomes a problem: Telegram limits how fast a bot may send, users block the bot, groups are upgraded to supergroups, and a webhook request is not the place to spend minutes calling the Bot API.

To assist you in building these features, LaraGram makes it easy to "broadcast" [Bot API](/v4/requests) calls and [templates](/v4/temple8) to an audience of chats. LaraGram records the chats your bot can reach, lets you filter them, splits the audience into [queued jobs](/v4/queues), paces the calls, retries rate limited ones, skips chats that blocked the bot, tracks the progress of each broadcast, and can even edit or delete what was sent.

In addition, LaraGram can broadcast your server-side [events](/v4/events) over a WebSocket connection to your Telegram Mini Apps and web pages, allowing you to share the same event names and data between your LaraGram application and your client-side JavaScript application.

<a name="supported-drivers"></a>
#### Supported Drivers

By default, LaraGram includes the following broadcasting drivers: `telegram`, which delivers Bot API calls to Telegram chats, and `redis`, which publishes events for a WebSocket server. A `log` driver is included for local development and debugging, and a `null` driver allows you to disable broadcasting.

> [!NOTE]
> Before diving into broadcasting, make sure you have read LaraGram's documentation on [queues](/v4/queues) and [events and listeners](/v4/events).

<a name="quickstart"></a>
## Quickstart

By default, broadcasting is not enabled in new LaraGram applications. You may enable broadcasting using the `install:broadcasting` Commander command:

```shell
php laragram install:broadcasting

php laragram migrate
```

The `install:broadcasting` command will create the `listens/channels.php` file, where you may register your broadcast audiences and channel authorization callbacks, and will load it from your application's `bootstrap/app.php` file. It will also register the `TrackChats` bot middleware and create the migration of the tables that store the chats your bot can reach.

All of your application's broadcasting configuration is stored in the `config/broadcasting.php` configuration file. Don't worry if this file does not exist in your application; LaraGram ships a default configuration, which you may publish using the `config:publish` Commander command:

```shell
php laragram config:publish broadcasting
```

Once broadcasting is enabled, sending a message to every user of your bot is a single expression:

```php
use LaraGram\Support\Facades\Broadcast;

Broadcast::users()->sendMessage('We have just released version 2! 🎉')->queue();
```

<a name="quickstart-next-steps"></a>
#### Next Steps

Once you have enabled broadcasting, you're ready to learn more about [choosing recipients](#choosing-recipients), [broadcast content](#broadcast-content), and [sending broadcasts](#sending-broadcasts). If you would like to push events to a Mini App instead, continue with [defining broadcast events](#defining-broadcast-events).

> [!NOTE]
> Before broadcasting, you should first configure and run a [queue worker](/v4/queues#running-the-queue-worker). Broadcasts are delivered by queued jobs so that the response time of your bot is not affected by the number of chats you are messaging.

<a name="server-side-installation"></a>
## Server Side Installation

<a name="telegram"></a>
### Telegram

The `telegram` connection defined in your `config/broadcasting.php` configuration file is used by every Telegram broadcast. It determines which bot connection sends the broadcast, how many recipients each queued job delivers to, and how the calls are paced:

```php
'telegram' => [
    'driver' => 'telegram',
    'bot' => env('BROADCAST_BOT'),
    'chunk' => 100,
    'queue_connection' => env('BROADCAST_QUEUE_CONNECTION'),
    'queue' => env('BROADCAST_QUEUE'),
    'anti_flood' => 'broadcast',
    'rate' => 25,
    'retries' => 3,
],
```

<a name="tracking-chats"></a>
#### Tracking Chats

Telegram does not tell a bot which chats it belongs to, so LaraGram records them itself. The `LaraGram\Broadcasting\Telegram\Middleware\TrackChats` bot middleware stores the chat of every update your bot receives, including its type, title or name, username, Telegram language, and when it was last active. It also records the users it sees in groups, along with their membership status, so that you may later broadcast to "the members of a group".

The middleware keeps each chat's reachability up to date as well. When a user blocks your bot, or your bot is removed from a group or channel, the chat is marked as unreachable and skipped by future broadcasts. When the user unblocks the bot, the chat becomes reachable again.

If you did not use the `install:broadcasting` command, you may register the middleware manually in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->bot(append: [
        \LaraGram\Broadcasting\Telegram\Middleware\TrackChats::class,
    ]);
})
```

And create the migration of the broadcast tables using the `make:broadcast-tables` Commander command:

```shell
php laragram make:broadcast-tables

php laragram migrate
```

Chat tracking is configured in the `tracking` section of your `config/broadcasting.php` file. The `members` option enables the recording of group members, and the `touch_every` option sets how many seconds pass between two writes for the same chat.

> [!WARNING]
> Your bot only receives `my_chat_member` updates, which report that the bot was blocked or removed, when they are allowed. If you set `allowed_updates` for a connection in your `config/bot.php` file, you should include `my_chat_member`. To record the joins and leaves of channels and large groups, include `chat_member` as well; Telegram only sends these updates to bots that are administrators.

<a name="broadcast-store"></a>
#### The Broadcast Store

The recorded chats, the members seen in them, and the chats reached by [recallable broadcasts](#editing-and-recalling-broadcasts) live in the broadcast store. The store is chosen by the `store` option of your `config/broadcasting.php` file:

<div class="overflow-auto">

| Driver | Description |
| --- | --- |
| `database` | Three tables: `broadcast_chats`, `broadcast_members` and `broadcast_targets`. Recommended for large audiences and complex filters. |
| `redis` | Hashes and index sets, without any table. No migration is needed. |
| `null` | Records nothing. Broadcasts may still be sent to chat identifiers and [custom audiences](#custom-audiences). |

</div>

```ini
BROADCAST_STORE=redis
```

The table names, the database connection, and the Redis connection and key prefix may all be changed in the `stores` section of the configuration file.

> [!NOTE]
> Redis has no query planner: audiences are read through the indexes, then filtered in memory. When your bot records hundreds of thousands of chats and your broadcasts use several filters, the `database` store scales better.

<a name="redis"></a>
### Redis

The `redis` driver publishes your broadcast events on Redis channels. A WebSocket server subscribed to Redis relays them to your Mini Apps and web pages. To broadcast events using Redis, set the `BROADCAST_CONNECTION` environment variable in your application's `.env` file:

```ini
BROADCAST_CONNECTION=redis
```

You may choose the Redis connection used by the broadcaster using the `REDIS_BROADCAST_CONNECTION` environment variable.

<a name="concept-overview"></a>
## Concept Overview

LaraGram's Telegram broadcasting is built around three questions: **who** receives the broadcast, **what** is sent, and **how and when** it is delivered. Each question is answered by a different object, so your editor only suggests the methods that make sense at each point of the chain.

`Broadcast::users()`, `Broadcast::groups()` and similar methods return a `Recipients` instance. Recipients may be narrowed using filters. Calling a Bot API method or `template` on the recipients returns a `TelegramBroadcast` instance, which offers the delivery options and the `send`, `queue`, and `later` methods.

<a name="using-example-application"></a>
### Using an Example Application

Before diving into each part of broadcasting, let's take a high level overview using an online course bot as an example.

In our application, let's assume that the students of each course are members of a Telegram group managed by the bot. When a new lesson is published, we would like to notify the students of the course who speak Persian, with a formatted message rendered from a template, and we would like the notification to arrive at nine o'clock tomorrow morning:

```php
use LaraGram\Support\Facades\Broadcast;

Broadcast::users()
    ->membersOf($course->group_id)
    ->language('fa')
    ->template('lessons.published', ['lesson' => $lesson->toArray()])
    ->localized()
    ->later(now()->addDay()->setTime(9, 0));
```

The `users` method selects every private chat recorded by the [TrackChats](#tracking-chats) middleware, while the `membersOf` and `language` methods narrow them down to the Persian speaking members of the course's group. Next, the `template` method chooses what to send: the `lessons.published` template will be rendered for each student, in their own language. Finally, the `later` method queues the broadcast to start tomorrow morning.

<a name="example-application-the-template"></a>
#### The Template

The template is a regular [Temple8](/v4/temple8) template. Within a broadcast template, `chat()` and `user()` return the recipient, so the template is written exactly like a reply to an update:

```blade
@text()
{{ __('lessons.published', ['name' => $recipient->first_name, 'title' => $lesson['title']]) }}
@endText

@keyboard()
    @row()
        @col(__('lessons.watch'), url: $lesson['url'])
    @endRow
@endKeyboard
```

<a name="example-application-following-the-broadcast"></a>
#### Following the Broadcast

The `later` method returns the broadcast's identifier. You may use this identifier to check on the broadcast's progress, or to cancel it before it starts:

```php
$id = Broadcast::users()->membersOf($course->group_id)->sendMessage('...')->later(3600);

Broadcast::progress($id)->status(); // scheduled

Broadcast::cancel($id);
```

<a name="choosing-recipients"></a>
## Choosing Recipients

The `Broadcast` facade provides a method for each kind of chat your bot records. Each of these methods returns a `LaraGram\Broadcasting\Telegram\Recipients` instance:

```php
use LaraGram\Support\Facades\Broadcast;

Broadcast::users();          // Every private chat
Broadcast::groups();         // Every group and supergroup
Broadcast::supergroups();    // Every supergroup
Broadcast::channels();       // Every channel
Broadcast::chats();          // Every chat
```

Chats that blocked or removed your bot are never included. You may also broadcast to specific chats using the `to` method, which accepts chat identifiers, `@username` strings, [audience](#custom-audiences) names, or an array containing any of them. A chat that appears more than once only receives the broadcast once:

```php
Broadcast::to(123456789)->sendMessage('Hello!')->queue();

Broadcast::to([123456789, '@my_channel', 'groups'])->sendMessage('Hello!')->queue();
```

If you would like to know how many chats a broadcast would reach, you may call the `count` method on the recipients:

```php
$count = Broadcast::users()->language('fa')->count();
```

<a name="filtering-recipients"></a>
### Filtering Recipients

Recipients may be narrowed down using filters, which select among the chats recorded by the [TrackChats](#tracking-chats) middleware. For example, you may broadcast only to the users who speak Persian and have used your bot during the last 30 days:

```php
Broadcast::users()
    ->language('fa')
    ->activeSince(30)
    ->sendMessage('سلام!')
    ->queue();
```

The `where`, `whereIn`, `whereNotIn`, `whereNull`, and `whereNotNull` methods allow you to filter by any column of the chats table, such as `type`, `title`, `username`, `first_name`, `last_name`, or `language_code`:

```php
Broadcast::supergroups()
    ->where('title', 'like', '%developers%')
    ->sendMessage('Our meetup starts at 18:00.')
    ->queue();
```

The `activeSince` and `inactiveSince` methods filter chats by the last time they sent an update, while the `joinedAfter` and `joinedBefore` methods filter them by the time they were first recorded. Each of these methods accepts a date or a number of days:

```php
Broadcast::users()->inactiveSince(60)->sendMessage('We miss you!')->queue();

Broadcast::users()->joinedAfter(now()->startOfWeek())->sendMessage('Welcome aboard!')->queue();
```

To skip specific chats, pass them to the `except` method. You may limit the number of recipients using the `limit` method:

```php
Broadcast::users()
    ->except([$adminId])
    ->limit(1000)
    ->sendMessage('You are one of the first 1000 users to try this feature.')
    ->queue();
```

> [!NOTE]
> Filters select among the recorded chats. When you use a filter together with chat identifiers or a [custom audience](#custom-audiences), chats that were never recorded by the `TrackChats` middleware are left out.

<a name="group-membership"></a>
### Group Membership

Since the `TrackChats` middleware records the users it sees in your bot's groups, you may broadcast to users based on the groups they belong to. The `membersOf` method keeps the users who are members of any of the given groups, while the `notMembersOf` method skips them:

```php
Broadcast::users()
    ->membersOf($developersGroupId)
    ->notMembersOf($designersGroupId)
    ->sendMessage('Join our designers group!')
    ->queue();
```

If you would like to broadcast to every recorded member of a group, you may also use the `members` method:

```php
Broadcast::members($groupId)->sendMessage('The group rules have been updated.')->queue();
```

The opposite direction is available too. The `withMember` method keeps the groups and channels where a given user is a member, and the `administeredBy` method keeps the ones the user administers. For example, you may ban a spammer from every group they were seen in:

```php
Broadcast::groups()->withMember($spammerId)->banChatMember($spammerId)->queue();
```

By default, only members whose status is `creator`, `administrator`, `member`, or `restricted` are considered. You may pass the statuses you would like to match as the second argument to the `membersOf` and `withMember` methods:

```php
Broadcast::users()->membersOf($groupId, ['creator', 'administrator'])->sendMessage('Admin meeting today.')->queue();
```

<a name="channel-subscriptions"></a>
#### Channel Subscriptions

A bot cannot list the subscribers of a channel. However, it may ask Telegram whether a given user is subscribed. The `subscribedTo` and `notSubscribedTo` methods perform this check for each recipient right before delivering the broadcast:

```php
Broadcast::users()->notSubscribedTo('@my_channel')->sendMessage('Join @my_channel for our daily news!')->queue();
```

Each check costs one `getChatMember` call per recipient, and your bot must be an administrator of the channel. Recipients that do not pass the check are counted as "skipped". For more control, you may use the `checkMembership` method, which accepts the chat, whether the user should be a member, and the statuses that count as a membership:

```php
Broadcast::users()->checkMembership($chatId, member: true, statuses: ['administrator'])->sendMessage('...')->queue();
```

<a name="tags"></a>
### Tags

Tags allow you to build your own segments of chats. You may attach tags to chats using the `tag` method of the `Broadcast` facade, remove them using the `untag` method, and broadcast to the tagged chats using the `tagged`, `taggedAll`, and `notTagged` filters:

```php
Broadcast::tag($user->user_id, 'premium');

Broadcast::untag($user->user_id, 'premium');

Broadcast::users()->tagged('premium')->notTagged('beta')->sendMessage('Your premium perks this month')->queue();
```


<a name="custom-audiences"></a>
### Custom Audiences

Sometimes the recipients of a broadcast are defined by your own data rather than by the recorded chats. In these situations, you may register a custom audience in your application's `listens/channels.php` file using the `Broadcast::audience` method. The audience resolver receives the name of the bot connection and returns the recipients:

```php
use App\Models\User;
use LaraGram\Support\Facades\Broadcast;

Broadcast::audience('premium', function (string $bot) {
    return User::where('premium', true)->select(['id', 'user_id']);
});
```

Once the audience has been registered, you may broadcast to it by passing its name to the `to` method:

```php
Broadcast::to('premium')->sendMessage('Thanks for supporting us!')->queue();
```

An audience resolver may return a query builder, which LaraGram iterates lazily so that audiences of any size use little memory, or any iterable of chat identifiers, arrays, or objects. For arrays and objects, LaraGram uses the value of the `broadcastChatId` method, the `chat_id` value, the `user_id` value, or the `id` value, in that order:

```php
/**
 * Get the chat identifier used when broadcasting to the user.
 */
public function broadcastChatId(): int
{
    return $this->telegram_id;
}
```

Audience names may contain placeholders. The values of the placeholders are passed to the resolver as named arguments:

```php
Broadcast::audience('city.{city}', function (string $bot, string $city) {
    return User::where('city', $city)->select(['id', 'user_id']);
});

Broadcast::to('city.tehran')->sendMessage('Our Tehran meetup is on Friday!')->queue();
```

If your audience resolver is more complex, you may register a class instead of a closure. The class will be resolved by the [service container](/v4/container), and its `resolve` or `__invoke` method will be called. You may list your application's audiences using the `channel:list` Commander command:

```shell
php laragram channel:list --count
```

<a name="broadcast-content"></a>
## Broadcast Content

<a name="bot-api-methods"></a>
### Bot API Methods

Every Bot API method that targets a chat or a user is available on the recipients. The methods accept the same parameters, in the same order, as the methods of the [request](/v4/requests#methods), except for the recipient parameter, which LaraGram fills in for each recipient. So, you may send a photo to every user just like you would reply with one:

```php
Broadcast::users()->sendPhoto($fileId, 'Our new logo')->queue();
```

Of course, named arguments may be used to pass any of the method's optional parameters:

```php
Broadcast::users()
    ->sendMessage('<b>Big sale</b> starts now!', parse_mode: 'HTML', disable_notification: true)
    ->queue();
```

The recipient parameter is `chat_id`, or `user_id` for the methods that do not accept a `chat_id`. For example, when broadcasting `banChatMember` to groups, each group is the `chat_id` while the user you pass is banned from all of them:

```php
Broadcast::groups()->banChatMember($userId)->queue();

Broadcast::groups()->setChatPermissions(['can_send_messages' => false])->queue();

Broadcast::users()->setUserEmojiStatus($emojiId)->queue();
```

If you need the recipient to be passed as another parameter, you may specify it using the `target` method:

```php
Broadcast::to('premium')->sendGift($giftId)->target('user_id')->queue();
```

The broadcast methods are generated from the Bot API schema that [Laraquest](/v4/requests) keeps in its `src/Schema/api.php` file. When Laraquest updates this schema, for example after running `composer update`, LaraGram regenerates the broadcast methods the first time broadcasting is used, so they always match the methods of the request.

> [!WARNING]
> Broadcasts are serialized when they are queued. Instead of passing a local file, you should upload the file once and broadcast its `file_id`, or use a URL. Templates may use local files, since they are rendered while the broadcast is being delivered.

<a name="templates"></a>
### Templates

The `template` method allows you to broadcast [Temple8 templates](/v4/temple8). A template is rendered for each recipient, and the Bot API calls it makes are delivered by the broadcast instead of being sent right away. Every feature of the template engine is available, including inputs, `@method`, keyboards, [rich messages](/v4/rich-messages), components, layouts, includes, and translations:

```php
Broadcast::users()->template('promotions.spring', ['discount' => 30])->queue();
```

Within a broadcast template, `chat()` and `user()` return the recipient, and the template receives a `$recipient` variable containing the recipient's recorded details, such as `first_name`, `username`, and `language_code`, and a `$broadcast` variable containing the broadcast's identifier:

```blade
@rich
<h1>Hello, {{ $recipient->first_name }}!</h1>
<p>Our spring sale is live: <b>{{ $discount }}%</b> off everything.</p>
@richPhoto($bannerFileId, caption: 'Spring sale')
@endrich
```

In addition to the name of a template, the `template` method accepts a template path, a template instance, or an inline template string:

```php
Broadcast::users()->template(template()->make('promotions.spring', ['discount' => 30]))->queue();

Broadcast::users()->template(resource_path('broadcasts/spring.t8.php'))->queue();

Broadcast::users()->template(<<<'T8'
    @text()
    <x-greeting :name="$recipient->name()"/> Our spring sale is live!
    @endText
    T8)->queue();
```

<a name="localized-templates"></a>
#### Localized Templates

If your bot speaks several languages, you may render each recipient's template in their Telegram language by calling the `localized` method. The `__` helper and the `@lang` directive will then use the recipient's language:

```php
Broadcast::users()->template('promotions.spring')->localized()->queue();
```

<a name="rendering-templates-once"></a>
#### Rendering Templates Once

By default, a template is rendered once for each recipient. If your template does not depend on the recipient, you may pass `false` as the third argument to the `template` method, and the template will be rendered only once for each queued job:

```php
Broadcast::channels()->template('news.post', ['post' => $post->toArray()], false)->queue();
```

<a name="multiple-steps"></a>
### Multiple Steps

Sometimes a single message is not enough. The `next` method allows you to add another Bot API call or template to the broadcast. The calls are delivered to each recipient in order, and if one of them fails for a recipient, the remaining ones are not sent to that recipient:

```php
Broadcast::users()
    ->sendPhoto($posterFileId)
    ->next()
    ->sendPoll('Will you join us?', [['text' => 'Yes'], ['text' => 'No']])
    ->next()
    ->template('events.details')
    ->queue();
```

<a name="sending-broadcasts"></a>
## Sending Broadcasts

Once you have chosen the recipients and the content of a broadcast, you may send it using one of three methods. Each method returns the broadcast's identifier:

```php
// Deliver the broadcast in the current process...
$id = Broadcast::users()->sendMessage('Hello!')->send();

// Push the broadcast onto the queue...
$id = Broadcast::users()->sendMessage('Hello!')->queue();

// Queue the broadcast to start at a given time...
$id = Broadcast::users()->sendMessage('Hello!')->later(now()->addHours(3));
```

When a queued broadcast is processed, LaraGram reads its recipients and dispatches a job for every `chunk` recipients, so several queue workers may deliver a large broadcast in parallel. The `send` method performs the same work in the current process, which makes it well suited to Commander commands:

```php
Commander::command('announce {message}', function (string $message) {
    $id = Broadcast::users()->sendMessage($message)->send();

    $this->info('Broadcast '.$id.' delivered.');
});
```

> [!WARNING]
> A webhook update should be handled quickly. You should not use the `send` method to broadcast to large audiences from your listens or controllers; use the `queue` or `later` methods instead.

<a name="scheduling-broadcasts"></a>
### Scheduling Broadcasts

The `later` method accepts a `DateTimeInterface` instance, a `DateInterval` instance, or a number of seconds. Scheduled broadcasts are recorded immediately, so they appear in the list of [recent broadcasts](#monitoring-broadcasts) and may be cancelled before they start:

```php
$id = Broadcast::users()->sendMessage('Happy new year! 🎉')->later(
    new DateTime('2027-01-01 00:00', new DateTimeZone('Asia/Tehran'))
);

Broadcast::cancel($id);
```

> [!WARNING]
> Scheduled broadcasts require a queue connection that supports delayed jobs, such as the `database`, `redis`, or `beanstalkd` connections. Since the `sync` connection runs jobs immediately, a `BroadcastException` is thrown instead of sending the broadcast early.

<a name="delivery-windows"></a>
#### Delivery Windows

You may not want to wake your users up in the middle of the night. The `between` method restricts the delivery of a broadcast to the given hours. When the window closes, the remaining recipients wait for the window to open again:

```php
Broadcast::users()
    ->sendMessage('Here is your weekly digest.')
    ->between('09:00', '21:00', 'Asia/Tehran')
    ->queue();
```

<a name="recurring-broadcasts"></a>
#### Recurring Broadcasts

To broadcast on a schedule, you may use the `broadcast` method of the [task scheduler](/v4/scheduling) in your application's `listens/console.php` file. The given closure should return a broadcast, which will be queued every time the task runs:

```php
use LaraGram\Support\Facades\Broadcast;
use LaraGram\Support\Facades\Schedule;

Schedule::broadcast(fn () => Broadcast::users()->activeSince(7)->template('digest')->localized())
    ->weeklyOn(5, '10:00')
    ->timezone('Asia/Tehran');
```

<a name="sending-once"></a>
#### Sending Once

Some messages should reach each recipient only once, however many times they are broadcast. The `once` method accepts a key, and recipients that already received a broadcast using this key are skipped. This makes it easy to build onboarding sequences using the scheduler:

```php
Schedule::broadcast(fn () => Broadcast::users()
    ->joinedBefore(now()->subDays(3))
    ->sendMessage('Did you know you can invite your friends?')
    ->once('onboarding.invite-friends')
)->hourly();
```

<a name="delivery-options"></a>
### Delivery Options

A broadcast may be customized using several additional methods. For example, the `reportTo` method sends a summary to the given chats once the broadcast is complete, and the `onQueue` method specifies the queue the broadcast should be pushed to:

```php
Broadcast::users()
    ->copyMessage('@my_drafts', 20)
    ->reportTo(config('bot.admins'))
    ->onQueue('broadcasts')
    ->queue();
```

If your application serves [multiple bots](/v4/requests#multi-connections), you may specify the bot connection that should send the broadcast using the `bot` method. When broadcasting outside of an update, such as from a command, you should always specify the bot connection:

```php
Broadcast::users()->sendMessage('Hello!')->bot('shop')->queue();
```

The following delivery options are also available:

<div class="content-list" markdown="1">

- `onConnection($connection)` specifies the queue connection of the broadcast's jobs.
- `chunk($size)` specifies how many recipients each queued job delivers to.
- `perSecond($rate)` limits the number of recipients each queue worker delivers to per second.
- `antiFlood($scope)` specifies the [anti-flood](/v4/requests#smart-anti-flood) scope pacing the calls.
- `identifiedBy($id)` uses your own identifier instead of a generated one.
- `via($connection)` specifies the broadcast connection that should be used.
- `recallable()` remembers the broadcast so it may be [edited or undone later](#editing-and-recalling-broadcasts).

</div>

<a name="previewing-broadcasts"></a>
### Previewing Broadcasts

Before sending a broadcast to thousands of chats, you may want to see it in your own chat. The `test` method delivers the broadcast immediately to the given chats, ignoring its filters, limits, and schedule:

```php
$broadcast = Broadcast::users()->language('fa')->template('promotions.spring')->localized();

$broadcast->test($adminChatId);

$broadcast->count();

$broadcast->queue();
```

<a name="rate-limits-and-failures"></a>
### Rate Limits and Failures

Telegram allows a bot to send about 30 messages per second. When [Smart Anti-Flood](/v4/requests#smart-anti-flood) is enabled, every broadcast call is paced by the `broadcast` scope of your `config/bot.php` file, so a broadcast never floods. When anti-flood is disabled, broadcasts are paced using the `rate` option of the `telegram` broadcast connection.

When Telegram asks the bot to slow down, the call is retried after the requested delay. When a chat blocked the bot, was deleted, or no longer exists, the chat is marked as unreachable and skipped by future broadcasts. When a group was upgraded to a supergroup, the recorded chat is moved to its new identifier and the call is retried.

> [!WARNING]
> Each queued job of a broadcast must finish before the `retry_after` value of your queue connection, otherwise another worker may process the job again and message the same recipients twice. If your broadcasts use templates, several steps, or channel subscription checks, you should lower the `chunk` size.

<a name="monitoring-broadcasts"></a>
## Monitoring Broadcasts

Every broadcast records its progress in your application's cache. You may retrieve the progress of a broadcast by passing its identifier to the `progress` method:

```php
$progress = Broadcast::progress($id);

$progress->status();      // queued, scheduled, running, waiting, finished, or cancelled
$progress->sent();
$progress->unreachable();
$progress->failed();
$progress->skipped();
$progress->percentage();
```

A broadcast that is queued, scheduled, or running may be cancelled using the `cancel` method. The recipients that were not reached yet will be skipped:

```php
Broadcast::cancel($id);
```

The `recent` method returns the most recent broadcasts. The same information is available from the command line using the `broadcast:status` Commander command, which lists the recent broadcasts when it is given no argument and shows the progress of a single one when it is given an identifier:

```shell
php laragram broadcast:status

php laragram broadcast:status 01J8Z6A0Q7P5TRN0YQ3M2W4C9D

php laragram broadcast:cancel 01J8Z6A0Q7P5TRN0YQ3M2W4C9D
```

> [!NOTE]
> Broadcast progress is stored in the cache store specified by the `progress.store` option of your `config/broadcasting.php` file. When your broadcasts are delivered by several queue workers, you should use a shared cache store, such as `redis` or `database`.

<a name="broadcast-events"></a>
### Broadcast Events

LaraGram dispatches several [events](/v4/events) while delivering a broadcast. You may listen for these events to store the results of your broadcasts or to react to unreachable chats:

<div class="content-list" markdown="1">

- `LaraGram\Broadcasting\Telegram\Events\BroadcastStarted`
- `LaraGram\Broadcasting\Telegram\Events\BroadcastCompleted`
- `LaraGram\Broadcasting\Telegram\Events\ChatUnreachable`
- `LaraGram\Broadcasting\Telegram\Events\ChatMigrated`
- `LaraGram\Broadcasting\Telegram\Events\DeliveryFailed`

</div>

```php
use LaraGram\Broadcasting\Telegram\Events\BroadcastCompleted;

class StoreBroadcastResult
{
    /**
     * Handle the event.
     */
    public function handle(BroadcastCompleted $event): void
    {
        BroadcastResult::create($event->progress->toArray());
    }
}
```

<a name="editing-and-recalling-broadcasts"></a>
## Editing and Recalling Broadcasts

Mistakes happen. A broadcast may be changed, or completely undone, after it has been delivered. To make this possible, LaraGram has to remember what a broadcast did, which you enable using the `recallable` method. Alternatively, you may remember every broadcast by setting the `BROADCAST_RECALL` environment variable to `true`:

```php
$id = Broadcast::users()->sendMessage('Flash sale: 20% off!')->recallable()->queue();
```

A recallable broadcast records the chats it reached, the messages it sent there, and the calls it made, for the number of seconds given by the `recall.ttl` option of your `config/broadcasting.php` file.

<a name="editing-sent-broadcasts"></a>
### Editing Sent Broadcasts

The `sent` method returns the recipients of a recallable broadcast. The sent message identifiers are filled in for each recipient, so you may call any Bot API method that accepts a message identifier:

```php
Broadcast::sent($id)->editMessageText('Flash sale: 30% off!')->queue();

Broadcast::sent($id)->unpinChatMessage()->queue();
```

The `pin` and `delete` methods pin or delete the sent messages, since those methods require a message identifier:

```php
Broadcast::sent($id)->pin()->queue();

Broadcast::sent($id)->delete()->queue();
```

When the broadcast sent several messages to each recipient, you may choose the message that should be edited using the `messageIndex` method:

```php
Broadcast::sent($id)->messageIndex(1)->editMessageCaption('A new caption')->queue();
```

<a name="recalling-broadcasts"></a>
### Recalling Broadcasts

The `recall` method undoes a broadcast in every chat it reached, by broadcasting the inverse of each of its calls, in reverse order:

```php
Broadcast::recall($id);
```

What was sent, copied, forwarded, or edited is deleted; pins are unpinned; bans and restrictions are lifted; promotions are undone; closed or hidden topics are reopened; reactions are cleared:

<div class="overflow-auto">

| Broadcast call | Recalled with |
| --- | --- |
| `sendMessage`, `sendPhoto`, `copyMessage`, `forwardMessage`, `editMessageText`, a template, ... | `deleteMessages` |
| `pinChatMessage` / `unpinChatMessage` | `unpinChatMessage` / `pinChatMessage` |
| `banChatMember` / `unbanChatMember` | `unbanChatMember` / `banChatMember` |
| `banChatSenderChat` / `unbanChatSenderChat` | `unbanChatSenderChat` / `banChatSenderChat` |
| `restrictChatMember` | `restrictChatMember` with every permission granted |
| `promoteChatMember` | `promoteChatMember` with every right removed |
| `closeForumTopic`, `closeGeneralForumTopic`, `hideGeneralForumTopic`, ... | The matching `reopen` or `unhide` method |
| `setMessageReaction` | `setMessageReaction` with no reaction |
| `sendChatAction` | Nothing; it leaves nothing behind |

</div>

Some calls cannot be undone, either because their previous state is unknown, such as `setChatTitle` and `setChatPermissions`, or because the action is final, such as `deleteMessage`, `unpinAllChatMessages` and `leaveChat`. Recalling a broadcast that contains such a call throws a `BroadcastException`. You may recall the rest of the broadcast by passing `partial`:

```php
Broadcast::recall($id, partial: true);
```

Alternatively, you may teach LaraGram how to undo a call using the `recallUsing` method, typically in the `boot` method of a [service provider](/v4/providers). The callback receives the action that was broadcast and returns the action that undoes it, or `null` when there is nothing to undo:

```php
use LaraGram\Broadcasting\Telegram\Action;
use LaraGram\Support\Facades\Broadcast;

Broadcast::recallUsing('setChatTitle', function (Action $action) {
    return Action::make('setChatTitle', ['title' => config('bot.default_group_title')]);
});
```

A broadcast may also be recalled from the command line. The `broadcast:recall` command accepts the same `--partial` option:

```shell
php laragram broadcast:recall 01J8Z6A0Q7P5TRN0YQ3M2W4C9D
```

> [!NOTE]
> When a broadcast was not sent as recallable, LaraGram no longer knows what it did. Its messages may still be deleted with `Broadcast::sent($id)->delete()` if the store still holds its targets.

<a name="telegram-broadcast-events"></a>
## Telegram Broadcast Events

Instead of building a broadcast where it is sent, you may describe it using an [event](/v4/events) class. For example, let's assume a `ProductLaunched` event is dispatched when a new product is launched:

```php
use App\Events\ProductLaunched;

ProductLaunched::dispatch($product);
```

To broadcast the event to Telegram, the event should implement the `ShouldBroadcast` interface and use the `telegram` broadcast connection. The `broadcastOn` method returns the recipients, the `broadcastAs` method returns the Bot API method, and the `broadcastWith` method returns its parameters:

```php
<?php

namespace App\Events;

use App\Models\Product;
use LaraGram\Broadcasting\InteractsWithBroadcasting;
use LaraGram\Broadcasting\Telegram\Audience;
use LaraGram\Contracts\Broadcasting\ShouldBroadcast;
use LaraGram\Foundation\Events\Dispatchable;
use LaraGram\Queue\SerializesModels;

class ProductLaunched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Product $product,
    ) {
        $this->broadcastVia('telegram');
    }

    /**
     * Get the recipients the event should broadcast to.
     *
     * @return array<int, \LaraGram\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [Audience::users()];
    }

    /**
     * Get the Bot API method the event should broadcast.
     */
    public function broadcastAs(): string
    {
        return 'sendMessage';
    }

    /**
     * Get the parameters of the Bot API method.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['text' => "New: {$this->product->name}"];
    }
}
```

If you would like to broadcast a template, or to use filters and delivery options, you may return an `Action` from the `broadcastWith` method:

```php
use LaraGram\Broadcasting\Telegram\Action;
use LaraGram\Broadcasting\Telegram\ChatCriteria;

/**
 * Get the broadcast action.
 *
 * @return array<string, mixed>
 */
public function broadcastWith(): array
{
    return Action::template('products.launched', ['product' => $this->product->toArray()])
        ->options([
            'localized' => true,
            'criteria' => (new ChatCriteria)->language(['fa', 'en'])->activeSince(30)->toArray(),
        ])
        ->toArray();
}
```

<a name="defining-broadcast-events"></a>
## Defining Broadcast Events

The remaining sections of this documentation describe how to broadcast events over a WebSocket connection using the `redis` driver, so that your Mini Apps and web pages may receive them.

To inform LaraGram that a given event should be broadcast, you must implement the `LaraGram\Contracts\Broadcasting\ShouldBroadcast` interface on the event class. The `ShouldBroadcast` interface requires you to implement a single method: `broadcastOn`. The `broadcastOn` method should return a channel or array of channels that the event should broadcast on. The channels should be instances of `Channel`, `PrivateChannel`, or `PresenceChannel`. Instances of `Channel` represent public channels that any user may subscribe to, while `PrivateChannels` and `PresenceChannels` represent private channels that require [channel authorization](#authorizing-channels):

```php
<?php

namespace App\Events;

use App\Models\Order;
use LaraGram\Broadcasting\InteractsWithSockets;
use LaraGram\Broadcasting\PrivateChannel;
use LaraGram\Contracts\Broadcasting\ShouldBroadcast;
use LaraGram\Queue\SerializesModels;

class OrderShipmentStatusUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \LaraGram\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.'.$this->order->id),
        ];
    }
}
```

After implementing the `ShouldBroadcast` interface, you only need to [fire the event](/v4/events) as you normally would. Once the event has been fired, a [queued job](/v4/queues) will automatically broadcast the event using your specified broadcast driver.

<a name="broadcast-name"></a>
### Broadcast Name

By default, LaraGram will broadcast the event using the event's class name. However, you may customize the broadcast name by defining a `broadcastAs` method on the event:

```php
/**
 * The event's broadcast name.
 */
public function broadcastAs(): string
{
    return 'order.updated';
}
```

<a name="broadcast-data"></a>
### Broadcast Data

When an event is broadcast, all of its `public` properties are automatically serialized and broadcast as the event's payload. However, if you wish to have more fine-grained control over your broadcast payload, you may add a `broadcastWith` method to your event. This method should return the array of data that you wish to broadcast as the event payload:

```php
/**
 * Get the data to broadcast.
 *
 * @return array<string, mixed>
 */
public function broadcastWith(): array
{
    return ['id' => $this->order->id];
}
```

<a name="broadcast-queue"></a>
### Broadcast Queue

By default, each broadcast event is placed on the default queue for the default queue connection specified in your `queue.php` configuration file. You may customize the queue connection and name used by the broadcaster by using the `Connection` and `Queue` attributes on your event class:

```php
use LaraGram\Queue\Attributes\Connection;
use LaraGram\Queue\Attributes\Queue;

#[Connection('redis')]
#[Queue('default')]
class OrderShipmentStatusUpdated implements ShouldBroadcast
{
    // ...
}
```

If you would like to broadcast your event immediately instead of using the queue, you may implement the `ShouldBroadcastNow` interface instead of `ShouldBroadcast`:

```php
use LaraGram\Contracts\Broadcasting\ShouldBroadcastNow;

class OrderShipmentStatusUpdated implements ShouldBroadcastNow
{
    // ...
}
```

<a name="broadcast-conditions"></a>
### Broadcast Conditions

Sometimes you want to broadcast your event only if a given condition is true. You may define these conditions by adding a `broadcastWhen` method to your event class:

```php
/**
 * Determine if this event should broadcast.
 */
public function broadcastWhen(): bool
{
    return $this->order->value > 100;
}
```

<a name="broadcasting-and-database-transactions"></a>
#### Broadcasting and Database Transactions

When broadcast events are dispatched within database transactions, they may be processed by the queue before the database transaction has committed. You may indicate that a particular broadcast event should be dispatched after all open database transactions have been committed by implementing the `ShouldDispatchAfterCommit` interface on the event class:

```php
use LaraGram\Contracts\Broadcasting\ShouldBroadcast;
use LaraGram\Contracts\Events\ShouldDispatchAfterCommit;

class OrderShipmentStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    // ...
}
```

<a name="authorizing-channels"></a>
## Authorizing Channels

Private channels require you to authorize that the currently authenticated user can actually listen on the channel. This is accomplished by making an HTTP request to your LaraGram application with the channel name and allowing your application to determine if the user can listen on that channel.

The `withBroadcasting` method in your application's `bootstrap/app.php` file registers the `/broadcasting/auth` route to handle authorization requests. You may pass the attributes of this route as the second argument:

```php
->withBroadcasting(
    __DIR__.'/../listens/channels.php',
    ['prefix' => 'api', 'middleware' => ['api']],
)
```

<a name="defining-authorization-callbacks"></a>
### Defining Authorization Callbacks

Next, we need to define the logic that will actually determine if the currently authenticated user can listen to a given channel. This is done in the `listens/channels.php` file that was created by the `install:broadcasting` Commander command. In this file, you may use the `Broadcast::channel` method to register channel authorization callbacks:

```php
use App\Models\Order;
use App\Models\User;

Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return $user->id === Order::findOrNew($orderId)->user_id;
});
```

The `channel` method accepts two arguments: the name of the channel and a callback which returns `true` or `false` indicating whether the user is authorized to listen on the channel.

All authorization callbacks receive the currently authenticated user as their first argument and any additional wildcard parameters as their subsequent arguments. In this example, we are using the `{orderId}` placeholder to indicate that the "ID" portion of the channel name is a wildcard.

<a name="authorization-callback-model-binding"></a>
#### Authorization Callback Model Binding

Just like HTTP routes, channel routes may also take advantage of implicit and explicit [route model binding](/v4/routing#route-model-binding). For example, instead of receiving a string or numeric order ID, you may request an actual `Order` model instance:

```php
use App\Models\Order;
use App\Models\User;

Broadcast::channel('orders.{order}', function (User $user, Order $order) {
    return $user->id === $order->user_id;
});
```

<a name="authorization-callback-authentication"></a>
#### Authorization Callback Authentication

Private and presence broadcast channels authenticate the current user via your application's default authentication guard. If the user is not authenticated, channel authorization is automatically denied and the authorization callback is never executed. However, you may assign multiple, custom guards that should authenticate the incoming request if necessary:

```php
Broadcast::channel('channel', function () {
    // ...
}, ['guards' => ['web', 'admin']]);
```

<a name="defining-channel-classes"></a>
### Defining Channel Classes

If your application is consuming many different channels, your `listens/channels.php` file could become bulky. So, instead of using closures to authorize channels, you may use channel classes. To generate a channel class, use the `make:channel` Commander command. This command will place a new channel class in the `App/Broadcasting` directory.

```shell
php laragram make:channel OrderChannel
```

Next, register your channel in your `listens/channels.php` file:

```php
use App\Broadcasting\OrderChannel;

Broadcast::channel('orders.{order}', OrderChannel::class);
```

Finally, you may place the authorization logic for your channel in the channel class' `join` method. This `join` method will house the same logic you would have typically placed in your channel authorization closure:

```php
<?php

namespace App\Broadcasting;

use App\Models\Order;
use App\Models\User;

class OrderChannel
{
    /**
     * Authenticate the user's access to the channel.
     */
    public function join(User $user, Order $order): array|bool
    {
        return $user->id === $order->user_id;
    }
}
```

<a name="broadcasting-events"></a>
## Broadcasting Events

Once you have defined an event and marked it with the `ShouldBroadcast` interface, you only need to fire the event using the event's dispatch method. The event dispatcher will notice that the event is marked with the `ShouldBroadcast` interface and will queue the event for broadcasting:

```php
use App\Events\OrderShipmentStatusUpdated;

OrderShipmentStatusUpdated::dispatch($order);
```

<a name="only-to-others"></a>
### Only to Others

When building an application that utilizes event broadcasting, you may occasionally need to broadcast an event to all subscribers to a given channel except for the current user. You may accomplish this using the `broadcast` helper and the `toOthers` method. LaraGram will use the `X-Socket-ID` header of the current HTTP request to exclude the current user's connection:

```php
use App\Events\OrderShipmentStatusUpdated;

broadcast(new OrderShipmentStatusUpdated($update))->toOthers();
```

> [!WARNING]
> Your event must use the `LaraGram\Broadcasting\InteractsWithSockets` trait in order to call the `toOthers` method.

<a name="customizing-the-connection"></a>
### Customizing the Connection

If your application interacts with multiple broadcast connections and you want to broadcast an event using a broadcaster other than your default, you may specify which connection to push an event to using the `via` method:

```php
broadcast(new OrderShipmentStatusUpdated($update))->via('redis');
```

Alternatively, you may specify the event's broadcast connection by calling the `broadcastVia` method within the event's constructor. However, before doing so, you should ensure that the event class uses the `InteractsWithBroadcasting` trait:

```php
use LaraGram\Broadcasting\InteractsWithBroadcasting;

class OrderShipmentStatusUpdated implements ShouldBroadcast
{
    use InteractsWithBroadcasting;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        $this->broadcastVia('redis');
    }
}
```

<a name="anonymous-events"></a>
### Anonymous Events

Sometimes, you may want to broadcast a simple event to your application's frontend without creating a dedicated event class. To accommodate this, the `Broadcast` facade allows you to broadcast "anonymous events":

```php
Broadcast::on('orders.'.$order->id)->send();
```

Using the `as` and `with` methods, you may customize the event's name and data:

```php
Broadcast::on('orders.'.$order->id)
    ->as('OrderPlaced')
    ->with($order)
    ->send();
```

If you would like to broadcast the anonymous event on a private or presence channel, you may utilize the `private` and `presence` methods:

```php
Broadcast::private('orders.'.$order->id)->send();
Broadcast::presence('channels.'.$channel->id)->send();
```

Broadcasting an anonymous event using the `send` method dispatches the event to your application's [queue](/v4/queues) for processing. However, if you would like to broadcast the event immediately, you may use the `sendNow` method:

```php
Broadcast::on('orders.'.$order->id)->sendNow();
```

<a name="rescuing-broadcasts"></a>
### Rescuing Broadcasts

When your application's queue server is unavailable or LaraGram encounters an error while broadcasting an event, an exception is thrown. Since event broadcasting is often supplementary to your application's core functionality, you can prevent these exceptions from disrupting the user experience by implementing the `ShouldRescue` interface on your events:

```php
use LaraGram\Contracts\Broadcasting\ShouldBroadcast;
use LaraGram\Contracts\Broadcasting\ShouldRescue;

class OrderShipmentStatusUpdated implements ShouldBroadcast, ShouldRescue
{
    // ...
}
```

<a name="presence-channels"></a>
## Presence Channels

Presence channels build on the security of private channels while exposing the additional feature of awareness of who is subscribed to the channel. This makes it easy to build collaborative application features such as notifying users when another user is viewing the same page of a Mini App.

<a name="authorizing-presence-channels"></a>
### Authorizing Presence Channels

All presence channels are also private channels; therefore, users must be [authorized to access them](#authorizing-channels). However, when defining authorization callbacks for presence channels, you will not return `true` if the user is authorized to join the channel. Instead, you should return an array of data about the user. If the user is not authorized to join the presence channel, you should return `false` or `null`:

```php
use App\Models\User;

Broadcast::channel('chat.{roomId}', function (User $user, int $roomId) {
    if ($user->canJoinRoom($roomId)) {
        return ['id' => $user->id, 'name' => $user->name];
    }
});
```

<a name="broadcasting-to-presence-channels"></a>
### Broadcasting to Presence Channels

Presence channels may receive events just like public or private channels. To broadcast an event to a presence channel, return an instance of `PresenceChannel` from the event's `broadcastOn` method:

```php
/**
 * Get the channels the event should broadcast on.
 *
 * @return array<int, \LaraGram\Broadcasting\Channel>
 */
public function broadcastOn(): array
{
    return [
        new PresenceChannel('chat.'.$this->message->room_id),
    ];
}
```

<a name="model-broadcasting"></a>
## Model Broadcasting

It is common to broadcast events when your application's [Eloquent models](/v4/eloquent) are created, updated, or deleted. However, it can be cumbersome to create event classes for the sole purpose of broadcasting them. To remedy this, LaraGram allows you to indicate that an Eloquent model should automatically broadcast its state changes.

To get started, your Eloquent model should use the `LaraGram\Database\Eloquent\BroadcastsEvents` trait. In addition, the model should define a `broadcastOn` method, which will return an array of channels that the model's events should broadcast on:

```php
<?php

namespace App\Models;

use LaraGram\Database\Eloquent\BroadcastsEvents;
use LaraGram\Database\Eloquent\Model;

class Post extends Model
{
    use BroadcastsEvents;

    /**
     * Get the channels that model events should broadcast on.
     *
     * @return array<int, \LaraGram\Broadcasting\Channel|\LaraGram\Database\Eloquent\Model>
     */
    public function broadcastOn(string $event): array
    {
        return match ($event) {
            'deleted' => [],
            default => [$this],
        };
    }
}
```

Once your model includes this trait and defines its broadcast channels, it will begin automatically broadcasting events when a model instance is created, updated, deleted, trashed, or restored. The `$event` argument contains the type of event that has occurred on the model.

If an Eloquent model instance is returned by your model's `broadcastOn` method, LaraGram will automatically instantiate a private channel instance for the model using the model's class name and primary key identifier as the channel name. So, an `App\Models\Post` model with an `id` of `1` would be converted into a `LaraGram\Broadcasting\PrivateChannel` instance with a name of `App.Models.Post.1`. Model broadcast events are named using the class name of the model and the name of the model event, such as `PostUpdated`.

If you would like to temporarily prevent your models from broadcasting, you may use the `withoutBroadcasting` method:

```php
use LaraGram\Database\Eloquent\Model;

Model::withoutBroadcasting(function () {
    Post::create([...]);
});
```

<a name="custom-drivers"></a>
## Custom Drivers

If none of the included drivers fit your needs, you may register your own broadcaster using the `extend` method of the `Broadcast` facade, typically within the `boot` method of one of your application's [service providers](/v4/providers). The callback should return an implementation of `LaraGram\Contracts\Broadcasting\Broadcaster`:

```php
use LaraGram\Support\Facades\Broadcast;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Broadcast::extend('webhook', function ($app, array $config) {
        return new WebhookBroadcaster($config['url']);
    });
}
```
