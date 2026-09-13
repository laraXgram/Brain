# Template templates

<a name="introduction"></a>
## Introduction

Template is the simple, yet powerful templating engine that is included with LaraGram. Unlike some PHP templating engines, Template does not restrict you from using plain PHP code in your templates. In fact, all Template templates are compiled into plain PHP code and cached until they are modified, meaning Template adds essentially zero overhead to your application. Template template files use the `.t8.php` file extension and are typically stored in the `app/templates` directory.

Template templates may be returned from listens or controllers using the global `template` helper. Of course, as mentioned in the documentation on [templates](/v4/templates), data may be passed to the Template template using the `template` helper's second argument:

```php
Bot::onText('hi', function () {
    return template('greeting', ['name' => 'Finn']);
});
```

<a name="displaying-data"></a>
## Displaying Data

You may display data that is passed to your Template templates by wrapping the variable in curly braces. For example, given the following listen:

```php
Bot::onText('hi', function () {
    return template('welcome', ['name' => 'Samantha']);
});
```

You may display the contents of the `name` variable like so:

```blade
Hello, {{ $name }}.
```

> [!NOTE]
> Template's `{{ }}` echo statements are automatically sent through PHP's `htmlspecialchars` function.

You are not limited to displaying the contents of the variables passed to the template. You may also echo the results of any PHP function. In fact, you can put any PHP code you wish inside of a Template echo statement:

```blade
The current UNIX timestamp is {{ time() }}.
```

<a name="displaying-unescaped-data"></a>
#### Displaying Unescaped Data

By default, Template `{{ }}` statements are automatically sent through PHP's `htmlspecialchars` function. If you do not want your data to be escaped, you may use the following syntax:

```blade
Hello, {!! $name !!}.
```

<a name="temple8-directives"></a>
## Template Directives

In addition to template inheritance and displaying data, Template also provides convenient shortcuts for common PHP control structures, such as conditional statements and loops. These shortcuts provide a very clean, terse way of working with PHP control structures while also remaining familiar to their PHP counterparts.

<a name="inputs"></a>
### Inputs

You can write all Telegram method inputs as Template directives, making your templates clean and expressive.

```blade
@text()
Hello
@endText

@parse_mode(markdown)

@chat_id(123456789)
```

The `chat_id` input is optional. if not defined, it will default to the `chat_id` from the current request.

<a name="method-field"></a>
### Method Field

The `@method` Template directive change API request method:

```blade
@method('sendAnimation')
```

The `method` is optional. if not defined, it will default to the `sendMessage`.

<a name="keyboard-builder"></a>
### Keyboard Builder

Defining keyboards is also incredibly simple using the `@keyboard`, `@row`, and `@col` directives.

```blade
@keyboard()
    @row()
        @col('first', callback_data: 'first_btn')
        @col('second', callback_data: 'second_btn')
    @endRow
    @row()
        @col('return', callback_data: 'return_btn') 
    @endRow
@endKeyboard()
```

To specify the keyboard type, simply pass the desired type as an argument to the `@keyboard` directive. By default, the keyboard type is set to `inlineKeyboardMarkup`.

```blade
@keyboard(reply)

@endKeyboard()
```

<a name="available-keyboard-types"></a>
#### Available Keyboard Types

| directive parameter name | type                 |
|--------------------------|----------------------|
| `reply`                  | replyKeyboardMarkup  |
| `remove`                 | replyKeyboardRemove  |
| `force`                  | forceReply           |
| `inline`                 | inlineKeyboardMarkup |

<a name="dynamic-keyboards"></a>
#### Dynamic Keyboards

The `@keyboard` directive is compiled imperatively, so you may freely place control structures such as `@if`, `@foreach`, and `@switch` between the `@row` and `@col` directives to build a keyboard dynamically:

```blade
@keyboard()
    @foreach ($products as $product)
        @row()
            @col($product->name, callback_data: 'buy:' . $product->id)

            @if ($product->discounted)
                @col('🔥 ' . $product->discount . '%', callback_data: 'sale:' . $product->id)
            @endif
        @endRow
    @endforeach

    @row()
        @col('Close', callback_data: 'close_btn')
    @endRow
@endKeyboard()
```

Empty rows are skipped automatically, so a `@row` whose `@col` directives are all guarded by a condition that evaluates to `false` will not be added to the keyboard.

<a name="keyboard-options"></a>
#### Keyboard Options

You may set keyboard options such as `resize_keyboard` or `one_time_keyboard` using the `@keyboardOptions` directive. The given array is forwarded to the keyboard builder's `setOptions` method:

```blade
@keyboard(reply)
    @keyboardOptions(['resize_keyboard' => true, 'one_time_keyboard' => true])

    @row()
        @col('Share contact', request_contact: true)
    @endRow
@endKeyboard()
```

<a name="if-statements"></a>
### If Statements

You may construct `if` statements using the `@if`, `@elseif`, `@else`, and `@endif` directives. These directives function identically to their PHP counterparts:

