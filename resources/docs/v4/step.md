# Step Manager

<a name="introduction"></a>
## Introduction

When building a Telegram bot, a single conversation often spans several messages. You might ask the user for their name, then their email, then a confirmation. Because each incoming update is handled independently, you need a way to remember "where" each user is within a conversation so you can react to their next message correctly.

The Step Manager provides an expressive, fluent API for tracking the current "step" of a conversation on a per-user basis. Each step is simply a string that you define, such as `awaiting_name` or `awaiting_email`. The manager stores the active step in your application's [cache](cache.md), keyed automatically by the current user, so you never have to manage the storage key yourself.

In addition to single steps, the Step Manager also supports **sequences** — an ordered list of steps you can move through with `next` and `previous`, which is perfect for building multi-stage wizards and forms.

You may interact with the Step Manager through the `LaraGram\Support\Facades\Step` facade:

```php
use LaraGram\Support\Facades\Step;

Step::set('awaiting_name');
```

> [!NOTE]
> The active step is stored per user. Internally, the manager derives its cache key from `user()->id`, so each user has their own independent step and sequence state.

<a name="storage-driver"></a>
### Storage Driver

The Step Manager does not implement its own storage. Instead, it writes through your application's default [cache](cache.md) store, as configured by the `default` key in your `config/cache.php` file. This means the step and sequence state is persisted using whichever cache driver your application already uses, such as:

<div class="content-list" markdown="1">

- `database`
- `redis`
- `memcached`
- `file`
- `array`
- `null`

</div>

Because state lives in the cache, the choice of driver affects how persistent it is. Persistent drivers like `database`, `redis`, `memcached`, and `file` survive across requests and restarts, while `array` is per-request only and `null` discards everything. For production conversations, use a persistent driver — `redis` is a great choice for fast, shared state.

> [!NOTE]
> Since the manager uses the default cache store, any TTL you pass to `set` is honored by the underlying driver just like any other cache entry.

<a name="storing-steps"></a>
## Storing Steps

<a name="setting-the-current-step"></a>
### Setting the Current Step

You may store the current step for a user using the `set` method. Any previously stored step for the user is cleared before the new value is written:

```php
use LaraGram\Support\Facades\Step;

Step::set('awaiting_name');
```

<a name="step-expiration"></a>
#### Step Expiration

By default, a step is stored according to your cache store's default behavior. If you would like the step to automatically expire after a number of seconds, you may pass a TTL as the second argument to the `set` method:

```php
// Forget the step automatically after 5 minutes...
Step::set('awaiting_name', 300);
```

<a name="retrieving-steps"></a>
### Retrieving the Current Step

The `get` method retrieves the current step for the user. If the user has no active step, `null` is returned:

```php
$step = Step::get();

if ($step === 'awaiting_name') {
    // ...
}
```

<a name="retrieving-and-deleting"></a>
### Retrieving and Deleting the Step

If you need to retrieve the current step and then immediately remove it, you may use the `pull` method:

```php
$step = Step::pull();
```

<a name="removing-steps"></a>
### Removing the Step

You may remove the current step using the `forget` method. This is typically called once a conversation has finished:

```php
Step::forget();
```

<a name="checking-for-steps"></a>
## Checking for Steps

<a name="determining-step-existence"></a>
### Determining Step Existence

The `hasStep` method may be used to determine if the user currently has any active step. The inverse, `hasNotStep`, returns `true` when no step is set:

```php
if (Step::hasStep()) {
    // The user is in the middle of a conversation...
}

if (Step::hasNotStep()) {
    // The user has no active step...
}
```

<a name="comparing-the-current-step"></a>
### Comparing the Current Step

To check whether the current step matches a specific value, use the `is` method. The `isNot` method performs the opposite comparison. Both methods use a strict comparison against the stored step:

```php
if (Step::is('awaiting_email')) {
    // Handle the email the user just sent...
}

if (Step::isNot('awaiting_email')) {
    // ...
}
```

