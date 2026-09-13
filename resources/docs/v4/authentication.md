# Authentication

<a name="introduction"></a>
## Introduction

LaraGram gives you the tools to authenticate users in two settings from a single, unified system:

- **Bot updates**, where a user is identified by their Telegram `user_id` on every incoming update — no password, no login form.
- **[Web](/v4/routing) requests**, where users log in through a browser using credentials, and their state is kept in the [session](/v4/session).

At its core, LaraGram's authentication facilities are made up of "guards" and "providers". Guards define how users are authenticated for each request. LaraGram ships with a `bot` guard, which identifies the user from the incoming Telegram update, and a `session` guard, which maintains state for web requests using session storage and cookies.

Providers define how users are retrieved from your persistent storage. LaraGram ships with support for retrieving users using [Eloquent](/v4/eloquent) and the database query builder. However, you are free to define additional providers as needed for your application.

Your application's authentication configuration file is located at `config/auth.php`. This file contains several well-documented options for tweaking the behavior of LaraGram's authentication services, including the default guards and providers:

```php
'guards' => [
    'bot' => [
        'driver' => 'bot',
        'provider' => 'users',
    ],

    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => env('AUTH_MODEL', App\Models\User::class),
        'column' => 'user_id',
    ],
],
```

Notice the provider's `column` option: it tells LaraGram which column on the users table holds the Telegram user id used by the `bot` guard.

> [!NOTE]
> Guards and providers should not be confused with "roles" and "permissions". To learn more about authorizing user actions via permissions, please refer to the [authorization](/v4/authorization) documentation.

<a name="starter-kits"></a>
### Starter Kits

Want to get started fast? Install a [LaraGram application starter kit](/v4/starter-kits) in a fresh LaraGram application. The starter kits take care of scaffolding your entire authentication system, so you can examine the generated controllers, routes, and views to learn how LaraGram's authentication features fit together.

<a name="introduction-database-considerations"></a>
### Database Considerations

By default, LaraGram includes an `App\Models\User` [Eloquent model](/v4/eloquent) in your `app/Models` directory. This model may be used with the default Eloquent authentication driver.

The `users` table stores each user's Telegram `user_id`, which the `bot` guard uses to identify the authenticated user on incoming updates.

If you also authenticate users through the web, make sure the `password` column on your `users` table is at least 60 characters in length. Of course, the `users` table migration that is included in new LaraGram applications already creates a column that exceeds this length.

You should also verify that your `users` table contains a nullable, string `remember_token` column of 100 characters. This column will be used to store a token for users that select the "remember me" option when logging into your application.

If your application resolves Telegram chat-member status (used by [authorization](/v4/authorization) helpers such as `->can('administrator')`) from the database, make sure the `status` column is at least 15 characters in length.

If your application is not using Eloquent, you may use the `database` authentication provider which uses the LaraGram query builder.

<a name="bot-authentication"></a>
## Bot Authentication

When your bot handles an incoming update, the `bot` guard automatically identifies the user by the Telegram `user_id` on the update and retrieves the matching `App\Models\User` record through the configured provider. There is no login step — the user is simply "authenticated" for the duration of the update.

Inside any listen or controller, you may access the current user through the `Auth` facade or the request's `user` method:

```php
use LaraGram\Support\Facades\Auth;
use LaraGram\Support\Facades\Bot;

Bot::onText('profile', function () {
    $user = Auth::user();

    // ...
});
```

Because the `bot` guard is the default guard while handling updates, `Auth::user()`, `Auth::id()`, and `Auth::check()` all just work without any configuration. If no matching user record exists, `Auth::user()` returns `null`.

<a name="authentication-quickstart"></a>
## Authentication Quickstart

