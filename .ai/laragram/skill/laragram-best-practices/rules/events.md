# Events Best Practices

## Rely on Event Discovery

LaraGram discovers listeners in the configured listener directories by inspecting type-hinted event arguments on `handle()` or `__invoke()` methods. Register listeners manually only when discovery is disabled, the listener is outside those directories, or explicit registration is clearer.

Event listeners (`app/Listeners`) are not bot listens (`listens/bot.php`). Event listeners react to application events; bot listens react to Telegram updates.

## Cache Event Discovery During Production Deployment

Cache discovered listeners during production deployment with `php laragram optimize` or `php laragram event:cache`. Rebuild the cache whenever listener definitions change.

## Use `ShouldDispatchAfterCommit` Inside Transactions

When an event is dispatched inside a database transaction, `ShouldDispatchAfterCommit` delays dispatch until all open database transactions commit. If a transaction rolls back, LaraGram discards the event. This affects synchronous and queued listeners; it is not limited to queue timing.

```php
class OrderShipped implements ShouldDispatchAfterCommit {}
```

## Queue Listeners That Send Telegram Messages

Telegram messages triggered by an event (an order update, an admin alert) call an external API. Queue those listeners with `ShouldQueue` when the message does not need to be sent before the current update or request finishes, so a slow or failing Telegram call does not block the user.

```php
class NotifyCustomerOfShipment implements ShouldQueue
{
    public function handle(OrderShipped $event): void
    {
        app('request')->sendMessage(
            $event->order->customer->chat_id,
            "Your order #{$event->order->id} has shipped.",
        );
    }
}
```

## Broadcast Events to Many Chats

When an event should reach an audience of chats (every user, every group), don't queue a listener that loops over chats. Make the event implement `ShouldBroadcast` and send it through the `telegram` broadcast connection: `broadcastOn()` returns the audiences, `broadcastAs()` the Bot API method, and `broadcastWith()` its parameters.

```php
class ProductLaunched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(public Product $product)
    {
        $this->broadcastVia('telegram');
    }

    public function broadcastOn(): array
    {
        return [Audience::users()];
    }

    public function broadcastAs(): string
    {
        return 'sendMessage';
    }

    public function broadcastWith(): array
    {
        return ['text' => "New: {$this->product->name}"];
    }
}
```

## Opt a Conditional Listener Out of Discovery

A listener that should only exist behind a flag implements `LaraGram\Contracts\Events\ShouldBeDiscovered` and returns `false` from `shouldBeDiscovered()`, rather than guarding every `handle()` call. The answer is baked into the `event:cache` manifest, so the cache has to be rebuilt when the condition changes.

## Debounce or Deduplicate Queued Listeners

A queued listener that reacts to a noisy event should not run for every dispatch. `#[DebounceFor(30)]` with a `debounceId()` keeps the last event of a burst; `ShouldBeUnique` (or `ShouldBeUniqueUntilProcessing`) keeps one instance on the queue. A listener may not be both.

## Defer Events Until Related Records Exist

When listeners need the whole picture — a user *and* their chats, an order *and* its items — wrap the writes in `Event::defer(fn () => ...)`. Every event raised inside is dispatched after the closure returns, and nothing is dispatched if it throws.

## Encrypt Listener Payloads That Carry Secrets

A queued listener's payload sits in the queue in plain text. A listener that carries personal data or a token should implement `ShouldBeEncrypted`.

## Pass Identifiers, Not the Current Update

Queued listeners run in a queue worker, where `chat()`, `user()`, and the incoming `LaraGram\Request\Request` data are not available. Put the chat id, user id, or model on the event instead of reading them from the update inside the listener.
