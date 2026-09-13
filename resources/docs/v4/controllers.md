# Controllers

<a name="introduction"></a>
## Introduction

Instead of defining all of your request handling logic as closures in your listen files, you may wish to organize this behavior using "controller" classes. Controllers can group related request handling logic into a single class. For example, a `UserController` class might handle all incoming requests related to users, including showing, creating, updating, and deleting users. By default, controllers are stored in the `app/Controllers` directory.

<a name="writing-controllers"></a>
## Writing Controllers

<a name="basic-controllers"></a>
### Basic Controllers

To quickly generate a new controller, you may run the `make:controller` Commander command. By default, all of the controllers for your application are stored in the `app/Controllers` directory:

```shell
php laragram make:controller UserController
```

Let's take a look at an example of a basic controller. A controller may have any number of public methods which will respond to incoming Bot requests:

```php
<?php

namespace App\Controllers;

use App\Models\User;

class UserController extends Controller
{
    /**
     * Show the profile for a given user.
     */
    public function show(string $id)
    {
        template('user.profile', [
            'user' => User::findOrFail($id)
        ]);
    }
}
```

Once you have written a controller class and method, you may define a listen to the controller method like so:

```php
use App\Controllers\UserController;

Bot::onText('user {id}', [UserController::class, 'show']);
```

When an incoming request matches the specified listen Pattern, the `show` method on the `App\Controllers\UserController` class will be invoked and the listen parameters will be passed to the method.

> [!NOTE]
> Controllers are not **required** to extend a base class. However, it is sometimes convenient to extend a base controller class that contains methods that should be shared across all of your controllers.

<a name="single-action-controllers"></a>
### Single Action Controllers

If a controller action is particularly complex, you might find it convenient to dedicate an entire controller class to that single action. To accomplish this, you may define a single `__invoke` method within the controller:

```php
<?php

namespace App\Controllers;

class ProvisionServer extends Controller
{
    /**
     * Provision a new web server.
     */
    public function __invoke()
    {
        // ...
    }
}
```

When registering listens for single action controllers, you do not need to specify a controller method. Instead, you may simply pass the name of the controller to the listener:

```php
use App\Controllers\ProvisionServer;

Bot::onText('server', ProvisionServer::class);
```

You may generate an invokable controller by using the `--invokable` option of the `make:controller` Commander command:

```shell
php laragram make:controller ProvisionServer --invokable
```

> [!NOTE]
> Controller stubs may be customized using [stub publishing](/v4/commander#stub-customization).

<a name="controller-middleware"></a>
## Controller Middleware

[Middleware](/v4/middleware) may be assigned to the controller's listens in your listen files:

```php
Bot::onText('profile', [UserController::class, 'show'])->middleware('auth');
```

Or, you may find it convenient to specify middleware within your controller class. To do so, your controller should implement the `HasMiddleware` interface, which dictates that the controller should have a static `middleware` method. From this method, you may return an array of middleware that should be applied to the controller's actions:

```php
<?php

namespace App\Controllers;

use LaraGram\Listening\Controllers\HasMiddleware;
use LaraGram\Listening\Controllers\Middleware;

class UserController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            new Middleware('log', only: ['index']),
            new Middleware('subscribed', except: ['store']),
        ];
    }

    // ...
}
```

You may also define controller middleware as closures, which provides a convenient way to define an inline middleware without writing an entire middleware class:

```php
use Closure;
use LaraGram\Request\Request;

/**
 * Get the middleware that should be assigned to the controller.
 */
public static function middleware(): array
{
    return [
        function (Request $request, Closure $next) {
            return $next($request);
        },
    ];
}
```

<a name="dependency-injection-and-controllers"></a>
## Dependency Injection and Controllers

<a name="constructor-injection"></a>
#### Constructor Injection

The LaraGram [service container](/v4/container) is used to resolve all LaraGram controllers. As a result, you are able to type-hint any dependencies your controller may need in its constructor. The declared dependencies will automatically be resolved and injected into the controller instance:

```php
<?php

namespace App\Controllers;

use App\Repositories\UserRepository;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected UserRepository $users,
    ) {}
}
```

<a name="method-injection"></a>
#### Method Injection

In addition to constructor injection, you may also type-hint dependencies on your controller's methods. A common use-case for method injection is injecting the `LaraGram\Request\Request` instance into your controller methods:

```php
<?php

namespace App\Controllers;

use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;

class UserController extends Controller
{
    /**
     * Store a new user.
     */
    public function store(Request $request): RedirectResponse
    {
        $name = $request->name;

        // Store the user...

        return to_listen('users');
    }
}
```

If your controller method is also expecting input from a listen parameter, list your listen arguments after your other dependencies. For example, if your listen is defined like so:

```php
use App\Controllers\UserController;

Bot::onText('user {id}', [UserController::class, 'foo']);
```

You may still type-hint the `LaraGram\Request\Request` and access your `id` parameter by defining your controller method as follows:

```php
<?php

namespace App\Controllers;

use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;

class UserController extends Controller
{
    /**
     * Update the given user.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        // Update the user...

        return to_listen('users');
    }
}
```
