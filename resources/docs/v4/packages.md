# Package Development

<a name="introduction"></a>
## Introduction

Packages are the primary way of adding functionality to LaraGram. Packages might be anything from a great way to work with dates like [Tempora](https://github.com/laraxgram/tempora).

There are different types of packages. Some packages are stand-alone, meaning they work with any PHP framework. Tempora an examples of stand-alone packages. Any of these packages may be used with LaraGram by requiring them in your `composer.json` file.

On the other hand, other packages are specifically intended for use with LaraGram. These packages may have listens, controllers, templates, and configuration specifically intended to enhance a LaraGram application. This guide primarily covers the development of those packages that are LaraGram specific.

<a name="package-discovery"></a>
## Package Discovery

A LaraGram application's `bootstrap/providers.php` file contains the list of service providers that should be loaded by LaraGram. However, instead of requiring users to manually add your service provider to the list, you may define the provider in the `extra` section of your package's `composer.json` file so that it is automatically loaded by LaraGram. In addition to service providers, you may also list any [facades](/v4/facades) you would like to be registered:

```json
"extra": {
    "laragram": {
        "providers": [
            "Foo\\Bar\\ServiceProvider"
        ],
        "aliases": {
            "Debugbar": "Foo\\Bar\\Facade"
        }
    }
},
```

Once your package has been configured for discovery, LaraGram will automatically register its service providers and facades when it is installed, creating a convenient installation experience for your package's users.

<a name="opting-out-of-package-discovery"></a>
#### Opting Out of Package Discovery

If you are the consumer of a package and would like to disable package discovery for a package, you may list the package name in the `extra` section of your application's `composer.json` file:

```json
"extra": {
    "laragram": {
        "dont-discover": [
            "foo/bar"
        ]
    }
},
```

You may disable package discovery for all packages using the `*` character inside of your application's `dont-discover` directive:

```json
"extra": {
    "laragram": {
        "dont-discover": [
            "*"
        ]
    }
},
```

<a name="service-providers"></a>
## Service Providers

[Service providers](/v4/providers) are the connection point between your package and LaraGram. A service provider is responsible for binding things into LaraGram's [service container](/v4/container) and informing LaraGram where to load package resources such as views, configuration, and language files.

A service provider extends the `LaraGram\Support\ServiceProvider` class and contains two methods: `register` and `boot`. The base `ServiceProvider` class is located in the `laraxgram/support` Composer package, which you should add to your own package's dependencies. To learn more about the structure and purpose of service providers, check out [their documentation](/v4/providers).

<a name="resources"></a>
## Resources

<a name="configuration"></a>
### Configuration

Typically, you will need to publish your package's configuration file to the application's `config` directory. This will allow users of your package to easily override your default configuration options. To allow your configuration files to be published, call the `publishes` method from the `boot` method of your service provider:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->publishes([
        __DIR__.'/../config/surge.php' => config_path('surge.php'),
    ]);
}
```

Now, when users of your package execute LaraGram's `vendor:publish` command, your file will be copied to the specified publish location. Once your configuration has been published, its values may be accessed like any other configuration file:

```php
$value = config('surge.option');
```

> [!WARNING]
> You should not define closures in your configuration files. They cannot be serialized correctly when users execute the `config:cache` Commander command.

<a name="default-package-configuration"></a>
#### Default Package Configuration

You may also merge your own package configuration file with the application's published copy. This will allow your users to define only the options they actually want to override in the published copy of the configuration file. To merge the configuration file values, use the `mergeConfigFrom` method within your service provider's `register` method.

The `mergeConfigFrom` method accepts the path to your package's configuration file as its first argument and the name of the application's copy of the configuration file as its second argument:

```php
/**
 * Register any application services.
 */
public function register(): void
{
    $this->mergeConfigFrom(
        __DIR__.'/../config/surge.php', 'surge'
    );
}
```

> [!WARNING]
> This method only merges the first level of the configuration array. If your users partially define a multi-dimensional configuration array, the missing options will not be merged.

<a name="listens"></a>
### Listens

If your package contains listens, you may load them using the `loadListensFrom` method. This method will automatically determine if the application's listens are cached and will not load your listens file if the listens have already been cached:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->loadListensFrom(__DIR__.'/../listens/bot.php');
}
```