<a name="step-listeners"></a>
## Step Listeners

While you can branch on the current step manually using `is`, LaraGram provides a dedicated `onStep` listener that registers a handler bound to a specific step. The listener only fires when the user's current step matches the given name, so you can split each stage of a conversation into its own clean handler instead of branching inside a single one.

<a name="defining-step-listeners"></a>
### Defining Step Listeners

The `onStep` method accepts the step name as its first argument and the handler as its second. When an update arrives, the listener runs only if `Step::is()` returns `true` for that step:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Support\Facades\Step;
use LaraGram\Request\Request;

// Begin the conversation...
Bot::onText('start', function (Request $request) {
    Step::set('awaiting_name');

    $request->sendMessage(chat()->id, 'What is your name?');
});

// Only runs while the user's step is "awaiting_name"...
Bot::onStep('awaiting_name', function (Request $request, string $name) {
    Step::set('awaiting_email');

    $request->sendMessage(chat()->id, "Nice to meet you, {$name}! What is your email?");
});

// Only runs while the user's step is "awaiting_email"...
Bot::onStep('awaiting_email', function (Request $request, string $email) {
    Step::forget();

    $request->sendMessage(chat()->id, 'Thanks, you are all set!');
});
```

By default, a step listener matches incoming text updates and captures the entire message text, passing it to your handler as a listen parameter. As with any listen, parameters are injected after your type-hinted dependencies and bound by position, so you may name the argument whatever you like.

<a name="customizing-the-match"></a>
### Customizing the Match

The `onStep` method accepts two optional arguments — a `pattern` and a `method` — for finer control over what the listener matches:

```php
Bot::onStep(
    step: 'awaiting_age',
    action: function (Request $request, string $age) {
        // ...
    },
    pattern: '{age}',
    method: 'TEXT'
);
```

The `pattern` argument lets you supply an explicit [listen pattern](listening.md#required-parameters) instead of capturing the whole message, while the `method` argument controls which update types the listener responds to. You may pass a single type, an array of types, or `'*'` to match every supported update verb. When omitted, the listener defaults to capturing all text updates.

<a name="step-listener-priority"></a>
### Step Listener Priority

By default, step listeners are evaluated after your regular listens. If you would like step listeners to be matched in definition order, mixed in with your other listens, you may enable priority registration:

```php
Bot::enableStepListensPriorityRegister();
```

<a name="sequences"></a>
## Sequences

While single steps are ideal for simple conversations, a **sequence** lets you define an ordered list of steps and walk through them one at a time. The Step Manager keeps track of the user's position within the sequence and automatically keeps the active step in sync as you move forward and backward.

<a name="starting-a-sequence"></a>
### Starting a Sequence

To begin a sequence, pass an ordered array of step names to the `startSequence` method. The sequence is stored for the user and the first step is immediately activated as the current step:

```php
use LaraGram\Support\Facades\Step;

Step::startSequence([
    'awaiting_name',
    'awaiting_email',
    'awaiting_confirmation',
]);

// The current step is now "awaiting_name"...
```

> [!NOTE]
> Calling `startSequence` with an empty array does nothing. Once a sequence is started, the active step (accessible via `Step::get`) always reflects the sequence's current position.

<a name="moving-through-a-sequence"></a>
### Moving Through a Sequence

Use the `next` and `previous` methods to move forward and backward through the sequence. Each move updates the active step to match the new position:

```php
Step::next();     // Advance to the next step...
Step::previous(); // Go back to the previous step...
```

These methods are safe to call at the boundaries of the sequence. Calling `next` while on the last step, or `previous` while on the first step, does nothing — the position remains unchanged. If no sequence has been started, both methods are no-ops as well.

<a name="inspecting-a-sequence"></a>
### Inspecting a Sequence

The `current` method returns the name of the step at the sequence's current position, or `null` if no sequence is active:

```php
$step = Step::current();
```

The `isFirst` and `isLast` methods let you determine whether the user is positioned at the start or end of the sequence. This is useful for deciding whether to display "back" or "finish" actions:

```php
if (Step::isFirst()) {
    // The user is on the first step...
}