> [!WARNING]
> The remainder of this documentation covers **web** authentication — logging users in through a browser via credentials. This applies to the [Web](/v4/routing) side of your LaraGram application. If you only build bot listens, the [Bot Authentication](#bot-authentication) section above is all you need.

<a name="retrieving-the-authenticated-user"></a>
### Retrieving the Authenticated User

While handling an incoming request, you may access the authenticated user via the `Auth` facade's `user` method:

```php
use LaraGram\Support\Facades\Auth;

// Retrieve the currently authenticated user...
$user = Auth::user();

// Retrieve the currently authenticated user's ID...
$id = Auth::id();
```

Alternatively, once a user is authenticated, you may access the authenticated user via a `LaraGram\Request\Request` instance. Remember, type-hinted classes will automatically be injected into your controller methods. By type-hinting the `LaraGram\Request\Request` object, you may gain convenient access to the authenticated user from any controller method in your application via the request's `user` method:

```php
<?php

namespace App\Controllers;

use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;

class FlightController extends Controller
{
    /**
     * Update the flight information for an existing flight.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // ...

        return redirect('/flights');
    }
}
```

<a name="determining-if-the-current-user-is-authenticated"></a>
#### Determining if the Current User is Authenticated

To determine if the user making the incoming request is authenticated, you may use the `check` method on the `Auth` facade. This method will return `true` if the user is authenticated:

```php
use LaraGram\Support\Facades\Auth;

if (Auth::check()) {
    // The user is logged in...
}
```

> [!NOTE]
> Even though it is possible to determine if a user is authenticated using the `check` method, you will typically use a middleware to verify that the user is authenticated before allowing the user access to certain routes / controllers. To learn more about this, check out the documentation on [protecting routes](#protecting-routes).

<a name="protecting-routes"></a>
### Protecting Routes

[Route middleware](/v4/middleware) can be used to only allow authenticated users to access a given route. LaraGram ships with an `auth` middleware, which is a middleware alias for the `LaraGram\Auth\Middleware\Authenticate` class. Since this middleware is already aliased internally by LaraGram, all you need to do is attach the middleware to a route definition:

```php
Route::get('/flights', function () {
    // Only authenticated users may access this route...
})->middleware('auth');
```

<a name="redirecting-unauthenticated-users"></a>
#### Redirecting Unauthenticated Users

When the `auth` middleware detects an unauthenticated user, it will redirect the user to the `login` [named route](/v4/routing#named-routes). You may modify this behavior using the `redirectGuestsTo` method within your application's `bootstrap/app.php` file:

```php
use LaraGram\Request\Request;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo('/login');

    // Using a closure...
    $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
})
```

<a name="specifying-a-guard"></a>
#### Specifying a Guard

When attaching the `auth` middleware to a route, you may also specify which "guard" should be used to authenticate the user. The guard specified should correspond to one of the keys in the `guards` array of your `auth.php` configuration file:

```php
Route::get('/flights', function () {
    // Only authenticated users may access this route...
})->middleware('auth:admin');
```

<a name="login-throttling"></a>
### Login Throttling

If you are using one of our [application starter kits](/v4/starter-kits), rate limiting will automatically be applied to login attempts. By default, the user will not be able to login for one minute if they fail to provide the correct credentials after several attempts. The throttling is unique to the user's username / email address and their IP address.

> [!NOTE]
> If you would like to rate limit other routes in your application, check out the [rate limiting documentation](/v4/rate-limiting).

<a name="authenticating-users"></a>
## Manually Authenticating Users

You are not required to use the authentication scaffolding included with LaraGram's [application starter kits](/v4/starter-kits). If you choose not to use this scaffolding, you will need to manage user authentication using the LaraGram authentication classes directly.

We will access LaraGram's authentication services via the `Auth` [facade](/v4/facades), so we'll need to make sure to import the `Auth` facade at the top of the class. Next, let's check out the `attempt` method. The `attempt` method is normally used to handle authentication attempts from your application's "login" form. If authentication is successful, you should regenerate the user's [session](/v4/session) to prevent [session fixation](https://en.wikipedia.org/wiki/Session_fixation):

```php
<?php

namespace App\Controllers;

use LaraGram\Request\Request;
use LaraGram\Request\RedirectResponse;
use LaraGram\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Handle an authentication attempt.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }
}
```

The `attempt` method accepts an array of key / value pairs as its first argument. The values in the array will be used to find the user in your database table. So, in the example above, the user will be retrieved by the value of the `email` column. If the user is found, the hashed password stored in the database will be compared with the `password` value passed to the method via the array. You should not hash the incoming request's `password` value, since the framework will automatically hash the value before comparing it to the hashed password in the database. An authenticated session will be started for the user if the two hashed passwords match.

Remember, LaraGram's authentication services will retrieve users from your database based on your authentication guard's "provider" configuration. In the default `config/auth.php` configuration file, the Eloquent user provider is specified and it is instructed to use the `App\Models\User` model when retrieving users.

The `attempt` method will return `true` if authentication was successful. Otherwise, `false` will be returned.

The `intended` method provided by LaraGram's redirector will redirect the user to the URL they were attempting to access before being intercepted by the authentication middleware. A fallback URI may be given to this method in case the intended destination is not available.

<a name="specifying-additional-conditions"></a>
#### Specifying Additional Conditions

If you wish, you may also add extra query conditions to the authentication query in addition to the user's email and password. To accomplish this, we may simply add the query conditions to the array passed to the `attempt` method. For example, we may verify that the user is marked as "active":

```php
if (Auth::attempt(['email' => $email, 'password' => $password, 'active' => 1])) {
    // Authentication was successful...
}
```

For complex query conditions, you may provide a closure in your array of credentials. This closure will be invoked with the query instance, allowing you to customize the query based on your application's needs:

```php
use LaraGram\Database\Eloquent\Builder;

if (Auth::attempt([
    'email' => $email,
    'password' => $password,
    fn (Builder $query) => $query->has('activeSubscription'),
])) {
    // Authentication was successful...
}
```

> [!WARNING]
> In these examples, `email` is not a required option, it is merely used as an example. You should use whatever column name corresponds to a "username" in your database table.

The `attemptWhen` method, which receives a closure as its second argument, may be used to perform more extensive inspection of the potential user before actually authenticating the user. The closure receives the potential user and should return `true` or `false` to indicate if the user may be authenticated:

```php
if (Auth::attemptWhen([
    'email' => $email,
    'password' => $password,
], function (User $user) {
    return $user->isNotBanned();
})) {
    // Authentication was successful...
}
```

<a name="accessing-specific-guard-instances"></a>
#### Accessing Specific Guard Instances

Via the `Auth` facade's `guard` method, you may specify which guard instance you would like to utilize when authenticating the user. This allows you to manage authentication for separate parts of your application using entirely separate authenticatable models or user tables.

The guard name passed to the `guard` method should correspond to one of the guards configured in your `auth.php` configuration file:

```php
if (Auth::guard('admin')->attempt($credentials)) {
    // ...
}
```

<a name="remembering-users"></a>
### Remembering Users

Many web applications provide a "remember me" checkbox on their login form. If you would like to provide "remember me" functionality in your application, you may pass a boolean value as the second argument to the `attempt` method.

When this value is `true`, LaraGram will keep the user authenticated indefinitely or until they manually logout. Your `users` table must include the string `remember_token` column, which will be used to store the "remember me" token. The `users` table migration included with new LaraGram applications already includes this column:

```php
use LaraGram\Support\Facades\Auth;

if (Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
    // The user is being remembered...
}
```

If your application offers "remember me" functionality, you may use the `viaRemember` method to determine if the currently authenticated user was authenticated using the "remember me" cookie:

```php
use LaraGram\Support\Facades\Auth;

if (Auth::viaRemember()) {
    // ...
}
```

<a name="other-authentication-methods"></a>
### Other Authentication Methods

<a name="authenticate-a-user-instance"></a>
#### Authenticate a User Instance

If you need to set an existing user instance as the currently authenticated user, you may pass the user instance to the `Auth` facade's `login` method. The given user instance must be an implementation of the `LaraGram\Contracts\Auth\Authenticatable` [contract](/v4/contracts). The `App\Models\User` model included with LaraGram already implements this interface. This method of authentication is useful when you already have a valid user instance, such as directly after a user registers with your application:

```php
use LaraGram\Support\Facades\Auth;

Auth::login($user);
```

You may pass a boolean value as the second argument to the `login` method. This value indicates if "remember me" functionality is desired for the authenticated session. Remember, this means that the session will be authenticated indefinitely or until the user manually logs out of the application:

```php
Auth::login($user, $remember = true);
```

If needed, you may specify an authentication guard before calling the `login` method:

```php
Auth::guard('admin')->login($user);
```

<a name="authenticate-a-user-by-id"></a>
#### Authenticate a User by ID

To authenticate a user using their database record's primary key, you may use the `loginUsingId` method. This method accepts the primary key of the user you wish to authenticate:

```php
Auth::loginUsingId(1);
```

You may pass a boolean value to the `remember` argument of the `loginUsingId` method. This value indicates if "remember me" functionality is desired for the authenticated session:

```php
Auth::loginUsingId(1, remember: true);
```

<a name="authenticate-a-user-once"></a>
#### Authenticate a User Once

You may use the `once` method to authenticate a user with the application for a single request. No sessions or cookies will be utilized when calling this method, and the `Login` event will not be dispatched:

```php
if (Auth::once($credentials)) {
    // ...
}
```

<a name="http-basic-authentication"></a>
## HTTP Basic Authentication

[HTTP Basic Authentication](https://en.wikipedia.org/wiki/Basic_access_authentication) provides a quick way to authenticate users of your application without setting up a dedicated "login" page. To get started, attach the `auth.basic` [middleware](/v4/middleware) to a route. The `auth.basic` middleware is included with the LaraGram framework, so you do not need to define it:

```php
Route::get('/profile', function () {
    // Only authenticated users may access this route...
})->middleware('auth.basic');
```

Once the middleware has been attached to the route, you will automatically be prompted for credentials when accessing the route in your browser. By default, the `auth.basic` middleware will assume the `email` column on your `users` database table is the user's "username".

<a name="stateless-http-basic-authentication"></a>
### Stateless HTTP Basic Authentication

You may also use HTTP Basic Authentication without setting a user identifier cookie in the session. This is primarily helpful if you choose to use HTTP Authentication to authenticate requests to your application's API. To accomplish this, [define a middleware](/v4/middleware) that calls the `onceBasic` method. If no response is returned by the `onceBasic` method, the request may be passed further into the application:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Auth;
use LaraGram\Request\Response;

class AuthenticateOnceWithBasicAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return Auth::onceBasic() ?: $next($request);
    }
}
```

Next, attach the middleware to a route:

```php
Route::get('/api/user', function () {
    // Only authenticated users may access this route...
})->middleware(AuthenticateOnceWithBasicAuth::class);
```

<a name="logging-out"></a>
## Logging Out

To manually log users out of your application, you may use the `logout` method provided by the `Auth` facade. This will remove the authentication information from the user's session so that subsequent requests are not authenticated.

In addition to calling the `logout` method, it is recommended that you invalidate the user's session and regenerate their CSRF token. After logging the user out, you would typically redirect the user to the root of your application:

```php
use LaraGram\Request\Request;
use LaraGram\Request\RedirectResponse;
use LaraGram\Support\Facades\Auth;

/**
 * Log the user out of the application.
 */
public function logout(Request $request): RedirectResponse
{
    Auth::logout();

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect('/');
}
```

<a name="invalidating-sessions-on-other-devices"></a>
### Invalidating Sessions on Other Devices

LaraGram also provides a mechanism for invalidating and "logging out" a user's sessions that are active on other devices without invalidating the session on their current device. This feature is typically utilized when a user is changing or updating their password and you would like to invalidate sessions on other devices while keeping the current device authenticated.

Before getting started, you should make sure that the `LaraGram\Session\Middleware\AuthenticateSession` middleware is included on the routes that should receive session authentication. Typically, you should place this middleware on a route group definition so that it can be applied to the majority of your application's routes. By default, the `AuthenticateSession` middleware may be attached to a route using the `auth.session` middleware alias:

```php
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/', function () {
        // ...
    });
});
```

Then, you may use the `logoutOtherDevices` method provided by the `Auth` facade. This method requires the user to confirm their current password, which your application should accept through an input form:

```php
use LaraGram\Support\Facades\Auth;

