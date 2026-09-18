# Conversations

<a name="introduction"></a>
## Introduction

Many bots need to ask the user a series of questions — a registration flow, a support ticket, an order form — and remember every answer along the way. Wiring this up by hand with steps and cache keys quickly becomes messy.

LaraGram's **Conversation** component gives you a clean, declarative way to build these multi-step question-and-answer flows. You declare the questions, LaraGram sends them one by one, validates each reply, collects the answers, and hands them back to you when the flow completes. State is persisted automatically between updates, so a conversation survives across the many separate requests a webhook bot receives.

```php
use LaraGram\Support\Facades\Bot;

Bot::onText('start', function () {
    Conversation::start('Onboarding');
});
```

<a name="how-it-works"></a>
### How It Works

A conversation is intercepted by a global [middleware](/v4/middleware) that runs on every incoming update *before* your listens are matched. While a conversation is active for a chat, the middleware feeds each update into the current question, validates it, stores the answer, and moves to the next question. When there are no more questions the conversation completes and control returns to your normal listens.

Because only lightweight state is cached (the current question index, the collected answers, attempt counts and timestamps), your question closures are **never serialized**. The conversation file is simply re-required on each update to rebuild the questions. This keeps conversations safe even on long-running [Surge](/v4/surge) servers.

<a name="configuration"></a>
## Configuration

The conversation configuration file is located at `config/conversation.php`:

```php
return [
    // The cache store used to persist conversation state (null = default store).
    'store' => env('CONVERSATION_STORE'),

    // The directory where conversation files live (default: app/Conversations).
    'path' => app_path('Conversations'),

    // Cache key prefix and the maximum lifetime (seconds) of stored state.
    'prefix' => 'conversation',
    'lifetime' => 3600,

    // Fallback defaults used when a conversation does not declare its own.
    'max_attempts' => 3,
    'cancel_command' => null,
    'cancel_timeout' => null,
    'forget_after_complete' => true,

    // What the user is told when an answer is rejected.
    'retry_message' => true,
    'invalid_choice' => 'Please choose one of the options.',

    // What happens to the keyboard of the last prompt once the flow is over.
    'clear_keyboard' => true,
    'keyboard_cleared_text' => null,
];
```

> [!NOTE]
> Conversation state is persisted through the [Cache](/v4/cache) component. On a webhook bot, set `store` to a shared driver such as `redis` so state is available across the separate processes each update spawns.

<a name="creating-conversations"></a>
## Creating Conversations

<a name="generating-conversations"></a>
### Generating Conversations

Conversations live in `app/Conversations` and are generated with the `make:conversation` command:

```shell
php laragram make:conversation Onboarding
```

Like a migration, a conversation file returns an **anonymous class** extending `LaraGram\Conversation\Conversation`. The file name is the conversation's name — there is no namespace or class name to remember:

```php
<?php

use LaraGram\Conversation\AnswersBag;
use LaraGram\Conversation\Conversation as BaseConversation;
use LaraGram\Conversation\Questioner;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Conversation;

return new class extends BaseConversation
{
    /**
     * Declare the conversation's questions.
     */
    public function start(): void
    {
        Conversation::create(function (Questioner $questioner) {
            $questioner->ask('What is your name?')->name('name');

            $questioner->ask('How old are you?')
                ->name('age')
                ->validate('required|integer|min:1');
        });
    }

    /**
     * Handle the conversation completing.
     */
    public function onComplete(Request $request, AnswersBag $answers): void
    {
        $request->sendMessage(
            user()->id,
            "Welcome, {$answers->get('name')}!"
        );
    }

    /**
     * Handle the conversation being cancelled.
     */
    public function onCancel(Request $request, string $reason): void
    {
        //
    }
};
```

The `start` method is where you declare your questions. It is called every time the conversation's state is rebuilt, so keep it declarative — do not perform side effects there.

<a name="starting-conversations"></a>
### Starting Conversations

Start a conversation from any listen using the `Conversation` facade's `start` method, passing the conversation's name (its file name) and, optionally, an array of parameters:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Support\Facades\Conversation;

Bot::onText('register', function () {
    Conversation::start('Onboarding');
});

