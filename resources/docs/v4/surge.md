# LaraGram Surge

<a name="introduction"></a>
## Introduction

[LaraGram Surge](https://github.com/laragram/surge) supercharges your application's performance by serving your application using high-powered application servers, including [Open Swoole](https://openswoole.com/) and [Swoole](https://github.com/swoole/swoole-src). Surge boots your application once, keeps it in memory, and then feeds it requests at supersonic speeds.

<a name="installation"></a>
## Installation

Surge may be installed via the Composer package manager:

```shell
composer require laraxgram/surge
```

After installing Surge, you may execute the `surge:install` Commander command, which will install Surge's configuration file into your application:

```shell
php laragram surge:install
```

<a name="server-prerequisites"></a>
## Server Prerequisites

<a name="swoole"></a>
### Swoole

If you plan to use the Swoole application server to serve your LaraGram Surge application, you must install the Swoole PHP extension. Typically, this can be done via PECL:

```shell
pecl install swoole
```

<a name="openswoole"></a>
#### Open Swoole

If you want to use the Open Swoole application server to serve your LaraGram Surge application, you must install the Open Swoole PHP extension. Typically, this can be done via PECL:

```shell
pecl install openswoole
```

Using LaraGram Surge with Open Swoole grants the same functionality provided by Swoole, such as concurrent tasks, ticks, and intervals.

<a name="swoole-configuration"></a>
#### Swoole Configuration

Swoole supports a few additional configuration options that you may add to your `surge` configuration file if necessary. Because they rarely need to be modified, these options are not included in the default configuration file:

```php
'swoole' => [
    'options' => [
        'log_file' => storage_path('logs/swoole_http.log'),
        'package_max_length' => 10 * 1024 * 1024,
    ],
],
```

<a name="serving-your-application"></a>
## Serving Your Application

The Surge server can be started via the `surge:start` Commander command. By default, this command will utilize the server specified by the `server` configuration option of your application's `surge` configuration file:

```shell
php laragram surge:start
```

By default, Surge will start the server on port 9000, so you may access your application in via `http://localhost:9000`.

<a name="serving-your-application-via-https"></a>
### Serving Your Application via HTTPS

By default, applications running via Surge generate links prefixed with `http://`. The `SURGE_HTTPS` environment variable, used within your application's `config/surge.php` configuration file, can be set to `true` when serving your application via HTTPS. When this configuration value is set to `true`, Surge will instruct LaraGram to prefix all generated links with `https://`:

```php
'https' => env('SURGE_HTTPS', false),
```

<a name="serving-your-application-via-nginx"></a>
### Serving Your Application via Nginx

In production environments, you should serve your Surge application behind a traditional web server such as Nginx or Apache. Doing so will allow the web server to serve your static assets such as images and stylesheets, as well as manage your SSL certificate termination.

In the Nginx configuration example below, Nginx will serve the site's static assets and proxy requests to the Surge server that is running on port 9000:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 80;
    listen [::]:80;
    server_name domain.com;
    server_tokens off;
    root /home/forge/domain.com/public;

    index index.php;

    charset utf-8;

    location /index.php {
        try_files /not_exists @surge;
    }

    location / {
        try_files $uri $uri/ @surge;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log off;
    error_log  /var/log/nginx/domain.com-error.log error;

    error_page 404 /index.php;

    location @surge {
        set $suffix "";

        if ($uri = /index.php) {
            set $suffix ?$query_string;
        }

        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;

        proxy_pass http://127.0.0.1:9000$suffix;
    }
}
```

<a name="watching-for-file-changes"></a>
### Watching for File Changes

Since your application is loaded in memory once when the Surge server starts, any changes to your application's files will not be reflected when you refresh your browser. For example, listen definitions added to your `listens/bot.php` file will not be reflected until the server is restarted. For convenience, you may use the `--watch` flag to instruct Surge to automatically restart the server on any file changes within your application:

```shell
php laragram surge:start --watch
```

Before using this feature, you should ensure that [Node](https://nodejs.org) is installed within your local development environment. In addition, you should install the [Chokidar](https://github.com/paulmillr/chokidar) file-watching library within your project:

```shell
npm install --save-dev chokidar
```

You may configure the directories and files that should be watched using the `watch` configuration option within your application's `config/surge.php` configuration file.

<a name="specifying-the-worker-count"></a>
### Specifying the Worker Count

By default, Surge will start an application request worker for each CPU core provided by your machine. These workers will then be used to serve incoming HTTP requests as they enter your application. You may manually specify how many workers you would like to start using the `--workers` option when invoking the `surge:start` command:

```shell
php laragram surge:start --workers=4
```

If you are using the Swoole application server, you may also specify how many ["task workers"](#concurrent-tasks) you wish to start:

```shell
php laragram surge:start --workers=4 --task-workers=6
```

<a name="specifying-the-max-request-count"></a>
### Specifying the Max Request Count

To help prevent stray memory leaks, Surge gracefully restarts any worker once it has handled 500 requests. To adjust this number, you may use the `--max-requests` option:

```shell
php laragram surge:start --max-requests=250
```

<a name="reloading-the-workers"></a>
### Reloading the Workers

You may gracefully restart the Surge server's application workers using the `surge:reload` command. Typically, this should be done after deployment so that your newly deployed code is loaded into memory and is used to serve to subsequent requests:

```shell
php laragram surge:reload
```

<a name="stopping-the-server"></a>
### Stopping the Server

You may stop the Surge server using the `surge:stop` Commander command:

```shell
php laragram surge:stop
```

<a name="checking-the-server-status"></a>
#### Checking the Server Status

You may check the current status of the Surge server using the `surge:status` Commander command:

```shell
php laragram surge:status
```

<a name="dependency-injection-and-surge"></a>
## Dependency Injection and Surge

Since Surge boots your application once and keeps it in memory while serving requests, there are a few caveats you should consider while building your application. For example, the `register` and `boot` methods of your application's service providers will only be executed once when the request worker initially boots. On subsequent requests, the same application instance will be reused.

In light of this, you should take special care when injecting the application service container or request into any object's constructor. By doing so, that object may have a  stale version of the container or request on subsequent requests.

Surge will automatically handle resetting any first-party framework state between requests. However, Surge does not always know how to reset the global state created by your application. Therefore, you should be aware of how to build your application in a way that is Surge friendly. Below, we will discuss the most common situations that may cause problems while using Surge.

<a name="container-injection"></a>
### Container Injection

In general, you should avoid injecting the application service container or HTTP request instance into the constructors of other objects. For example, the following binding injects the entire application service container into an object that is bound as a singleton:

```php
use App\Service;
use LaraGram\Contracts\Foundation\Application;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(Service::class, function (Application $app) {
        return new Service($app);
    });
}
```

In this example, if the `Service` instance is resolved during the application boot process, the container will be injected into the service and that same container will be held by the `Service` instance on subsequent requests. This **may** not be a problem for your particular application; however, it can lead to the container unexpectedly missing bindings that were added later in the boot cycle or by a subsequent request.

As a work-around, you could either stop registering the binding as a singleton, or you could inject a container resolver closure into the service that always resolves the current container instance:

```php
use App\Service;
use LaraGram\Container\Container;
use LaraGram\Contracts\Foundation\Application;