<a name="migrations"></a>
### Migrations

If your package contains [database migrations](/v4/migrations), you may use the `publishesMigrations` method to inform LaraGram that the given directory or file contains migrations. When LaraGram publishes the migrations, it will automatically update the timestamp within their filename to reflect the current date and time:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->publishesMigrations([
        __DIR__.'/../database/migrations' => database_path('migrations'),
    ]);
}
```

<a name="language-files"></a>
### Language Files

If your package contains [language files](/v4/localization), you may use the `loadTranslationsFrom` method to inform LaraGram how to load them. For example, if your package is named `courier`, you should add the following to your service provider's `boot` method:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->loadTranslationsFrom(__DIR__.'/../lang', 'surge');
}
```

Package translation lines are referenced using the `package::file.line` syntax convention. So, you may load the `surge` package's `welcome` line from the `messages` file like so:

```php
echo trans('surge::messages.welcome');
```

You can register JSON translation files for your package using the `loadJsonTranslationsFrom` method. This method accepts the path to the directory that contains your package's JSON translation files:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
}
```

<a name="publishing-language-files"></a>
#### Publishing Language Files

If you would like to publish your package's language files to the application's `lang/vendor` directory, you may use the service provider's `publishes` method. The `publishes` method accepts an array of package paths and their desired publish locations. For example, to publish the language files for the `surge` package, you may do the following:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->loadTranslationsFrom(__DIR__.'/../lang', 'surge');

    $this->publishes([
        __DIR__.'/../lang' => $this->app->langPath('vendor/surge'),
    ]);
}
```

Now, when users of your package execute LaraGram's `vendor:publish` Commander command, your package's language files will be published to the specified publish location.

<a name="templates"></a>
### Templates

To register your package's [templates](/v4/templates) with LaraGram, you need to tell LaraGram where the templates are located. You may do this using the service provider's `loadTemplatesFrom` method. The `loadTemplatesFrom` method accepts two arguments: the path to your templates and your package's name. For example, if your package's name is `surge`, you would add the following to your service provider's `boot` method:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->loadTemplatesFrom(__DIR__.'/../app/templates', 'surge');
}
```

Package views are referenced using the `package::template` syntax convention. So, once your view path is registered in a service provider, you may load the `dashboard` view from the `surge` package like so:

```php
Bot::onCommand('dashboard', function () {
    return template('surge::dashboard');
});
```

<a name="overriding-package-templates"></a>
#### Overriding Package Templates

When you use the `loadTemplatesFrom` method, LaraGram actually registers two locations for your templates: the application's `app/templates/vendor` directory and the directory you specify. So, using the `surge` package as an example, LaraGram will first check if a custom version of the view has been placed in the `app/templates/vendor/surge` directory by the developer. Then, if the view has not been customized, LaraGram will search the package view directory you specified in your call to `loadTemplatesFrom`. This makes it easy for package users to customize / override your package's views.

<a name="publishing-templates"></a>
#### Publishing Templates

If you would like to make your views available for publishing to the application's `app/templates/vendor` directory, you may use the service provider's `publishes` method. The `publishes` method accepts an array of package template paths and their desired publish locations:

```php
/**
 * Bootstrap the package services.
 */