// Pass parameters that are available to the conversation...
Conversation::start('Onboarding', ['plan' => 'pro']);
```

As a shortcut, you may register a listen that immediately starts a conversation with the `Bot::conversation` method. It accepts the listen pattern, the conversation name, the update verbs to match (default `TEXT`), and optional parameters:

```php
Bot::conversation('register', 'Onboarding');

Bot::conversation('register', 'Onboarding', 'TEXT', ['plan' => 'pro']);
```

<a name="asking-questions"></a>
## Asking Questions

Every question begins with `ask` and is customized with a fluent chain of methods. The prompt string is the only required argument:

```php
$questioner->ask('What is your email address?')
    ->name('email')
    ->validate('required|email');
```

<a name="naming-answers"></a>
### Naming Answers

Use `name` to choose the key the answer is stored under. If you omit `name`, the answer is keyed by its zero-based position among the questions:

```php
$questioner->ask('First?')->name('first');  // stored as "first"
$questioner->ask('Second?');                // stored as 1
$questioner->ask('Third?')->name('third');  // stored as "third"
```

<a name="validating-answers"></a>
### Validating Answers

Attach [validation rules](/v4/validation) to a question with `validate`. When an answer fails, the conversation explains why (see [retry messages](#retry-messages)), asks the same question again — up to the allowed number of attempts — and fires the `onInvalid` hook. You may pass custom messages as the second argument:

```php
$questioner->ask('How old are you?')
    ->name('age')
    ->validate('required|integer|between:1,120', [
        'integer' => 'Please send a number.',
    ]);
```

<a name="dynamic-prompts"></a>
### Dynamic Prompts

A prompt may be a closure instead of a string. It receives the answers collected so far, so a question can build itself from what the user has already said:

```php
use LaraGram\Conversation\AnswersBag;

$questioner->ask(fn (AnswersBag $answers) => "Thanks {$answers->get('name')}! What is your email?")
    ->name('email')
    ->validate('required|email');
```

<a name="answer-types"></a>
### Expecting Specific Answer Types

By default a question expects text. Use `type` to expect a different kind of update — a photo, contact, location, and so on. Aliases such as `img`, `file`, `gps`, and `place` are supported:

```php
$questioner->ask('Send your profile photo')->name('avatar')->type('photo');

$questioner->ask('Share your location')->name('spot')->type('location');
```

<a name="choices"></a>
### Offering Choices

Most questions in a bot are answered with a button rather than with typing. The `choices` method takes the options, builds the keyboard, matches the reply against it, and stores the value you chose — there is no keyboard to build and no callback data to parse:

```php
$questioner->ask('Choose a plan')
    ->name('plan')
    ->choices(['free' => 'Free', 'pro' => 'Pro']);
```

The array maps the **value that is stored** to the **label the user sees**; a plain list uses each item as both. A closure receives the answers so far and returns the options, so they may depend on earlier replies:

```php
$questioner->ask('Pick a city')
    ->name('city')
    ->choices(fn (AnswersBag $answers) => City::where('country', (string) $answers->get('country'))
        ->pluck('name', 'id')
        ->all());
```

Options are drawn as an inline keyboard, two per row. Use `asReply` for a reply keyboard, and `columns` to change the layout:

```php
$questioner->ask('Pick a colour')->name('colour')
    ->choices(['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'])
    ->asReply()
    ->columns(3);
```

Whichever keyboard is used, the answer is the value: an inline button carries it in its callback data, and the text of a reply button is mapped back to it. A reply that matches none of the options is rejected like a failed validation rule.

<a name="multiple-choices"></a>
#### Multiple Choices

Add `multiple` to let the user select several options. Each tap toggles an option and redraws the keyboard, and the question is answered when the "done" button is tapped. The answer is an array of values:

```php
$questioner->ask('Which topics interest you?')
    ->name('topics')
    ->choices(['php' => 'PHP', 'js' => 'JavaScript', 'go' => 'Go'])
    ->multiple(done: 'Done', min: 1, max: 2);
```

<a name="confirmations"></a>
#### Confirmations

`confirm` is a yes-or-no question whose answer is a boolean:

```php
$questioner->ask('Do you accept the terms?')->name('terms')->confirm();

