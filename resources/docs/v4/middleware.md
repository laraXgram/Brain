# Middleware

<a name="introduction"></a>
## Introduction

Middleware provide a convenient mechanism for inspecting and filtering Bot requests entering your application. For example, LaraGram includes a middleware that verifies the user of your application is authenticated. If the user is not authenticated, the middleware will redirect the user to another part of your application. However, if the user is authenticated, the middleware will allow the request to proceed further into the application.

Additional middleware can be written to perform a variety of tasks besides authentication. For example, a logging middleware might log all incoming requests to your application. A variety of middleware are included in LaraGram; however, all user-defined middleware are typically located in your application's `app/Middleware` directory.

<a name="defining-middleware"></a>
## Defining Middleware

To create a new middleware, use the `make:middleware` Commander command:

```shell
php laragram make:middleware EnsureTokenIsValid
```

This command will place a new `EnsureTokenIsValid` class within your `app/Middleware` directory. In this middleware, we will only allow access to the listen if the supplied `token` input matches a specified value. Otherwise, we will redirect the users back to the `home` section:

```php
<?php

namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

class EnsureTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\LaraGram\Request\Request): (\LaraGram\Request\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->message->text !== 'my-secret-token') {
            return to_listen('home');
        }

        return $next($request);
    }
}
```

As you can see, if the given `token` does not match our secret token, the middleware will return an Bot redirect to the client; otherwise, the request will be passed further into the application. To pass the request deeper into the application (allowing the middleware to "pass"), you should call the `$next` callback with the `$request`.

It's best to envision middleware as a series of "layers" Bot requests must pass through before they hit your application. Each layer can examine the request and even reject it entirely.

> [!NOTE]
> All middleware are resolved via the [service container](/v4/container), so you may type-hint any dependencies you need within a middleware's constructor.

<a name="middleware-and-responses"></a>
#### Middleware and Responses

Of course, a middleware can perform tasks before or after passing the request deeper into the application. For example, the following middleware would perform some task **before** the request is handled by the application:

```php
<?php

namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

class BeforeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Perform action

        return $next($request);
    }
}
```

However, this middleware would perform its task **after** the request is handled by the application:

```php
<?php

namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

class AfterMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Perform action

        return $response;
    }
}
```

<a name="registering-middleware"></a>
## Registering Middleware

<a name="global-middleware"></a>
### Global Middleware

If you want a middleware to run during every Bot request to your application, you may append it to the global middleware stack in your application's `bootstrap/app.php` file:

```php
use App\Middleware\EnsureTokenIsValid;

->withMiddleware(function (Middleware $middleware) {
     $middleware->append(EnsureTokenIsValid::class);
})
```

The `$middleware` object provided to the `withMiddleware` closure is an instance of `LaraGram\Foundation\Configuration\Middleware` and is responsible for managing the middleware assigned to your application's listens. The `append` method adds the middleware to the end of the list of global middleware. If you would like to add a middleware to the beginning of the list, you should use the `prepend` method.

<a name="manually-managing-laragrams-default-global-middleware"></a>
#### Manually Managing LaraGram's Default Global Middleware

If you would like to manage LaraGram's global middleware stack manually, you may provide LaraGram's default stack of global middleware to the `use` method. Then, you may adjust the default middleware stack as necessary:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->use([
        \LaraGram\Foundation\Bot\Middleware\InvokeDeferredCallbacks::class,
        // ...
    ]);
})
```

<a name="assigning-middleware-to-listens"></a>
### Assigning Middleware to Listens

If you would like to assign middleware to specific listens, you may invoke the `middleware` method when defining the listen:

```php
use App\Middleware\EnsureTokenIsValid;

Bot::onText('profile', function () {
    // ...
})->middleware(EnsureTokenIsValid::class);
```

You may assign multiple middleware to the listen by passing an array of middleware names to the `middleware` method:

```php
Bot::onText('profile', function () {
    // ...
})->middleware([First::class, Second::class]);
```

<a name="excluding-middleware"></a>
#### Excluding Middleware

When assigning middleware to a group of listens, you may occasionally need to prevent the middleware from being applied to an individual listen within the group. You may accomplish this using the `withoutMiddleware` method:

```php
use App\Middleware\EnsureTokenIsValid;

Bot::middleware([EnsureTokenIsValid::class])->group(function () {
    Bot::onText('profile', function () {
        // ...
    });

    Bot::onText('profile', function () {
        // ...
    })->withoutMiddleware([EnsureTokenIsValid::class]);
});
```

You may also exclude a given set of middleware from an entire [group](/v4/listening#listen-groups) of listen definitions:

```php
use App\Middleware\EnsureTokenIsValid;

Bot::withoutMiddleware([EnsureTokenIsValid::class])->group(function () {
    Bot::onText('profile', function () {
        // ...
    });
});
```

The `withoutMiddleware` method can only remove listen middleware and does not apply to [global middleware](#global-middleware).

<a name="middleware-groups"></a>
### Middleware Groups

Sometimes you may want to group several middleware under a single key to make them easier to assign to listens. You may accomplish this using the `appendToGroup` method within your application's `bootstrap/app.php` file:

```php
use App\Middleware\First;
use App\Middleware\Second;

->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('group-name', [
        First::class,
        Second::class,
    ]);

    $middleware->prependToGroup('group-name', [
        First::class,
        Second::class,
    ]);
})
```

Middleware groups may be assigned to listens and controller actions using the same syntax as individual middleware:

```php
Bot::onText('profile', function () {
    // ...
})->middleware('group-name');