if (Step::isLast()) {
    // The user reached the final step...
}
```

> [!NOTE]
> When no sequence is active, both `isFirst` and `isLast` return `true`.

If you need access to the entire sequence structure, the `getSequence` method returns the raw sequence data — an array containing the ordered `steps` and the `current` index — or `null` when no sequence exists:

```php
$sequence = Step::getSequence();

// [
//     'steps'   => ['awaiting_name', 'awaiting_email', 'awaiting_confirmation'],
//     'current' => 0,
// ]
```

<a name="ending-a-sequence"></a>
### Ending a Sequence

Once the user has worked through the sequence, call `endSequence` to clear both the stored sequence and the active step:

```php
Step::endSequence();
```

<a name="sequence-example"></a>
### A Complete Sequence Example

By combining sequences with [step listeners](#step-listeners), each stage stays in its own handler while the sequence keeps track of the user's position:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Support\Facades\Step;
use LaraGram\Request\Request;

Bot::onText('register', function (Request $request) {
    Step::startSequence([
        'awaiting_name',
        'awaiting_email',
        'awaiting_confirmation',
    ]);

    $request->sendMessage(chat()->id, 'What is your name?');
});

Bot::onStep('awaiting_name', function (Request $request, string $name) {
    // Store the name, then advance the sequence...
    Step::next();

    $request->sendMessage(chat()->id, 'What is your email?');
});

Bot::onStep('awaiting_email', function (Request $request, string $email) {
    // Store the email, then advance the sequence...
    Step::next();

    $request->sendMessage(chat()->id, 'Please confirm by sending "yes".');
});

Bot::onStep('awaiting_confirmation', function (Request $request, string $answer) {
    Step::endSequence();

    $request->sendMessage(chat()->id, 'Registration complete!');
});
```

<a name="step-middleware"></a>
## Step Middleware

In addition to the [`onStep` listener](#step-listeners), LaraGram ships with a `step` [middleware](middleware.md) that lets you restrict any listener to users who are currently on a specific step. The middleware accepts the expected step name as a parameter and only allows the request to continue when `Step::is()` returns `true` for that value:

```php
use LaraGram\Request\Request;

Bot::onMessage(function (Request $request) {
    // Only reached when the user's current step is "awaiting_email"...
})->middleware('step:awaiting_email');
```

If the user's current step does not match, the middleware halts the request and the listener is not executed. This is useful when you want to gate a listener of any update type — not just text — behind a step, while `onStep` remains the most convenient option for text-based conversation flows.

<a name="method-reference"></a>
## Method Reference

The following methods are available on the `Step` facade:

| Method | Description |
| --- | --- |
| `set(string $key, int $ttl = null)` | Clear any existing step and store a new one, optionally with a TTL in seconds. |
| `get()` | Get the current step, or `null` if none is set. |
| `pull()` | Get the current step and immediately remove it. |
| `forget()` | Remove the current step. |
| `hasStep()` | Determine if the user has any active step. |
| `hasNotStep()` | Determine if the user has no active step. |
| `is(string $key)` | Determine if the current step strictly equals the given value. |
| `isNot(string $key)` | Determine if the current step does not equal the given value. |
| `startSequence(array $sequence)` | Start a new sequence and activate its first step. |
| `endSequence()` | Clear the sequence and the active step. |
| `next()` | Advance to the next step in the sequence. |
| `previous()` | Move back to the previous step in the sequence. |
| `current()` | Get the current step name in the sequence, or `null`. |
| `isFirst()` | Determine if the current step is the first in the sequence. |
| `isLast()` | Determine if the current step is the last in the sequence. |
| `getSequence()` | Get the raw sequence array, or `null` if none exists. |
