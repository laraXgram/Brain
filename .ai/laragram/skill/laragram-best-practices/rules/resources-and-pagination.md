# API Resources and Pagination Best Practices

## Shape Output With Resources

Transform models for JSON APIs, Luna props, and MCP tools with Eloquent API Resources instead of returning models directly:

```php
// php laragram make:resource OrderResource  → app/Http/Resources/OrderResource.php
use LaraGram\Http\Request;
use LaraGram\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'can_cancel' => $this->when($request->user()?->can('cancel', $this->resource), true),
        ];
    }
}

return OrderResource::collection(Order::with('items')->latest()->paginate());
```

- Use `whenLoaded()` / `whenCounted()` so resources never trigger lazy loading; eager load in the controller.
- Expose only fields the client needs; never include tokens, Telegram session data, or hidden attributes.
- Follow the project's convention (resources vs. `toArray()` / `only()`); `$model->toResource()` and JSON:API resources are also available.

## Paginate Every Unbounded List

- Web and API: `paginate()` for numbered pages, `simplePaginate()` for previous/next, and `cursorPaginate()` for large or frequently changing tables. Resources wrap pagination metadata automatically; Luna uses `Luna::scroll()` for infinite scroll.
- Bots: `telegramPaginate(perPage: 10, key: 'orders')` or `simpleTelegramPaginate(...)`. Navigation buttons carry `paginate:<key>:<page>` in `callback_data`, so add a `Bot::onCallbackQueryData('paginate:orders:{page}', ...)` listen that re-renders with `page: $page` and `editMessageText`, and attach `$paginator->keyboard()`.
- Use a distinct `key` per paginated screen so two lists don't read each other's page.
- Never `->get()` a whole table to paginate or count in PHP; let the database do it.