public function boot(): void
{
    $this->loadTemplatesFrom(__DIR__.'/../app/templates', 'surge');

    $this->publishes([
        __DIR__.'/../app/templates' => resource_path('templates/vendor/surge'),
    ]);
}
```

Now, when users of your package execute LaraGram's `vendor:publish` Commander command, your package's views will be copied to the specified publish location.

<a name="template-components"></a>
### Template Components

If you are building a package that utilizes Template components or placing components in non-conventional directories, you will need to manually register your component class and its tag alias so that LaraGram knows where to find the component. You should typically register your components in the `boot` method of your package's service provider:

```php
use LaraGram\Support\Facades\Template;
use VendorPackage\Template\Components\AlertComponent;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Template::component('package-alert', AlertComponent::class);
}
```

Once your component has been registered, it may be rendered using its tag alias:

```blade
<x-package-alert/>
```

<a name="autoloading-package-components"></a>
#### Autoloading Package Components

Alternatively, you may use the `componentNamespace` method to autoload component classes by convention:

```php
use LaraGram\Support\Facades\Template;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Template::componentNamespace('Surge\\Template\\Components', 'surge');
}
```

This will allow the usage of package components by their vendor namespace using the `package-name::` syntax:

```blade
<x-surge::foo />
<x-surge::bar />
```

Blade will automatically detect the class that's linked to this component by pascal-casing the component name. Subdirectories are also supported using "dot" notation.

<a name="anonymous-components"></a>
#### Anonymous Components

If your package contains anonymous components, they must be placed within a `components` directory of your package's "views" templates (as specified by the [loadTemplatesFrom method](#templates)). Then, you may render them by prefixing the component name with the package's view namespace:

```blade
<x-surge::alert />
```

<a name="about-artisan-command"></a>
### "About" Commander Command

LaraGram's built-in `about` Commander command provides a synopsis of the application's environment and configuration. Packages may push additional information to this command's output via the `AboutCommand` class. Typically, this information may be added from your package service provider's `boot` method:

```php
use LaraGram\Foundation\Console\AboutCommand;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    AboutCommand::add('My Package', fn () => ['Version' => '1.0.0']);
}
```

<a name="commands"></a>
## Commands

To register your package's Commander commands with LaraGram, you may use the `commands` method. This method expects an array of command class names. Once the commands have been registered, you may execute them using the [Commander CLI](/v4/commander):

```php
use Surge\Console\Commands\InstallCommand;

/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            InstallCommand::class,
        ]);
    }
}
```

<a name="optimize-commands"></a>
### Optimize Commands

LaraGram's [optimize command](/v4/deployment#optimization) caches the application's configuration, events, listens, and templates. Using the `optimizes` method, you may register your package's own Commander commands that should be invoked when the `optimize` and `optimize:clear` commands are executed:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    if ($this->app->runningInConsole()) {
        $this->optimizes(
            optimize: 'package:optimize',
            clear: 'package:clear-optimizations',
        );
    }
}
```

<a name="public-assets"></a>
## Public Assets

Your package may have assets such images. To publish these assets to the application's `public` directory, use the service provider's `publishes` method. In this example, we will also add a `public` asset group tag, which may be used to easily publish groups of related assets:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->publishes([
        __DIR__.'/../public' => public_path('vendor/surge'),
    ], 'public');
}
```

Now, when your package's users execute the `vendor:publish` command, your assets will be copied to the specified publish location. Since users will typically need to overwrite the assets every time the package is updated, you may use the `--force` flag:

```shell
php laragram vendor:publish --tag=public --force
```

<a name="publishing-file-groups"></a>
## Publishing File Groups

You may want to publish groups of package assets and resources separately. For instance, you might want to allow your users to publish your package's configuration files without being forced to publish your package's assets. You may do this by "tagging" them when calling the `publishes` method from a package's service provider. For example, let's use tags to define two publish groups for the `surge` package (`surge-config` and `surge-migrations`) in the `boot` method of the package's service provider:

```php
/**
 * Bootstrap any package services.
 */
public function boot(): void
{
    $this->publishes([
        __DIR__.'/../config/package.php' => config_path('package.php')
    ], 'surge-config');

    $this->publishesMigrations([
        __DIR__.'/../database/migrations/' => database_path('migrations')
    ], 'surge-migrations');
}
```

Now your users may publish these groups separately by referencing their tag when executing the `vendor:publish` command:

```shell
php laragram vendor:publish --tag=surge-config
```

Your users can also publish all publishable files defined by your package's service provider using the `--provider` flag:

```shell
php laragram vendor:publish --provider="Your\Package\ServiceProvider"
```
