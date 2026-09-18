# Broadcasting

Use the `Broadcast` facade whenever Bot API calls or a template must reach many chats: an announcement to every user, a pin or permission change in every group, a localized template to a segment. Never write a `foreach` over chat ids that calls `sendMessage` inside a listen, controller, command, or job.

If `vendor/laraxgram/core/src/Broadcasting` does not exist, the installed core predates broadcasting: dispatch a queued job that loops over the chat ids with `app('request')->antiFloodWith('broadcast')->sendMessage(...)` instead.

## Setup

Check `bootstrap/app.php` for `withBroadcasting(...)` and the `TrackChats` bot middleware before the first broadcast. When they are missing, run `php laragram install:broadcasting --no-interaction` and `php laragram migrate`; the command creates `listens/channels.php`, registers both in `bootstrap/app.php`, and creates the migration of the `broadcast_chats`, `broadcast_members` and `broadcast_targets` tables (`make:broadcast-tables`).

- `TrackChats` records chats (with names, language, last activity), group members (senders, joins, leaves, `chat_member` updates), and marks chats unreachable when the bot is blocked or removed. If a connection sets `allowed_updates`, it must include `my_chat_member` (and `chat_member` to record channel joins; the bot must be an admin).
- Chats, members and recall data live in the broadcast store (`broadcasting.store`): `database` (three tables; best for large audiences and many filters), `redis` (no tables, filtered in memory), or `null`. Read or write it from code with `Broadcast::store()`.
- A queue worker must run (`php laragram queue:work`). Progress, cancellation and `once()` use the cache, so workers need a shared store (redis or database).

## The Three Objects

The chain is typed, so only the methods that make sense are available at each point. Don't fight it:

1. `Broadcast::users()`, `groups()`, `to()`, `members()`, `sent()` return **`Recipients`**: audiences, filters, and what to send (every Bot API method, `template()`), plus `count()`.
2. A Bot API method or `template()` returns **`TelegramBroadcast`**: delivery options and `send()` / `queue()` / `later()`.
3. `next()` returns **`NextStep`**: only Bot API methods and `template()`, leading back to the broadcast.

```php
use LaraGram\Support\Facades\Broadcast;

$id = Broadcast::users()
    ->language('fa')->activeSince(30)
    ->template('promotions.spring', ['discount' => 30])
    ->localized()
    ->between('09:00', '21:00', 'Asia/Tehran')
    ->queue();
```

## Who

- Audiences: `users()`, `groups()`, `supergroups()`, `channels()`, `chats()`, `members($groupId)`, or `to()` with chat ids, `@usernames`, audience names, `Audience` objects, or an array of them (de-duplicated). Custom audiences are defined in `listens/channels.php` with `Broadcast::audience('name', fn (string $bot) => Query)` (placeholders like `city.{city}` arrive as named arguments). Return a query builder so large audiences are read lazily.
- Filters (work on every audience; they match against recorded chats, so unrecorded ids drop out once any filter is used): `where`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `ofType`, `language`, `activeSince`, `inactiveSince`, `joinedAfter`, `joinedBefore`, `tagged`, `taggedAll`, `notTagged`, `except`, `limit`, `reachableOnly`.
- Membership: users in a group `membersOf($groupId)` / `notMembersOf(...)`; groups containing a user `withMember($userId)` / `withoutMember(...)` / `administeredBy($userId)`. Channel subscribers cannot be listed: use the live checks `subscribedTo('@channel')` / `notSubscribedTo(...)` / `checkMembership($chat, member: true, statuses: [...])` (one `getChatMember` per recipient; the bot must be an admin).
- Tags are custom segments: `Broadcast::tag($chatIds, 'vip')`, `Broadcast::untag(...)`.

## What

- Every Bot API method that targets a chat or a user is a real method on the recipients, with **Laraquest's parameters in Laraquest's order, minus the recipient** (`chat_id`, or `user_id` when the method has no `chat_id`): `->sendPhoto($fileId, 'Caption')`, `->sendMessage('Hi', parse_mode: 'HTML', disable_notification: true)`, `->banChatMember($userId)`, `->setChatPermissions([...])`. The methods are generated from Laraquest's schema and regenerate themselves when it changes, so they always match `$request`. Unknown names throw a `BadMethodCallException`; use `target('param')` when another parameter should receive the recipient.
- There are no `message()` / `copy()` / `silent()` shortcuts: use `sendMessage`, `copyMessage`, and the methods' own parameters.
- Several calls per recipient: `->sendPhoto($file)->next()->sendPoll('Q?', $options)`. A failed step stops the remaining steps for that recipient.
- Queued broadcasts are serialized: pass `file_id`s or URLs (never `CURLFile`) and arrays or scalars as template data. Templates may build local files, since they render during delivery.

