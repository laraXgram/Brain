# Contracts

<a name="introduction"></a>
## Introduction

LaraGram's "contracts" are a set of interfaces that define the core services provided by the framework. For example, an `LaraGram\Contracts\Queue\Queue` contract defines the methods needed for queueing jobs.

Each contract has a corresponding implementation provided by the framework.

All of the LaraGram contracts live in [their own GitHub repository](https://github.com/laraxgram/contracts). This provides a quick reference point for all available contracts, as well as a single, decoupled package that may be utilized when building packages that interact with LaraGram services.

<a name="contracts-vs-facades"></a>
### Contracts vs. Facades

LaraGram's [facades](/v4/facades) and helper functions provide a simple way of utilizing LaraGram's services without needing to type-hint and resolve contracts out of the service container. In most cases, each facade has an equivalent contract.

Unlike facades, which do not require you to require them in your class' constructor, contracts allow you to define explicit dependencies for your classes. Some developers prefer to explicitly define their dependencies in this way and therefore prefer to use contracts, while other developers enjoy the convenience of facades. **In general, most applications can use facades without issue during development.**

<a name="when-to-use-contracts"></a>
## When to Use Contracts

The decision to use contracts or facades will come down to personal taste and the tastes of your development team. Both contracts and facades can be used to create robust, well-tested LaraGram applications. Contracts and facades are not mutually exclusive. Some parts of your applications may use facades while others depend on contracts. As long as you are keeping your class' responsibilities focused, you will notice very few practical differences between using contracts and facades.

In general, most applications can use facades without issue during development. If you are building a package that integrates with multiple PHP frameworks you may wish to use the `laraxgram/contracts` package to define your integration with LaraGram's services without the need to require LaraGram's concrete implementations in your package's `composer.json` file.

<a name="how-to-use-contracts"></a>
## How to Use Contracts

So, how do you get an implementation of a contract? It's actually quite simple.

Many types of classes in LaraGram are resolved through the [service container](/v4/container), including controllers, event listeners, middleware, queued jobs, and even listen closures. So, to get an implementation of a contract, you can just "type-hint" the interface in the constructor of the class being resolved.

For example, take a look at this event listener:

```php
<?php

namespace App\Listeners;

use App\Events\OrderWasPlaced;
use App\Models\User;
use LaraGram\Contracts\Redis\Factory;

class CacheOrderInformation
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected Factory $redis,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderWasPlaced $event): void
    {
        // ...
    }
}
```

When the event listener is resolved, the service container will read the type-hints on the constructor of the class, and inject the appropriate value. To learn more about registering things in the service container, check out [its documentation](/v4/container).

<a name="contract-reference"></a>
## Contract Reference

This table provides a quick reference to all of the LaraGram contracts and their equivalent facades:

<div class="overflow-auto">

| Contract                                                                                                                                            | References Facade        |
|-----------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------|
| [LaraGram\Contracts\Auth\Access\Authorizable](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/Access/Authorizable.php)                 | &nbsp;                   |
| [LaraGram\Contracts\Auth\Access\Gate](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/Access/Gate.php)                                 | `Gate`                   |
| [LaraGram\Contracts\Auth\Authenticatable](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/Authenticatable.php)                         | &nbsp;                   |
| [LaraGram\Contracts\Auth\CanResetPassword](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/CanResetPassword.php)                       | &nbsp;                   |
| [LaraGram\Contracts\Auth\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/Factory.php)                                         | `Auth`                   |
| [LaraGram\Contracts\Auth\Guard](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/Guard.php)                                             | `Auth::guard()`          |
| [LaraGram\Contracts\Auth\PasswordBroker](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/PasswordBroker.php)                           | `Password::broker()`     |
| [LaraGram\Contracts\Auth\PasswordBrokerFactory](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/PasswordBrokerFactory.php)             | `Password`               |
| [LaraGram\Contracts\Auth\StatefulGuard](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/StatefulGuard.php)                             | &nbsp;                   |
| [LaraGram\Contracts\Auth\SupportsBasicAuth](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/SupportsBasicAuth.php)                     | &nbsp;                   |
| [LaraGram\Contracts\Auth\UserProvider](https://github.com/laraxgram/contracts/blob/{{version}}/Auth/UserProvider.php)                               | &nbsp;                   |
| [LaraGram\Contracts\Bus\Dispatcher](https://github.com/laraxgram/contracts/blob/{{version}}/Bus/Dispatcher.php)                                     | `Bus`                    |
| [LaraGram\Contracts\Bus\QueueingDispatcher](https://github.com/laraxgram/contracts/blob/{{version}}/Bus/QueueingDispatcher.php)                     | `Bus::dispatchToQueue()` |
| [LaraGram\Contracts\Cache\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Cache/Factory.php)                                       | `Cache`                  |
| [LaraGram\Contracts\Cache\Lock](https://github.com/laraxgram/contracts/blob/{{version}}/Cache/Lock.php)                                             | &nbsp;                   |
| [LaraGram\Contracts\Cache\LockProvider](https://github.com/laraxgram/contracts/blob/{{version}}/Cache/LockProvider.php)                             | &nbsp;                   |
| [LaraGram\Contracts\Cache\Repository](https://github.com/laraxgram/contracts/blob/{{version}}/Cache/Repository.php)                                 | `Cache::driver()`        |
| [LaraGram\Contracts\Cache\Store](https://github.com/laraxgram/contracts/blob/{{version}}/Cache/Store.php)                                           | &nbsp;                   |
| [LaraGram\Contracts\Config\Repository](https://github.com/laraxgram/contracts/blob/{{version}}/Config/Repository.php)                               | `Config`                 |
| [LaraGram\Contracts\Console\Application](https://github.com/laraxgram/contracts/blob/{{version}}/Console/Application.php)                           | &nbsp;                   |
| [LaraGram\Contracts\Console\Kernel](https://github.com/laraxgram/contracts/blob/{{version}}/Console/Kernel.php)                                     | `Commander`              |
| [LaraGram\Contracts\Container\Container](https://github.com/laraxgram/contracts/blob/{{version}}/Container/Container.php)                           | `App`                    |
| [LaraGram\Contracts\Cookie\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Cookie/Factory.php)                                     | `Cookie`                 |
| [LaraGram\Contracts\Cookie\QueueingFactory](https://github.com/laraxgram/contracts/blob/{{version}}/Cookie/QueueingFactory.php)                     | `Cookie::queue()`        |
| [LaraGram\Contracts\Database\ModelIdentifier](https://github.com/laraxgram/contracts/blob/{{version}}/Database/ModelIdentifier.php)                 | &nbsp;                   |
| [LaraGram\Contracts\Debug\ExceptionHandler](https://github.com/laraxgram/contracts/blob/{{version}}/Debug/ExceptionHandler.php)                     | &nbsp;                   |
| [LaraGram\Contracts\Encryption\Encrypter](https://github.com/laraxgram/contracts/blob/{{version}}/Encryption/Encrypter.php)                         | `Crypt`                  |
| [LaraGram\Contracts\Events\Dispatcher](https://github.com/laraxgram/contracts/blob/{{version}}/Events/Dispatcher.php)                               | `Event`                  |
| [LaraGram\Contracts\Filesystem\Cloud](https://github.com/laraxgram/contracts/blob/{{version}}/Filesystem/Cloud.php)                                 | `Storage::cloud()`       |
| [LaraGram\Contracts\Filesystem\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Filesystem/Factory.php)                             | `Storage`                |
| [LaraGram\Contracts\Filesystem\Filesystem](https://github.com/laraxgram/contracts/blob/{{version}}/Filesystem/Filesystem.php)                       | `Storage::disk()`        |
| [LaraGram\Contracts\Foundation\Application](https://github.com/laraxgram/contracts/blob/{{version}}/Foundation/Application.php)                     | `App`                    |
| [LaraGram\Contracts\Hashing\Hasher](https://github.com/laraxgram/contracts/blob/{{version}}/Hashing/Hasher.php)                                     | `Hash`                   |
| [LaraGram\Contracts\Bot\Kernel](https://github.com/laraxgram/contracts/blob/{{version}}/Bot/Kernel.php)                                             | &nbsp;                   |
| [LaraGram\Contracts\Pagination\LengthAwarePaginator](https://github.com/laraxgram/contracts/blob/{{version}}/Pagination/LengthAwarePaginator.php)   | &nbsp;                   |
| [LaraGram\Contracts\Pagination\Paginator](https://github.com/laraxgram/contracts/blob/{{version}}/Pagination/Paginator.php)                         | &nbsp;                   |
| [LaraGram\Contracts\Pipeline\Hub](https://github.com/laraxgram/contracts/blob/{{version}}/Pipeline/Hub.php)                                         | &nbsp;                   |
| [LaraGram\Contracts\Pipeline\Pipeline](https://github.com/laraxgram/contracts/blob/{{version}}/Pipeline/Pipeline.php)                               | `Pipeline`               |
| [LaraGram\Contracts\Queue\EntityResolver](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/EntityResolver.php)                         | &nbsp;                   |
| [LaraGram\Contracts\Queue\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/Factory.php)                                       | `Queue`                  |
| [LaraGram\Contracts\Queue\Job](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/Job.php)                                               | &nbsp;                   |
| [LaraGram\Contracts\Queue\Monitor](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/Monitor.php)                                       | `Queue`                  |
| [LaraGram\Contracts\Queue\Queue](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/Queue.php)                                           | `Queue::connection()`    |
| [LaraGram\Contracts\Queue\QueueableCollection](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/QueueableCollection.php)               | &nbsp;                   |
| [LaraGram\Contracts\Queue\QueueableEntity](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/QueueableEntity.php)                       | &nbsp;                   |
| [LaraGram\Contracts\Queue\ShouldQueue](https://github.com/laraxgram/contracts/blob/{{version}}/Queue/ShouldQueue.php)                               | &nbsp;                   |
| [LaraGram\Contracts\Redis\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Redis/Factory.php)                                       | `Redis`                  |
| [LaraGram\Contracts\Routing\BindingRegistrar](https://github.com/laraxgram/contracts/blob/{{version}}/Routing/BindingRegistrar.php)                 | `Route`                  |
| [LaraGram\Contracts\Routing\Registrar](https://github.com/laraxgram/contracts/blob/{{version}}/Routing/Registrar.php)                               | `Route`                  |
| [LaraGram\Contracts\Routing\ResponseFactory](https://github.com/laraxgram/contracts/blob/{{version}}/Routing/ResponseFactory.php)                   | `Response`               |
| [LaraGram\Contracts\Routing\UrlGenerator](https://github.com/laraxgram/contracts/blob/{{version}}/Routing/UrlGenerator.php)                         | `URL`                    |
| [LaraGram\Contracts\Routing\UrlRoutable](https://github.com/laraxgram/contracts/blob/{{version}}/Routing/UrlRoutable.php)                           | &nbsp;                   |
| [LaraGram\Contracts\Session\Session](https://github.com/laraxgram/contracts/blob/{{version}}/Session/Session.php)                                   | `Session::driver()`      |
| [LaraGram\Contracts\Listening\BindingRegistrar](https://github.com/laraxgram/contracts/blob/{{version}}/Listening/BindingRegistrar.php)             | `Listen`                 |
| [LaraGram\Contracts\Listening\Registrar](https://github.com/laraxgram/contracts/blob/{{version}}/Listening/Registrar.php)                           | `Listen`                 |
| [LaraGram\Contracts\Listening\ResponseFactory](https://github.com/laraxgram/contracts/blob/{{version}}/Listening/ResponseFactory.php)               | `Response`               |
| [LaraGram\Contracts\Listening\PathGenerator](https://github.com/laraxgram/contracts/blob/{{version}}/Listening/PathGenerator.php)                   | `URL`                    |
| [LaraGram\Contracts\Listening\PathListenable](https://github.com/laraxgram/contracts/blob/{{version}}/Listening/PathListenable.php)                 | &nbsp;                   |
| [LaraGram\Contracts\Support\Arrayable](https://github.com/laraxgram/contracts/blob/{{version}}/Support/Arrayable.php)                               | &nbsp;                   |
| [LaraGram\Contracts\Support\Htmlable](https://github.com/laraxgram/contracts/blob/{{version}}/Support/Htmlable.php)                                 | &nbsp;                   |
| [LaraGram\Contracts\Support\Jsonable](https://github.com/laraxgram/contracts/blob/{{version}}/Support/Jsonable.php)                                 | &nbsp;                   |
| [LaraGram\Contracts\Support\MessageBag](https://github.com/laraxgram/contracts/blob/{{version}}/Support/MessageBag.php)                             | &nbsp;                   |
| [LaraGram\Contracts\Support\MessageProvider](https://github.com/laraxgram/contracts/blob/{{version}}/Support/MessageProvider.php)                   | &nbsp;                   |
| [LaraGram\Contracts\Support\Renderable](https://github.com/laraxgram/contracts/blob/{{version}}/Support/Renderable.php)                             | &nbsp;                   |
| [LaraGram\Contracts\Support\Responsable](https://github.com/laraxgram/contracts/blob/{{version}}/Support/Responsable.php)                           | &nbsp;                   |
| [LaraGram\Contracts\Translation\Loader](https://github.com/laraxgram/contracts/blob/{{version}}/Translation/Loader.php)                             | &nbsp;                   |
| [LaraGram\Contracts\Translation\Translator](https://github.com/laraxgram/contracts/blob/{{version}}/Translation/Translator.php)                     | `Lang`                   |
| [LaraGram\Contracts\Validation\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Validation/Factory.php)                             | `Validator`              |
| [LaraGram\Contracts\Validation\ValidatesWhenResolved](https://github.com/laraxgram/contracts/blob/{{version}}/Validation/ValidatesWhenResolved.php) | &nbsp;                   |
| [LaraGram\Contracts\Validation\ValidationRule](https://github.com/laraxgram/contracts/blob/{{version}}/Validation/ValidationRule.php)               | &nbsp;                   |
| [LaraGram\Contracts\Validation\Validator](https://github.com/laraxgram/contracts/blob/{{version}}/Validation/Validator.php)                         | `Validator::make()`      |
| [LaraGram\Contracts\Template\Engine](https://github.com/laraxgram/contracts/blob/{{version}}/Template/Engine.php)                                   | &nbsp;                   |
| [LaraGram\Contracts\Template\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/Template/Factory.php)                                 | `Template`               |
| [LaraGram\Contracts\Template\Template](https://github.com/laraxgram/contracts/blob/{{version}}/Template/Template.php)                               | `Template::make()`       |
| [LaraGram\Contracts\View\Engine](https://github.com/laraxgram/contracts/blob/{{version}}/View/Engine.php)                                           | &nbsp;                   |
| [LaraGram\Contracts\View\Factory](https://github.com/laraxgram/contracts/blob/{{version}}/View/Factory.php)                                         | `View`                   |
| [LaraGram\Contracts\View\View](https://github.com/laraxgram/contracts/blob/{{version}}/View/View.php)                                               | `View::make()`           |

</div>
