# Authorization

<a name="introduction"></a>
## Introduction

In addition to providing built-in [authentication](/v4/authentication) services, LaraGram also provides a simple way to authorize user actions against a given resource. For example, even though a user is authenticated, they may not be authorized to update or delete certain Eloquent models or database records managed by your application. LaraGram's authorization features provide an easy, organized way of managing these types of authorization checks.

LaraGram provides two primary ways of authorizing actions: [gates](#gates) and [policies](#creating-policies). Think of gates and policies like listens and controllers. Gates provide a simple, closure-based approach to authorization while policies, like controllers, group logic around a particular model or resource. In this documentation, we'll explore gates first and then examine policies.

You do not need to choose between exclusively using gates or exclusively using policies when building an application. Most applications will most likely contain some mixture of gates and policies, and that is perfectly fine! Gates are most applicable to actions that are not related to any model or resource, such as viewing an administrator dashboard. In contrast, policies should be used when you wish to authorize an action for a particular model or resource.

<a name="gates"></a>
## Gates

<a name="writing-gates"></a>
### Writing Gates

> [!WARNING]
> Gates are a great way to learn the basics of LaraGram's authorization features; however, when building robust LaraGram applications you should consider using [policies](#creating-policies) to organize your authorization rules.

Gates are simply closures that determine if a user is authorized to perform a given action. Typically, gates are defined within the `boot` method of the `App\Providers\AppServiceProvider` class using the `Gate` facade. Gates always receive a user instance as their first argument and may optionally receive additional arguments such as a relevant Eloquent model.

In this example, we'll define a gate to determine if a user can update a given `App\Models\Post` model. The gate will accomplish this by comparing the user's `id` against the `user_id` of the user that created the post:

```php
use App\Models\Post;
use App\Models\User;
use LaraGram\Support\Facades\Gate;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Gate::define('update-post', function (User $user, Post $post) {
        return $user->id === $post->user_id;
    });
}
```

Like controllers, gates may also be defined using a class callback array:

```php
use App\Policies\PostPolicy;
use LaraGram\Support\Facades\Gate;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Gate::define('update-post', [PostPolicy::class, 'update']);
}
```

<a name="authorizing-actions-via-gates"></a>
### Authorizing Actions

To authorize an action using gates, you should use the `allows` or `denies` methods provided by the `Gate` facade. Note that you are not required to pass the currently authenticated user to these methods. LaraGram will automatically take care of passing the user into the gate closure. It is typical to call the gate authorization methods within your application's controllers before performing an action that requires authorization:

```php
<?php

namespace App\Controllers;

use App\Models\Post;
use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Gate;

class PostController extends Controller
{
    /**
     * Update the given post.
     */
    public function update(Request $request, Post $post): RedirectResponse
    {
        if (Gate::allows('update-post', $post)) {
            // Update the post...
            
            return to_listen('posts');
        }
    }
}
```

If you would like to determine if a user other than the currently authenticated user is authorized to perform an action, you may use the `forUser` method on the `Gate` facade:

```php
if (Gate::forUser($user)->allows('update-post', $post)) {
    // The user can update the post...
}

if (Gate::forUser($user)->denies('update-post', $post)) {
    // The user can't update the post...
}
```

You may authorize multiple actions at a time using the `any` or `none` methods:

```php
if (Gate::any(['update-post', 'delete-post'], $post)) {
    // The user can update or delete the post...
}

if (Gate::none(['update-post', 'delete-post'], $post)) {
    // The user can't update or delete the post...
}
```

<a name="authorizing-or-throwing-exceptions"></a>
#### Authorizing or Throwing Exceptions

If you would like to attempt to authorize an action and automatically throw an `LaraGram\Auth\Access\AuthorizationException` if the user is not allowed to perform the given action, you may use the `Gate` facade's `authorize` method:

```php
Gate::authorize('update-post', $post);

// The action is authorized...
```

<a name="gates-supplying-additional-context"></a>
#### Supplying Additional Context

The gate methods for authorizing abilities (`allows`, `denies`, `check`, `any`, `none`, `authorize`, `can`, `cannot`) and the authorization [Template directives](#via-temple8-templates) (`@can`, `@cannot`, `@canany`) can receive an array as their second argument. These array elements are passed as parameters to the gate closure, and can be used for additional context when making authorization decisions:

```php
use App\Models\Category;
use App\Models\User;
use LaraGram\Support\Facades\Gate;

Gate::define('create-post', function (User $user, Category $category, bool $pinned) {
    if (! $user->canPublishToGroup($category->group)) {
        return false;
    } elseif ($pinned && ! $user->canPinPosts()) {
        return false;
    }

    return true;
});

if (Gate::check('create-post', [$category, $pinned])) {
    // The user can create the post...
}
```

<a name="gate-responses"></a>
### Gate Responses

So far, we have only examined gates that return simple boolean values. However, sometimes you may wish to return a more detailed response, including an error message. To do so, you may return an `LaraGram\Auth\Access\Response` from your gate:

```php
use App\Models\User;
use LaraGram\Auth\Access\Response;
use LaraGram\Support\Facades\Gate;

Gate::define('edit-settings', function (User $user) {
    return $user->isAdmin
        ? Response::allow()
        : Response::deny('You must be an administrator.');
});
```

Even when you return an authorization response from your gate, the `Gate::allows` method will still return a simple boolean value; however, you may use the `Gate::inspect` method to get the full authorization response returned by the gate:

```php
$response = Gate::inspect('edit-settings');

if ($response->allowed()) {
    // The action is authorized...
} else {
    echo $response->message();
}
```

When using the `Gate::authorize` method, which throws an `AuthorizationException` if the action is not authorized, the error message provided by the authorization response will be propagated to the HTTP response:

```php
Gate::authorize('edit-settings');

// The action is authorized...
```

<a name="intercepting-gate-checks"></a>
### Intercepting Gate Checks

Sometimes, you may wish to grant all abilities to a specific user. You may use the `before` method to define a closure that is run before all other authorization checks:

```php
use App\Models\User;
use LaraGram\Support\Facades\Gate;

Gate::before(function (User $user, string $ability) {
    if ($user->isAdministrator()) {
        return true;
    }
});
```

If the `before` closure returns a non-null result that result will be considered the result of the authorization check.

You may use the `after` method to define a closure to be executed after all other authorization checks:

```php
use App\Models\User;

Gate::after(function (User $user, string $ability, bool|null $result, mixed $arguments) {
    if ($user->isAdministrator()) {
        return true;
    }
});
```

Values returned by `after` closures will not override the result of the authorization check unless the gate or policy returned `null`.

<a name="inline-authorization"></a>
### Inline Authorization

Occasionally, you may wish to determine if the currently authenticated user is authorized to perform a given action without writing a dedicated gate that corresponds to the action. LaraGram allows you to perform these types of "inline" authorization checks via the `Gate::allowIf` and `Gate::denyIf` methods. Inline authorization does not execute any defined ["before" or "after" authorization hooks](#intercepting-gate-checks):

```php
use App\Models\User;
use LaraGram\Support\Facades\Gate;

Gate::allowIf(fn (User $user) => $user->isAdministrator());

Gate::denyIf(fn (User $user) => $user->banned());
```

If the action is not authorized or if no user is currently authenticated, LaraGram will automatically throw an `LaraGram\Auth\Access\AuthorizationException` exception. Instances of `AuthorizationException` are automatically converted to a 403 HTTP response by LaraGram's exception handler.

<a name="creating-policies"></a>
## Creating Policies

<a name="generating-policies"></a>
### Generating Policies

Policies are classes that organize authorization logic around a particular model or resource. For example, if your application is a blog, you may have an `App\Models\Post` model and a corresponding `App\Policies\PostPolicy` to authorize user actions such as creating or updating posts.

You may generate a policy using the `make:policy` Commander command. The generated policy will be placed in the `app/Policies` directory. If this directory does not exist in your application, LaraGram will create it for you:

```shell
php laragram make:policy PostPolicy
```

The `make:policy` command will generate an empty policy class. If you would like to generate a class with example policy methods related to viewing, creating, updating, and deleting the resource, you may provide a `--model` option when executing the command:

```shell
php laragram make:policy PostPolicy --model=Post
```

<a name="registering-policies"></a>
### Registering Policies

<a name="policy-discovery"></a>
#### Policy Discovery

By default, LaraGram automatically discover policies as long as the model and policy follow standard LaraGram naming conventions. Specifically, the policies must be in a `Policies` directory at or above the directory that contains your models. So, for example, the models may be placed in the `app/Models` directory while the policies may be placed in the `app/Policies` directory. In this situation, LaraGram will check for policies in `app/Models/Policies` then `app/Policies`. In addition, the policy name must match the model name and have a `Policy` suffix. So, a `User` model would correspond to a `UserPolicy` policy class.

If you would like to define your own policy discovery logic, you may register a custom policy discovery callback using the `Gate::guessPolicyNamesUsing` method. Typically, this method should be called from the `boot` method of your application's `AppServiceProvider`:

```php
use LaraGram\Support\Facades\Gate;

Gate::guessPolicyNamesUsing(function (string $modelClass) {
    // Return the name of the policy class for the given model...
});
```

<a name="manually-registering-policies"></a>
#### Manually Registering Policies

Using the `Gate` facade, you may manually register policies and their corresponding models within the `boot` method of your application's `AppServiceProvider`:

```php
use App\Models\Order;
use App\Policies\OrderPolicy;
use LaraGram\Support\Facades\Gate;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Gate::policy(Order::class, OrderPolicy::class);
}
```

Alternatively, you may place the `UsePolicy` attribute on a model class to inform LaraGram of the model's corresponding policy:

```php
<?php

namespace App\Models;

use App\Policies\OrderPolicy;
use LaraGram\Database\Eloquent\Attributes\UsePolicy;
use LaraGram\Database\Eloquent\Model;

#[UsePolicy(OrderPolicy::class)]
class Order extends Model
{
    //
}
```

<a name="writing-policies"></a>
## Writing Policies

<a name="policy-methods"></a>
### Policy Methods

Once the policy class has been registered, you may add methods for each action it authorizes. For example, let's define an `update` method on our `PostPolicy` which determines if a given `App\Models\User` can update a given `App\Models\Post` instance.

The `update` method will receive a `User` and a `Post` instance as its arguments, and should return `true` or `false` indicating whether the user is authorized to update the given `Post`. So, in this example, we will verify that the user's `id` matches the `user_id` on the post:

```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine if the given post can be updated by the user.
     */
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
```

You may continue to define additional methods on the policy as needed for the various actions it authorizes. For example, you might define `view` or `delete` methods to authorize various `Post` related actions, but remember you are free to give your policy methods any name you like.

If you used the `--model` option when generating your policy via the Commander console, it will already contain methods for the `viewAny`, `view`, `create`, `update`, `delete`, `restore`, and `forceDelete` actions.

> [!NOTE]
> All policies are resolved via the LaraGram [service container](/v4/container), allowing you to type-hint any needed dependencies in the policy's constructor to have them automatically injected.

<a name="policy-responses"></a>
### Policy Responses

So far, we have only examined policy methods that return simple boolean values. However, sometimes you may wish to return a more detailed response, including an error message. To do so, you may return an `LaraGram\Auth\Access\Response` instance from your policy method:

```php
use App\Models\Post;
use App\Models\User;
use LaraGram\Auth\Access\Response;

/**
 * Determine if the given post can be updated by the user.
 */
public function update(User $user, Post $post): Response
{
    return $user->id === $post->user_id
        ? Response::allow()
        : Response::deny('You do not own this post.');
}
```

When returning an authorization response from your policy, the `Gate::allows` method will still return a simple boolean value; however, you may use the `Gate::inspect` method to get the full authorization response returned by the gate:

```php
use LaraGram\Support\Facades\Gate;

$response = Gate::inspect('update', $post);

if ($response->allowed()) {
    // The action is authorized...
} else {
    echo $response->message();
}
```

When using the `Gate::authorize` method, which throws an `AuthorizationException` if the action is not authorized, the error message provided by the authorization response will be propagated to the HTTP response:

```php
Gate::authorize('update', $post);

// The action is authorized...
```

<a name="methods-without-models"></a>
### Methods Without Models

Some policy methods only receive an instance of the currently authenticated user. This situation is most common when authorizing `create` actions. For example, if you are creating a blog, you may wish to determine if a user is authorized to create any posts at all. In these situations, your policy method should only expect to receive a user instance:

```php
/**
 * Determine if the given user can create posts.
 */
public function create(User $user): bool
{
    return $user->role == 'writer';
}
```

<a name="policy-filters"></a>
### Policy Filters

For certain users, you may wish to authorize all actions within a given policy. To accomplish this, define a `before` method on the policy. The `before` method will be executed before any other methods on the policy, giving you an opportunity to authorize the action before the intended policy method is actually called. This feature is most commonly used for authorizing application administrators to perform any action:

```php
use App\Models\User;

/**
 * Perform pre-authorization checks.
 */
public function before(User $user, string $ability): bool|null
{
    if ($user->isAdministrator()) {
        return true;
    }

    return null;
}
```

If you would like to deny all authorization checks for a particular type of user then you may return `false` from the `before` method. If `null` is returned, the authorization check will fall through to the policy method.

> [!WARNING]
> The `before` method of a policy class will not be called if the class doesn't contain a method with a name matching the name of the ability being checked.

<a name="authorizing-actions-using-policies"></a>
## Authorizing Actions Using Policies

<a name="via-the-user-model"></a>
### Via the User Model

The `App\Models\User` model that is included with your LaraGram application includes two helpful methods for authorizing actions: `can` and `cannot`. The `can` and `cannot` methods receive the name of the action you wish to authorize and the relevant model. For example, let's determine if a user is authorized to update a given `App\Models\Post` model. Typically, this will be done within a controller method:

```php
<?php

namespace App\Controllers;

use App\Models\Post;
use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;

class PostController extends Controller
{
    /**
     * Update the given post.
     */
    public function update(Request $request, Post $post): RedirectResponse
    {
        if ($request->user()->cannot('update', $post)) {
            // handle ...
        }

        // Update the post...

        return to_lsiten('posts');
    }
}
```

If a [policy is registered](#registering-policies) for the given model, the `can` method will automatically call the appropriate policy and return the boolean result. If no policy is registered for the model, the `can` method will attempt to call the closure-based Gate matching the given action name.

<a name="user-model-actions-that-dont-require-models"></a>
#### Actions That Don't Require Models

Remember, some actions may correspond to policy methods like `create` that do not require a model instance. In these situations, you may pass a class name to the `can` method. The class name will be used to determine which policy to use when authorizing the action:

```php
<?php

namespace App\Controllers;

use App\Models\Post;
use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;

class PostController extends Controller
{
    /**
     * Create a post.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->cannot('create', Post::class)) {
            // handle
        }

        // Create the post...

        return to_lsiten('posts');
    }
}
```

<a name="via-the-gate-facade"></a>
### Via the `Gate` Facade

In addition to helpful methods provided to the `App\Models\User` model, you can always authorize actions via the `Gate` facade's `authorize` method.

Like the `can` method, this method accepts the name of the action you wish to authorize and the relevant model. If the action is not authorized, the `authorize` method will throw an `LaraGram\Auth\Access\AuthorizationException` exception which the LaraGram exception handler will automatically convert to an HTTP response with a 403 status code:

```php
<?php

namespace App\Controllers;

use App\Models\Post;
use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Gate;

class PostController extends Controller
{
    /**
     * Update the given blog post.
     *
     * @throws \LaraGram\Auth\Access\AuthorizationException
     */
    public function update(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        // The current user can update the blog post...

        return to_lsiten('posts');
    }
}
```

<a name="controller-actions-that-dont-require-models"></a>
#### Actions That Don't Require Models

As previously discussed, some policy methods like `create` do not require a model instance. In these situations, you should pass a class name to the `authorize` method. The class name will be used to determine which policy to use when authorizing the action:

```php
use App\Models\Post;
use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Gate;

/**
 * Create a new blog post.
 *
 * @throws \LaraGram\Auth\Access\AuthorizationException
 */
public function create(Request $request): RedirectResponse
{
    Gate::authorize('create', Post::class);

    // The current user can create blog posts...

    return to_lsiten('posts');
}
```

<a name="via-middleware"></a>
### Via Middleware

LaraGram includes a middleware that can authorize actions before the incoming request even reaches your listens or controllers. By default, the `LaraGram\Auth\Middleware\Authorize` middleware may be attached to a listen using the `can` [middleware alias](/v4/middleware#middleware-aliases), which is automatically registered by LaraGram. Let's explore an example of using the `can` middleware to authorize that a user can update a post:

```php
use App\Models\Post;

Bot::onText('edit {post}', function (Post $post) {
    // The current user may update the post...
})->middleware('can:update,post');
```

In this example, we're passing the `can` middleware two arguments. The first is the name of the action we wish to authorize and the second is the listen parameter we wish to pass to the policy method. In this case, since we are using [implicit model binding](/v4/listening#implicit-binding), an `App\Models\Post` model will be passed to the policy method. If the user is not authorized to perform the given action, an HTTP response with a 403 status code will be returned by the middleware.

For convenience, you may also attach the `can` middleware to your listen using the `can` method:

```php
use App\Models\Post;

Bot::onText('edit {post}', function (Post $post) {
    // The current user may update the post...
})->can('update', 'post');
```

<a name="middleware-actions-that-dont-require-models"></a>
#### Actions That Don't Require Models

Again, some policy methods like `create` do not require a model instance. In these situations, you may pass a class name to the middleware. The class name will be used to determine which policy to use when authorizing the action:

```php
Bot::onText('post', function () {
    // The current user may create posts...
})->middleware('can:create,App\Models\Post');
```

Specifying the entire class name within a string middleware definition can become cumbersome. For that reason, you may choose to attach the `can` middleware to your listen using the `can` method:

```php
use App\Models\Post;

Bot::onText('post', function () {
    // The current user may create posts...
})->can('create', Post::class);
```

<a name="via-temple8-templates"></a>
### Via Template Templates

When writing Template templates, you may wish to display a portion of the page only if the user is authorized to perform a given action. For example, you may wish to show an update form for a blog post only if the user can actually update the post. In this situation, you may use the `@can` and `@cannot` directives:

```blade
@can('update', $post)
    <!-- The current user can update the post... -->
@elsecan('create', App\Models\Post::class)
    <!-- The current user can create new posts... -->
@else
    <!-- ... -->
@endcan

@cannot('update', $post)
    <!-- The current user cannot update the post... -->
@elsecannot('create', App\Models\Post::class)
    <!-- The current user cannot create new posts... -->
@endcannot
```

These directives are convenient shortcuts for writing `@if` and `@unless` statements. The `@can` and `@cannot` statements above are equivalent to the following statements:

```blade
@if (Auth::user()->can('update', $post))
    <!-- The current user can update the post... -->
@endif

@unless (Auth::user()->can('update', $post))
    <!-- The current user cannot update the post... -->
@endunless
```

You may also determine if a user is authorized to perform any action from a given array of actions. To accomplish this, use the `@canany` directive:

```blade
@canany(['update', 'view', 'delete'], $post)
    <!-- The current user can update, view, or delete the post... -->
@elsecanany(['create'], \App\Models\Post::class)
    <!-- The current user can create a post... -->
@endcanany
```

<a name="blade-actions-that-dont-require-models"></a>
#### Actions That Don't Require Models

Like most of the other authorization methods, you may pass a class name to the `@can` and `@cannot` directives if the action does not require a model instance:

```blade
@can('create', App\Models\Post::class)
    <!-- The current user can create posts... -->
@endcan

@cannot('create', App\Models\Post::class)
    <!-- The current user can't create posts... -->
@endcannot
```

<a name="supplying-additional-context"></a>
### Supplying Additional Context

When authorizing actions using policies, you may pass an array as the second argument to the various authorization functions and helpers. The first element in the array will be used to determine which policy should be invoked, while the rest of the array elements are passed as parameters to the policy method and can be used for additional context when making authorization decisions. For example, consider the following `PostPolicy` method definition which contains an additional `$category` parameter:

```php
/**
 * Determine if the given post can be updated by the user.
 */
public function update(User $user, Post $post, int $category): bool
{
    return $user->id === $post->user_id &&
           $user->canUpdateCategory($category);
}
```

When attempting to determine if the authenticated user can update a given post, we can invoke this policy method like so:

```php
/**
 * Update the given blog post.
 *
 * @throws \LaraGram\Auth\Access\AuthorizationException
 */
public function update(Request $request, Post $post): RedirectResponse
{
    Gate::authorize('update', [$post, $request->category]);

    // The current user can update the blog post...

    return to_lsiten('posts');
}
```

<a name="check-user-status"></a>
## Check User Status

Beyond models and policies, LaraGram can authorize a listen against a user's **Telegram chat-member status**. The six Telegram statuses — `creator`, `administrator` `member`, `restricted`, `left` and `kicked` — are registered as gates, so you can authorize them like any other ability:

```php
Bot::onText('ban', function () {
    // The current user is an administrator of this chat...
})->can('administrator');
```

When a status name is passed to a gate and no policy model is supplied, the authorization is resolved by the configured **status driver** rather than a gate closure. The framework is never coupled to your `User` model for this — the driver decides where the status comes from.

You may require any one of several statuses (OR semantics). The check passes as soon as one of them matches:

```php
Bot::onText('promote {user}', function () {
    // The current user is an administrator OR the creator...
})->can(['administrator', 'creator']);
```

<a name="status-drivers"></a>

### Status Drivers

The strategy used to resolve a user's status is configured in the `status` section of your `config/auth.php` file. The active driver is set with `default`, and each driver is configured under `drivers`:

```php
'status' => [

    'default' => env('AUTH_STATUS_DRIVER', 'live'),

    'observe' => env('AUTH_STATUS_OBSERVE', null),

    'drivers' => [

        'live' => [
            'driver' => 'live',
        ],

        'eloquent' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
            'status_column' => 'status',
            'user_column' => 'user_id',
            'chat_column' => 'chat_id',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('AUTH_STATUS_CONNECTION'),
            'table' => 'users',
            'status_column' => 'status',
            'user_column' => 'user_id',
            'chat_column' => 'chat_id',
        ],

        'cache' => [
            'driver' => 'cache',
            'store' => env('AUTH_STATUS_CACHE_STORE'),
            'prefix' => 'chat_status',
            'ttl' => env('AUTH_STATUS_CACHE_TTL', 3600),
        ],

    ],

],
```

LaraGram ships with four drivers:

<div class="content-list" markdown="1">

- `live` — asks Telegram via a `getChatMember` request on demand. The result is cached for the duration of the current update, so multiple status checks in one request only cost a single API call. Always fresh, requires no storage and no migrations. This is the default.
- `eloquent` — reads the status from an Eloquent model. The model and the `status` / `user_id` / `chat_id` columns are fully configurable, so you are not tied to `App\Models\User` or to a column named `status`.
- `database` — reads the status straight from a database table using the query builder, with the same configurable columns.
- cache — reads the status from any cache store (`redis`, `memcached`, `file`, `array`, ...). Ideal for a fast, disposable mirror. `prefix` and `ttl` control the cache key and lifetime (`ttl` of `null` stores forever).

</div>

> [!NOTE]
> The `live` driver requires no `users` table. If you choose `eloquent` or `database`, make sure your table holds the configured `status`, `user_id` and `chat_id` columns.

Because each `drivers` entry names its underlying type with a `driver` key, you may register several named configurations of the same type and switch between them with `default` — for example a `redis`-backed cache mirror:

```php
'default' => 'fast',

'drivers' => [
    'fast' => [
        'driver' => 'cache',
        'store' => 'redis',
        'prefix' => 'chat_status',
        'ttl' => 1800,
    ],
],
```

<a name="observing-status"></a>

### Observing Status

LaraGram registers two listeners — for `chat_member` and `my_chat_member` updates — that write the new status back through the active driver whenever a user's status changes in a group or channel. This keeps your mirror in sync automatically.

Observing is controlled by the `status.observe` option:

<div class="content-list" markdown="1">

- `null` (default) — automatic. Every writable driver (`eloquent`, `database`, `cache`) observes, while the read-only `live` driver does not.
- `true` — always observe, regardless of driver.
- `false` — never observe. Useful when the mirror is populated elsewhere (a cron job, an admin panel, or another service that owns the writes).

</div>

So selecting the `eloquent` driver is enough to start keeping the database in sync — no extra configuration required.

> [!WARNING]
> The `chat_member` update is only delivered when your bot is an administrator of the chat and the update type is included in your `allowed_updates`.

<a name="custom-status-drivers"></a>

### Custom Status Drivers

If none of the built-in drivers fit, you may register your own. Implement the `LaraGram\Contracts\Auth\StatusProvider` contract:

```php
<?php

namespace LaraGram\Contracts\Auth;

interface StatusProvider
{
    public function get($userId, $chatId);

    public function put($userId, $chatId, array $attributes);
}
```

`get` returns the user's status string (or `null`), and `put` persists a status change (a read-only driver may leave it empty). Register the driver type with the status manager's `extend` method, typically from the `boot` method of your `AppServiceProvider`. The closure receives the container and the driver's configuration array:

```php
use App\Status\MongoStatusProvider;

app('auth.status')->extend('mongo', function ($app, array $config) {
    return new MongoStatusProvider($app->make('mongo.connection'), $config);
});
```

Then point a driver configuration at your new type:

```php
'drivers' => [
    'users' => [
        'driver' => 'mongo',
        // ...any options your provider needs...
    ],
],
```

<a name="checking-status-manually"></a>

### Checking Status Manually

You may resolve a status outside of a listen through the `auth.status` manager. It resolves the current user and chat from the incoming update by default, but you may pass an explicit user and chat id:

```php
$manager = app('auth.status');

// Does the current user hold the given status?
if ($manager->is('administrator')) {
    // ...
}

// The raw status of a specific user in a specific chat...
$status = $manager->statusFor($userId, $chatId);

// Persist a status change through the active driver...
$manager->record($userId, $chatId, ['status' => 'member']);
```
