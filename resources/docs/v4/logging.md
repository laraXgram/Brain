# Logging

<a name="introduction"></a>
## Introduction

To help you learn more about what's happening within your application, LaraGram provides robust logging services that allow you to log messages to files, the system error log, and even to Slack to notify your entire team.

LaraGram logging is based on "channels". Each channel represents a specific way of writing log information. For example, the `single` channel writes log files to a single log file, while the `slack` channel sends log messages to Slack. Log messages may be written to multiple channels based on their severity.

Under the hood, LaraGram utilizes the custom rewritten [Monolog](https://github.com/Seldaek/monolog) library, which provides support for a variety of powerful log handlers. LaraGram makes it a cinch to configure these handlers, allowing you to mix and match them to customize your application's log handling.

<a name="configuration"></a>
## Configuration

All of the configuration options that control your application's logging behavior are housed in the `config/logging.php` configuration file. This file allows you to configure your application's log channels, so be sure to review each of the available channels and their options. We'll review a few common options below.

By default, LaraGram will use the `stack` channel when logging messages. The `stack` channel is used to aggregate multiple log channels into a single channel. For more information on building stacks, check out the [documentation below](#building-log-stacks).

<a name="available-channel-drivers"></a>
### Available Channel Drivers

Each log channel is powered by a "driver". The driver determines how and where the log message is actually recorded. The following log channel drivers are available in every LaraGram application. An entry for most of these drivers is already present in your application's `config/logging.php` configuration file, so be sure to review this file to become familiar with its contents:

<div class="overflow-auto">

| Name         | Description                                                          |
| ------------ | -------------------------------------------------------------------- |
| `custom`     | A driver that calls a specified factory to create a channel.         |
| `daily`      | A `RotatingFileHandler` based LaraGram driver which rotates daily.    |
| `errorlog`   | An `ErrorLogHandler` based LaraGram driver.                           |
| `single`     | A single file or path based logger channel (`StreamHandler`).        |
| `slack`      | A `SlackWebhookHandler` based LaraGram driver.                        |
| `stack`      | A wrapper to facilitate creating "multi-channel" channels.           |
| `syslog`     | A `SyslogHandler` based LaraGram driver.                              |

</div>

> [!NOTE]
> Check out the documentation on [advanced channel customization](#laragram-channel-customization) to learn more about the `custom` drivers.

<a name="configuring-the-channel-name"></a>
#### Configuring the Channel Name

By default, LaraGram is instantiated with a "channel name" that matches the current environment, such as `production` or `local`. To change this value, you may add a `name` option to your channel's configuration:

```php
'stack' => [
    'driver' => 'stack',
    'name' => 'channel-name',
    'channels' => ['single', 'slack'],
],
```

<a name="channel-prerequisites"></a>
### Channel Prerequisites

<a name="configuring-the-single-and-daily-channels"></a>
#### Configuring the Single and Daily Channels

The `single` and `daily` channels have three optional configuration options: `bubble`, `permission`, and `locking`.

<div class="overflow-auto">

| Name         | Description                                                                   | Default |
| ------------ | ----------------------------------------------------------------------------- | ------- |
| `bubble`     | Indicates if messages should bubble up to other channels after being handled. | `true`  |
| `locking`    | Attempt to lock the log file before writing to it.                            | `false` |
| `permission` | The log file's permissions.                                                   | `0644`  |

</div>

Additionally, the retention policy for the `daily` channel can be configured via the `LOG_DAILY_DAYS` environment variable or by setting the `days` configuration option.

<div class="overflow-auto">

| Name   | Description                                                 | Default |
| ------ | ----------------------------------------------------------- | ------- |
| `days` | The number of days that daily log files should be retained. | `14`    |

</div>

<a name="configuring-the-slack-channel"></a>
#### Configuring the Slack Channel

The `slack` channel requires a `url` configuration option. This value may be defined via the `LOG_SLACK_WEBHOOK_URL` environment variable. This URL should match a URL for an [incoming webhook](https://slack.com/apps/A0F7XDUAZ-incoming-webhooks) that you have configured for your Slack team.

By default, Slack will only receive logs at the `critical` level and above; however, you can adjust this using the `LOG_LEVEL` environment variable or by modifying the `level` configuration option within your Slack log channel's configuration array.

<a name="logging-deprecation-warnings"></a>
### Logging Deprecation Warnings

PHP, LaraGram, and other libraries often notify their users that some of their features have been deprecated and will be removed in a future version. If you would like to log these deprecation warnings, you may specify your preferred `deprecations` log channel using the `LOG_DEPRECATIONS_CHANNEL` environment variable, or within your application's `config/logging.php` configuration file:

```php
'deprecations' => [
    'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
    'trace' => env('LOG_DEPRECATIONS_TRACE', false),
],

'channels' => [
    // ...
]
```

Or, you may define a log channel named `deprecations`. If a log channel with this name exists, it will always be used to log deprecations:

```php
'channels' => [
    'deprecations' => [
        'driver' => 'single',
        'path' => storage_path('logs/php-deprecation-warnings.log'),
    ],
],
```

<a name="building-log-stacks"></a>
## Building Log Stacks

As mentioned previously, the `stack` driver allows you to combine multiple channels into a single log channel for convenience. To illustrate how to use log stacks, let's take a look at an example configuration that you might see in a production application:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['syslog', 'slack'], // [!code ++]
        'ignore_exceptions' => false,
    ],

    'syslog' => [
        'driver' => 'syslog',
        'level' => env('LOG_LEVEL', 'debug'),
        'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
        'replace_placeholders' => true,
    ],

    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => env('LOG_SLACK_USERNAME', 'LaraGram Log'),
        'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
        'level' => env('LOG_LEVEL', 'critical'),
        'replace_placeholders' => true,
    ],
],
```

Let's dissect this configuration. First, notice our `stack` channel aggregates two other channels via its `channels` option: `syslog` and `slack`. So, when logging messages, both of these channels will have the opportunity to log the message. However, as we will see below, whether these channels actually log the message may be determined by the message's severity / "level".

<a name="log-levels"></a>
#### Log Levels

Take note of the `level` configuration option present on the `syslog` and `slack` channel configurations in the example above. This option determines the minimum "level" a message must be in order to be logged by the channel. LaraGram, which powers LaraGram's logging services, offers all of the log levels defined in the [RFC 5424 specification](https://tools.ietf.org/html/rfc5424). In descending order of severity, these log levels are: **emergency**, **alert**, **critical**, **error**, **warning**, **notice**, **info**, and **debug**.

So, imagine we log a message using the `debug` method:

```php
Log::debug('An informational message.');
```

Given our configuration, the `syslog` channel will write the message to the system log; however, since the error message is not `critical` or above, it will not be sent to Slack. However, if we log an `emergency` message, it will be sent to both the system log and Slack since the `emergency` level is above our minimum level threshold for both channels:

```php
Log::emergency('The system is down!');
```

<a name="writing-log-messages"></a>
## Writing Log Messages

You may write information to the logs using the `Log` [facade](/v4/facades). As previously mentioned, the logger provides the eight logging levels defined in the [RFC 5424 specification](https://tools.ietf.org/html/rfc5424): **emergency**, **alert**, **critical**, **error**, **warning**, **notice**, **info** and **debug**:

```php
use LaraGram\Support\Facades\Log;

Log::emergency($message);
Log::alert($message);
Log::critical($message);
Log::error($message);
Log::warning($message);
Log::notice($message);
Log::info($message);
Log::debug($message);
```

You may call any of these methods to log a message for the corresponding level. By default, the message will be written to the default log channel as configured by your `logging` configuration file:

```php
<?php

namespace App\Controllers;

use App\Models\User;
use LaraGram\Support\Facades\Log;
use LaraGram\Template\Template;

class UserController extends Controller
{
    /**
     * Show the profile for the given user.
     */
    public function show(string $id): Template
    {
        Log::info('Showing the user profile for user: {id}', ['id' => $id]);

        return template('user.profile', [
            'user' => User::findOrFail($id)
        ]);
    }
}
```

<a name="contextual-information"></a>
### Contextual Information

An array of contextual data may be passed to the log methods. This contextual data will be formatted and displayed with the log message:

```php
use LaraGram\Support\Facades\Log;

Log::info('User {id} failed to login.', ['id' => $user->id]);
```

Occasionally, you may wish to specify some contextual information that should be included with all subsequent log entries in a particular channel. For example, you may wish to log a request ID that is associated with each incoming request to your application. To accomplish this, you may call the `Log` facade's `withContext` method:

```php
<?php

namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Log;
use LaraGram\Support\Str;
use Symfony\Request\Response;

class AssignRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\LaraGram\Request\Request): (\LaraGram\Request\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Log::withContext([
            'request-id' => $requestId
        ]);

        $response = $next($request);

        $response->headers->set('Request-Id', $requestId);

        return $response;
    }
}
```

If you would like to share contextual information across _all_ logging channels, you may invoke the `Log::shareContext()` method. This method will provide the contextual information to all created channels and any channels that are created subsequently:

```php
<?php