$questioner->ask('Ship it?')->name('ship')->confirm(yes: 'Ship it', no: 'Not yet');
```

<a name="keyboards"></a>
### Custom Keyboards

When a question needs a keyboard of its own — a request-contact button, a web app button, a layout the options cannot express — pass it with `keyboard`. It accepts a [keyboard builder](/v4/keyboards) instance, an array, or a JSON string, and the conversation adds its own back and skip buttons to it:

```php
use LaraGram\Keyboard\Keyboard;
use LaraGram\Keyboard\Make;

$questioner->ask('Share your phone number')
    ->name('phone')
    ->type('contact')
    ->keyboard(Keyboard::replyKeyboardMarkup(
        Make::row(Make::text('Share', request_contact: true))
    ));
```

<a name="media-prompts"></a>
### Media Prompts

A prompt itself can be media. The prompt text becomes the caption where the type supports one:

```php
$questioner->ask('Here is our welcome guide')->document('guide.pdf');

$questioner->ask('Scan this code')->photo($fileId);
```

Available media prompt methods: `photo`, `video`, `audio`, `voice`, `document`, `animation`, `videoNote`, and `sticker` (or `media($kind, $file)` for the generic form). Captions are not supported for `videoNote` and `sticker`.

<a name="template-prompts"></a>
### Template Prompts

A question may be rendered by a [Temple8 template](/v4/temple8), which gives you everything the template engine offers — formatting, keyboards, [rich messages](/v4/rich-messages), components, translations — inside a conversation:

```php
$questioner->ask()
    ->name('plan')
    ->prompt('Choose a plan')
    ->template('conversations.plan', ['trial' => $trial])
    ->choices(['free' => 'Free', 'pro' => 'Pro']);
```

The template builds the whole message and the conversation only adds its own controls to the keyboard the template produced. Besides the data you pass, every prompt template receives:

<div class="content-list" markdown="1">

- `$prompt` — the question's prompt text.
- `$answers` — the `AnswersBag` collected so far.
- `$choices` — the question's options, each with its `value`, `label`, `data` (the callback data of its button) and `selected` flag.
- `$parameters` — the parameters the conversation was started with.
- `$step` and `$steps` — the position of the question and the number of questions.

</div>

```blade
{{-- app/templates/conversations/plan.t8.php --}}
@parse_mode(html)

@text
<b>{{ $prompt }}</b> ({{ $step }}/{{ $steps }})
@endtext

@keyboard(inline)
    @foreach ($choices as $choice)
        @row
            @col($choice['label'], callback_data: $choice['data'])
        @endRow
    @endforeach
@endKeyboard()
```

<a name="custom-senders"></a>
### Custom Senders

For full control over how a prompt is delivered, use `askUsing`. The closure receives the request and is responsible for sending the prompt:

```php
$questioner->ask('Pick a color')
    ->name('color')
    ->askUsing(function (Request $request) {
        $request->sendMessage(user()->id, 'Pick a color', reply_markup: $markup);
    });
```

<a name="skipping-questions"></a>
### Optional Questions

`optional` adds a skip button to the prompt and stores the given value when it is tapped. A `skipCommand` does the same for a command the user types. Either way the `onSkip` hook fires:

```php
$questioner->ask('Add a bio')
    ->name('bio')
    ->optional('Skip', default: null);

$questioner->ask('Add a bio')
    ->name('bio')
    ->skipCommand('/skip')
    ->default('');
```

<a name="transforming-answers"></a>
### Transforming Answers

Answers arrive as text. `cast` converts one to a native type, and `transform` runs it through a closure, before it is stored:

```php
$questioner->ask('How old are you?')->name('age')->validate('integer')->cast('int');

$questioner->ask('What is your username?')
    ->name('user')
    ->transform(fn (string $value) => User::firstWhere('username', ltrim($value, '@')));
```

`cast` understands `int`, `float`, `bool`, `string` and `array`.

<a name="retry-messages"></a>
### Retry Messages

When an answer is rejected, the conversation tells the user why and asks again. By default the first validation error is sent; `retry` replaces it for a single question, and a closure receives the errors and the attempt number:

```php
$questioner->ask('Enter the code')
    ->name('code')
    ->validate('digits:6')
    ->retry('The code is six digits. Try again.');

$questioner->ask('Enter the code')
    ->name('code')
    ->retry(fn (array $errors, int $attempt) => "{$errors[0]} ({$attempt} of 3)");
