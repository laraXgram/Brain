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

<a name="middleware-attributes"></a>
### Middleware Attributes

Middleware may also be attached with the `Middleware` attribute, on the class or on a single method. The attribute is repeatable, and accepts the same `only` / `except` options:

```php
<?php

namespace App\Controllers;

use LaraGram\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth')]
class UserController extends Controller
{
    #[Middleware('subscribed')]
    public function index(): mixed
    {
        // ...
    }

    #[Middleware('log', except: ['destroy'])]
    public function store(): mixed
    {
        // ...
    }
}
```

<a name="authorization-attributes"></a>
### Authorization Attributes

The `Authorize` attribute is a shortcut for the `can` middleware: it runs an [authorization](/v4/authorization) check before the action, and may name the models whose route parameters are passed to the policy:

```php
use App\Models\Post;
use LaraGram\Routing\Attributes\Controllers\Authorize;

#[Authorize('viewAny', Post::class)]
class PostController extends Controller
{
    #[Authorize('update', 'post')]
    public function update(Post $post): mixed
    {
        // ...
    }
}
```

<a name="resource-controllers"></a>
## Resource Controllers

When a controller serves [web routes](/v4/routing) for an Eloquent model, it usually needs the same set of actions for every model: creating, reading, updating and deleting records. A resource controller declares those actions, and a single route declaration registers all of their routes.

Generate one with the `--resource` option:

```shell
php laragram make:controller PhotoController --resource
```