namespace App\Middleware;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Log;
use LaraGram\Support\Str;
use LaraGram\Request\Response;

class AssignRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\LaraGram\Request\Request): (\LaraGram\Request\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Log::shareContext([
            'request-id' => $requestId
        ]);

        // ...
    }
}
```

> [!NOTE]
> If you need to share log context while processing queued jobs, you may utilize [job middleware](/v4/queues#job-middleware).

<a name="writing-to-specific-channels"></a>
### Writing to Specific Channels

Sometimes you may wish to log a message to a channel other than your application's default channel. You may use the `channel` method on the `Log` facade to retrieve and log to any channel defined in your configuration file:

```php
use LaraGram\Support\Facades\Log;

Log::channel('slack')->info('Something happened!');
```

If you would like to create an on-demand logging stack consisting of multiple channels, you may use the `stack` method:

```php
Log::stack(['single', 'slack'])->info('Something happened!');
```

<a name="on-demand-channels"></a>
#### On-Demand Channels

It is also possible to create an on-demand channel by providing the configuration at runtime without that configuration being present in your application's `logging` configuration file. To accomplish this, you may pass a configuration array to the `Log` facade's `build` method:

```php
use LaraGram\Support\Facades\Log;

Log::build([
  'driver' => 'single',
  'path' => storage_path('logs/custom.log'),
])->info('Something happened!');
```

You may also wish to include an on-demand channel in an on-demand logging stack. This can be achieved by including your on-demand channel instance in the array passed to the `stack` method:

```php
use LaraGram\Support\Facades\Log;

