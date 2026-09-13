# Views

<a name="introduction"></a>
## Introduction

Of course, it's not practical to return entire HTML documents strings directly from your routes and controllers. Thankfully, views provide a convenient way to place all of our HTML in separate files.

Views separate your controller / application logic from your presentation logic and are stored in the `resources/views` directory. When using LaraGram, view templates are usually written using the [Blade templating language](/v4/blade). A simple view might look something like this:

```blade
<!-- View stored in resources/views/greeting.blade.php -->

<html>
    <body>
        <h1>Hello, {{ $name }}</h1>
    </body>
</html>
```

Since this view is stored at `resources/views/greeting.blade.php`, we may return it using the global `view` helper like so:

```php
Route::get('/', function () {
    return view('greeting', ['name' => 'James']);
});
```

> [!NOTE]
> Looking for more information on how to write Blade templates? Check out the full [Blade documentation](/v4/blade) to get started.

<a name="writing-views-in-react-svelte-or-vue"></a>
### Writing Views in React / Svelte / Vue

Instead of writing their frontend templates in PHP via Blade, many developers have begun to prefer to write their templates using React, Svelte, or Vue. LaraGram makes this painless thanks to [Luna.js](https://laraxgram.github.io/v4/luna), a library that makes it a cinch to tie your React / Svelte / Vue frontend to your LaraGram backend without the typical complexities of building an SPA.

Our [React, Svelte, and Vue application starter kits](/v4/starter-kits) give you a great starting point for your next LaraGram application powered by Luna.

<a name="creating-and-rendering-views"></a>
## Creating and Rendering Views

You may create a view by placing a file with the `.blade.php` extension in your application's `resources/views` directory or by using the `make:view` Commander command:

```shell
php laragram make:view greeting
```

The `.blade.php` extension informs the framework that the file contains a [Blade template](/v4/blade). Blade templates contain HTML as well as Blade directives that allow you to easily echo values, create "if" statements, iterate over data, and more.

Once you have created a view, you may return it from one of your application's routes or controllers using the global `view` helper:

```php
Route::get('/', function () {
    return view('greeting', ['name' => 'James']);
});
```

Views may also be returned using the `View` facade:

```php
use LaraGram\Support\Facades\View;

return View::make('greeting', ['name' => 'James']);
```

As you can see, the first argument passed to the `view` helper corresponds to the name of the view file in the `resources/views` directory. The second argument is an array of data that should be made available to the view. In this case, we are passing the `name` variable, which is displayed in the view using [Blade syntax](/v4/blade).

<a name="nested-view-directories"></a>
### Nested View Directories

Views may also be nested within subdirectories of the `resources/views` directory. "Dot" notation may be used to reference nested views. For example, if your view is stored at `resources/views/admin/profile.blade.php`, you may return it from one of your application's routes / controllers like so:

```php
return view('admin.profile', $data);
```

> [!WARNING]
> View directory names should not contain the `.` character.

<a name="creating-the-first-available-view"></a>
### Creating the First Available View

Using the `View` facade's `first` method, you may create the first view that exists in a given array of views. This may be useful if your application or package allows views to be customized or overwritten:

```php
use LaraGram\Support\Facades\View;

return View::first(['custom.admin', 'admin'], $data);
```

<a name="determining-if-a-view-exists"></a>
### Determining if a View Exists

If you need to determine if a view exists, you may use the `View` facade. The `exists` method will return `true` if the view exists:

```php
use LaraGram\Support\Facades\View;

if (View::exists('admin.profile')) {
    // ...
}
```

<a name="passing-data-to-views"></a>
## Passing Data to Views

As you saw in the previous examples, you may pass an array of data to views to make that data available to the view:

```php
return view('greetings', ['name' => 'Victoria']);
```

When passing information in this manner, the data should be an array with key / value pairs. After providing data to a view, you can then access each value within your view using the data's keys, such as `<?php echo $name; ?>`.

As an alternative to passing a complete array of data to the `view` helper function, you may use the `with` method to add individual pieces of data to the view. The `with` method returns an instance of the view object so that you can continue chaining methods before returning the view:

```php
return view('greeting')
    ->with('name', 'Victoria')
    ->with('occupation', 'Astronaut');
```

<a name="sharing-data-with-all-views"></a>
### Sharing Data With All Views

Occasionally, you may need to share data with all views that are rendered by your application. You may do so using the `View` facade's `share` method. Typically, you should place calls to the `share` method within a service provider's `boot` method. You are free to add them to the `App\Providers\AppServiceProvider` class or generate a separate service provider to house them:

```php
<?php

namespace App\Providers;

use LaraGram\Support\Facades\View;

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
        View::share('key', 'value');
    }
}
```

<a name="view-composers"></a>
## View Composers

View composers are callbacks or class methods that are called when a view is rendered. If you have data that you want to be bound to a view each time that view is rendered, a view composer can help you organize that logic into a single location. View composers may prove particularly useful if the same view is returned by multiple routes or controllers within your application and always needs a particular piece of data.

Typically, view composers will be registered within one of your application's [service providers](/v4/providers). In this example, we'll assume that the `App\Providers\AppServiceProvider` will house this logic.

We'll use the `View` facade's `composer` method to register the view composer. LaraGram does not include a default directory for class-based view composers, so you are free to organize them however you wish. For example, you could create an `app/View/Composers` directory to house all of your application's view composers:

```php
<?php

namespace App\Providers;

use App\View\Composers\ProfileComposer;
use LaraGram\Support\Facades;
use LaraGram\Support\ServiceProvider;
use LaraGram\View\View;

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
        Facades\View::composer('profile', ProfileComposer::class);

        // Using closure-based composers...
        Facades\View::composer('welcome', function (View $view) {
            // ...
        });

        Facades\View::composer('dashboard', function (View $view) {
            // ...
        });
    }
}
```

Now that we have registered the composer, the `compose` method of the `App\View\Composers\ProfileComposer` class will be executed each time the `profile` view is being rendered. Let's take a look at an example of the composer class:

```php
<?php

namespace App\View\Composers;

use App\Repositories\UserRepository;
use LaraGram\View\View;

class ProfileComposer
{
    /**
     * Create a new profile composer.
     */
    public function __construct(
        protected UserRepository $users,
    ) {}

    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $view->with('count', $this->users->count());
    }
}
```

As you can see, all view composers are resolved via the [service container](/v4/container), so you may type-hint any dependencies you need within a composer's constructor.

<a name="attaching-a-composer-to-multiple-views"></a>
#### Attaching a Composer to Multiple Views

You may attach a view composer to multiple views at once by passing an array of views as the first argument to the `composer` method:

```php
use App\Views\Composers\MultiComposer;
use LaraGram\Support\Facades\View;

View::composer(
    ['profile', 'dashboard'],
    MultiComposer::class
);
```

The `composer` method also accepts the `*` character as a wildcard, allowing you to attach a composer to all views:

```php
use LaraGram\Support\Facades;
use LaraGram\View\View;

Facades\View::composer('*', function (View $view) {
    // ...
});
```

<a name="view-creators"></a>
### View Creators

View "creators" are very similar to view composers; however, they are executed immediately after the view is instantiated instead of waiting until the view is about to render. To register a view creator, use the `creator` method:

```php
use App\View\Creators\ProfileCreator;
use LaraGram\Support\Facades\View;

View::creator('profile', ProfileCreator::class);
```

<a name="optimizing-views"></a>
## Optimizing Views

By default, Blade template views are compiled on demand. When a request is executed that renders a view, LaraGram will determine if a compiled version of the view exists. If the file exists, LaraGram will then determine if the uncompiled view has been modified more recently than the compiled view. If the compiled view either does not exist, or the uncompiled view has been modified, LaraGram will recompile the view.

Compiling views during the request may have a small negative impact on performance, so LaraGram provides the `view:cache` Commander command to precompile all of the views utilized by your application. For increased performance, you may wish to run this command as part of your deployment process:

```shell
php laragram view:cache
```

You may use the `view:clear` command to clear the view cache:

```shell
php laragram view:clear
```