## Templates

- `->template($template, $data)` accepts a template name, a path, a `Template` instance, or an inline Temple8 string, and renders it once per recipient. Everything the engine supports works: inputs and `@method`, keyboards, rich messages, components, layouts, includes, translations.
- Inside the template, `chat()` / `user()` and the default `chat_id` are the recipient, plus `$recipient` (`name()`, `first_name`, `language_code`, `known`, ...) and `$broadcast`.
- `->localized()` renders each recipient's template in their Telegram language. Pass `false` as the third argument for templates that do not depend on the recipient (rendered once per job).
- Prefer templates for formatted, keyboarded or translated broadcasts; follow the template conventions of the app (see [`templates.md`](templates.md)).

## When

- `send()` delivers in the current process (commands, jobs, a handful of chats), `queue()` queues it, `later($when)` queues it for a moment, an interval, or a number of seconds. Never `send()` a large audience from a webhook listen.
- `between('09:00', '21:00', $tz)` restricts delivery hours. `later()` and `between()` need a queue that supports delays (database, redis, beanstalkd); on `sync` they throw on purpose.
- Recurring: `Schedule::broadcast(fn () => Broadcast::users()->...)->weeklyOn(5, '10:00')` in `listens/console.php`. Reminders that must reach each user once: `->once('onboarding-day-3')`.
- Multi-bot applications outside an update (commands, schedules, jobs): always call `->bot('connection')`.

## Before Sending

- Show the audience size with `->count()` and send a preview with `->test($developerChatId)` (delivers now to that chat only, ignoring filters, limits and schedules).
- Never send to real chats while testing or verifying a change unless the user asks. Use `->test(...)`, or `->via('log')` to write the broadcast to the log instead.
- Broadcast commands should confirm (`confirm()` or `--force`) and show `count()` first. Telegram paid broadcasts (`allow_paid_broadcast: true`, billed in Stars) only when the user asks.

## After Sending

- Progress: `Broadcast::progress($id)` (`status()`: queued, scheduled, running, waiting, finished, cancelled; `sent()`, `unreachable()`, `failed()`, `skipped()`, `percentage()`), `Broadcast::cancel($id)`, `Broadcast::recent()`, and the `broadcast:status` (no argument lists the recent ones, an id shows one), `broadcast:cancel` and `broadcast:recall` commands. `->reportTo($adminIds)` sends a summary when the broadcast completes.
- Edit or undo what was done: send with `->recallable()` (or `BROADCAST_RECALL=true`), then `Broadcast::sent($id)->editMessageText(...)` / `pin()` / `delete()` (`messageIndex(n)` picks the n-th message), or `Broadcast::recall($id)` to undo the whole broadcast: sends and edits are deleted, pins unpinned, bans and restrictions lifted, promotions undone, topics reopened, reactions cleared.
- Calls whose previous state is unknown (`setChatTitle`, `setChatPermissions`, `setChatPhoto`) or that are final (`deleteMessage`, `unpinAllChatMessages`, `leaveChat`) cannot be undone: `recall` throws and names them. Pass `partial: true` to skip them, or teach LaraGram the inverse with `Broadcast::recallUsing('setChatTitle', fn ($action) => Action::make('setChatTitle', [...]))` in a service provider.
- Failures are handled for you: 429s are retried, blocked or deleted chats are marked unreachable, upgraded groups are migrated. React with listeners for `LaraGram\Broadcasting\Telegram\Events\BroadcastCompleted`, `ChatUnreachable`, `ChatMigrated`, `DeliveryFailed` instead of checking responses.
- Each chunk job must finish before the queue connection's `retry_after`, or recipients may be messaged twice. Lower `chunk()` for templates, several steps, or live checks rather than raising timeouts.

## Event Classes

For a broadcast tied to a domain event, implement `ShouldBroadcast`, call `$this->broadcastVia('telegram')` (trait `InteractsWithBroadcasting`), return audiences from `broadcastOn()`, the Bot API method from `broadcastAs()`, and its parameters from `broadcastWith()`. For templates, options or filters, return an `Action` from `broadcastWith()`: `Action::template('name', $data)->options(['localized' => true, 'criteria' => (new ChatCriteria)->language('fa')->toArray()])->toArray()`.