$channel = Log::build([
  'driver' => 'single',
  'path' => storage_path('logs/custom.log'),
]);

Log::stack(['slack', $channel])->info('Something happened!');
```

<a name="laragram-channel-customization"></a>
## LaraGram Channel Customization

<a name="customizing-laragram-for-channels"></a>
### Customizing LaraGram for Channels

Sometimes you may need complete control over how LaraGram is configured for an existing channel. For example, you may want to configure a custom LaraGram `FormatterInterface` implementation for LaraGram's built-in `single` channel.

To get started, define a `tap` array on the channel's configuration. The `tap` array should contain a list of classes that should have an opportunity to customize (or "tap" into) the LaraGram instance after it is created. There is no conventional location where these classes should be placed, so you are free to create a directory within your application to contain these classes:

```php
'single' => [
    'driver' => 'single',
    'tap' => [App\Logging\CustomizeFormatter::class],
    'path' => storage_path('logs/laragram.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'replace_placeholders' => true,
],
```

Once you have configured the `tap` option on your channel, you're ready to define the class that will customize your LaraGram instance. This class only needs a single method: `__invoke`, which receives an `LaraGram\Log\Logger` instance. The `LaraGram\Log\Logger` instance proxies all method calls to the underlying LaraGram instance:

```php
<?php

namespace App\Logging;

use LaraGram\Log\Logger;
use LaraGram\Formatter\LineFormatter;

class CustomizeFormatter
{
    /**
     * Customize the given logger instance.
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new LineFormatter(
                '[%datetime%] %channel%.%level_name%: %message% %context% %extra%'
            ));
        }
    }
}
```

> [!NOTE]
> All of your "tap" classes are resolved by the [service container](/v4/container), so any constructor dependencies they require will automatically be injected.

<a name="creating-laragram-handler-channels"></a>
### Creating LaraGram Handler Channels

rewritten Monolog has a variety of [available handlers](https://github.com/Seldaek/monolog/tree/main/src/Monolog/Handler) and LaraGram does not include a built-in channel for each one. In some cases, you may wish to create a custom channel that is merely an instance of a specific LaraGram handler that does not have a corresponding LaraGram log driver.  These channels can be easily created using the `laragram` driver.

When using the `laragram` driver, the `handler` configuration option is used to specify which handler will be instantiated. Optionally, any constructor parameters the handler needs may be specified using the `handler_with` configuration option:

```php
'logentries' => [
    'driver'  => 'laragram',
    'handler' => LaraGram\Log\Logger\Handler\SyslogUdpHandler::class,
    'handler_with' => [
        'host' => 'my.logentries.internal.datahubhost.company.com',
        'port' => '10000',
    ],
],
```

<a name="laragram-formatters"></a>
#### LaraGram Formatters

When using the `laragram` driver, the LaraGram `LineFormatter` will be used as the default formatter. However, you may customize the type of formatter passed to the handler using the `formatter` and `formatter_with` configuration options:

```php
'browser' => [
    'driver' => 'laragram',
    'handler' => LaraGram\Log\Logger\Handler\BrowserConsoleHandler::class,
    'formatter' => LaraGram\Log\Logger\Formatter\HtmlFormatter::class,
    'formatter_with' => [
        'dateFormat' => 'Y-m-d',
    ],
],
```

If you are using a LaraGram handler that is capable of providing its own formatter, you may set the value of the `formatter` configuration option to `default`:

```php
'newrelic' => [
    'driver' => 'laragram',
    'handler' => LaraGram\Log\Logger\Handler\NewRelicHandler::class,
    'formatter' => 'default',
],
```

<a name="laragram-processors"></a>
#### LaraGram Processors

LaraGram can also process messages before logging them. You can create your own processors or use the [existing processors offered by Monolog](https://github.com/Seldaek/monolog/tree/main/src/Monolog/Processor).

If you would like to customize the processors for a `laragram` driver, add a `processors` configuration value to your channel's configuration:

```php
'memory' => [
    'driver' => 'laragram',
    'handler' => LaraGram\Log\Logger\Handler\StreamHandler::class,
    'handler_with' => [
        'stream' => 'php://stderr',
    ],
    'processors' => [
        // Simple syntax...
        LaraGram\Log\Logger\Processor\MemoryUsageProcessor::class,

        // With options...
        [
            'processor' => LaraGram\Log\Logger\Processor\PsrLogMessageProcessor::class,
            'with' => ['removeUsedContextFields' => true],
        ],
    ],
],
```

<a name="creating-custom-channels-via-factories"></a>
### Creating Custom Channels via Factories

If you would like to define an entirely custom channel in which you have full control over LaraGram's instantiation and configuration, you may specify a `custom` driver type in your `config/logging.php` configuration file. Your configuration should include a `via` option that contains the name of the factory class which will be invoked to create the rewritten Monolog instance:

```php
'channels' => [
    'example-custom-channel' => [
        'driver' => 'custom',
        'via' => App\Logging\CreateCustomLogger::class,
    ],
],
```

Once you have configured the `custom` driver channel, you're ready to define the class that will create your LaraGram instance. This class only needs a single `__invoke` method which should return the LaraGram logger instance. The method will receive the channels configuration array as its only argument:

```php
<?php

namespace App\Logging;

use LaraGram\Log\Logger\Logger;

class CreateCustomLogger
{
    /**
     * Create a custom LaraGram instance.
     */
    public function __invoke(array $config): Logger
    {
        return new Logger(/* ... */);
    }
}
```