Auth::logoutOtherDevices($currentPassword);
```

When the `logoutOtherDevices` method is invoked, the user's other sessions will be invalidated entirely, meaning they will be "logged out" of all guards they were previously authenticated by.

<a name="password-confirmation"></a>
## Password Confirmation

While building your application, you may occasionally have actions that should require the user to confirm their password before the action is performed or before the user is redirected to a sensitive area of the application. LaraGram includes built-in middleware to make this process a breeze. Implementing this feature will require you to define two routes: one route to display a view asking the user to confirm their password and another route to confirm that the password is valid and redirect the user to their intended destination.

> [!NOTE]
> The following documentation discusses how to integrate with LaraGram's password confirmation features directly; however, if you would like to get started more quickly, the [LaraGram application starter kits](/v4/starter-kits) include support for this feature!

<a name="password-confirmation-configuration"></a>
### Configuration

After confirming their password, a user will not be asked to confirm their password again for three hours. However, you may configure the length of time before the user is re-prompted for their password by changing the value of the `password_timeout` configuration value within your application's `config/auth.php` configuration file.

<a name="password-confirmation-routing"></a>
### Routing

<a name="the-password-confirmation-form"></a>
#### The Password Confirmation Form

First, we will define a route to display a view that requests the user to confirm their password:

```php
Route::get('/confirm-password', function () {
    return view('auth.confirm-password');
})->middleware('auth')->name('password.confirm');
```

As you might expect, the view that is returned by this route should have a form containing a `password` field. In addition, feel free to include text within the view that explains that the user is entering a protected area of the application and must confirm their password.

<a name="confirming-the-password"></a>
#### Confirming the Password

Next, we will define a route that will handle the form request from the "confirm password" view. This route will be responsible for validating the password and redirecting the user to their intended destination:

```php
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Hash;

