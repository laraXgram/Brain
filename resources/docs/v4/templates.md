# Templates

<a name="introduction"></a>
## Introduction

Of course, it's not always practical for controllers to generate all the messages and keyboards directly. Thankfully, templates provide a convenient way to organize this content into separate files.

Templates separate your controller / application logic from your presentation logic and are stored in the `app/templates` directory. When using LaraGram, template are usually written using the [Template templating language](/v4/temple8). A simple template might look something like this:

```blade
<!-- Template stored in app/templates/greeting.t8.php -->

@text()
Hello, {{ $name }}
@endText
```

Since this template is stored at `app/templates/greeting.t8.php`, we may return it using the global `template` helper like so:

```php
Bot::onText('hi', function () {
    return template('greeting', ['name' => 'James']);
});
```

> [!NOTE]
> Looking for more information on how to write Template templates? Check out the full [Template documentation](/v4/temple8) to get started.

<a name="creating-and-rendering-templates"></a>
## Creating and Rendering Templates

You may create a template by placing a file with the `.t8.php` extension in your application's `app/templates` directory or by using the `make:template` Commander command:

```shell
php laragram make:template greeting
```

The `.t8.php` extension informs the framework that the file contains a [Template template](/v4/temple8). Template templates contain method input as well as Template directives that allow you to easily call a api request, create "if" statements, iterate over data, and more.

Once you have created a template, you may return it from one of your application's listens or controllers using the global `template` helper:

```php
Bot::onText('hi', function () {
    return template('greeting', ['name' => 'James']);
});
```

Templates may also be returned using the `Template` facade:

```php
use LaraGram\Support\Facades\Template;

return Template::make('greeting', ['name' => 'James']);
```

As you can see, the first argument passed to the `template` helper corresponds to the name of the template file in the `app/templates` directory. The second argument is an array of data that should be made available to the template. In this case, we are passing the `name` variable, which is displayed in the template using [Template syntax](/v4/temple8).

<a name="nested-template-directories"></a>
### Nested Template Directories

Templates may also be nested within subdirectories of the `app/templates` directory. "Dot" notation may be used to reference nested templates. For example, if your template is stored at `app/templates/admin/profile.t8.php`, you may return it from one of your application's listens / controllers like so:

```php
return template('admin.profile', $data);
```

> [!WARNING]
> Template directory names should not contain the `.` character.

<a name="creating-the-first-available-template"></a>
### Creating the First Available Template

Using the `Template` facade's `first` method, you may create the first template that exists in a given array of templates. This may be useful if your application or package allows templates to be customized or overwritten:

```php
use LaraGram\Support\Facades\Template;

return Template::first(['custom.admin', 'admin'], $data);
```

<a name="determining-if-a-template-exists"></a>
### Determining if a Template Exists

If you need to determine if a template exists, you may use the `Template` facade. The `exists` method will return `true` if the template exists:

```php
use LaraGram\Support\Facades\Template;

if (Template::exists('admin.profile')) {
    // ...
}
```

<a name="passing-data-to-templates"></a>
## Passing Data to Templates

As you saw in the previous examples, you may pass an array of data to templates to make that data available to the template:

```php
return template('greetings', ['name' => 'Victoria']);
```

When passing information in this manner, the data should be an array with key / value pairs. After providing data to a template, you can then access each value within your template using the data's keys, such as `<?php echo $name; ?>`.

As an alternative to passing a complete array of data to the `template` helper function, you may use the `with` method to add individual pieces of data to the template. The `with` method returns an instance of the template object so that you can continue chaining methods before returning the template:

```php
return template('greeting')
    ->with('name', 'Victoria')
    ->with('occupation', 'Astronaut');
```

<a name="sharing-data-with-all-templates"></a>
### Sharing Data With All Templates

Occasionally, you may need to share data with all templates that are rendered by your application. You may do so using the `Template` facade's `share` method. Typically, you should place calls to the `share` method within a service provider's `boot` method. You are free to add them to the `App\Providers\AppServiceProvider` class or generate a separate service provider to house them:

```php
<?php

namespace App\Providers;

use LaraGram\Support\Facades\Template;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ...
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Template::share('key', 'value');
    }
}
```

<a name="template-composers"></a>
## Template Composers

Template composers are callbacks or class methods that are called when a template is rendered. If you have data that you want to be bound to a template each time that template is rendered, a template composer can help you organize that logic into a single location. Template composers may prove particularly useful if the same template is returned by multiple listens or controllers within your application and always needs a particular piece of data.

Typically, template composers will be registered within one of your application's [service providers](/v4/providers). In this example, we'll assume that the `App\Providers\AppServiceProvider` will house this logic.

We'll use the `Template` facade's `composer` method to register the template composer. LaraGram does not include a default directory for class-based template composers, so you are free to organize them however you wish. For example, you could create an `app/Template/Composers` directory to house all of your application's template composers:

```php
<?php

namespace App\Providers;

use App\Template\Composers\ProfileComposer;
use LaraGram\Support\Facades;
use LaraGram\Support\ServiceProvider;
use LaraGram\Template\Template;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ...
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Using class-based composers...
        Facades\Template::composer('profile', ProfileComposer::class);

        // Using closure-based composers...
        Facades\Template::composer('welcome', function (Template $template) {
            // ...
        });

        Facades\Template::composer('dashboard', function (Template $template) {
            // ...
        });
    }
}
```

Now that we have registered the composer, the `compose` method of the `App\Template\Composers\ProfileComposer` class will be executed each time the `profile` template is being rendered. Let's take a look at an example of the composer class:

```php
<?php

namespace App\Template\Composers;

use App\Repositories\UserRepository;
use LaraGram\Template\Template;

class ProfileComposer
{
    /**
     * Create a new profile composer.
     */
    public function __construct(
        protected UserRepository $users,
    ) {}

    /**
     * Bind data to the template.
     */
    public function compose(Template $template): void
    {
        $template->with('count', $this->users->count());
    }
}
```

As you can see, all template composers are resolved via the [service container](/v4/container), so you may type-hint any dependencies you need within a composer's constructor.

<a name="attaching-a-composer-to-multiple-templates"></a>
#### Attaching a Composer to Multiple Templates

You may attach a template composer to multiple templates at once by passing an array of templates as the first argument to the `composer` method:

```php
use App\Templates\Composers\MultiComposer;
use LaraGram\Support\Facades\Template;

Template::composer(
    ['profile', 'dashboard'],
    MultiComposer::class
);
```

The `composer` method also accepts the `*` character as a wildcard, allowing you to attach a composer to all templates:

```php
use LaraGram\Support\Facades;
use LaraGram\Template\Template;

Facades\Template::composer('*', function (Template $template) {
    // ...
});
```

<a name="template-creators"></a>
### Template Creators

Template "creators" are very similar to template composers; however, they are executed immediately after the template is instantiated instead of waiting until the template is about to render. To register a template creator, use the `creator` method:

```php
use App\Template\Creators\ProfileCreator;
use LaraGram\Support\Facades\Template;

Template::creator('profile', ProfileCreator::class);
```

<a name="optimizing-templates"></a>
## Optimizing Templates

By default, Template template templates are compiled on demand. When a request is executed that renders a template, LaraGram will determine if a compiled version of the template exists. If the file exists, LaraGram will then determine if the uncompiled template has been modified more recently than the compiled template. If the compiled template either does not exist, or the uncompiled template has been modified, LaraGram will recompile the template.

Compiling templates during the request may have a small negative impact on performance, so LaraGram provides the `template:cache` Commander command to precompile all of the templates utilized by your application. For increased performance, you may wish to run this command as part of your deployment process:

```shell
php laragram template:cache
```

You may use the `template:clear` command to clear the template cache:

```shell
php laragram template:clear
```