```blade
@if (count($records) === 1)
    I have one record!
@elseif (count($records) > 1)
    I have multiple records!
@else
    I don't have any records!
@endif
```

For convenience, Template also provides an `@unless` directive:

```blade
@unless ($user->signed())
    You are not signed in.
@endunless
```

In addition to the conditional directives already discussed, the `@isset` and `@empty` directives may be used as convenient shortcuts for their respective PHP functions:

```blade
@isset($records)
    // $records is defined and is not null...
@endisset

@empty($records)
    // $records is "empty"...
@endempty
```

<a name="environment-directives"></a>
#### Environment Directives

You may check if the application is running in the production environment using the `@production` directive:

```blade
@production
    // Production specific content...
@endproduction
```

Or, you may determine if the application is running in a specific environment using the `@env` directive:

```blade
@env('staging')
    // The application is running in "staging"...
@endenv

@env(['staging', 'production'])
    // The application is running in "staging" or "production"...
@endenv
```

<a name="section-directives"></a>
#### Section Directives

You may determine if a template inheritance section has content using the `@hasSection` directive:

```blade
@hasSection('greeting')
    @text()
        Hello
    @endText()
@endif
```

You may use the `sectionMissing` directive to determine if a section does not have content:

```blade
@sectionMissing('greeting')
    @text()
        @include('default-greeting')
    @endText()
@endif
```

<a name="context-directives"></a>
#### Context Directives

The `@context` directive may be used to determine if a [context](/v4/context) value exists. If the context value exists, the template contents within the `@context` and `@endcontext` directives will be evaluated. Within the `@context` directive's contents, you may echo the `$value` variable to display the context value:

```blade
@context('canonical')
    
@endcontext
```

<a name="switch-statements"></a>
### Switch Statements

Switch statements can be constructed using the `@switch`, `@case`, `@break`, `@default` and `@endswitch` directives:

```blade
@switch($i)
    @case(1)
        First case...
        @break

    @case(2)
        Second case...
        @break

    @default
        Default case...
@endswitch
```

<a name="loops"></a>
### Loops

In addition to conditional statements, Template provides simple directives for working with PHP's loop structures. Again, each of these directives functions identically to their PHP counterparts:

```blade
@for ($i = 0; $i < 10; $i++)
    The current value is {{ $i }}
@endfor

@foreach ($users as $user)
    This is user {{ $user->id }}
@endforeach

@forelse ($users as $user)
    {{ $user->name }}
@empty
    No users
@endforelse

@while (true)
    I'm looping forever.
@endwhile
```