Route::post('/confirm-password', function (Request $request) {
    if (! Hash::check($request->password, $request->user()->password)) {
        return back()->withErrors([
            'password' => ['The provided password does not match our records.']
        ]);
    }

    $request->session()->passwordConfirmed();

    return redirect()->intended();
})->middleware(['auth', 'throttle:6,1']);
```

Before moving on, let's examine this route in more detail. First, the request's `password` field is determined to actually match the authenticated user's password. If the password is valid, we need to inform LaraGram's session that the user has confirmed their password. The `passwordConfirmed` method will set a timestamp in the user's session that LaraGram can use to determine when the user last confirmed their password. Finally, we can redirect the user to their intended destination.

<a name="password-confirmation-protecting-routes"></a>
### Protecting Routes

You should ensure that any route that performs an action which requires recent password confirmation is assigned the `password.confirm` middleware. This middleware is included with the default installation of LaraGram and will automatically store the user's intended destination in the session so that the user may be redirected to that location after confirming their password. After storing the user's intended destination in the session, the middleware will redirect the user to the `password.confirm` [named route](/v4/routing#named-routes):

```php
Route::get('/settings', function () {
    // ...
})->middleware(['password.confirm']);

Route::post('/settings', function () {
    // ...
})->middleware(['password.confirm']);
```

<a name="adding-custom-guards"></a>
## Adding Custom Guards

You may define your own authentication guards using the `extend` method on the `Auth` facade. You should place your call to the `extend` method within a [service provider](/v4/providers). Since LaraGram already ships with an `AppServiceProvider`, we can place the code in that provider:

```php
<?php

