# Validation

<a name="introduction"></a>
## Introduction

LaraGram provides several different approaches to validate your application's incoming data. It is most common to use the `validate` method available on all incoming HTTP requests. However, we will discuss other approaches to validation as well.

LaraGram includes a wide variety of convenient validation rules that you may apply to data, even providing the ability to validate if values are unique in a given database table. We'll cover each of these validation rules in detail so that you are familiar with all of LaraGram's validation features.

<a name="validating-telegram-updates"></a>
## Validating Telegram Updates

The `validate` method available on the `LaraGram\Request\Request` instance lets you validate the incoming Telegram update directly. Because an update is a nested structure (`message`, `chat`, `from`, ...), you may target any field using "dot" notation:

```php
use LaraGram\Request\Request;

Bot::onText('register {name}', function (Request $request) {
    $validated = $request->validate([
        'message.text' => 'required|string|max:255',
        'message.chat.id' => 'required|integer',
    ]);

    // ...
});
```

If the rules pass, execution continues normally. If validation fails, a `LaraGram\Validation\ValidationException` is thrown.

The rules may also be specified as arrays of rules instead of a single `|` delimited string:

```php
$validated = $request->validate([
    'message.text' => ['required', 'string', 'max:255'],
]);
```

To store the error messages in a [named error bag](#named-error-bags), use the `validateWithBag` method:

```php
$validated = $request->validateWithBag('post', [
    'message.text' => 'required|string|max:255',
]);
```

The data returned by `validate` is a `LaraGram\Request\ValidatedInput` instance. See [Working With Validated Input](#working-with-validated-input) for details on how to read it.

<a name="validation-quickstart"></a>
## Validation Quickstart

To learn about LaraGram's powerful validation features, let's look at a complete example of validating a form and displaying the error messages back to the user. By reading this high-level overview, you'll be able to gain a good general understanding of how to validate incoming request data using LaraGram:

<a name="quick-defining-the-listens"></a>
### Defining the Listens

First, let's assume we have the following listens defined in our `listens/bot.php` file:

```php
use App\Controllers\PostController;

Bot::onText('create post', [PostController::class, 'create']);
```

The `TEXT` listen will display a form for the user to create a new blog post, and will store the new blog post in the database.

<a name="quick-creating-the-controller"></a>
### Creating the Controller

Next, let's take a look at a simple controller that handles incoming requests to these listens. We'll leave the `store` method empty for now:

```php
<?php

namespace App\Controllers;

use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;
use LaraGram\Tempalte\Tempalte;

class PostController extends Controller
{
    /**
     * Show the form to create a new blog post.
     */
    public function create(): Tempalte
    {
        return template('post.create');
    }

    /**
     * Store a new blog post.
     */
    public function store(Request $request): RedirectResponse
    {
        // Validate and store the blog post...

        $post = /** ... */

        return to_listen('post.show', ['post' => $post->id]);
    }
}
```

<a name="quick-writing-the-validation-logic"></a>
### Writing the Validation Logic

Now we are ready to fill in our `store` method with the logic to validate the new blog post. To do this, we will use the `validate` method provided by the `LaraGram\Request\Request` object. If the validation rules pass, your code will keep executing normally; however, if validation fails, an `LaraGram\Validation\ValidationException` exception will be thrown and the proper error response will automatically be sent back to the user.

If validation fails during a traditional HTTP request, a redirect response to the previous URL will be generated. If the incoming request is an XHR request, a [JSON response containing the validation error messages](#validation-error-response-format) will be returned.

To get a better understanding of the `validate` method, let's jump back into the `store` method:

```php
ues LaraGram\Support\Facades\Validator;

/**
 * Store a new blog post.
 */
public function store(Request $request, $title, $body): RedirectResponse
{
    $validator = Validator::make([
        'title' => $title,
        'body' => $body,
    ], [
        'title' => 'required|unique:posts|max:255',
        'body' => 'required',
    ]);

    if (!$validator->fails()){
        return to_listen('posts');
    }
    
    // ...
}
```

As you can see, the validation rules are passed into the `make` method. Don't worry - all available validation rules are [documented](#available-validation-rules). Again, if the validation fails, the proper response will automatically be generated. If the validation passes, our controller will continue executing normally.

Alternatively, validation rules may be specified as arrays of rules instead of a single `|` delimited string:

```php
$rules = [
    'title' => ['required', 'unique:posts', 'max:255'],
    'body' => ['required'],
];
```

<a name="quick-displaying-the-validation-errors"></a>
### Displaying the Validation Errors

So, what if the incoming request fields do not pass the given validation rules? As mentioned previously, LaraGram will automatically redirect the user back to their previous location. In addition, all of the validation errors will automatically be [putted to the cache](/v4/cache#storing-items-in-the-cache).

An `$errors` variable is shared with all of your application's templates, which is provided by the `bot` middleware group. When this middleware is applied an `$errors` variable will always be available in your templates, allowing you to conveniently assume the `$errors` variable is always defined and can be safely used. The `$errors` variable will be an instance of `LaraGram\Support\MessageBag`. For more information on working with this object, [check out its documentation](#working-with-error-messages).

So, in our example, the user will be redirected to our controller's `create` method when validation fails, allowing us to display the error messages in the view:

```blade
<!-- /resources/templates/post/create.t8.php -->

***Create Post***

@if ($errors->any())
    @foreach ($errors->all() as $error)
        {{ $error }}
    @endforeach
@endif

<!-- Create Post Form -->
```

<a name="quick-customizing-the-error-messages"></a>
#### Customizing the Error Messages

LaraGram's built-in validation rules each have an error message that is located in your application's `lang/en/validation.php` file. If your application does not have a `lang` directory, you may instruct LaraGram to create it using the `lang:publish` Commander command.

Within the `lang/en/validation.php` file, you will find a translation entry for each validation rule. You are free to change or modify these messages based on the needs of your application.

In addition, you may copy this file to another language directory to translate the messages for your application's language. To learn more about LaraGram localization, check out the complete [localization documentation](/v4/localization).

> [!WARNING]
> By default, the LaraGram application skeleton does not include the `lang` directory. If you would like to customize LaraGram's language files, you may publish them via the `lang:publish` Commander command.

<a name="the-at-error-directive"></a>
#### The `@error` Directive

You may use the `@error` [temple8](/v4/temple8) directive to quickly determine if validation error messages exist for a given attribute. Within an `@error` directive, you may echo the `$message` variable to display the error message:

```blade
<!-- /resources/templates/post/create.t8.php -->

@error('title')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
```
<a name="validation-error-response-format"></a>
### Validation Error Response Format

When your application throws a `LaraGram\Validation\ValidationException` exception and the incoming HTTP request is expecting a JSON response, LaraGram will automatically format the error messages for you and return a `422 Unprocessable Entity` HTTP response.

Below, you can review an example of the JSON response format for validation errors. Note that nested error keys are flattened into "dot" notation format:

```json
{
    "message": "The team name must be a string. (and 4 more errors)",
    "errors": {
        "team_name": [
            "The team name must be a string.",
            "The team name must be at least 1 characters."
        ],
        "authorization.role": [
            "The selected authorization.role is invalid."
        ],
        "users.0.email": [
            "The users.0.email field is required."
        ],
        "users.2.email": [
            "The users.2.email must be a valid email address."
        ]
    }
}
```

<a name="manually-creating-validators"></a>
## Manually Creating Validators

If you do not want to use the `validate` method on the request, you may create a validator instance manually using the `Validator` [facade](/v4/facades). The `make` method on the facade generates a new validator instance:

```php
<?php

namespace App\Controllers;

use LaraGram\Request\RedirectResponse;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Validator;

class PostController extends Controller
{
    /**
     * Store a new blog post.
     */
    public function store(Request $request, $title, $body): RedirectResponse
    {
        $validator = Validator::make([
            'title' => $title,
            'body' => $body,
        ], [
            'title' => 'required|unique:posts|max:255',
            'body' => 'required',
        ]);

        if ($validator->fails()) {
            return to_listen('form')
                ->withErrors($validator)
                ->withInput();
        }

        // Retrieve the validated input...
        $validated = $validator->validated();

        // Retrieve a portion of the validated input...
        $validated = $validator->safe()->only(['name', 'email']);
        $validated = $validator->safe()->except(['name', 'email']);

        // Store the blog post...

        return to_listen('posts');
    }
}
```

The first argument passed to the `make` method is the data under validation. The second argument is an array of the validation rules that should be applied to the data.

After determining whether the request validation failed, you may use the `withErrors` method to flash the error messages to the session. When using this method, the `$errors` variable will automatically be shared with your templates after redirection, allowing you to easily display them back to the user. The `withErrors` method accepts a validator, a `MessageBag`, or a PHP `array`.

#### Stopping on First Validation Failure

The `stopOnFirstFailure` method will inform the validator that it should stop validating all attributes once a single validation failure has occurred:

```php
if ($validator->stopOnFirstFailure()->fails()) {
    // ...
}
```

<a name="automatic-redirection"></a>
### Automatic Redirection

If you would like to create a validator instance manually but still take advantage of the automatic redirection offered by the HTTP request's `validate` method, you may call the `validate` method on an existing validator instance. If validation fails, the user will automatically be redirected or, in the case of an XHR request, a [JSON response will be returned](#validation-error-response-format):

```php
Validator::make([
    'title' => $title,
    'body' => $body,
], [
    'title' => 'required|unique:posts|max:255',
    'body' => 'required',
])->validate();
```

You may use the `validateWithBag` method to store the error messages in a [named error bag](#named-error-bags) if validation fails:

```php
Validator::make([
    'title' => $title,
    'body' => $body,
], [
    'title' => 'required|unique:posts|max:255',
    'body' => 'required',
])->validateWithBag('post');
```

<a name="named-error-bags"></a>
### Named Error Bags

If you have multiple forms on a single page, you may wish to name the `MessageBag` containing the validation errors, allowing you to retrieve the error messages for a specific form. To achieve this, pass a name as the second argument to `withErrors`:

```php
return to_listen('register')->withErrors($validator, 'login');
```

You may then access the named `MessageBag` instance from the `$errors` variable:

```blade
{{ $errors->login->first('email') }}
```

<a name="manual-customizing-the-error-messages"></a>
### Customizing the Error Messages

If needed, you may provide custom error messages that a validator instance should use instead of the default error messages provided by LaraGram. There are several ways to specify custom messages. First, you may pass the custom messages as the third argument to the `Validator::make` method:

```php
$validator = Validator::make($input, $rules, $messages = [
    'required' => 'The :attribute field is required.',
]);
```

In this example, the `:attribute` placeholder will be replaced by the actual name of the field under validation. You may also utilize other placeholders in validation messages. For example:

```php
$messages = [
    'same' => 'The :attribute and :other must match.',
    'size' => 'The :attribute must be exactly :size.',
    'between' => 'The :attribute value :input is not between :min - :max.',
    'in' => 'The :attribute must be one of the following types: :values',
];
```

<a name="specifying-a-custom-message-for-a-given-attribute"></a>
#### Specifying a Custom Message for a Given Attribute

Sometimes you may wish to specify a custom error message only for a specific attribute. You may do so using "dot" notation. Specify the attribute's name first, followed by the rule:

```php
$messages = [
    'email.required' => 'We need to know your email address!',
];
```

<a name="specifying-custom-attribute-values"></a>
#### Specifying Custom Attribute Values

Many of LaraGram's built-in error messages include an `:attribute` placeholder that is replaced with the name of the field or attribute under validation. To customize the values used to replace these placeholders for specific fields, you may pass an array of custom attributes as the fourth argument to the `Validator::make` method:

```php
$validator = Validator::make($input, $rules, $messages, [
    'email' => 'email address',
]);
```

<a name="performing-additional-validation"></a>
### Performing Additional Validation

Sometimes you need to perform additional validation after your initial validation is complete. You can accomplish this using the validator's `after` method. The `after` method accepts a closure or an array of callables which will be invoked after validation is complete. The given callables will receive an `LaraGram\Validation\Validator` instance, allowing you to raise additional error messages if necessary:

```php
use LaraGram\Support\Facades\Validator;

$validator = Validator::make(/* ... */);

$validator->after(function ($validator) {
    if ($this->somethingElseIsInvalid()) {
        $validator->errors()->add(
            'field', 'Something is wrong with this field!'
        );
    }
});

if ($validator->fails()) {
    // ...
}
```

As noted, the `after` method also accepts an array of callables, which is particularly convenient if your "after validation" logic is encapsulated in invokable classes, which will receive an `LaraGram\Validation\Validator` instance via their `__invoke` method:

```php
use App\Validation\ValidateShippingTime;
use App\Validation\ValidateUserStatus;

$validator->after([
    new ValidateUserStatus,
    new ValidateShippingTime,
    function ($validator) {
        // ...
    },
]);
```

<a name="working-with-validated-input"></a>
## Working With Validated Input

After validating incoming request data using a form request or a manually created validator instance, you may wish to retrieve the incoming request data that actually underwent validation. This can be accomplished in several ways. First, you may call the `validated` method on a form request or validator instance. This method returns an array of the data that was validated:

```php
$validated = $validator->validated();
```

<a name="the-validated-input-container"></a>
#### The Validated Input Container

When you call the `validate` method on the `LaraGram\Request\Request` instance, the validated data is returned as a `LaraGram\Request\ValidatedInput` instance. This container is recursive, so any nested value may be read using either object or array syntax at any depth:

```php
$validated = $request->validate([
    'message.text' => 'required|string|max:255',
]);

// Object access...
$text = $validated->message->text;

// Array access...
$text = $validated['message']['text'];

// "dot" notation via the get method...
$text = $validated->get('message.text');
```

The container also implements `Countable`, `IteratorAggregate`, and `JsonSerializable`, so you may iterate over it, count it, or cast it to JSON. To retrieve the underlying array, call the `toArray` method:

```php
$array = $validated->toArray();
```

<a name="working-with-error-messages"></a>
## Working With Error Messages

After calling the `errors` method on a `Validator` instance, you will receive an `LaraGram\Support\MessageBag` instance, which has a variety of convenient methods for working with error messages. The `$errors` variable that is automatically made available to all templates is also an instance of the `MessageBag` class.

<a name="retrieving-the-first-error-message-for-a-field"></a>
#### Retrieving the First Error Message for a Field

To retrieve the first error message for a given field, use the `first` method:

```php
$errors = $validator->errors();

echo $errors->first('email');
```

<a name="retrieving-all-error-messages-for-a-field"></a>
#### Retrieving All Error Messages for a Field

If you need to retrieve an array of all the messages for a given field, use the `get` method:

```php
foreach ($errors->get('email') as $message) {
    // ...
}
```

If you are validating an array form field, you may retrieve all of the messages for each of the array elements using the `*` character:

```php
foreach ($errors->get('attachments.*') as $message) {
    // ...
}
```

<a name="retrieving-all-error-messages-for-all-fields"></a>
#### Retrieving All Error Messages for All Fields

To retrieve an array of all messages for all fields, use the `all` method:

```php
foreach ($errors->all() as $message) {
    // ...
}
```

<a name="determining-if-messages-exist-for-a-field"></a>
#### Determining if Messages Exist for a Field

The `has` method may be used to determine if any error messages exist for a given field:

```php
if ($errors->has('email')) {
    // ...
}
```

<a name="specifying-custom-messages-in-language-files"></a>
### Specifying Custom Messages in Language Files

LaraGram's built-in validation rules each have an error message that is located in your application's `lang/en/validation.php` file. If your application does not have a `lang` directory, you may instruct LaraGram to create it using the `lang:publish` Commander command.

Within the `lang/en/validation.php` file, you will find a translation entry for each validation rule. You are free to change or modify these messages based on the needs of your application.

In addition, you may copy this file to another language directory to translate the messages for your application's language. To learn more about LaraGram localization, check out the complete [localization documentation](/v4/localization).

> [!WARNING]
> By default, the LaraGram application skeleton does not include the `lang` directory. If you would like to customize LaraGram's language files, you may publish them via the `lang:publish` Commander command.

<a name="custom-messages-for-specific-attributes"></a>
#### Custom Messages for Specific Attributes

You may customize the error messages used for specified attribute and rule combinations within your application's validation language files. To do so, add your message customizations to the `custom` array of your application's `lang/xx/validation.php` language file:

```php
'custom' => [
    'email' => [
        'required' => 'We need to know your email address!',
        'max' => 'Your email address is too long!'
    ],
],
```

<a name="specifying-attribute-in-language-files"></a>
### Specifying Attributes in Language Files

Many of LaraGram's built-in error messages include an `:attribute` placeholder that is replaced with the name of the field or attribute under validation. If you would like the `:attribute` portion of your validation message to be replaced with a custom value, you may specify the custom attribute name in the `attributes` array of your `lang/xx/validation.php` language file:

```php
'attributes' => [
    'email' => 'email address',
],
```

> [!WARNING]
> By default, the LaraGram application skeleton does not include the `lang` directory. If you would like to customize LaraGram's language files, you may publish them via the `lang:publish` Commander command.

<a name="specifying-values-in-language-files"></a>
### Specifying Values in Language Files

Some of LaraGram's built-in validation rule error messages contain a `:value` placeholder that is replaced with the current value of the request attribute. However, you may occasionally need the `:value` portion of your validation message to be replaced with a custom representation of the value. For example, consider the following rule that specifies that a credit card number is required if the `payment_type` has a value of `cc`:

```php
Validator::make($inputs, [
    'credit_card_number' => 'required_if:payment_type,cc'
]);
```

If this validation rule fails, it will produce the following error message:

```text
The credit card number field is required when payment type is cc.
```

Instead of displaying `cc` as the payment type value, you may specify a more user-friendly value representation in your `lang/xx/validation.php` language file by defining a `values` array:

```php
'values' => [
    'payment_type' => [
        'cc' => 'credit card'
    ],
],
```

> [!WARNING]
> By default, the LaraGram application skeleton does not include the `lang` directory. If you would like to customize LaraGram's language files, you may publish them via the `lang:publish` Commander command.

After defining this value, the validation rule will produce the following error message:

```text
The credit card number field is required when payment type is credit card.
```

<a name="available-validation-rules"></a>
## Available Validation Rules

Below is a list of all available validation rules and their function:

<style>
    .collection-method-list > p {
        columns: 10.8em 3; -moz-columns: 10.8em 3; -webkit-columns: 10.8em 3;
    }

    .collection-method-list a {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>

#### Booleans

<div class="collection-method-list" markdown="1">

[Accepted](#rule-accepted)
[Accepted If](#rule-accepted-if)
[Boolean](#rule-boolean)
[Declined](#rule-declined)
[Declined If](#rule-declined-if)

</div>

#### Strings

<div class="collection-method-list" markdown="1">

[Active URL](#rule-active-url)
[Alpha](#rule-alpha)
[Alpha Dash](#rule-alpha-dash)
[Alpha Numeric](#rule-alpha-num)
[Ascii](#rule-ascii)
[Confirmed](#rule-confirmed)
[Current Password](#rule-current-password)
[Different](#rule-different)
[Doesnt Start With](#rule-doesnt-start-with)
[Doesnt End With](#rule-doesnt-end-with)
[Email](#rule-email)
[Ends With](#rule-ends-with)
[Enum](#rule-enum)
[Hex Color](#rule-hex-color)
[In](#rule-in)
[IP Address](#rule-ip)
[JSON](#rule-json)
[Lowercase](#rule-lowercase)
[MAC Address](#rule-mac)
[Max](#rule-max)
[Min](#rule-min)
[Not In](#rule-not-in)
[Regular Expression](#rule-regex)
[Not Regular Expression](#rule-not-regex)
[Same](#rule-same)
[Size](#rule-size)
[Starts With](#rule-starts-with)
[String](#rule-string)
[Uppercase](#rule-uppercase)
[URL](#rule-url)
[ULID](#rule-ulid)
[UUID](#rule-uuid)

</div>

#### Numbers

<div class="collection-method-list" markdown="1">

[Between](#rule-between)
[Decimal](#rule-decimal)
[Different](#rule-different)
[Digits](#rule-digits)
[Digits Between](#rule-digits-between)
[Greater Than](#rule-gt)
[Greater Than Or Equal](#rule-gte)
[Integer](#rule-integer)
[Less Than](#rule-lt)
[Less Than Or Equal](#rule-lte)
[Max](#rule-max)
[Max Digits](#rule-max-digits)
[Min](#rule-min)
[Min Digits](#rule-min-digits)
[Multiple Of](#rule-multiple-of)
[Numeric](#rule-numeric)
[Same](#rule-same)
[Size](#rule-size)

</div>

#### Arrays

<div class="collection-method-list" markdown="1">

[Array](#rule-array)
[Between](#rule-between)
[Contains](#rule-contains)
[Distinct](#rule-distinct)
[In Array](#rule-in-array)
[In Array Keys](#rule-in-array-keys)
[List](#rule-list)
[Max](#rule-max)
[Min](#rule-min)
[Size](#rule-size)

</div>

#### Dates

<div class="collection-method-list" markdown="1">

[After](#rule-after)
[After Or Equal](#rule-after-or-equal)
[Before](#rule-before)
[Before Or Equal](#rule-before-or-equal)
[Date](#rule-date)
[Date Equals](#rule-date-equals)
[Date Format](#rule-date-format)
[Different](#rule-different)
[Timezone](#rule-timezone)

</div>

#### Files

<div class="collection-method-list" markdown="1">

[Between](#rule-between)
[Dimensions](#rule-dimensions)
[Extensions](#rule-extensions)
[File](#rule-file)
[Image](#rule-image)
[Max](#rule-max)
[MIME Types](#rule-mimetypes)
[MIME Type By File Extension](#rule-mimes)
[Size](#rule-size)

</div>

#### Database

<div class="collection-method-list" markdown="1">

[Exists](#rule-exists)
[Unique](#rule-unique)

</div>

#### Utilities

<div class="collection-method-list" markdown="1">

[Any Of](#rule-anyof)
[Bail](#rule-bail)
[Exclude](#rule-exclude)
[Exclude If](#rule-exclude-if)
[Exclude Unless](#rule-exclude-unless)
[Exclude With](#rule-exclude-with)
[Exclude Without](#rule-exclude-without)
[Filled](#rule-filled)
[Missing](#rule-missing)
[Missing If](#rule-missing-if)
[Missing Unless](#rule-missing-unless)
[Missing With](#rule-missing-with)
[Missing With All](#rule-missing-with-all)
[Nullable](#rule-nullable)
[Present](#rule-present)
[Present If](#rule-present-if)
[Present Unless](#rule-present-unless)
[Present With](#rule-present-with)
[Present With All](#rule-present-with-all)
[Prohibited](#rule-prohibited)
[Prohibited If](#rule-prohibited-if)
[Prohibited If Accepted](#rule-prohibited-if-accepted)
[Prohibited If Declined](#rule-prohibited-if-declined)
[Prohibited Unless](#rule-prohibited-unless)
[Prohibits](#rule-prohibits)
[Required](#rule-required)
[Required If](#rule-required-if)
[Required If Accepted](#rule-required-if-accepted)
[Required If Declined](#rule-required-if-declined)
[Required Unless](#rule-required-unless)
[Required With](#rule-required-with)
[Required With All](#rule-required-with-all)
[Required Without](#rule-required-without)
[Required Without All](#rule-required-without-all)
[Required Array Keys](#rule-required-array-keys)
[Sometimes](#validating-when-present)

</div>

<a name="rule-accepted"></a>
#### accepted

The field under validation must be `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"`. This is useful for validating "Terms of Service" acceptance or similar fields.

<a name="rule-accepted-if"></a>
#### accepted_if:anotherfield,value,...

The field under validation must be `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"` if another field under validation is equal to a specified value. This is useful for validating "Terms of Service" acceptance or similar fields.

<a name="rule-active-url"></a>
#### active_url

The field under validation must have a valid A or AAAA record according to the `dns_get_record` PHP function. The hostname of the provided URL is extracted using the `parse_url` PHP function before being passed to `dns_get_record`.

<a name="rule-after"></a>
#### after:_date_

The field under validation must be a value after a given date. The dates will be passed into the `strtotime` PHP function in order to be converted to a valid `DateTime` instance:

```php
'start_date' => 'required|date|after:tomorrow'
```

Instead of passing a date string to be evaluated by `strtotime`, you may specify another field to compare against the date:

```php
'finish_date' => 'required|date|after:start_date'
```

For convenience, date-based rules may be constructed using the fluent `date` rule builder:

```php
use LaraGram\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->after(today()->addDays(7)),
],
```

The `afterToday` and `todayOrAfter` methods may be used to fluently express the date must be after today or or today or after, respectively:

```php
'start_date' => [
    'required',
    Rule::date()->afterToday(),
],
```

<a name="rule-after-or-equal"></a>
#### after\_or\_equal:_date_

The field under validation must be a value after or equal to the given date. For more information, see the [after](#rule-after) rule.

For convenience, date-based rules may be constructed using the fluent `date` rule builder:

```php
use LaraGram\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->afterOrEqual(today()->addDays(7)),
],
```

<a name="rule-anyof"></a>
#### anyOf

The `Rule::anyOf` validation rule allows you to specify that the field under validation must satisfy any of the given validation rulesets. For example, the following rule will validate that the `username` field is either an email address or an alpha-numeric string (including dashes) that is at least 6 characters long:

```php
use LaraGram\Validation\Rule;

'username' => [
    'required',
    Rule::anyOf([
        ['string', 'email'],
        ['string', 'alpha_dash', 'min:6'],
    ]),
],
```

<a name="rule-alpha"></a>
#### alpha

The field under validation must be entirely Unicode alphabetic characters contained in [\p{L}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AL%3A%5D&g=&i=) and [\p{M}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AM%3A%5D&g=&i=).

To restrict this validation rule to characters in the ASCII range (`a-z` and `A-Z`), you may provide the `ascii` option to the validation rule:

```php
'username' => 'alpha:ascii',
```

<a name="rule-alpha-dash"></a>
#### alpha_dash

The field under validation must be entirely Unicode alpha-numeric characters contained in [\p{L}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AL%3A%5D&g=&i=), [\p{M}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AM%3A%5D&g=&i=), [\p{N}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AN%3A%5D&g=&i=), as well as ASCII dashes (`-`) and ASCII underscores (`_`).

To restrict this validation rule to characters in the ASCII range (`a-z`, `A-Z`, and `0-9`), you may provide the `ascii` option to the validation rule:

```php
'username' => 'alpha_dash:ascii',
```

<a name="rule-alpha-num"></a>
#### alpha_num

The field under validation must be entirely Unicode alpha-numeric characters contained in [\p{L}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AL%3A%5D&g=&i=), [\p{M}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AM%3A%5D&g=&i=), and [\p{N}](https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5B%3AN%3A%5D&g=&i=).

To restrict this validation rule to characters in the ASCII range (`a-z`, `A-Z`, and `0-9`), you may provide the `ascii` option to the validation rule:

```php
'username' => 'alpha_num:ascii',
```

<a name="rule-array"></a>
#### array

The field under validation must be a PHP `array`.

When additional values are provided to the `array` rule, each key in the input array must be present within the list of values provided to the rule. In the following example, the `admin` key in the input array is invalid since it is not contained in the list of values provided to the `array` rule:

```php
use LaraGram\Support\Facades\Validator;

$input = [
    'user' => [
        'name' => 'Taylor Otwell',
        'username' => 'taylorotwell',
        'admin' => true,
    ],
];

Validator::make($input, [
    'user' => 'array:name,username',
]);
```

In general, you should always specify the array keys that are allowed to be present within your array.

<a name="rule-ascii"></a>
#### ascii

The field under validation must be entirely 7-bit ASCII characters.

<a name="rule-bail"></a>
#### bail

Stop running validation rules for the field after the first validation failure.

While the `bail` rule will only stop validating a specific field when it encounters a validation failure, the `stopOnFirstFailure` method will inform the validator that it should stop validating all attributes once a single validation failure has occurred:

```php
if ($validator->stopOnFirstFailure()->fails()) {
    // ...
}
```

<a name="rule-before"></a>
#### before:_date_

The field under validation must be a value preceding the given date. The dates will be passed into the PHP `strtotime` function in order to be converted into a valid `DateTime` instance. In addition, like the [after](#rule-after) rule, the name of another field under validation may be supplied as the value of `date`.

For convenience, date-based rules may also be constructed using the fluent `date` rule builder:

```php
use LaraGram\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->before(today()->subDays(7)),
],
```

The `beforeToday` and `todayOrBefore` methods may be used to fluently express the date must be before today or or today or before, respectively:

```php
'start_date' => [
    'required',
    Rule::date()->beforeToday(),
],
```

<a name="rule-before-or-equal"></a>
#### before\_or\_equal:_date_

The field under validation must be a value preceding or equal to the given date. The dates will be passed into the PHP `strtotime` function in order to be converted into a valid `DateTime` instance. In addition, like the [after](#rule-after) rule, the name of another field under validation may be supplied as the value of `date`.

For convenience, date-based rules may also be constructed using the fluent `date` rule builder:

```php
use LaraGram\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->beforeOrEqual(today()->subDays(7)),
],
```

<a name="rule-between"></a>
#### between:_min_,_max_

The field under validation must have a size between the given _min_ and _max_ (inclusive). Strings, numerics, arrays, and files are evaluated in the same fashion as the [size](#rule-size) rule.

<a name="rule-boolean"></a>
#### boolean

The field under validation must be able to be cast as a boolean. Accepted input are `true`, `false`, `1`, `0`, `"1"`, and `"0"`.

You may use the `strict` parameter to only consider the field valid if its value is `true` or `false`:

```php
'foo' => 'boolean:strict'
```

<a name="rule-confirmed"></a>
#### confirmed

The field under validation must have a matching field of `{field}_confirmation`. For example, if the field under validation is `password`, a matching `password_confirmation` field must be present in the input.

You may also pass a custom confirmation field name. For example, `confirmed:repeat_username` will expect the field `repeat_username` to match the field under validation.

<a name="rule-contains"></a>
#### contains:_foo_,_bar_,...

The field under validation must be an array that contains all of the given parameter values. Since this rule often requires you to `implode` an array, the `Rule::contains` method may be used to fluently construct the rule:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($data, [
    'roles' => [
        'required',
        'array',
        Rule::contains(['admin', 'editor']),
    ],
]);
```

<a name="rule-current-password"></a>
#### current_password

The field under validation must match the authenticated user's password. You may specify an [authentication guard](/v4/authentication) using the rule's first parameter:

```php
'password' => 'current_password:api'
```

<a name="rule-date"></a>
#### date

The field under validation must be a valid, non-relative date according to the `strtotime` PHP function.

<a name="rule-date-equals"></a>
#### date_equals:_date_

The field under validation must be equal to the given date. The dates will be passed into the PHP `strtotime` function in order to be converted into a valid `DateTime` instance.

<a name="rule-date-format"></a>
#### date_format:_format_,...

The field under validation must match one of the given _formats_. You should use **either** `date` or `date_format` when validating a field, not both. This validation rule supports all formats supported by PHP's [DateTime](https://www.php.net/manual/en/class.datetime.php) class.

For convenience, date-based rules may be constructed using the fluent `date` rule builder:

```php
use LaraGram\Validation\Rule;

'start_date' => [
    'required',
    Rule::date()->format('Y-m-d'),
],
```

<a name="rule-decimal"></a>
#### decimal:_min_,_max_

The field under validation must be numeric and must contain the specified number of decimal places:

```php
// Must have exactly two decimal places (9.99)...
'price' => 'decimal:2'

// Must have between 2 and 4 decimal places...
'price' => 'decimal:2,4'
```

<a name="rule-declined"></a>
#### declined

The field under validation must be `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"`.

<a name="rule-declined-if"></a>
#### declined_if:anotherfield,value,...

The field under validation must be `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"` if another field under validation is equal to a specified value.

<a name="rule-different"></a>
#### different:_field_

The field under validation must have a different value than _field_.

<a name="rule-digits"></a>
#### digits:_value_

The integer under validation must have an exact length of _value_.

<a name="rule-digits-between"></a>
#### digits_between:_min_,_max_

The integer validation must have a length between the given _min_ and _max_.

<a name="rule-dimensions"></a>
#### dimensions

The file under validation must be an image meeting the dimension constraints as specified by the rule's parameters:

```php
'avatar' => 'dimensions:min_width=100,min_height=200'
```

Available constraints are: _min\_width_, _max\_width_, _min\_height_, _max\_height_, _width_, _height_, _ratio_.

A _ratio_ constraint should be represented as width divided by height. This can be specified either by a fraction like `3/2` or a float like `1.5`:

```php
'avatar' => 'dimensions:ratio=3/2'
```

Since this rule requires several arguments, it is often more convenient to use the `Rule::dimensions` method to fluently construct the rule:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($data, [
    'avatar' => [
        'required',
        Rule::dimensions()
            ->maxWidth(1000)
            ->maxHeight(500)
            ->ratio(3 / 2),
    ],
]);
```

<a name="rule-distinct"></a>
#### distinct

When validating arrays, the field under validation must not have any duplicate values:

```php
'foo.*.id' => 'distinct'
```

Distinct uses loose variable comparisons by default. To use strict comparisons, you may add the `strict` parameter to your validation rule definition:

```php
'foo.*.id' => 'distinct:strict'
```

You may add `ignore_case` to the validation rule's arguments to make the rule ignore capitalization differences:

```php
'foo.*.id' => 'distinct:ignore_case'
```

<a name="rule-doesnt-start-with"></a>
#### doesnt_start_with:_foo_,_bar_,...

The field under validation must not start with one of the given values.

<a name="rule-doesnt-end-with"></a>
#### doesnt_end_with:_foo_,_bar_,...

The field under validation must not end with one of the given values.

<a name="rule-email"></a>
#### email

The field under validation must be formatted as an email address. This validation rule utilizes the [egulias/email-validator](https://github.com/egulias/EmailValidator) package for validating the email address. By default, the `RFCValidation` validator is applied, but you can apply other validation styles as well:

```php
'email' => 'email:rfc,dns'
```

The example above will apply the `RFCValidation` and `DNSCheckValidation` validations. Here's a full list of validation styles you can apply:

<div class="content-list" markdown="1">

- `rfc`: `RFCValidation` - Validate the email address according to [supported RFCs](https://github.com/egulias/EmailValidator?tab=readme-ov-file#supported-rfcs).
- `strict`: `NoRFCWarningsValidation` - Validate the email according to [supported RFCs](https://github.com/egulias/EmailValidator?tab=readme-ov-file#supported-rfcs), failing when warnings are found (e.g. trailing periods and multiple consecutive periods).
- `dns`: `DNSCheckValidation` - Ensure the email address's domain has a valid MX record.
- `spoof`: `SpoofCheckValidation` - Ensure the email address does not contain homograph or deceptive Unicode characters.
- `filter`: `FilterEmailValidation` - Ensure the email address is valid according to PHP's `filter_var` function.
- `filter_unicode`: `FilterEmailValidation::unicode()` - Ensure the email address is valid according to PHP's `filter_var` function, allowing some Unicode characters.

</div>

> [!WARNING]
> The `dns` and `spoof` validators require the PHP `intl` extension.

<a name="rule-ends-with"></a>
#### ends_with:_foo_,_bar_,...

The field under validation must end with one of the given values.

<a name="rule-enum"></a>
#### enum

The `Enum` rule is a class-based rule that validates whether the field under validation contains a valid enum value. The `Enum` rule accepts the name of the enum as its only constructor argument. When validating primitive values, a backed Enum should be provided to the `Enum` rule:

```php
use App\Enums\ServerStatus;
use LaraGram\Validation\Rule;

$validator = Validator::make($inputs, [
    'status' => [Rule::enum(ServerStatus::class)],
]);
```

The `Enum` rule's `only` and `except` methods may be used to limit which enum cases should be considered valid:

```php
Rule::enum(ServerStatus::class)
    ->only([ServerStatus::Pending, ServerStatus::Active]);

Rule::enum(ServerStatus::class)
    ->except([ServerStatus::Pending, ServerStatus::Active]);
```

The `when` method may be used to conditionally modify the `Enum` rule:

```php
use LaraGram\Support\Facades\Auth;
use LaraGram\Validation\Rule;

Rule::enum(ServerStatus::class)
    ->when(
        Auth::user()->isAdmin(),
        fn ($rule) => $rule->only(...),
        fn ($rule) => $rule->only(...),
    );
```

<a name="rule-exclude"></a>
#### exclude

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods.

<a name="rule-exclude-if"></a>
#### exclude_if:_anotherfield_,_value_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods if the _anotherfield_ field is equal to _value_.

If complex conditional exclusion logic is required, you may utilize the `Rule::excludeIf` method. This method accepts a boolean or a closure. When given a closure, the closure should return `true` or `false` to indicate if the field under validation should be excluded:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($inputs, [
    'role_id' => Rule::excludeIf($request->user()->is_admin),
]);

Validator::make($inputs, [
    'role_id' => Rule::excludeIf(fn () => $request->user()->is_admin),
]);
```

<a name="rule-exclude-unless"></a>
#### exclude_unless:_anotherfield_,_value_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods unless _anotherfield_'s field is equal to _value_. If _value_ is `null` (`exclude_unless:name,null`), the field under validation will be excluded unless the comparison field is `null` or the comparison field is missing from the request data.

<a name="rule-exclude-with"></a>
#### exclude_with:_anotherfield_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods if the _anotherfield_ field is present.

<a name="rule-exclude-without"></a>
#### exclude_without:_anotherfield_

The field under validation will be excluded from the request data returned by the `validate` and `validated` methods if the _anotherfield_ field is not present.

<a name="rule-exists"></a>
#### exists:_table_,_column_

The field under validation must exist in a given database table.

<a name="basic-usage-of-exists-rule"></a>
#### Basic Usage of Exists Rule

```php
'state' => 'exists:states'
```

If the `column` option is not specified, the field name will be used. So, in this case, the rule will validate that the `states` database table contains a record with a `state` column value matching the request's `state` attribute value.

<a name="specifying-a-custom-column-name"></a>
#### Specifying a Custom Column Name

You may explicitly specify the database column name that should be used by the validation rule by placing it after the database table name:

```php
'state' => 'exists:states,abbreviation'
```

Occasionally, you may need to specify a specific database connection to be used for the `exists` query. You can accomplish this by prepending the connection name to the table name:

```php
'email' => 'exists:connection.staff,email'
```

Instead of specifying the table name directly, you may specify the Eloquent model which should be used to determine the table name:

```php
'user_id' => 'exists:App\Models\User,id'
```

If you would like to customize the query executed by the validation rule, you may use the `Rule` class to fluently define the rule. In this example, we'll also specify the validation rules as an array instead of using the `|` character to delimit them:

```php
use LaraGram\Database\Query\Builder;
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($data, [
    'email' => [
        'required',
        Rule::exists('staff')->where(function (Builder $query) {
            $query->where('account_id', 1);
        }),
    ],
]);
```

You may explicitly specify the database column name that should be used by the `exists` rule generated by the `Rule::exists` method by providing the column name as the second argument to the `exists` method:

```php
'state' => Rule::exists('states', 'abbreviation'),
```

Sometimes, you may wish to validate whether an array of values exists in the database. You can do so by adding both the `exists` and [array](#rule-array) rules to the field being validated:

```php
'states' => ['array', Rule::exists('states', 'abbreviation')],
```

When both of these rules are assigned to a field, LaraGram will automatically build a single query to determine if all of the given values exist in the specified table.

<a name="rule-extensions"></a>
#### extensions:_foo_,_bar_,...

The file under validation must have a user-assigned extension corresponding to one of the listed extensions:

```php
'photo' => ['required', 'extensions:jpg,png'],
```

> [!WARNING]
> You should never rely on validating a file by its user-assigned extension alone. This rule should typically always be used in combination with the [mimes](#rule-mimes) or [mimetypes](#rule-mimetypes) rules.

<a name="rule-file"></a>
#### file

The field under validation must be a successfully uploaded file.

<a name="rule-filled"></a>
#### filled

The field under validation must not be empty when it is present.

<a name="rule-gt"></a>
#### gt:_field_

The field under validation must be greater than the given _field_ or _value_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the [size](#rule-size) rule.

<a name="rule-gte"></a>
#### gte:_field_

The field under validation must be greater than or equal to the given _field_ or _value_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the [size](#rule-size) rule.

<a name="rule-hex-color"></a>
#### hex_color

The field under validation must contain a valid color value in [hexadecimal](https://developer.mozilla.org/en-US/docs/Web/CSS/hex-color) format.

<a name="rule-image"></a>
#### image

The file under validation must be an image (jpg, jpeg, png, bmp, gif, or webp).

> [!WARNING]
> By default, the image rule does not allow SVG files due to the possibility of XSS vulnerabilities. If you need to allow SVG files, you may provide the `allow_svg` directive to the `image` rule (`image:allow_svg`).

<a name="rule-in"></a>
#### in:_foo_,_bar_,...

The field under validation must be included in the given list of values. Since this rule often requires you to `implode` an array, the `Rule::in` method may be used to fluently construct the rule:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($data, [
    'zones' => [
        'required',
        Rule::in(['first-zone', 'second-zone']),
    ],
]);
```

When the `in` rule is combined with the `array` rule, each value in the input array must be present within the list of values provided to the `in` rule. In the following example, the `LAS` airport code in the input array is invalid since it is not contained in the list of airports provided to the `in` rule:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

$input = [
    'airports' => ['NYC', 'LAS'],
];

Validator::make($input, [
    'airports' => [
        'required',
        'array',
    ],
    'airports.*' => Rule::in(['NYC', 'LIT']),
]);
```

<a name="rule-in-array"></a>
#### in_array:_anotherfield_.*

The field under validation must exist in _anotherfield_'s values.

<a name="rule-in-array-keys"></a>
#### in_array_keys:_value_.*

The field under validation must be an array having at least one of the given _values_ as a key within the array:

```php
'config' => 'array|in_array_keys:timezone'
```

<a name="rule-integer"></a>
#### integer

The field under validation must be an integer.

> [!WARNING]
> This validation rule does not verify that the input is of the "integer" variable type, only that the input is of a type accepted by PHP's `FILTER_VALIDATE_INT` rule. If you need to validate the input as being a number please use this rule in combination with [the `numeric` validation rule](#rule-numeric).

<a name="rule-ip"></a>
#### ip

The field under validation must be an IP address.

<a name="ipv4"></a>
#### ipv4

The field under validation must be an IPv4 address.

<a name="ipv6"></a>
#### ipv6

The field under validation must be an IPv6 address.

<a name="rule-json"></a>
#### json

The field under validation must be a valid JSON string.

<a name="rule-lt"></a>
#### lt:_field_

The field under validation must be less than the given _field_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the [size](#rule-size) rule.

<a name="rule-lte"></a>
#### lte:_field_

The field under validation must be less than or equal to the given _field_. The two fields must be of the same type. Strings, numerics, arrays, and files are evaluated using the same conventions as the [size](#rule-size) rule.

<a name="rule-lowercase"></a>
#### lowercase

The field under validation must be lowercase.

<a name="rule-list"></a>
#### list

The field under validation must be an array that is a list. An array is considered a list if its keys consist of consecutive numbers from 0 to `count($array) - 1`.

<a name="rule-mac"></a>
#### mac_address

The field under validation must be a MAC address.

<a name="rule-max"></a>
#### max:_value_

The field under validation must be less than or equal to a maximum _value_. Strings, numerics, arrays, and files are evaluated in the same fashion as the [size](#rule-size) rule.

<a name="rule-max-digits"></a>
#### max_digits:_value_

The integer under validation must have a maximum length of _value_.

<a name="rule-mimetypes"></a>
#### mimetypes:_text/plain_,...

The file under validation must match one of the given MIME types:

```php
'video' => 'mimetypes:video/avi,video/mpeg,video/quicktime'
```

To determine the MIME type of the uploaded file, the file's contents will be read and the framework will attempt to guess the MIME type, which may be different from the client's provided MIME type.

<a name="rule-mimes"></a>
#### mimes:_foo_,_bar_,...

The file under validation must have a MIME type corresponding to one of the listed extensions:

```php
'photo' => 'mimes:jpg,bmp,png'
```

Even though you only need to specify the extensions, this rule actually validates the MIME type of the file by reading the file's contents and guessing its MIME type. A full listing of MIME types and their corresponding extensions may be found at the following location:

[https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types](https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

<a name="mime-types-and-extensions"></a>
#### MIME Types and Extensions

This validation rule does not verify agreement between the MIME type and the extension the user assigned to the file. For example, the `mimes:png` validation rule would consider a file containing valid PNG content to be a valid PNG image, even if the file is named `photo.txt`. If you would like to validate the user-assigned extension of the file, you may use the [extensions](#rule-extensions) rule.

<a name="rule-min"></a>
#### min:_value_

The field under validation must have a minimum _value_. Strings, numerics, arrays, and files are evaluated in the same fashion as the [size](#rule-size) rule.

<a name="rule-min-digits"></a>
#### min_digits:_value_

The integer under validation must have a minimum length of _value_.

<a name="rule-multiple-of"></a>
#### multiple_of:_value_

The field under validation must be a multiple of _value_.

<a name="rule-missing"></a>
#### missing

The field under validation must not be present in the input data.

<a name="rule-missing-if"></a>
#### missing_if:_anotherfield_,_value_,...

The field under validation must not be present if the _anotherfield_ field is equal to any _value_.

<a name="rule-missing-unless"></a>
#### missing_unless:_anotherfield_,_value_

The field under validation must not be present unless the _anotherfield_ field is equal to any _value_.

<a name="rule-missing-with"></a>
#### missing_with:_foo_,_bar_,...

The field under validation must not be present _only if_ any of the other specified fields are present.

<a name="rule-missing-with-all"></a>
#### missing_with_all:_foo_,_bar_,...

The field under validation must not be present _only if_ all of the other specified fields are present.

<a name="rule-not-in"></a>
#### not_in:_foo_,_bar_,...

The field under validation must not be included in the given list of values. The `Rule::notIn` method may be used to fluently construct the rule:

```php
use LaraGram\Validation\Rule;

Validator::make($data, [
    'toppings' => [
        'required',
        Rule::notIn(['sprinkles', 'cherries']),
    ],
]);
```

<a name="rule-not-regex"></a>
#### not_regex:_pattern_

The field under validation must not match the given regular expression.

Internally, this rule uses the PHP `preg_match` function. The pattern specified should obey the same formatting required by `preg_match` and thus also include valid delimiters. For example: `'email' => 'not_regex:/^.+$/i'`.

> [!WARNING]
> When using the `regex` / `not_regex` patterns, it may be necessary to specify your validation rules using an array instead of using `|` delimiters, especially if the regular expression contains a `|` character.

<a name="rule-nullable"></a>
#### nullable

The field under validation may be `null`.

<a name="rule-numeric"></a>
#### numeric

The field under validation must be [numeric](https://www.php.net/manual/en/function.is-numeric.php).

<a name="rule-present"></a>
#### present

The field under validation must exist in the input data.

<a name="rule-present-if"></a>
#### present_if:_anotherfield_,_value_,...

The field under validation must be present if the _anotherfield_ field is equal to any _value_.

<a name="rule-present-unless"></a>
#### present_unless:_anotherfield_,_value_

The field under validation must be present unless the _anotherfield_ field is equal to any _value_.

<a name="rule-present-with"></a>
#### present_with:_foo_,_bar_,...

The field under validation must be present _only if_ any of the other specified fields are present.

<a name="rule-present-with-all"></a>
#### present_with_all:_foo_,_bar_,...

The field under validation must be present _only if_ all of the other specified fields are present.

<a name="rule-prohibited"></a>
#### prohibited

The field under validation must be missing or empty. A field is "empty" if it meets one of the following criteria:

<div class="content-list" markdown="1">

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

</div>

<a name="rule-prohibited-if"></a>
#### prohibited_if:_anotherfield_,_value_,...

The field under validation must be missing or empty if the _anotherfield_ field is equal to any _value_. A field is "empty" if it meets one of the following criteria:

<div class="content-list" markdown="1">

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

</div>

If complex conditional prohibition logic is required, you may utilize the `Rule::prohibitedIf` method. This method accepts a boolean or a closure. When given a closure, the closure should return `true` or `false` to indicate if the field under validation should be prohibited:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($inputs, [
    'role_id' => Rule::prohibitedIf($request->user()->is_admin),
]);

Validator::make($inputs, [
    'role_id' => Rule::prohibitedIf(fn () => $request->user()->is_admin),
]);
```
<a name="rule-prohibited-if-accepted"></a>
#### prohibited_if_accepted:_anotherfield_,...

The field under validation must be missing or empty if the _anotherfield_ field is equal to `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"`.

<a name="rule-prohibited-if-declined"></a>
#### prohibited_if_declined:_anotherfield_,...

The field under validation must be missing or empty if the _anotherfield_ field is equal to `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"`.

<a name="rule-prohibited-unless"></a>
#### prohibited_unless:_anotherfield_,_value_,...

The field under validation must be missing or empty unless the _anotherfield_ field is equal to any _value_. A field is "empty" if it meets one of the following criteria:

<div class="content-list" markdown="1">

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

</div>

<a name="rule-prohibits"></a>
#### prohibits:_anotherfield_,...

If the field under validation is not missing or empty, all fields in _anotherfield_ must be missing or empty. A field is "empty" if it meets one of the following criteria:

<div class="content-list" markdown="1">

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with an empty path.

</div>

<a name="rule-regex"></a>
#### regex:_pattern_

The field under validation must match the given regular expression.

Internally, this rule uses the PHP `preg_match` function. The pattern specified should obey the same formatting required by `preg_match` and thus also include valid delimiters. For example: `'email' => 'regex:/^.+@.+$/i'`.

> [!WARNING]
> When using the `regex` / `not_regex` patterns, it may be necessary to specify rules in an array instead of using `|` delimiters, especially if the regular expression contains a `|` character.

<a name="rule-required"></a>
#### required

The field under validation must be present in the input data and not empty. A field is "empty" if it meets one of the following criteria:

<div class="content-list" markdown="1">

- The value is `null`.
- The value is an empty string.
- The value is an empty array or empty `Countable` object.
- The value is an uploaded file with no path.

</div>

<a name="rule-required-if"></a>
#### required_if:_anotherfield_,_value_,...

The field under validation must be present and not empty if the _anotherfield_ field is equal to any _value_.

If you would like to construct a more complex condition for the `required_if` rule, you may use the `Rule::requiredIf` method. This method accepts a boolean or a closure. When passed a closure, the closure should return `true` or `false` to indicate if the field under validation is required:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($inputs, [
    'role_id' => Rule::requiredIf($request->user()->is_admin),
]);

Validator::make($inputs, [
    'role_id' => Rule::requiredIf(fn () => $request->user()->is_admin),
]);
```

<a name="rule-required-if-accepted"></a>
#### required_if_accepted:_anotherfield_,...

The field under validation must be present and not empty if the _anotherfield_ field is equal to `"yes"`, `"on"`, `1`, `"1"`, `true`, or `"true"`.

<a name="rule-required-if-declined"></a>
#### required_if_declined:_anotherfield_,...

The field under validation must be present and not empty if the _anotherfield_ field is equal to `"no"`, `"off"`, `0`, `"0"`, `false`, or `"false"`.

<a name="rule-required-unless"></a>
#### required_unless:_anotherfield_,_value_,...

The field under validation must be present and not empty unless the _anotherfield_ field is equal to any _value_. This also means _anotherfield_ must be present in the request data unless _value_ is `null`. If _value_ is `null` (`required_unless:name,null`), the field under validation will be required unless the comparison field is `null` or the comparison field is missing from the request data.

<a name="rule-required-with"></a>
#### required_with:_foo_,_bar_,...

The field under validation must be present and not empty _only if_ any of the other specified fields are present and not empty.

<a name="rule-required-with-all"></a>
#### required_with_all:_foo_,_bar_,...

The field under validation must be present and not empty _only if_ all of the other specified fields are present and not empty.

<a name="rule-required-without"></a>
#### required_without:_foo_,_bar_,...

The field under validation must be present and not empty _only when_ any of the other specified fields are empty or not present.

<a name="rule-required-without-all"></a>
#### required_without_all:_foo_,_bar_,...

The field under validation must be present and not empty _only when_ all of the other specified fields are empty or not present.

<a name="rule-required-array-keys"></a>
#### required_array_keys:_foo_,_bar_,...

The field under validation must be an array and must contain at least the specified keys.

<a name="rule-same"></a>
#### same:_field_

The given _field_ must match the field under validation.

<a name="rule-size"></a>
#### size:_value_

The field under validation must have a size matching the given _value_. For string data, _value_ corresponds to the number of characters. For numeric data, _value_ corresponds to a given integer value (the attribute must also have the `numeric` or `integer` rule). For an array, _size_ corresponds to the `count` of the array. For files, _size_ corresponds to the file size in kilobytes. Let's look at some examples:

```php
// Validate that a string is exactly 12 characters long...
'title' => 'size:12';

// Validate that a provided integer equals 10...
'seats' => 'integer|size:10';

// Validate that an array has exactly 5 elements...
'tags' => 'array|size:5';

// Validate that an uploaded file is exactly 512 kilobytes...
'image' => 'file|size:512';
```

<a name="rule-starts-with"></a>
#### starts_with:_foo_,_bar_,...

The field under validation must start with one of the given values.

<a name="rule-string"></a>
#### string

The field under validation must be a string. If you would like to allow the field to also be `null`, you should assign the `nullable` rule to the field.

<a name="rule-timezone"></a>
#### timezone

The field under validation must be a valid timezone identifier according to the `DateTimeZone::listIdentifiers` method.

The arguments [accepted by the `DateTimeZone::listIdentifiers` method](https://www.php.net/manual/en/datetimezone.listidentifiers.php) may also be provided to this validation rule:

```php
'timezone' => 'required|timezone:all';

'timezone' => 'required|timezone:Africa';

'timezone' => 'required|timezone:per_country,US';
```

<a name="rule-unique"></a>
#### unique:_table_,_column_

The field under validation must not exist within the given database table.

**Specifying a Custom Table / Column Name:**

Instead of specifying the table name directly, you may specify the Eloquent model which should be used to determine the table name:

```php
'email' => 'unique:App\Models\User,email_address'
```

The `column` option may be used to specify the field's corresponding database column. If the `column` option is not specified, the name of the field under validation will be used.

```php
'email' => 'unique:users,email_address'
```

**Specifying a Custom Database Connection**

Occasionally, you may need to set a custom connection for database queries made by the Validator. To accomplish this, you may prepend the connection name to the table name:

```php
'email' => 'unique:connection.users,email_address'
```

**Forcing a Unique Rule to Ignore a Given ID:**

Sometimes, you may wish to ignore a given ID during unique validation. For example, consider an "update profile" screen that includes the user's name, email address, and location. You will probably want to verify that the email address is unique. However, if the user only changes the name field and not the email field, you do not want a validation error to be thrown because the user is already the owner of the email address in question.

To instruct the validator to ignore the user's ID, we'll use the `Rule` class to fluently define the rule. In this example, we'll also specify the validation rules as an array instead of using the `|` character to delimit the rules:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

Validator::make($data, [
    'email' => [
        'required',
        Rule::unique('users')->ignore($user->id),
    ],
]);
```

> [!WARNING]
> You should never pass any user controlled request input into the `ignore` method. Instead, you should only pass a system generated unique ID such as an auto-incrementing ID or UUID from an Eloquent model instance. Otherwise, your application will be vulnerable to an SQL injection attack.

Instead of passing the model key's value to the `ignore` method, you may also pass the entire model instance. LaraGram will automatically extract the key from the model:

```php
Rule::unique('users')->ignore($user)
```

If your table uses a primary key column name other than `id`, you may specify the name of the column when calling the `ignore` method:

```php
Rule::unique('users')->ignore($user->id, 'user_id')
```

By default, the `unique` rule will check the uniqueness of the column matching the name of the attribute being validated. However, you may pass a different column name as the second argument to the `unique` method:

```php
Rule::unique('users', 'email_address')->ignore($user->id)
```

**Adding Additional Where Clauses:**

You may specify additional query conditions by customizing the query using the `where` method. For example, let's add a query condition that scopes the query to only search records that have an `account_id` column value of `1`:

```php
'email' => Rule::unique('users')->where(fn (Builder $query) => $query->where('account_id', 1))
```

**Ignoring Soft Deleted Records in Unique Checks:**

By default, the unique rule includes soft deleted records when determining uniqueness. To exclude soft deleted records from the uniqueness check, you may invoke the `withoutTrashed` method:

```php
Rule::unique('users')->withoutTrashed();
```

If your model uses a column name other than `deleted_at` for soft deleted records, you may provide the column name when invoking the `withoutTrashed` method:

```php
Rule::unique('users')->withoutTrashed('was_deleted_at');
```

<a name="rule-uppercase"></a>
#### uppercase

The field under validation must be uppercase.

<a name="rule-url"></a>
#### url

The field under validation must be a valid URL.

If you would like to specify the URL protocols that should be considered valid, you may pass the protocols as validation rule parameters:

```php
'url' => 'url:http,https',

'game' => 'url:minecraft,steam',
```

<a name="rule-ulid"></a>
#### ulid

The field under validation must be a valid [Universally Unique Lexicographically Sortable Identifier](https://github.com/ulid/spec) (ULID).

<a name="rule-uuid"></a>
#### uuid

The field under validation must be a valid RFC 9562 (version 1, 3, 4, 5, 6, 7, or 8) universally unique identifier (UUID).

You may also validate that the given UUID matches a UUID specification by version:

```php
'uuid' => 'uuid:4'
```

<a name="conditionally-adding-rules"></a>
## Conditionally Adding Rules

<a name="skipping-validation-when-fields-have-certain-values"></a>
#### Skipping Validation When Fields Have Certain Values

You may occasionally wish to not validate a given field if another field has a given value. You may accomplish this using the `exclude_if` validation rule. In this example, the `appointment_date` and `doctor_name` fields will not be validated if the `has_appointment` field has a value of `false`:

```php
use LaraGram\Support\Facades\Validator;

$validator = Validator::make($data, [
    'has_appointment' => 'required|boolean',
    'appointment_date' => 'exclude_if:has_appointment,false|required|date',
    'doctor_name' => 'exclude_if:has_appointment,false|required|string',
]);
```

Alternatively, you may use the `exclude_unless` rule to not validate a given field unless another field has a given value:

```php
$validator = Validator::make($data, [
    'has_appointment' => 'required|boolean',
    'appointment_date' => 'exclude_unless:has_appointment,true|required|date',
    'doctor_name' => 'exclude_unless:has_appointment,true|required|string',
]);
```

<a name="validating-when-present"></a>
#### Validating When Present

In some situations, you may wish to run validation checks against a field **only** if that field is present in the data being validated. To quickly accomplish this, add the `sometimes` rule to your rule list:

```php
$validator = Validator::make($data, [
    'email' => 'sometimes|required|email',
]);
```

In the example above, the `email` field will only be validated if it is present in the `$data` array.

> [!NOTE]
> If you are attempting to validate a field that should always be present but may be empty, check out [this note on optional fields](#a-note-on-optional-fields).

<a name="complex-conditional-validation"></a>
#### Complex Conditional Validation

Sometimes you may wish to add validation rules based on more complex conditional logic. For example, you may wish to require a given field only if another field has a greater value than 100. Or, you may need two fields to have a given value only when another field is present. Adding these validation rules doesn't have to be a pain. First, create a `Validator` instance with your _static rules_ that never change:

```php
use LaraGram\Support\Facades\Validator;

$validator = Validator::make($inputs, [
    'email' => 'required|email',
    'games' => 'required|integer|min:0',
]);
```

Let's assume our bot application is for game collectors. If a game collector registers with our application and they own more than 100 games, we want them to explain why they own so many games. For example, perhaps they run a game resale shop, or maybe they just enjoy collecting games. To conditionally add this requirement, we can use the `sometimes` method on the `Validator` instance.

```php
use LaraGram\Support\Fluent;

$validator->sometimes('reason', 'required|max:500', function (Fluent $input) {
    return $input->games >= 100;
});
```

The first argument passed to the `sometimes` method is the name of the field we are conditionally validating. The second argument is a list of the rules we want to add. If the closure passed as the third argument returns `true`, the rules will be added. This method makes it a breeze to build complex conditional validations. You may even add conditional validations for several fields at once:

```php
$validator->sometimes(['reason', 'cost'], 'required', function (Fluent $input) {
    return $input->games >= 100;
});
```

> [!NOTE]
> The `$input` parameter passed to your closure will be an instance of `LaraGram\Support\Fluent` and may be used to access your input and files under validation.

<a name="complex-conditional-array-validation"></a>
#### Complex Conditional Array Validation

Sometimes you may want to validate a field based on another field in the same nested array whose index you do not know. In these situations, you may allow your closure to receive a second argument which will be the current individual item in the array being validated:

```php
$input = [
    'channels' => [
        [
            'type' => 'email',
            'address' => 'abigail@example.com',
        ],
        [
            'type' => 'url',
            'address' => 'https://example.com',
        ],
    ],
];

$validator->sometimes('channels.*.address', 'email', function (Fluent $input, Fluent $item) {
    return $item->type === 'email';
});

$validator->sometimes('channels.*.address', 'url', function (Fluent $input, Fluent $item) {
    return $item->type !== 'email';
});
```

Like the `$input` parameter passed to the closure, the `$item` parameter is an instance of `LaraGram\Support\Fluent` when the attribute data is an array; otherwise, it is a string.

<a name="validating-arrays"></a>
## Validating Arrays

As discussed in the [array validation rule documentation](#rule-array), the `array` rule accepts a list of allowed array keys. If any additional keys are present within the array, validation will fail:

```php
use LaraGram\Support\Facades\Validator;

$input = [
    'user' => [
        'name' => 'Taylor Otwell',
        'username' => 'taylorotwell',
        'admin' => true,
    ],
];

Validator::make($input, [
    'user' => 'array:name,username',
]);
```

In general, you should always specify the array keys that are allowed to be present within your array. Otherwise, the validator's `validate` and `validated` methods will return all of the validated data, including the array and all of its keys, even if those keys were not validated by other nested array validation rules.

<a name="validating-nested-array-input"></a>
### Validating Nested Array Input

Validating nested array-based form input fields doesn't have to be a pain. You may use "dot notation" to validate attributes within an array. For example, if the incoming HTTP request contains a `photos[profile]` field, you may validate it like so:

```php
use LaraGram\Support\Facades\Validator;

$validator = Validator::make($inputs, [
    'photos.profile' => 'required|image',
]);
```

You may also validate each element of an array. For example, to validate that each email in a given array input field is unique, you may do the following:

```php
$validator = Validator::make($inputs, [
    'users.*.email' => 'email|unique:users',
    'users.*.first_name' => 'required_with:users.*.last_name',
]);
```

Likewise, you may use the `*` character when specifying [custom validation messages in your language files](#custom-messages-for-specific-attributes), making it a breeze to use a single validation message for array-based fields:

```php
'custom' => [
    'users.*.email' => [
        'unique' => 'Each user must have a unique email address',
    ]
],
```

<a name="accessing-nested-array-data"></a>
#### Accessing Nested Array Data

Sometimes you may need to access the value for a given nested array element when assigning validation rules to the attribute. You may accomplish this using the `Rule::forEach` method. The `forEach` method accepts a closure that will be invoked for each iteration of the array attribute under validation and will receive the attribute's value and explicit, fully-expanded attribute name. The closure should return an array of rules to assign to the array element:

```php
use App\Rules\HasPermission;
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;

$validator = Validator::make($inputs, [
    'companies.*.id' => Rule::forEach(function (string|null $value, string $attribute) {
        return [
            Rule::exists(Company::class, 'id'),
            new HasPermission('manage-company', $value),
        ];
    }),
]);
```

<a name="error-message-indexes-and-positions"></a>
### Error Message Indexes and Positions

When validating arrays, you may want to reference the index or position of a particular item that failed validation within the error message displayed by your application. To accomplish this, you may include the `:index` (starts from `0`) and `:position` (starts from `1`) placeholders within your [custom validation message](#manual-customizing-the-error-messages):

```php
use LaraGram\Support\Facades\Validator;

$input = [
    'photos' => [
        [
            'name' => 'BeachVacation.jpg',
            'description' => 'A photo of my beach vacation!',
        ],
        [
            'name' => 'GrandCanyon.jpg',
            'description' => '',
        ],
    ],
];

Validator::validate($input, [
    'photos.*.description' => 'required',
], [
    'photos.*.description.required' => 'Please describe photo #:position.',
]);
```

Given the example above, validation will fail and the user will be presented with the following error of _"Please describe photo #2."_

If necessary, you may reference more deeply nested indexes and positions via `second-index`, `second-position`, `third-index`, `third-position`, etc.

```php
'photos.*.attributes.*.string' => 'Invalid attribute for photo #:second-position.',
```

<a name="validating-files"></a>
## Validating Files

LaraGram provides a variety of validation rules that may be used to validate uploaded files, such as `mimes`, `image`, `min`, and `max`. While you are free to specify these rules individually when validating files, LaraGram also offers a fluent file validation rule builder that you may find convenient:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rules\File;

Validator::validate($input, [
    'attachment' => [
        'required',
        File::types(['mp3', 'wav'])
            ->min(1024)
            ->max(12 * 1024),
    ],
]);
```

<a name="validating-files-file-types"></a>
#### Validating File Types

Even though you only need to specify the extensions when invoking the `types` method, this method actually validates the MIME type of the file by reading the file's contents and guessing its MIME type. A full listing of MIME types and their corresponding extensions may be found at the following location:

[https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types](https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

<a name="validating-files-file-sizes"></a>
#### Validating File Sizes

For convenience, minimum and maximum file sizes may be specified as a string with a suffix indicating the file size units. The `kb`, `mb`, `gb`, and `tb` suffixes are supported:

```php
File::types(['mp3', 'wav'])
    ->min('1kb')
    ->max('10mb');
```

<a name="validating-files-image-files"></a>
#### Validating Image Files

If your application accepts images uploaded by your users, you may use the `File` rule's `image` constructor method to ensure that the file under validation is an image (jpg, jpeg, png, bmp, gif, or webp).

In addition, the `dimensions` rule may be used to limit the dimensions of the image:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rule;
use LaraGram\Validation\Rules\File;

Validator::validate($input, [
    'photo' => [
        'required',
        File::image()
            ->min(1024)
            ->max(12 * 1024)
            ->dimensions(Rule::dimensions()->maxWidth(1000)->maxHeight(500)),
    ],
]);
```

> [!NOTE]
> More information regarding validating image dimensions may be found in the [dimension rule documentation](#rule-dimensions).

> [!WARNING]
> By default, the `image` rule does not allow SVG files due to the possibility of XSS vulnerabilities. If you need to allow SVG files, you may pass `allowSvg: true` to the `image` rule: `File::image(allowSvg: true)`.

<a name="validating-files-image-dimensions"></a>
#### Validating Image Dimensions

You may also validate the dimensions of an image. For example, to validate that an uploaded image is at least 1000 pixels wide and 500 pixels tall, you may use the `dimensions` rule:

```php
use LaraGram\Validation\Rule;
use LaraGram\Validation\Rules\File;

File::image()->dimensions(
    Rule::dimensions()
        ->maxWidth(1000)
        ->maxHeight(500)
)
```

> [!NOTE]
> More information regarding validating image dimensions may be found in the [dimension rule documentation](#rule-dimensions).

<a name="validating-passwords"></a>
## Validating Passwords

To ensure that passwords have an adequate level of complexity, you may use LaraGram's `Password` rule object:

```php
use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\Rules\Password;

$validator = Validator::make($inputs, [
    'password' => ['required', 'confirmed', Password::min(8)],
]);
```

The `Password` rule object allows you to easily customize the password complexity requirements for your application, such as specifying that passwords require at least one letter, number, symbol, or characters with mixed casing:

```php
// Require at least 8 characters...
Password::min(8)

// Require at least one letter...
Password::min(8)->letters()

// Require at least one uppercase and one lowercase letter...
Password::min(8)->mixedCase()

// Require at least one number...
Password::min(8)->numbers()

// Require at least one symbol...
Password::min(8)->symbols()
```

In addition, you may ensure that a password has not been compromised in a public password data breach leak using the `uncompromised` method:

```php
Password::min(8)->uncompromised()
```

Internally, the `Password` rule object uses the [k-Anonymity](https://en.wikipedia.org/wiki/K-anonymity) model to determine if a password has been leaked via the [haveibeenpwned.com](https://haveibeenpwned.com) service without sacrificing the user's privacy or security.

By default, if a password appears at least once in a data leak, it will be considered compromised. You can customize this threshold using the first argument of the `uncompromised` method:

```php
// Ensure the password appears less than 3 times in the same data leak...
Password::min(8)->uncompromised(3);
```

Of course, you may chain all the methods in the examples above:

```php
Password::min(8)
    ->letters()
    ->mixedCase()
    ->numbers()
    ->symbols()
    ->uncompromised()
```

<a name="defining-default-password-rules"></a>
#### Defining Default Password Rules

You may find it convenient to specify the default validation rules for passwords in a single location of your application. You can easily accomplish this using the `Password::defaults` method, which accepts a closure. The closure given to the `defaults` method should return the default configuration of the Password rule. Typically, the `defaults` rule should be called within the `boot` method of one of your application's service providers:

```php
use LaraGram\Validation\Rules\Password;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Password::defaults(function () {
        $rule = Password::min(8);

        return $this->app->isProduction()
            ? $rule->mixedCase()->uncompromised()
            : $rule;
    });
}
```

Then, when you would like to apply the default rules to a particular password undergoing validation, you may invoke the `defaults` method with no arguments:

```php
'password' => ['required', Password::defaults()],
```

Occasionally, you may want to attach additional validation rules to your default password validation rules. You may use the `rules` method to accomplish this:

```php
use App\Rules\ZxcvbnRule;

Password::defaults(function () {
    $rule = Password::min(8)->rules([new ZxcvbnRule]);

    // ...
});
```

<a name="custom-validation-rules"></a>
## Custom Validation Rules

<a name="using-rule-objects"></a>
### Using Rule Objects

LaraGram provides a variety of helpful validation rules; however, you may wish to specify some of your own. One method of registering custom validation rules is using rule objects. To generate a new rule object, you may use the `make:rule` Commander command. Let's use this command to generate a rule that verifies a string is uppercase. LaraGram will place the new rule in the `app/Rules` directory. If this directory does not exist, LaraGram will create it when you execute the Commander command to create your rule:

```shell
php laragram make:rule Uppercase
```

Once the rule has been created, we are ready to define its behavior. A rule object contains a single method: `validate`. This method receives the attribute name, its value, and a callback that should be invoked on failure with the validation error message:

```php
<?php

namespace App\Rules;

use Closure;
use LaraGram\Contracts\Validation\ValidationRule;

class Uppercase implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strtoupper($value) !== $value) {
            $fail('The :attribute must be uppercase.');
        }
    }
}
```

Once the rule has been defined, you may attach it to a validator by passing an instance of the rule object with your other validation rules:

```php
use App\Rules\Uppercase;

$validator = Validator::make($inputs, [
    'name' => ['required', 'string', new Uppercase],
]);
```

#### Translating Validation Messages

Instead of providing a literal error message to the `$fail` closure, you may also provide a [translation string key](/v4/localization) and instruct LaraGram to translate the error message:

```php
if (strtoupper($value) !== $value) {
    $fail('validation.uppercase')->translate();
}
```

If necessary, you may provide placeholder replacements and the preferred language as the first and second arguments to the `translate` method:

```php
$fail('validation.location')->translate([
    'value' => $this->value,
], 'fr');
```

#### Accessing Additional Data

If your custom validation rule class needs to access all of the other data undergoing validation, your rule class may implement the `LaraGram\Contracts\Validation\DataAwareRule` interface. This interface requires your class to define a `setData` method. This method will automatically be invoked by LaraGram (before validation proceeds) with all of the data under validation:

```php
<?php

namespace App\Rules;

use LaraGram\Contracts\Validation\DataAwareRule;
use LaraGram\Contracts\Validation\ValidationRule;

class Uppercase implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected $data = [];

    // ...

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
```

Or, if your validation rule requires access to the validator instance performing the validation, you may implement the `ValidatorAwareRule` interface:

```php
<?php

namespace App\Rules;

use LaraGram\Contracts\Validation\ValidationRule;
use LaraGram\Contracts\Validation\ValidatorAwareRule;
use LaraGram\Validation\Validator;

class Uppercase implements ValidationRule, ValidatorAwareRule
{
    /**
     * The validator instance.
     *
     * @var \LaraGram\Validation\Validator
     */
    protected $validator;

    // ...

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }
}
```

<a name="using-closures"></a>
### Using Closures

If you only need the functionality of a custom rule once throughout your application, you may use a closure instead of a rule object. The closure receives the attribute's name, the attribute's value, and a `$fail` callback that should be called if validation fails:

```php
use LaraGram\Support\Facades\Validator;
use Closure;

$validator = Validator::make($inputs, [
    'title' => [
        'required',
        'max:255',
        function (string $attribute, mixed $value, Closure $fail) {
            if ($value === 'foo') {
                $fail("The {$attribute} is invalid.");
            }
        },
    ],
]);
```

<a name="implicit-rules"></a>
### Implicit Rules

By default, when an attribute being validated is not present or contains an empty string, normal validation rules, including custom rules, are not run. For example, the [unique](#rule-unique) rule will not be run against an empty string:

```php
use LaraGram\Support\Facades\Validator;

$rules = ['name' => 'unique:users,name'];

$input = ['name' => ''];

Validator::make($input, $rules)->passes(); // true
```

For a custom rule to run even when an attribute is empty, the rule must imply that the attribute is required. To quickly generate a new implicit rule object, you may use the `make:rule` Commander command with the `--implicit` option:

```shell
php laragram make:rule Uppercase --implicit
```

> [!WARNING]
> An "implicit" rule only _implies_ that the attribute is required. Whether it actually invalidates a missing or empty attribute is up to you.
