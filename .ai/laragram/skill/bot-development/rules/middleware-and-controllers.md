# Bot Middleware and Controllers

## Bot vs. Web Classes

LaraGram has two parallel stacks. Never mix their classes:

| | Bot (Telegram updates) | Web (browser requests) |
| --- | --- | --- |
| Request | `LaraGram\Request\Request` | `LaraGram\Http\Request` |
| Response | `LaraGram\Request\Response` | `LaraGram\Http\Response` |
| Controllers | `app/Controllers` (`make:controller`) | `app/Http/Controllers` (`make:controller --web`) |
| Middleware | `app/Middleware` (`make:middleware`) | `app/Http/Middleware` (`make:middleware --web`) |
| Middleware group | `bot` (applied to `listens/bot.php`) | `web` (applied to `routes/web.php`) |

## Controllers

Generate bot controllers with `php laragram make:controller OrderController` (add `--invokable` for a single-action controller). Controllers are resolved from the container, so type-hint dependencies in the constructor and the `Request` (plus listen parameters) in the action:

```php
namespace App\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use LaraGram\Request\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orders) {}

    public function show(Request $request, Order $order)
    {
        return template('orders.show', ['order' => $order]);
    }
}
```

```php
Bot::onCallbackQueryData('order:show:{order}', [OrderController::class, 'show']);
Bot::onText('checkout', CheckoutController::class); // invokable
```

Share a controller across a group with `Bot::controller(OrderController::class)->group(fn () => Bot::onText('orders', 'index'))`.

## Writing Bot Middleware

```php
namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

class EnsureUserIsRegistered
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! User::where('user_id', user()->id)->exists()) {
            $request->sendMessage(chat()->id, 'Please send /start first.');

            return new Response;
        }

        return $next($request);
    }
}
```

- Stop an update by replying and returning a response instead of calling `$next`; redirect to another listen with `return to_listen('home');`.
- Parameters follow the class and a colon: `->middleware(EnsureUserHasRole::class.':editor,publisher')`, received after `$next`.
- A `terminate(Request $request, Response $response)` method runs after the response is sent.

## Registering Middleware

Configure middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->bot(append: [EnsureUserIsRegistered::class]); // every bot listen
    $middleware->web(append: [HandleLunaRequests::class]);     // every web route
    $middleware->alias(['registered' => EnsureUserIsRegistered::class]);
})
```

- IMPORTANT: `$middleware->append()` / `prepend()` add **global** middleware to both the bot and the web kernel. A middleware that type-hints `LaraGram\Request\Request` breaks web requests there, so add bot middleware with `$middleware->bot(...)` and web middleware with `$middleware->web(...)`.
- Assign middleware per listen (`->middleware('registered')`), per group (`Bot::middleware(['registered'])->group(...)`), or exclude it (`->withoutMiddleware(...)`).
- Built-in bot aliases: `throttle` (rate limiting), `can` (authorization and chat-member status), `scope` (chat types), `reply`, and `step` (`->middleware('step:awaiting_email')` gates any update type behind a step).

## Controller Middleware

Implement `HasMiddleware` to declare middleware on the controller:

```php
use LaraGram\Listening\Controllers\HasMiddleware;
use LaraGram\Listening\Controllers\Middleware;

class AdminController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'can:administrator',
            new Middleware('throttle:admin', only: ['broadcast']),
        ];
    }
}
```

## Rate Limiting Users

Define limiters in `AppServiceProvider::boot()` and attach them with `throttle:<name>`:

```php
use LaraGram\Cache\RateLimiting\Limit;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\RateLimiter;

RateLimiter::for('bot', fn (Request $request) => Limit::perMinute(30)->by(user()->id)
    ->response(fn (Request $request) => $request->sendMessage(chat()->id, 'Slow down a little.')));
```

Use `Limit::perMinute(...)->by('minute:'.user()->id)` prefixes when returning several limits, and `$middleware->throttleWithRedis()` when the cache store is Redis. Rate limiting throttles incoming user actions; anti-flood paces outgoing Bot API calls (see the requests rule).