$this->app->bind(Service::class, function (Application $app) {
    return new Service($app);
});

$this->app->singleton(Service::class, function () {
    return new Service(fn () => Container::getInstance());
});
```

The global `app` helper and the `Container::getInstance()` method will always return the latest version of the application container.

<a name="request-injection"></a>
### Request Injection

In general, you should avoid injecting the application service container or HTTP request instance into the constructors of other objects. For example, the following binding injects the entire request instance into an object that is bound as a singleton:

```php
use App\Service;
use LaraGram\Contracts\Foundation\Application;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(Service::class, function (Application $app) {
        return new Service($app['request']);
    });
}
```

In this example, if the `Service` instance is resolved during the application boot process, the HTTP request will be injected into the service and that same request will be held by the `Service` instance on subsequent requests. Therefore, all headers, input, and query string data will be incorrect, as well as all other request data.

As a work-around, you could either stop registering the binding as a singleton, or you could inject a request resolver closure into the service that always resolves the current request instance. Or, the most recommended approach is simply to pass the specific request information your object needs to one of the object's methods at runtime:

```php
use App\Service;
use LaraGram\Contracts\Foundation\Application;

$this->app->bind(Service::class, function (Application $app) {
    return new Service($app['request']);
});

$this->app->singleton(Service::class, function (Application $app) {
    return new Service(fn () => $app['request']);
});

// Or...

$service->method($request->input('name'));
```

The global `request` helper will always return the request the application is currently handling and is therefore safe to use within your application.

> [!WARNING]
> It is acceptable to type-hint the `LaraGram\Request\Request` instance on your controller methods and listen closures.

<a name="configuration-repository-injection"></a>
### Configuration Repository Injection

In general, you should avoid injecting the configuration repository instance into the constructors of other objects. For example, the following binding injects the configuration repository into an object that is bound as a singleton:

```php
use App\Service;
use LaraGram\Contracts\Foundation\Application;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(Service::class, function (Application $app) {
        return new Service($app->make('config'));
    });
}
```

In this example, if the configuration values change between requests, that service will not have access to the new values because it's depending on the original repository instance.

As a work-around, you could either stop registering the binding as a singleton, or you could inject a configuration repository resolver closure to the class:

```php
use App\Service;
use LaraGram\Container\Container;
use LaraGram\Contracts\Foundation\Application;

