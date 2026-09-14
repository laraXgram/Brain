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

## Pass Identifiers, Not the Current Update

Queued listeners run in a queue worker, where `chat()`, `user()`, and the incoming `LaraGram\Request\Request` data are not available. Put the chat id, user id, or model on the event instead of reading them from the update inside the listener.