```

Set `retry_message` to `false` in your configuration file, or declare `public $retryMessage = false` on the conversation, to say nothing at all.

<a name="per-question-callbacks"></a>
### Per-Question Callbacks

Run logic immediately after a question is answered with `then`. The callback receives the request, the `Answer`, and the full `AnswersBag` collected so far. Pass `defer: true` (or chain `->defer()`) to run the callback at the end of the conversation instead:

```php
use LaraGram\Conversation\Answer;
use LaraGram\Conversation\AnswersBag;

$questioner->ask('What is your name?')
    ->name('name')
    ->then(function (Request $request, Answer $answer, AnswersBag $answers) {
        logger("Got name: {$answer->text()}");
    });
```

<a name="per-question-attempts"></a>
### Per-Question Attempts

Override the maximum invalid attempts for a single question with `attempts`:

```php
$questioner->ask('Enter the code')->name('code')->attempts(5);
```

<a name="branching"></a>
## Branching

Real flows are rarely a straight line: a question may not apply, an answer may make the rest of the flow pointless, and a wrong turn may need to be undone.

<a name="conditional-questions"></a>
### Conditional Questions

`when` and `unless` decide whether a question is asked at all. The closure receives the answers collected so far, and is evaluated when the conversation reaches the question:

```php
$questioner->ask('Personal or business?')
    ->name('type')
    ->choices(['personal' => 'Personal', 'business' => 'Business']);

$questioner->ask('What is the company name?')
    ->name('company')
    ->when(fn (AnswersBag $answers) => (string) $answers->get('type') === 'business');

$questioner->ask('Add a VAT number')
    ->name('vat')
    ->unless(fn (AnswersBag $answers) => $answers->get('company')->isSkipped());
```

A question that does not apply is passed over silently and leaves no answer behind. Going back also skips it, returning to the question the user actually saw.

<a name="flow-control"></a>
### Jumping, Finishing and Cancelling

A question callback or a lifecycle hook may return a `Flow` instruction to steer the conversation:

```php
use LaraGram\Conversation\Flow;

$questioner->ask('Do you have a coupon?')
    ->name('coupon')
    ->confirm()
    ->then(fn (Request $request, Answer $answer) => $answer->raw()
        ? null
        : Flow::goTo('email'));

$questioner->ask('Coupon code')->name('code')->validate('alpha_num');

$questioner->ask('Your email')->name('email')->validate('email');
```

<div class="content-list" markdown="1">

- `Flow::goTo('name')` — continue with the question of that name.
- `Flow::repeat()` — ask the current question again.
- `Flow::finish()` — complete now, keeping the answers collected so far.
- `Flow::cancel('reason')` — cancel now.

</div>

Inside a conversation class the same instructions are available as `$this->goTo(...)`, `$this->repeat()` and `$this->finish()`:

```php
public function onAnswer(Request $request, Question $question, Answer $answer)
{
    if ($answer->key() === 'plan' && (string) $answer === 'enterprise') {
        return $this->goTo('contact');
    }
}
```

<a name="conversation-parameters"></a>
### Parameters

The parameters a conversation is started with are available on the conversation itself, and in every prompt template:

```php
Conversation::start('Onboarding', ['plan' => 'pro']);
```

```php
public function start(): void
{
    Conversation::create(function (Questioner $questioner) {
        $questioner->ask('How many seats?')
            ->name('seats')
            ->when(fn () => $this->parameter('plan') === 'pro');
    });
}
```

<a name="working-with-answers"></a>
## Working With Answers

<a name="the-answers-bag"></a>
### The Answers Bag

When a conversation completes, `onComplete` receives an `AnswersBag` — a countable, iterable collection of `Answer` objects:

```php
public function onComplete(Request $request, AnswersBag $answers): void
{
    $name = $answers->get('name');       // the "name" Answer
    $all  = $answers->all();             // array of Answer objects
    $data = $answers->toArray();         // plain array of values

    if ($answers->has('bio')) {
        // ...
    }
}
```

You may also retrieve the current answers at any time via the facade:

```php
$answers = Conversation::answers();
```

<a name="the-answer-object"></a>
### The Answer Object

Each answer is a type-aware `Answer` object. It exposes helpers for reading the value according to its kind:

```php
$answer = $answers->get('avatar');

$answer->text();        // text of a text answer
$answer->data();        // callback data of an inline button press
$answer->file();        // FileBag for a media answer
$answer->media();       // the largest MediaFile for a media answer
$answer->download('avatars/user.jpg', 'public');