namespace App\Providers;

use App\Services\Auth\JwtGuard;
use LaraGram\Contracts\Foundation\Application;
use LaraGram\Support\Facades\Auth;
use LaraGram\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // ...

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::extend('jwt', function (Application $app, string $name, array $config) {
            // Return an instance of LaraGram\Contracts\Auth\Guard...

            return new JwtGuard(Auth::createUserProvider($config['provider']));
        });
    }
}
```

As you can see in the example above, the callback passed to the `extend` method should return an implementation of `LaraGram\Contracts\Auth\Guard`. This interface contains a few methods you will need to implement to define a custom guard. Once your custom guard has been defined, you may reference the guard in the `guards` configuration of your `auth.php` configuration file:

```php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

<a name="closure-request-guards"></a>
### Closure Request Guards

The simplest way to implement a custom, request based authentication system is by using the `Auth::viaRequest` method. This method allows you to quickly define your authentication process using a single closure.

To get started, call the `Auth::viaRequest` method within the `boot` method of your application's `AppServiceProvider`. The `viaRequest` method accepts an authentication driver name as its first argument. This name can be any string that describes your custom guard. The second argument passed to the method should be a closure that receives the incoming request and returns a user instance or, if authentication fails, `null`:

```php
use App\Models\User;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Auth;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Auth::viaRequest('custom-token', function (Request $request) {
        return User::where('token', (string) $request->token)->first();
    });
}
```