> [!NOTE]
> While iterating through a `foreach` loop, you may use the [loop variable](#the-loop-variable) to gain valuable information about the loop, such as whether you are in the first or last iteration through the loop.

When using loops you may also skip the current iteration or end the loop using the `@continue` and `@break` directives:

```blade
@foreach ($users as $user)
    @if ($user->type == 1)
        @continue
    @endif

    {{ $user->name }}

    @if ($user->number == 5)
        @break
    @endif
@endforeach
```

You may also include the continuation or break condition within the directive declaration:

```blade
@foreach ($users as $user)
    @continue($user->type == 1)

    {{ $user->name }}

    @break($user->number == 5)
@endforeach
```

<a name="the-loop-variable"></a>
### The Loop Variable

While iterating through a `foreach` loop, a `$loop` variable will be available inside of your loop. This variable provides access to some useful bits of information such as the current loop index and whether this is the first or last iteration through the loop:

```blade
@foreach ($users as $user)
    @if ($loop->first)
        This is the first iteration.
    @endif

    @if ($loop->last)
        This is the last iteration.
    @endif

    This is user {{ $user->id }}
@endforeach
```

If you are in a nested loop, you may access the parent loop's `$loop` variable via the `parent` property:

```blade
@foreach ($users as $user)
    @foreach ($user->posts as $post)
        @if ($loop->parent->first)
            This is the first iteration of the parent loop.
        @endif
    @endforeach
@endforeach
```

The `$loop` variable also contains a variety of other useful properties:

class="overflow-auto

| Property           | Description                                            |
| ------------------ | ------------------------------------------------------ |
| `$loop->index`     | The index of the current loop iteration (starts at 0). |
| `$loop->iteration` | The current loop iteration (starts at 1).              |
| `$loop->remaining` | The iterations remaining in the loop.                  |
| `$loop->count`     | The total number of items in the array being iterated. |
| `$loop->first`     | Whether this is the first iteration through the loop.  |
| `$loop->last`      | Whether this is the last iteration through the loop.   |
| `$loop->even`      | Whether this is an even iteration through the loop.    |
| `$loop->odd`       | Whether this is an odd iteration through the loop.     |
| `$loop->depth`     | The nesting level of the current loop.                 |
| `$loop->parent`    | When in a nested loop, the parent's loop variable.     |



<a name="including-subtemplates"></a>
### Including Subtemplates

> [!NOTE]
> While you're free to use the `@include` directive, Template [components](#components) provide similar functionality and offer several benefits over the `@include` directive such as data and attribute binding.

Template's `@include` directive allows you to include a Template template from within another template. All variables that are available to the parent template will be made available to the included template:

```blade
@text
    @include('shared.errors')

    Another Text
@endText
```

Even though the included template will inherit all data available in the parent template, you may also pass an array of additional data that should be made available to the included template:

```blade
@include('template.name', ['status' => 'complete'])
```

If you attempt to `@include` a template which does not exist, LaraGram will throw an error. If you would like to include a template that may or may not be present, you should use the `@includeIf` directive:

```blade
@includeIf('template.name', ['status' => 'complete'])
```

If you would like to `@include` a template if a given boolean expression evaluates to `true` or `false`, you may use the `@includeWhen` and `@includeUnless` directives:

```blade
@includeWhen($boolean, 'template.name', ['status' => 'complete'])

@includeUnless($boolean, 'template.name', ['status' => 'complete'])
```

To include the first template that exists from a given array of templates, you may use the `includeFirst` directive:

```blade
@includeFirst(['custom.admin', 'admin'], ['status' => 'complete'])
```

> [!WARNING]
> You should avoid using the `__DIR__` and `__FILE__` constants in your Template templates, since they will refer to the location of the cached, compiled template.

<a name="rendering-templates-for-collections"></a>
#### Rendering Templates for Collections

You may combine loops and includes into one line with Template's `@each` directive:

```blade
@each('template.name', $jobs, 'job')
```

The `@each` directive's first argument is the template to render for each element in the array or collection. The second argument is the array or collection you wish to iterate over, while the third argument is the variable name that will be assigned to the current iteration within the template. So, for example, if you are iterating over an array of `jobs`, typically you will want to access each job as a `job` variable within the template. The array key for the current iteration will be available as the `key` variable within the template.

You may also pass a fourth argument to the `@each` directive. This argument determines the template that will be rendered if the given array is empty.

```blade
@each('template.name', $jobs, 'job', 'template.empty')
```

> [!WARNING]
> Templates rendered via `@each` do not inherit the variables from the parent template. If the child template requires these variables, you should use the `@foreach` and `@include` directives instead.

<a name="the-once-directive"></a>
### The `@once` Directive

The `@once` directive allows you to define a portion of the template that will only be evaluated once per rendering cycle. For example, if you are rendering a given [component](#components) within a loop, you may wish to only push the Text to the header the first time the component is rendered:

```blade
@once
    @push('greeting')
        
    @endpush
@endonce
```

Since the `@once` directive is often used in conjunction with the `@push` or `@prepend` directives, the `@pushOnce` and `@prependOnce` directives are available for your convenience:

```blade
@pushOnce('greeting')

@endPushOnce
```

<a name="raw-php"></a>
### Raw PHP

In some situations, it's useful to embed PHP code into your templates. You can use the Template `@php` directive to execute a block of plain PHP within your template:

```blade
@php
    $counter = 1;
@endphp
```

Or, if you only need to use PHP to import a class, you may use the `@use` directive:

```blade
@use('App\Models\Flight')
```

A second argument may be provided to the `@use` directive to alias the imported class:

```blade
@use('App\Models\Flight', 'FlightModel')
```

If you have multiple classes within the same namespace, you may group the imports of those classes:

```blade
@use('App\Models\{Flight, Airport}')
```

The `@use` directive also supports importing PHP functions and constants by prefixing the import path with the `function` or `const` modifiers:

```blade
@use(function App\Helpers\format_currency)
@use(const App\Constants\MAX_ATTEMPTS)
```

Just like class imports, aliases are supported for functions and constants as well:

```blade
@use(function App\Helpers\format_currency, 'formatMoney')
@use(const App\Constants\MAX_ATTEMPTS, 'MAX_TRIES')
```

Grouped imports are also supported with both function and const modifiers, allowing you to import multiple symbols from the same namespace in a single directive:

```blade
@use(function App\Helpers\{format_currency, format_date})
@use(const App\Constants\{MAX_ATTEMPTS, DEFAULT_TIMEOUT})
```

<a name="comments"></a>
### Comments

Template also allows you to define comments in your templates. However, unlike PHP comments, Template comments are not included in the request by your application:

```blade
{{-- This comment will not be present in the rendered --}}
```

<a name="components"></a>
## Components

Components and slots provide similar benefits to sections, layouts, and includes; however, some may find the mental model of components and slots easier to understand. There are two approaches to writing components: class-based components and anonymous components.

To create a class-based component, you may use the `make:component` Commander command. To illustrate how to use components, we will create a simple `Alert` component. The `make:component` command will place the component in the `app/Template/Components` directory:

```shell
php laragram make:component Alert
```

The `make:component` command will also create a template template for the component. The template will be placed in the `app/templates/components` directory. When writing components for your own application, components are automatically discovered within the `app/Template/Components` directory and `app/templates/components` directory, so no further component registration is typically required.

You may also create components within subdirectories:

```shell
php laragram make:component Forms/Input
```

The command above will create an `Input` component in the `app/Template/Components/Forms` directory and the template will be placed in the `app/templates/components/forms` directory.

If you would like to create an anonymous component (a component with only a Template template and no class), you may use the `--template` flag when invoking the `make:component` command:

```shell
php laragram make:component forms.input --template
```

The command above will create a Template file at `app/templates/components/forms/input.t8.php` which can be rendered as a component via `<x-forms.input />`.

<a name="manually-registering-package-components"></a>
#### Manually Registering Package Components

When writing components for your own application, components are automatically discovered within the `app/Template/Components` directory and `app/templates/components` directory.

However, if you are building a package that utilizes Template components, you will need to manually register your component class. You should typically register your components in the `boot` method of your package's service provider:

```php
use LaraGram\Support\Facades\Template;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Template::component('package-alert', Alert::class);
}
```

Once your component has been registered, it may be rendered using its tag alias:

```blade
<x-package-alert/>
```

Alternatively, you may use the `componentNamespace` method to autoload component classes by convention. For example, a `Nightshade` package might have `Calendar` and `ColorPicker` components that reside within the `Package\Templates\Components` namespace:

```php
use LaraGram\Support\Facades\Template;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Template::componentNamespace('Nightshade\\Templates\\Components', 'nightshade');
}
```

This will allow the usage of package components by their vendor namespace using the `package-name::` syntax:

```blade
<x-nightshade::calendar />
<x-nightshade::color-picker />
```

Template will automatically detect the class that's linked to this component by pascal-casing the component name. Subdirectories are also supported using "dot" notation.

<a name="rendering-components"></a>
### Rendering Components

To display a component, you may use a Template component tag within one of your Template templates. Template component tags start with the string `x-` followed by the kebab case name of the component class:

```blade
<x-alert/>

<x-user-profile/>
```

If the component class is nested deeper within the `app/Template/Components` directory, you may use the `.` character to indicate directory nesting. For example, if we assume a component is located at `app/Template/Components/Inputs/Button.php`, we may render it like so:

```blade
<x-inputs.button/>
```

If you would like to conditionally render your component, you may define a `shouldRender` method on your component class. If the `shouldRender` method returns `false` the component will not be rendered:

```php
use LaraGram\Support\Str;

/**
 * Whether the component should be rendered
 */
public function shouldRender(): bool
{
    return Str::length($this->message) > 0;
}
```

<a name="disable-request"></a>
### Disable request

Each template file is automatically converted into a Telegram request. However, when using components, multiple template files may be called simultaneously, which can cause conflicts between requests.
By placing the phrase `<!-- !component! -->` at the beginning of a template file, that file will not be converted into a Telegram request.

```php
<!-- !component! -->

<x-alert :message="$message"/>

@keyboard()
    @row()
        @col('Ok', callback_data: 'ok_btn')
    @endRow()
@endKeyboard()
```

<a name="index-components"></a>
### Index Components

Sometimes components are part of a component group and you may wish to group the related components within a single directory. For example, imagine a "card" component with the following class structure:

```text
App\Templates\Components\Card\Card
App\Templates\Components\Card\Header
App\Templates\Components\Card\Body
```

Since the root `Card` component is nested within a `Card` directory, you might expect that you would need to render the component via `<x-card.card>`. However, when a component's file name matches the name of the component's directory, LaraGram automatically assumes that component is the "root" component and allows you to render the component without repeating the directory name:

```blade
<x-card>
    <x-card.header>...</x-card.header>
    <x-card.body>...</x-card.body>
</x-card>
```

<a name="passing-data-to-components"></a>
### Passing Data to Components

You may pass data to Template components using HTML attributes. Hard-coded, primitive values may be passed to the component using simple HTML attribute strings. PHP expressions and variables should be passed to the component via attributes that use the `:` character as a prefix:

```blade
<x-alert :message="$message"/>
```

You should define all of the component's data attributes in its class constructor. All public properties on a component will automatically be made available to the component's template. It is not necessary to pass the data to the template from the component's `render` method:

```php
<?php

namespace App\Template\Components;

use LaraGram\Template\Component;
use LaraGram\Template\Template;

class Alert extends Component
{
    /**
     * Create the component instance.
     */
    public function __construct(
        public string $message,
    ) {}

    /**
     * Get the template / contents that represent the component.
     */
    public function render(): Template
    {
        return template('components.alert');
    }
}
```

When your component is rendered, you may display the contents of your component's public variables by echoing the variables by name:

```blade
{{ $message }}
```

<a name="casing"></a>
#### Casing

Component constructor arguments should be specified using `camelCase`, while `kebab-case` should be used when referencing the argument names in your component attributes. For example, given the following component constructor:

```php
/**
 * Create the component instance.
 */
public function __construct(
    public string $alertType,
) {}
```

The `$alertType` argument may be provided to the component like so:

```blade
<x-alert alert-type="danger" />
```

<a name="short-attribute-syntax"></a>
#### Short Attribute Syntax

When passing attributes to components, you may also use a "short attribute" syntax. This is often convenient since attribute names frequently match the variable names they correspond to:

```blade
{{-- Short attribute syntax... --}}
<x-profile :$userId :$name />

{{-- Is equivalent to... --}}
<x-profile :user-id="$userId" :name="$name" />
```

<a name="escaping-attribute-rendering"></a>
#### Escaping Attribute Rendering

You also use colon-prefixed attributes, you may use a double colon (`::`) prefix to inform Template that the attribute is not a PHP expression. For example, given the following component:

```blade
<x-button ::type="{ reply: markup }">
    Submit
</x-button>
```

<a name="additional-dependencies"></a>
#### Additional Dependencies

If your component requires dependencies from LaraGram's [service container](/v4/container), you may list them before any of the component's data attributes and they will automatically be injected by the container:

```php
use App\Services\AlertCreator;

/**
 * Create the component instance.
 */
public function __construct(
    public AlertCreator $creator,
    public string $type,
    public string $message,
) {}
```

<a name="hiding-attributes-and-methods"></a>
#### Hiding Attributes / Methods

If you would like to prevent some public methods or properties from being exposed as variables to your component template, you may add them to an `$except` array property on your component:

```php
<?php

namespace App\Template\Components;

use LaraGram\Template\Component;

class Alert extends Component
{
    /**
     * The properties / methods that should not be exposed to the component template.
     *
     * @var array
     */
    protected $except = ['type'];

    /**
     * Create the component instance.
     */
    public function __construct(
        public string $type,
    ) {}
}
```

<a name="filtering-attributes"></a>
#### Retrieving and Filtering Attributes

You may filter attributes using the `filter` method. This method accepts a closure which should return `true` if you wish to retain the attribute in the attribute bag:

```blade
{{ $attributes->filter(fn (string $value, string $key) => $key == 'foo') }}
```

For convenience, you may use the `whereStartsWith` method to retrieve all attributes whose keys begin with a given string:

```blade
{{ $attributes->whereStartsWith('type:replymarkup') }}
```

Conversely, the `whereDoesntStartWith` method may be used to exclude all attributes whose keys begin with a given string:

```blade
{{ $attributes->whereDoesntStartWith('type:replymarkup') }}
```

Using the `first` method, you may render the first attribute in a given attribute bag:

```blade
{{ $attributes->whereStartsWith('type:replymarkup')->first() }}
```

If you would like to check if an attribute is present on the component, you may use the `has` method. This method accepts the attribute name as its only argument and returns a boolean indicating whether or not the attribute is present:

```blade
@if ($attributes->has('type'))
    Class attribute is present
@endif
```

If an array is passed to the `has` method, the method will determine if all of the given attributes are present on the component:

```blade
@if ($attributes->has(['name', 'type']))
    All of the attributes are present
@endif
```

The `hasAny` method may be used to determine if any of the given attributes are present on the component:

```blade
@if ($attributes->hasAny(['type', ':type', 'v-bind:type']))
    One of the attributes is present
@endif
```

You may retrieve a specific attribute's value using the `get` method:

```blade
{{ $attributes->get('type') }}
```

The `only` method may be used to retrieve only the attributes with the given keys:

```blade
{{ $attributes->only(['type']) }}
```

The `except` method may be used to retrieve all attributes except those with the given keys:

```blade
{{ $attributes->except(['type']) }}
```

<a name="reserved-keywords"></a>
### Reserved Keywords

By default, some keywords are reserved for Template's internal use in order to render components. The following keywords cannot be defined as public properties or method names within your components:

class="content-list" markdown="1

- `data`
- `render`
- `resolveTemplate`
- `shouldRender`
- `template`
- `withAttributes`
- `withName`



<a name="slots"></a>
### Slots

You will often need to pass additional content to your component via "slots". Component slots are rendered by echoing the `$slot` variable. To explore this concept, let's imagine that an `alert` component has the following markup:

```blade
<!-- /app/templates/components/alert.t8.php -->

{{ $slot }}

```

We may pass content to the `slot` by injecting content into the component:

```blade
<x-alert>
    <b>Whoops!</strong> Something went wrong!
</x-alert>
```

Sometimes a component may need to render multiple different slots in different locations within the component. Let's modify our alert component to allow for the injection of a "title" slot:

```blade
<!-- /app/templates/components/alert.t8.php -->

{{ $title }}
-------------

{{ $slot }}

```

You may define the content of the named slot using the `x-slot` tag. Any content not within an explicit `x-slot` tag will be passed to the component in the `$slot` variable:

```xml
<x-alert>
    <x-slot:title>
        Server Error
    </x-slot>

    <b>Whoops!</b> Something went wrong!
</x-alert>
```

You may invoke a slot's `isEmpty` method to determine if the slot contains content:

```blade
{ $title }}

@if ($slot->isEmpty())
    This is default content if the slot is empty.
@else
    {{ $slot }}
@endif

```

Additionally, the `hasActualContent` method may be used to determine if the slot contains any "actual" content that is not an comment:

```blade
@if ($slot->hasActualContent())
    The scope has non-comment content.
@endif
```

<a name="scoped-slots"></a>
#### Scoped Slots

If you have used a JavaScript framework such as Vue, you may be familiar with "scoped slots", which allow you to access data or methods from the component within your slot. You may achieve similar behavior in LaraGram by defining public methods or properties on your component and accessing the component within your slot via the `$component` variable. In this example, we will assume that the `x-alert` component has a public `formatAlert` method defined on its component class:

```blade
<x-alert>
    <x-slot:title>
        {{ $component->formatAlert('Server Error') }}
    </x-slot>

    <strong>Whoops!</strong> Something went wrong!
</x-alert>
```

<a name="inline-component-templates"></a>
### Inline Component Templates

For very small components, it may feel cumbersome to manage both the component class and the component's template template. For this reason, you may return the component's markup directly from the `render` method:

```php
/**
 * Get the template / contents that represent the component.
 */
public function render(): string
{
    return <<<'t8'
            {{ $slot }}
    t8;
}
```

<a name="generating-inline-template-components"></a>
#### Generating Inline Template Components

To create a component that renders an inline template, you may use the `inline` option when executing the `make:component` command:

```shell
php laragram make:component Alert --inline
```

<a name="dynamic-components"></a>
### Dynamic Components

Sometimes you may need to render a component but not know which component should be rendered until runtime. In this situation, you may use LaraGram's built-in `dynamic-component` component to render the component based on a runtime value or variable:

```blade
// $componentName = "secondary-button";

<x-dynamic-component :component="$componentName" class="mt-4" />
```

<a name="manually-registering-components"></a>
### Manually Registering Components

> [!WARNING]
> The following documentation on manually registering components is primarily applicable to those who are writing LaraGram packages that include template components. If you are not writing a package, this portion of the component documentation may not be relevant to you.

When writing components for your own application, components are automatically discovered within the `app/Template/Components` directory and `app/templates/components` directory.

However, if you are building a package that utilizes Template components or placing components in non-conventional directories, you will need to manually register your component class so that LaraGram knows where to find the component. You should typically register your components in the `boot` method of your package's service provider:

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

#### Autoloading Package Components

Alternatively, you may use the `componentNamespace` method to autoload component classes by convention. For example, a `Nightshade` package might have `Calendar` and `ColorPicker` components that reside within the `Package\Templates\Components` namespace:

```php
use LaraGram\Support\Facades\Template;

/**
 * Bootstrap your package's services.
 */
public function boot(): void
{
    Template::componentNamespace('Nightshade\\Templates\\Components', 'nightshade');
}
```

This will allow the usage of package components by their vendor namespace using the `package-name::` syntax:

```blade
<x-nightshade::calendar />
<x-nightshade::color-picker />
```

Template will automatically detect the class that's linked to this component by pascal-casing the component name. Subdirectories are also supported using "dot" notation.

<a name="anonymous-components"></a>
## Anonymous Components

Similar to inline components, anonymous components provide a mechanism for managing a component via a single file. However, anonymous components utilize a single template file and have no associated class. To define an anonymous component, you only need to place a Template template within your `app/templates/components` directory. For example, assuming you have defined a component at `app/templates/components/alert.t8.php`, you may simply render it like so:

```blade
<x-alert/>
```

You may use the `.` character to indicate if a component is nested deeper inside the `components` directory. For example, assuming the component is defined at `app/templates/components/inputs/button.t8.php`, you may render it like so:

```blade
<x-inputs.button/>
```

<a name="anonymous-index-components"></a>
### Anonymous Index Components

Sometimes, when a component is made up of many Template templates, you may wish to group the given component's templates within a single directory. For example, imagine an "accordion" component with the following directory structure:

```text
/app/templates/components/accordion.t8.php
/app/templates/components/accordion/item.t8.php
```

This directory structure allows you to render the accordion component and its item like so:

```blade
<x-accordion>
    <x-accordion.item>
        ...
    </x-accordion.item>
</x-accordion>
```

However, in order to render the accordion component via `x-accordion`, we were forced to place the "index" accordion component template in the `app/templates/components` directory instead of nesting it within the `accordion` directory with the other accordion related templates.

Thankfully, Template allows you to place a file matching the component's directory name within the component's directory itself. When this template exists, it can be rendered as the "root" element of the component even though it is nested within a directory. So, we can continue to use the same Template syntax given in the example above; however, we will adjust our directory structure like so:

```text
/app/templates/components/accordion/accordion.t8.php
/app/templates/components/accordion/item.t8.php
```

<a name="anonymous-component-paths"></a>
### Anonymous Component Paths

As previously discussed, anonymous components are typically defined by placing a Template template within your `app/templates/components` directory. However, you may occasionally want to register other anonymous component paths with LaraGram in addition to the default path.

The `anonymousComponentPath` method accepts the "path" to the anonymous component location as its first argument and an optional "namespace" that components should be placed under as its second argument. Typically, this method should be called from the `boot` method of one of your application's [service providers](/v4/providers):

```php
/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Template::anonymousComponentPath(__DIR__.'/../components');
}
```

When component paths are registered without a specified prefix as in the example above, they may be rendered in your Template components without a corresponding prefix as well. For example, if a `panel.t8.php` component exists in the path registered above, it may be rendered like so:

```blade
<x-panel />
```

Prefix "namespaces" may be provided as the second argument to the `anonymousComponentPath` method:

```php
Template::anonymousComponentPath(__DIR__.'/../components', 'dashboard');
```

When a prefix is provided, components within that "namespace" may be rendered by prefixing to the component's namespace to the component name when the component is rendered:

```blade
<x-dashboard::panel />
```

<a name="building-layouts"></a>
## Building Layouts

<a name="layouts-using-components"></a>
### Layouts Using Components

Most bot applications maintain the same general layout across various pages. It would be incredibly cumbersome and hard to maintain our application if we had to repeat the entire layout in every template we create. Thankfully, it's convenient to define this layout as a single [Template component](#components) and then use it throughout our application.

<a name="applying-the-layout-component"></a>
#### Applying the Layout Component

Once the `layout` component has been defined, we may create a Template template that utilizes the component. In this example, we will define a simple template that displays our task list:

```blade
<!-- app/templates/tasks.t8.php -->

<x-layout>
    @foreach ($tasks as $task)
        {{ $task }}
    @endforeach
</x-layout>
```

Remember, content that is injected into a component will be supplied to the default `$slot` variable within our `layout` component. As you may have noticed, our `layout` also respects a `$title` slot if one is provided; otherwise, a default title is shown. We may inject a custom title from our task list template using the standard slot syntax discussed in the [component documentation](#components):

```blade
<!-- app/templates/tasks.t8.php -->

<x-layout>
    <x-slot:title>
        Custom Title
    </x-slot>

    @foreach ($tasks as $task)
        {{ $task }}
    @endforeach
</x-layout>
```

Now that we have defined our layout and task list templates, we just need to return the `task` template from a listen:

```php
use App\Models\Task;

Bot::onText('/tasks', function () {
    return template('tasks', ['tasks' => Task::all()]);
});
```

<a name="layouts-using-template-inheritance"></a>
### Layouts Using Template Inheritance

<a name="defining-a-layout"></a>
#### Defining a Layout

Layouts may also be created via "template inheritance". This was the primary way of building applications prior to the introduction of [components](#components).

To get started, let's take a look at a simple example. First, we will examine a page layout. Since most bot applications maintain the same general layout across various pages, it's convenient to define this layout as a single Template template:

```blade
<!-- app/templates/layouts/app.t8.php -->

@yield('title')
-------------------

@yield('content')
```

As you can see, this file contains typical mark-up. However, take note of the `@section` and `@yield` directives. The `@section` directive, as the name implies, defines a section of content, while the `@yield` directive is used to display the contents of a given section.

Now that we have defined a layout for our application, let's define a child page that inherits the layout.

<a name="extending-a-layout"></a>
#### Extending a Layout

When defining a child template, use the `@extends` Template directive to specify which layout the child template should "inherit". Templates which extend a Template layout may inject content into the layout's sections using `@section` directives. Remember, as seen in the example above, the contents of these sections will be displayed in the layout using `@yield`:

```blade
<!-- app/templates/child.t8.php -->

@extends('layouts.app')

@section('title', 'Page Title')

@section('content')
    This is my body content.
@endsection
```

The `@yield` directive also accepts a default value as its second parameter. This value will be rendered if the section being yielded is undefined:

```blade
@yield('content', 'Default content')
```

<a name="validation-errors"></a>
### Validation Errors

The `@error` directive may be used to quickly check if [validation error messages](/v4/validation#quick-displaying-the-validation-errors) exist for a given attribute. Within an `@error` directive, you may echo the `$message` variable to display the error message:

```blade
<!-- /app/templates/post/create.t8.php -->

@error('title')
    
@enderror
```

Since the `@error` directive compiles to an "if" statement, you may use the `@else` directive to render content when there is not an error for an attribute.

You may pass [the name of a specific error bag](/v4/validation#named-error-bags) as the second parameter to the `@error` directive to retrieve validation error messages on pages containing multiple forms:

```blade
<!-- /app/templates/auth.t8.php -->

@error('email', 'login')
    
@enderror
```

<a name="stacks"></a>
## Stacks

Template allows you to push to named stacks which can be rendered somewhere else in another template or layout.

```blade
@push('head')
    
@endpush
```

If you would like to `@push` content if a given boolean expression evaluates to `true`, you may use the `@pushIf` directive:

```blade
@pushIf($shouldPush, 'head')
    
@endPushIf
```

You may push to a stack as many times as needed. To render the complete stack contents, pass the name of the stack to the `@stack` directive:

```blade
@stack('head')
```

If you would like to prepend content onto the beginning of a stack, you should use the `@prepend` directive:

```blade
@push('head')
    This will be second...
@endpush

// Later...

@prepend('head')
    This will be first...
@endprepend
```

<a name="service-injection"></a>
## Service Injection

The `@inject` directive may be used to retrieve a service from the LaraGram [service container](/v4/container). The first argument passed to `@inject` is the name of the variable the service will be placed into, while the second argument is the class or interface name of the service you wish to resolve:

```blade
@inject('metrics', 'App\Services\MetricsService')

Monthly Revenue: {{ $metrics->monthlyRevenue() }}.
```

<a name="rendering-inline-temple8-templates"></a>
## Rendering Inline Template templates

Sometimes you may need to transform a raw Template template string into valid PHP code. You may accomplish this using the `render` method provided by the `Template` facade. The `render` method accepts the Template template string and an optional array of data to provide to the template:

```php
use LaraGram\Support\Facades\Template;

return Template::render('Hello, {{ $name }}', ['name' => 'Julian Bashir']);
```

LaraGram renders inline Template templates by writing them to the `storage/framework/templates` directory. If you would like LaraGram to remove these temporary files after rendering the Template template, you may provide the `deleteCachedTemplate` argument to the method:

```php
return Template::render(
    'Hello, {{ $name }}',
    ['name' => 'Julian Bashir'],
    deleteCachedTemplate: true
);
```

<a name="rendering-temple8-fragments"></a>
## Rendering Template Fragments

```blade
@fragment('user-list')
    @foreach ($users as $user)
        {{ $user->name }}
    @endforeach
@endfragment
```

Then, when rendering the template that utilizes this template, you may invoke the `fragment` method to specify that only the specified fragment should be included in the outgoing HTTP response:

```php
return template('dashboard', ['users' => $users])->fragment('user-list');
```

The `fragmentIf` method allows you to conditionally return a fragment of a template based on a given condition. Otherwise, the entire template will be returned:

```php
return template('dashboard', ['users' => $users])
    ->fragmentIf($request->user()->id(), 'xxx');
```

The `fragments` and `fragmentsIf` methods allow you to return multiple template fragments in the response. The fragments will be concatenated together:

```php
template('dashboard', ['users' => $users])
    ->fragments(['user-list', 'comment-list']);

template('dashboard', ['users' => $users])
    ->fragmentsIf(
        $request->user()->id(),
        ['xxx', 'yyy']
    );
```

<a name="extending-temple8"></a>
## Extending Template

Template allows you to define your own custom directives using the `directive` method. When the Template compiler encounters the custom directive, it will call the provided callback with the expression that the directive contains.

The following example creates a `@datetime($var)` directive which formats a given `$var`, which should be an instance of `DateTime`:

```php
<?php

namespace App\Providers;

use LaraGram\Support\Facades\Template;
use LaraGram\Support\ServiceProvider;

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
        Template::directive('datetime', function (string $expression) {
            return "<?php echo ($expression)->format('m/d/Y H:i'); ?>";
        });
    }
}
```

As you can see, we will chain the `format` method onto whatever expression is passed into the directive. So, in this example, the final PHP generated by this directive will be:

```php
<?php echo ($var)->format('m/d/Y H:i'); ?>
```

> [!WARNING]
> After updating the logic of a Template directive, you will need to delete all of the cached Template templates. The cached Template templates may be removed using the `template:clear` Commander command.

<a name="custom-echo-handlers"></a>
### Custom Echo Handlers

If you attempt to "echo" an object using Template, the object's `__toString` method will be invoked. The [__toString](https://www.php.net/manual/en/language.oop5.magic.php#object.tostring) method is one of PHP's built-in "magic methods". However, sometimes you may not have control over the `__toString` method of a given class, such as when the class that you are interacting with belongs to a third-party library.

In these cases, Template allows you to register a custom echo handler for that particular type of object. To accomplish this, you should invoke Template's `stringable` method. The `stringable` method accepts a closure. This closure should type-hint the type of object that it is responsible for rendering. Typically, the `stringable` method should be invoked within the `boot` method of your application's `AppServiceProvider` class:

```php
use LaraGram\Support\Facades\Template;
use Money\Money;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Template::stringable(function (Money $money) {
        return $money->formatTo('en_GB');
    });
}
```

Once your custom echo handler has been defined, you may simply echo the object in your Template template:

```blade
Cost: {{ $money }}
```

<a name="custom-if-statements"></a>
### Custom If Statements

Programming a custom directive is sometimes more complex than necessary when defining simple, custom conditional statements. For that reason, Template provides a `Template::if` method which allows you to quickly define custom conditional directives using closures. For example, let's define a custom conditional that checks the configured default "disk" for the application. We may do this in the `boot` method of our `AppServiceProvider`:

```php
use LaraGram\Support\Facades\Template;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Template::if('disk', function (string $value) {
        return config('filesystems.default') === $value;
    });
}
```

Once the custom conditional has been defined, you can use it within your templates:

```blade
@disk('local')
    <!-- The application is using the local disk... -->
@elsedisk('public')
    <!-- The application is using the s3 disk... -->
@else
    <!-- The application is using some other disk... -->
@enddisk

@unlessdisk('local')
    <!-- The application is not using the local disk... -->
@enddisk
```