$answer->isText();
$answer->isCallback();
$answer->isMedia();
$answer->isSkipped();

(string) $answer;       // the text (or callback data) of the answer
```

Because `Answer` is `Stringable`, you can drop it straight into a message and it renders its text:

```php
$request->sendMessage(user()->id, "Hi {$answers->get('name')}!");
```

<a name="lifecycle-hooks"></a>
## Lifecycle Hooks

Override any of these methods on your conversation class to react to events during the flow:

<div class="overflow-auto">

| Hook | Fired when |
| ---- | ---------- |
| `onStart(Request $request)` | The conversation begins. |
| `onAsk(Request $request, Question $question)` | Right before a question is sent. |
| `onAnswer(Request $request, Question $question, Answer $answer)` | A question receives a valid answer. May return a [`Flow`](#flow-control). |
| `onSkip(Request $request, Question $question)` | A question is skipped through its button or command. |
| `onBack(Request $request, Question $question)` | The user goes back to the previous question. May return a `Flow`. |
| `onInvalid(Request $request, Question $question, array $errors, int $attempt)` | An answer fails validation. |
| `onCancel(Request $request, string $reason)` | The conversation is cancelled. |
| `onComplete(Request $request, AnswersBag $answers)` | Every question has been answered. |

</div>

<a name="cancelling-conversations"></a>
## Cancelling Conversations

<a name="cancel-command"></a>
### Cancel Command

Declare a `cancelCommand` so the user can exit the flow at any point. When matched, the conversation is cancelled with the reason `"command"`:

```php
return new class extends BaseConversation
{
    public string $cancelCommand = '/cancel';

    // ...
};
```

<a name="cancel-timeout"></a>
### Timeout

Set `cancelTimeout` to automatically cancel a conversation after a period of inactivity (in seconds). The timeout is checked lazily on the next update; when exceeded, the conversation cancels with the reason `"timeout"` and the update passes through to your normal listens:

```php
public int $cancelTimeout = 300; // 5 minutes
```

<a name="cancelling-manually"></a>
### Cancelling Manually

Cancel the active conversation programmatically with the facade. The default reason is `"manual"`:

```php
Conversation::cancel();

Conversation::cancel('user_left');
```

The cancellation reasons passed to `onCancel` are: `command`, `timeout`, `max_attempts`, `interrupted`, and `manual`.

<a name="back-navigation"></a>
## Back Navigation

Every question but the first carries a back button, which returns to the previous question the user actually saw, clears its answer, fires the `onBack` hook and asks it again.

The button follows the keyboard of the question it belongs to: it joins an inline keyboard as an inline button and a reply keyboard as a reply button, and a question without a keyboard gets an inline one, so no reply keyboard is left behind on the user's screen. Configure it per question with `back`, or turn it off with `noBack`:

```php
$questioner->ask('How old are you?')
    ->name('age')
    ->back(mode: 'inline', label: '‹ Back');

$questioner->ask('Enter the code')->name('code')->noBack();
```

The `mode` may be `auto` (the default described above), `reply`, `inline`, `command`, `text`, or `none`. The `command` and `text` modes match what the user types instead of drawing a button:

```php
$questioner->ask('How old are you?')->name('age')->back(mode: 'command', command: '/back');
```

To configure back navigation for the whole conversation, override the `back` method (or declare a `public ?Back $back` property). Per-question settings are merged field-by-field over the conversation-wide default:

```php
use LaraGram\Conversation\Back;

public function back(): ?Back
{
    return Back::make(label: '‹ Back', onFirst: 'cancel');
}
```

`onFirst` decides what the button does on the first question: `hide` (the default) leaves it out, and `cancel` turns it into a way out of the conversation.

<a name="keyboard-clean-up"></a>
## Keyboard Clean Up

A keyboard that outlives its conversation is confusing, so LaraGram takes the last one back when the flow ends, whether it completed or was cancelled:

<div class="content-list" markdown="1">

- An **inline** keyboard is removed from the message it belongs to.
- A **reply** keyboard is replaced by every following prompt, and removed at the end — which needs a message of its own, so it is only sent when you provide its text.

</div>

```php
// On a conversation class...
public string|bool $clearKeyboard = 'Thanks!';