$this->app->bind(Service::class, function (Application $app) {
    return new Service($app->make('config'));
});

$this->app->singleton(Service::class, function () {
    return new Service(fn () => Container::getInstance()->make('config'));
});
```

The global `config` will always return the latest version of the configuration repository and is therefore safe to use within your application.

<a name="managing-memory-leaks"></a>
### Managing Memory Leaks

Remember, Surge keeps your application in memory between requests; therefore, adding data to a statically maintained array will result in a memory leak. For example, the following controller has a memory leak since each request to the application will continue to add data to the static `$data` array:

```php
use App\Service;
use LaraGram\Request\Request;
use LaraGram\Support\Str;

/**
 * Handle an incoming request.
 */
public function index(Request $request): array
{
    Service::$data[] = Str::random(10);

    return [
        // ...
    ];
}
```

While building your application, you should take special care to avoid creating these types of memory leaks. It is recommended that you monitor your application's memory usage during local development to ensure you are not introducing new memory leaks into your application.

<a name="concurrent-tasks"></a>
## Concurrent Tasks

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may execute operations concurrently via light-weight background tasks. You may accomplish this using Surge's `concurrently` method. You may combine this method with PHP array destructuring to retrieve the results of each operation:

```php
use App\Models\User;
use App\Models\Server;
use LaraGram\Surge\Facades\Surge;

[$users, $servers] = Surge::concurrently([
    fn () => User::all(),
    fn () => Server::all(),
]);
```

Concurrent tasks processed by Surge utilize Swoole's "task workers", and execute within an entirely different process than the incoming request. The amount of workers available to process concurrent tasks is determined by the `--task-workers` directive on the `surge:start` command:

```shell
php laragram surge:start --workers=4 --task-workers=6
```

When invoking the `concurrently` method, you should not provide more than 1024 tasks due to limitations imposed by Swoole's task system.

<a name="ticks-and-intervals"></a>
## Ticks and Intervals

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may register "tick" operations that will be executed every specified number of seconds. You may register "tick" callbacks via the `tick` method. The first argument provided to the `tick` method should be a string that represents the name of the ticker. The second argument should be a callable that will be invoked at the specified interval.

In this example, we will register a closure to be invoked every 10 seconds. Typically, the `tick` method should be called within the `boot` method of one of your application's service providers:

```php
Surge::tick('simple-ticker', fn () => ray('Ticking...'))
    ->seconds(10);
```

Using the `immediate` method, you may instruct Surge to immediately invoke the tick callback when the Surge server initially boots, and every N seconds thereafter:

```php
Surge::tick('simple-ticker', fn () => ray('Ticking...'))
    ->seconds(10)
    ->immediate();
```

<a name="the-surge-cache"></a>
## The Surge Cache

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may leverage the Surge cache driver, which provides read and write speeds of up to 2 million operations per second. Therefore, this cache driver is an excellent choice for applications that need extreme read / write speeds from their caching layer.

This cache driver is powered by [Swoole tables](https://www.swoole.co.uk/docs/modules/swoole-table). All data stored in the cache is available to all workers on the server. However, the cached data will be flushed when the server is restarted:

```php
Cache::store('surge')->put('framework', 'LaraGram', 30);
```

> [!NOTE]
> The maximum number of entries allowed in the Surge cache may be defined in your application's `surge` configuration file.

<a name="cache-intervals"></a>
### Cache Intervals

In addition to the typical methods provided by LaraGram's cache system, the Surge cache driver features interval based caches. These caches are automatically refreshed at the specified interval and should be registered within the `boot` method of one of your application's service providers. For example, the following cache will be refreshed every five seconds:

```php
use LaraGram\Support\Str;

Cache::store('surge')->interval('random', function () {
    return Str::random(10);
}, seconds: 5);
```

<a name="tables"></a>
## Tables

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may define and interact with your own arbitrary [Swoole tables](https://www.swoole.co.uk/docs/modules/swoole-table). Swoole tables provide extreme performance throughput and the data in these tables can be accessed by all workers on the server. However, the data within them will be lost when the server is restarted.

Tables should be defined within the `tables` configuration array of your application's `surge` configuration file. An example table that allows a maximum of 1000 rows is already configured for you. The maximum size of string columns may be configured by specifying the column size after the column type as seen below:

```php
'tables' => [
    'example:1000' => [
        'name' => 'string:1000',
        'votes' => 'int',
    ],
],
```

To access a table, you may use the `Surge::table` method:

```php
use LaraGram\Surge\Facades\Surge;

Surge::table('example')->set('uuid', [
    'name' => 'Nuno Maduro',
    'votes' => 1000,
]);

return Surge::table('example')->get('uuid');
```

> [!WARNING]
> The column types supported by Swoole tables are: `string`, `int`, and `float`.