The generated controller has a method for each available resource operation. The `--model` option type-hints the model in every method, `--parent` generates a nested resource controller, and `--singleton` generates a [singleton resource controller](#singleton-resource-controllers):

```shell
php laragram make:controller PhotoController --model=Photo --resource

php laragram make:controller CommentController --parent=Photo --resource

php laragram make:controller ProfileController --singleton
``` Next, register the resource route:

```php
use App\Controllers\PhotoController;
use LaraGram\Support\Facades\Route;

Route::resource('photos', PhotoController::class);
```

This single declaration creates the routes of every action the controller handles:

<div class="overflow-auto">

| Verb      | URI                    | Action  | Route Name     |
| --------- | ---------------------- | ------- | -------------- |
| GET       | `/photos`              | index   | photos.index   |
| GET       | `/photos/create`       | create  | photos.create  |
| POST      | `/photos`              | store   | photos.store   |
| GET       | `/photos/{photo}`      | show    | photos.show    |
| GET       | `/photos/{photo}/edit` | edit    | photos.edit    |
| PUT/PATCH | `/photos/{photo}`      | update  | photos.update  |
| DELETE    | `/photos/{photo}`      | destroy | photos.destroy |

</div>

Several resources may be registered at once by passing an array to the `resources` method:

```php
Route::resources([
    'photos' => PhotoController::class,
    'posts' => PostController::class,
]);
```

<a name="missing-model-behavior"></a>
#### Customizing Missing Model Behavior

When an implicitly bound model is not found, a 404 response is returned. The `missing` method defines what happens instead:

```php
use LaraGram\Http\Request;
use LaraGram\Support\Facades\Redirect;

Route::resource('photos', PhotoController::class)
    ->missing(fn (Request $request) => Redirect::route('photos.index'));
```

<a name="soft-deleted-models"></a>
#### Soft Deleted Models

Implicit [model binding](/v4/routing#implicit-binding) skips soft deleted models. `withTrashed` lets a resource resolve them, for every action or for the ones you name:

```php
Route::resource('photos', PhotoController::class)->withTrashed();

Route::resource('photos', PhotoController::class)->withTrashed(['show']);
```

<a name="api-resource-routes"></a>
#### API Resource Routes

An API has no HTML forms, so the `create` and `edit` routes are pointless. Use `apiResource` to leave them out:

```php
Route::apiResource('photos', PhotoController::class);

Route::apiResources([
    'photos' => PhotoController::class,
    'posts' => PostController::class,
]);
```

Generate a controller without those methods with the `--api` option:

```shell
php laragram make:controller PhotoController --api
```

<a name="partial-resource-routes"></a>
### Partial Resource Routes

A resource may register only a part of its actions:

```php
Route::resource('photos', PhotoController::class)->only([
    'index', 'show',
]);

Route::resource('photos', PhotoController::class)->except([
    'create', 'store', 'update', 'destroy',
]);
```

<a name="nested-resources"></a>
### Nested Resources

A resource that belongs to another one is nested with "dot" notation, which produces URIs such as `/photos/{photo}/comments/{comment}`:

```php
Route::resource('photos.comments', PhotoCommentController::class);
```

<a name="shallow-nesting"></a>
#### Shallow Nesting

The parent identifier is only needed where it adds something: listing and creating. The `shallow` method keeps the parent for those actions and drops it from the rest:

```php
Route::resource('photos.comments', CommentController::class)->shallow();
```

<a name="naming-resource-routes"></a>
### Naming Resource Routes

Every resource action gets a route name, and any of them may be overridden:

```php
Route::resource('photos', PhotoController::class)->names([
    'create' => 'photos.build',
]);

Route::resource('photos', PhotoController::class)->name('create', 'photos.build');
```

<a name="naming-resource-route-parameters"></a>
### Naming Resource Route Parameters

Resource routes name their parameters after the singularized resource name. The `parameters` method changes that per resource, and `parameter` renames a single one:

```php
Route::resource('users', AdminUserController::class)->parameters([
    'users' => 'admin_user',
]);

Route::resource('users', AdminUserController::class)->parameter('user', 'admin_user');
```

The example above produces `/users/{admin_user}` for the resource's `show` route.

<a name="scoping-resource-routes"></a>
### Scoping Resource Routes

LaraGram's [scoped implicit model binding](/v4/routing#implicit-model-binding-scoping) can make sure a nested model belongs to its parent. The `scoped` method enables it, and names the field each nested parameter resolves by:

```php
Route::resource('photos.comments', PhotoCommentController::class)->scoped([
    'comment' => 'slug',
]);
```

That resource resolves `/photos/{photo}/comments/{comment:slug}` through the photo's `comments` relationship.

<a name="localizing-resource-uris"></a>
### Localizing Resource URIs

By default, resource routes use English verbs in their URIs (`create` and `edit`). Those verbs may be translated with the `ResourceRegistrar::verbs` method, which is best called from the `boot` method of your `App\Providers\AppServiceProvider`:

```php
use LaraGram\Routing\ResourceRegistrar;

public function boot(): void
{
    ResourceRegistrar::verbs([
        'create' => 'crear',
        'edit' => 'editar',
    ]);
}
```

LaraGram's pluralizer supports [several languages](/v4/localization), so the resource name is pluralized accordingly: `Route::resource('publicacion', PublicacionController::class)` then produces URIs such as `/publicacion/crear`.

<a name="supplementing-resource-controllers"></a>
### Supplementing Resource Controllers

A resource controller may of course have routes of its own. Register them **before** the resource, or the resource's `show` route will match first:

```php
Route::get('/photos/popular', [PhotoController::class, 'popular']);

Route::resource('photos', PhotoController::class);
```

> [!NOTE]
> Keep your controllers focused. A controller that needs methods outside the typical resource actions is usually two controllers.

<a name="singleton-resource-controllers"></a>
### Singleton Resource Controllers

Some resources only ever have one instance — a user's profile, the settings of an application, the thumbnail of an image. Such a resource has no `index` route and takes no identifier:

```php
use App\Controllers\ProfileController;

Route::singleton('profile', ProfileController::class);
```

<div class="overflow-auto">

| Verb      | URI             | Action | Route Name     |
| --------- | --------------- | ------ | -------------- |
| GET       | `/profile`      | show   | profile.show   |
| GET       | `/profile/edit` | edit   | profile.edit   |
| PUT/PATCH | `/profile`      | update | profile.update |

</div>

A singleton may also be nested inside a standard resource:

```php
Route::singleton('photos.thumbnail', ThumbnailController::class);
```

When a singleton resource may be created or destroyed, add `creatable` or `destroyable`:

```php
Route::singleton('photos.thumbnail', ThumbnailController::class)->creatable();

Route::singleton('photos.thumbnail', ThumbnailController::class)->destroyable();
```

`apiSingleton` registers a singleton for an API, leaving out the `create` and `edit` routes:

```php
Route::apiSingleton('profile', ProfileController::class);

Route::apiSingleton('photos.thumbnail', ProfileController::class)->creatable();
```

<a name="middleware-and-resource-controllers"></a>
### Middleware and Resource Controllers

Middleware may be assigned to every action of a resource, or to the actions you name:

```php
Route::resource('photos', PhotoController::class)
    ->middleware(['auth', 'verified']);

Route::resource('photos', PhotoController::class)
    ->middlewareFor('show', 'auth')
    ->middlewareFor(['create', 'store', 'update'], 'auth');

Route::singleton('profile', ProfileController::class)
    ->middlewareFor('show', 'auth');
```

`withoutMiddleware` and `withoutMiddlewareFor` remove middleware a group applied:

```php
Route::middleware(['auth', 'verified', 'subscribed'])->group(function () {
    Route::resource('photos', PhotoController::class)
        ->withoutMiddlewareFor('index', ['auth', 'verified'])
        ->withoutMiddlewareFor(['create', 'store'], 'verified')
        ->withoutMiddleware(['subscribed']);
});
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