// Or for every conversation, in config/conversation.php...
'clear_keyboard' => true,
'keyboard_cleared_text' => 'Thanks!',
```

Set `clearKeyboard` to `false` to leave keyboards alone.

<a name="priority"></a>
## Priority: Listens vs. Conversation

By default, your regular and [step](/v4/step) listens take precedence over an active conversation. If a listen matches an incoming update, that listen runs and the active conversation is **interrupted** (cancelled with the reason `"interrupted"`). This lets a user run a command like `/help` in the middle of a flow.

The full resolution order is:

```text
regular listen  >  step listen  >  conversation  >  bot fallback
```

To make a conversation (or a single question) handle the update *before* any listen, use the `Priority` enum. This prevents interruption for as long as that priority is in effect:

```php
use LaraGram\Conversation\Priority;

// For a single question...
$questioner->ask('Type the confirmation code')
    ->name('code')
    ->priority(Priority::Conversation);
```

```php
// For the whole conversation...
use LaraGram\Conversation\Priority;

public function priority(): ?Priority
{
    return Priority::Conversation;
}
```

The effective priority is resolved as: the current question's priority, falling back to the conversation's priority, falling back to `Priority::Listen`.

<a name="inline-conversations"></a>
## Inline Conversations

For quick flows you don't want to put in a dedicated file, build a conversation inline. `Conversation::inline` receives the same `Questioner` and returns a fluent builder — it starts automatically when the builder is discarded, or you may call `start` explicitly:

```php
use LaraGram\Conversation\Answer;
use LaraGram\Conversation\AnswersBag;
use LaraGram\Conversation\Questioner;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Conversation;

Bot::onText('feedback', function () {
    Conversation::inline(function (Questioner $q) {
        $q->ask('What went wrong?')->name('issue');
        $q->ask('How can we reach you?')->name('contact');
    })
    ->maxAttempts(2)
    ->cancelCommand('/cancel')
    ->onComplete(function (Request $request, AnswersBag $answers) {
        $request->sendMessage(user()->id, 'Thanks for your feedback!');
    })
    ->start();
});
```

For a single-question flow, `Conversation::ask` is even shorter. Every method a question understands may be called straight on the builder:

```php
Conversation::ask('What is your email?', 'email')
    ->validate('required|email')
    ->retry('That does not look like an email address.')
    ->onComplete(function (Request $request, AnswersBag $answers) {
        $request->sendMessage(user()->id, "Thanks, {$answers->get('email')}!");
    });
```

`Conversation::choose` and `Conversation::confirm` are shortcuts for a single question with options:

```php
Conversation::choose('Choose a plan', ['free' => 'Free', 'pro' => 'Pro'], 'plan')
    ->onComplete(fn (Request $request, AnswersBag $answers) => Subscription::start($answers->get('plan')->raw()));

Conversation::confirm('Delete your account?', 'sure')
    ->onComplete(fn (Request $request, AnswersBag $answers) => $answers->get('sure')->raw() ? $user->delete() : null);
```

The inline builder offers the same settings as a file conversation: `maxAttempts`, `cancelTimeout`, `cancelCommand`, `forgetAfterComplete`, `retryMessage`, `clearKeyboard`, `back`, `noBack`, `priority`, `name`, `with` (parameters), and the `onStart`, `onAsk`, `onAnswer`, `onSkip`, `onBack`, `onInvalid`, `onCancel`, and `onComplete` hooks.

> [!NOTE]
> Inline conversations serialize their closures to persist across updates, so any variables captured with `use` (or `$this`) must be serializable.

<a name="events"></a>
## Events

LaraGram dispatches [events](/v4/events) throughout a conversation's lifecycle. You may listen for any of them:

<div class="overflow-auto">

| Event |
| ----- |
| `LaraGram\Conversation\Events\ConversationStarted` |
| `LaraGram\Conversation\Events\QuestionAsked` |
| `LaraGram\Conversation\Events\AnswerReceived` |
| `LaraGram\Conversation\Events\AnswerInvalid` |
| `LaraGram\Conversation\Events\QuestionSkipped` |
| `LaraGram\Conversation\Events\BackRequested` |
| `LaraGram\Conversation\Events\ConversationCancelled` |
| `LaraGram\Conversation\Events\ConversationCompleted` |

</div>