Once your custom authentication driver has been defined, you may configure it as a driver within the `guards` configuration of your `auth.php` configuration file:

```php
'guards' => [
    'api' => [
        'driver' => 'custom-token',
    ],
],
```

Finally, you may reference the guard when assigning the authentication middleware to a route:

```php
Route::middleware('auth:api')->group(function () {
    // ...
});
```

<a name="adding-custom-user-providers"></a>
## Adding Custom User Providers

If you are not using a traditional relational database to store your users, you will need to extend LaraGram with your own authentication user provider. We will use the `provider` method on the `Auth` facade to define a custom user provider. The user provider resolver should return an implementation of `LaraGram\Contracts\Auth\UserProvider`:

```php
<?php

namespace App\Providers;

use App\Extensions\MongoUserProvider;
use LaraGram\Contracts\Foundation\Application;
use LaraGram\Support\Facades\Auth;
use LaraGram\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // ...

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('mongo', function (Application $app, array $config) {
            // Return an instance of LaraGram\Contracts\Auth\UserProvider...

            return new MongoUserProvider($app->make('mongo.connection'));
        });
    }
}
```

After you have registered the provider using the `provider` method, you may switch to the new user provider in your `auth.php` configuration file. First, define a `provider` that uses your new driver:

```php
'providers' => [
    'users' => [
        'driver' => 'mongo',
    ],
],
```

Finally, you may reference this provider in your `guards` configuration:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

<a name="the-user-provider-contract"></a>
### The User Provider Contract

`LaraGram\Contracts\Auth\UserProvider` implementations are responsible for fetching a `LaraGram\Contracts\Auth\Authenticatable` implementation out of a persistent storage system, such as MySQL, MongoDB, etc. These two interfaces allow the LaraGram authentication mechanisms to continue functioning regardless of how the user data is stored or what type of class is used to represent the authenticated user.

Let's take a look at the `LaraGram\Contracts\Auth\UserProvider` contract:

```php
<?php

namespace LaraGram\Contracts\Auth;

interface UserProvider
{
    public function retrieveByUserId($identifier);
    public function retrieveById($identifier);
    public function retrieveByToken($identifier, $token);
    public function updateRememberToken(Authenticatable $user, $token);
    public function retrieveByCredentials(array $credentials);
    public function validateCredentials(Authenticatable $user, array $credentials);
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false);
}
```

The `retrieveByUserId` function receives the Telegram `user_id` of the user on an incoming update. The `Authenticatable` implementation matching that id should be retrieved and returned. This is the method the `bot` guard relies on.

The `retrieveById` function typically receives a key representing the user, such as an auto-incrementing ID from a MySQL database. The `Authenticatable` implementation matching the ID should be retrieved and returned by the method.

The `retrieveByToken` function retrieves a user by their unique `$identifier` and "remember me" `$token`, typically stored in a database column like `remember_token`. As with the previous method, the `Authenticatable` implementation with a matching token value should be returned by this method.

The `updateRememberToken` method updates the `$user` instance's `remember_token` with the new `$token`. A fresh token is assigned to users on a successful "remember me" authentication attempt or when the user is logging out.

The `retrieveByCredentials` method receives the array of credentials passed to the `Auth::attempt` method when attempting to authenticate with an application. The method should then "query" the underlying persistent storage for the user matching those credentials. Typically, this method will run a query with a "where" condition that searches for a user record with a "username" matching the value of `$credentials['username']`. The method should return an implementation of `Authenticatable`. **This method should not attempt to do any password validation or authentication.**