Bot::middleware(['group-name'])->group(function () {
    // ...
});
```

<a name="laragrams-default-middleware-groups"></a>
#### LaraGram's Default Middleware Groups

LaraGram includes predefined `bot` middleware groups that contain common middleware you may want to apply to your bot listens. Remember, LaraGram automatically applies these middleware groups to the corresponding `listens/bot.php` files:

<div class="overflow-auto">

| The `bot` Middleware Group                                |
|-----------------------------------------------------------|
| `LaraGram\Listening\Middleware\Reply`                     |
| `LaraGram\Listening\Middleware\Scope`                     |
| `LaraGram\Listening\Middleware\Step`                      |
| `LaraGram\Listening\Middleware\SubstituteBindings`        |
| `LaraGram\Listening\Middleware\ThrottleRequests`          |
| `LaraGram\Listening\Middleware\ThrottleRequestsWithRedis` |

</div>

If you would like to append or prepend middleware to these groups, you may use the `bot` methods within your application's `bootstrap/app.php` file. The `bot` methods are convenient alternatives to the `appendToGroup` method:

```php
use App\Middleware\EnsureTokenIsValid;
use App\Middleware\EnsureUserIsSubscribed;

->withMiddleware(function (Middleware $middleware) {
    $middleware->bot(append: [
        EnsureUserIsSubscribed::class,
    ]);
})
```

You may even replace one of LaraGram's default middleware group entries with a custom middleware of your own:

```php
$middleware->bot(replace: [
    Old::class => new::class,
]);
```

Or, you may remove a middleware entirely:

```php
$middleware->bot(remove: [
    Foo::class,
]);
```

<a name="manually-managing-laragrams-default-middleware-groups"></a>
#### Manually Managing LaraGram's Default Middleware Groups

If you would like to manually manage all of the middleware within LaraGram's default `bot` middleware groups, you may redefine the groups entirely. The example below will define the `bot` middleware groups with their default middleware, allowing you to customize them as necessary:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('bot', [
        \LaraGram\Listening\Middleware\SubstituteBindings::class,
    ]);
})
```

> [!NOTE]
> By default, the `bot` middleware groups are automatically applied to your application's corresponding `listens/bot.php` file by the `bootstrap/app.php` file.

<a name="middleware-aliases"></a>
### Middleware Aliases

You may assign aliases to middleware in your application's `bootstrap/app.php` file. Middleware aliases allow you to define a short alias for a given middleware class, which can be especially useful for middleware with long class names:

```php
use App\Middleware\EnsureUserIsSubscribed;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'subscribed' => EnsureUserIsSubscribed::class
    ]);
})
```

Once the middleware alias has been defined in your application's `bootstrap/app.php` file, you may use the alias when assigning the middleware to listens:

```php
Bot::onText('profile', function () {
    // ...
})->middleware('subscribed');
```

For convenience, some of LaraGram's built-in middleware are aliased by default. For example, the `reply` middleware is an alias for the `LaraGram\Listening\Middleware\Reply` middleware. Below is a list of the default middleware aliases:

<div class="overflow-auto">

| Alias      | Middleware                                                                                                    |
|------------|---------------------------------------------------------------------------------------------------------------|
| `can`      | `LaraGram\Auth\Middleware\Authorize`                                                                          |
| `reply`    | `LaraGram\Listening\Middleware\Reply`                                                                         |
| `scope`    | `LaraGram\Listening\Middleware\Scope`                                                                         |
| `step`     | `LaraGram\Listening\Middleware\Step`                                                                          |
| `throttle` | `LaraGram\Listening\Middleware\ThrottleRequests` or `LaraGram\Listening\Middleware\ThrottleRequestsWithRedis` |

</div>

<a name="sorting-middleware"></a>
### Sorting Middleware

Rarely, you may need your middleware to execute in a specific order but not have control over their order when they are assigned to the listen. In these situations, you may specify your middleware priority using the `priority` method in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->priority([
        // ...
    ]);
})
```

<a name="middleware-parameters"></a>
## Middleware Parameters

Middleware can also receive additional parameters. For example, if your application needs to verify that the authenticated user has a given "role" before performing a given action, you could create an `EnsureUserHasRole` middleware that receives a role name as an additional argument.

Additional middleware parameters will be passed to the middleware after the `$next` argument:

```php
<?php

namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\LaraGram\Request\Request): (\LaraGram\Request\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()->hasRole($role)) {
            // Redirect...
        }

        return $next($request);
    }
}
```

Middleware parameters may be specified when defining the listen by separating the middleware name and parameters with a `:`:

```php
use App\Middleware\EnsureUserHasRole;

Bot::onText('edit {id}', function (string $id) {
    // ...
})->middleware(EnsureUserHasRole::class.':editor');
```

Multiple parameters may be delimited by commas:

```php
Bot::onText('edit {id}', function (string $id) {
    // ...
})->middleware(EnsureUserHasRole::class.':editor,publisher');
```

<a name="terminable-middleware"></a>
## Terminable Middleware

Sometimes a middleware may need to do some work after the Bot response has been sent to the browser. If you define a `terminate` method on your middleware and your web server is using FastCGI, the `terminate` method will automatically be called after the response is sent to the bot:

```php
<?php

namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

class TerminatingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\LaraGram\Request\Request): (\LaraGram\Request\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the bot.
     */
    public function terminate(Request $request, Response $response): void
    {
        // ...
    }
}
```

The `terminate` method should receive both the request and the response. Once you have defined a terminable middleware, you should add it to the list of listens or global middleware in your application's `bootstrap/app.php` file.

When calling the `terminate` method on your middleware, LaraGram will resolve a fresh instance of the middleware from the [service container](/v4/container). If you would like to use the same middleware instance when the `handle` and `terminate` methods are called, register the middleware with the container using the container's `singleton` method. Typically this should be done in the `register` method of your `AppServiceProvider`:

```php
use App\Middleware\TerminatingMiddleware;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(TerminatingMiddleware::class);
}
```