The `validateCredentials` method should compare the given `$user` with the `$credentials` to authenticate the user. For example, this method will typically use the `Hash::check` method to compare the value of `$user->getAuthPassword()` to the value of `$credentials['password']`. This method should return `true` or `false` indicating whether the password is valid.

The `rehashPasswordIfRequired` method should rehash the given `$user`'s password if required and supported. For example, this method will typically use the `Hash::needsRehash` method to determine if the `$credentials['password']` value needs to be rehashed. If the password needs to be rehashed, the method should use the `Hash::make` method to rehash the password and update the user's record in the underlying persistent storage.

<a name="the-authenticatable-contract"></a>
### The Authenticatable Contract

Now that we have explored the `UserProvider`, let's take a look at the `Authenticatable` contract. Remember, user providers should return implementations of this interface from the `retrieveByUserId`, `retrieveById`, `retrieveByToken`, and `retrieveByCredentials` methods:

```php
<?php

namespace LaraGram\Contracts\Auth;

interface Authenticatable
{
    public function getAuthIdentifierName();
    public function getAuthIdentifier();
}
```

This interface is simple. The `getAuthIdentifierName` method should return the name of the "primary key" column for the user and the `getAuthIdentifier` method should return the "primary key" of the user.

For web authentication, where a user is authenticated by a password and may be remembered across sessions, your user model should additionally implement the `LaraGram\Contracts\Auth\StatefulAuthenticatable` contract, which adds the password and "remember me" methods:

```php
<?php

namespace LaraGram\Contracts\Auth;

interface StatefulAuthenticatable extends Authenticatable
{
    public function getAuthPasswordName();
    public function getAuthPassword();
    public function getRememberToken();
    public function setRememberToken($value);
    public function getRememberTokenName();
}
```

The `getAuthPasswordName` method should return the name of the user's password column, and `getAuthPassword` should return the user's hashed password. The remaining methods read and write the user's "remember me" token.

This interface allows the authentication system to work with any "user" class, regardless of what ORM or storage abstraction layer you are using. By default, LaraGram includes an `App\Models\User` class in the `app/Models` directory which implements these interfaces.

<a name="automatic-password-rehashing"></a>
## Automatic Password Rehashing

LaraGram's default password hashing algorithm is bcrypt. The "work factor" for bcrypt hashes can be adjusted via your application's `config/hashing.php` configuration file or the `BCRYPT_ROUNDS` environment variable.

Typically, the bcrypt work factor should be increased over time as CPU / GPU processing power increases. If you increase the bcrypt work factor for your application, LaraGram will gracefully and automatically rehash user passwords as users authenticate with your application via LaraGram's starter kits or when you [manually authenticate users](#authenticating-users) via the `attempt` method.

Typically, automatic password rehashing should not disrupt your application; however, you may disable this behavior by publishing the `hashing` configuration file:

```shell
php laragram config:publish hashing
```

Once the configuration file has been published, you may set the `rehash_on_login` configuration value to `false`:

```php
'rehash_on_login' => false,
```

<a name="events"></a>
## Events

LaraGram dispatches a variety of [events](/v4/events) during the authentication process. You may [define listeners](/v4/events) for any of the following events:

<div class="overflow-auto">

| Event Name |
| ---------- |
| `LaraGram\Auth\Events\Registered` |
| `LaraGram\Auth\Events\Attempting` |
| `LaraGram\Auth\Events\Authenticated` |
| `LaraGram\Auth\Events\Login` |
| `LaraGram\Auth\Events\Failed` |
| `LaraGram\Auth\Events\Validated` |
| `LaraGram\Auth\Events\Verified` |
| `LaraGram\Auth\Events\Logout` |
| `LaraGram\Auth\Events\CurrentDeviceLogout` |
| `LaraGram\Auth\Events\OtherDeviceLogout` |
| `LaraGram\Auth\Events\Lockout` |
| `LaraGram\Auth\Events\PasswordReset` |
| `LaraGram\Auth\Events\PasswordResetLinkSent` |

</div>
