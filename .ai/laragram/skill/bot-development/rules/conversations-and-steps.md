# Conversations and Steps

Webhook bots handle every update in a fresh request, so multi-message flows need persisted state. LaraGram offers two tools; both store state in the cache, so use a shared, persistent store (such as `redis`) in production.

| Use | When |
| --- | --- |
| **Conversation** | A guided series of questions: validation, typed answers, option keyboards, branching, skip/back/cancel, and a completion callback |
| **Step Manager** | Free-form state machines where the next step depends on arbitrary logic, or a single "awaiting input" state |

## Conversations

Generate a conversation with `php laragram make:conversation Onboarding`. The file in `app/Conversations` returns an anonymous class; its file name is the conversation's name.

```php
use LaraGram\Conversation\AnswersBag;
use LaraGram\Conversation\Conversation as BaseConversation;
use LaraGram\Conversation\Questioner;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Conversation;

return new class extends BaseConversation
{
    public string $cancelCommand = '/cancel';

    public int $cancelTimeout = 300;

    public function start(): void
    {
        Conversation::create(function (Questioner $questioner) {
            $questioner->ask('What is your name?')->name('name');

            $questioner->ask(fn (AnswersBag $answers) => "How old are you, {$answers->get('name')}?")
                ->name('age')
                ->validate('required|integer|between:1,120')
                ->cast('int');

            $questioner->ask('Choose a plan')
                ->name('plan')
                ->choices(['free' => 'Free', 'pro' => 'Pro']);

            $questioner->ask('How many seats?')
                ->name('seats')
                ->when(fn (AnswersBag $answers) => (string) $answers->get('plan') === 'pro');

            $questioner->ask('Send your profile photo')->name('avatar')->type('photo')->optional('Skip');
        });
    }

    public function onComplete(Request $request, AnswersBag $answers): void
    {
        User::updateOrCreate(['user_id' => user()->id], [
            'first_name' => (string) $answers->get('name'),
            'age' => (int) (string) $answers->get('age'),
        ]);

        $request->sendMessage(user()->id, "Welcome, {$answers->get('name')}!");
    }
};
```

Start it from a listen with `Conversation::start('Onboarding')`, or register a listen that starts it with `Bot::conversation('register', 'Onboarding')`.

- Keep `start()` declarative: it runs every time the conversation's state is rebuilt, so never perform side effects there. Do the work in `onComplete` or per-question `then()` callbacks.
- Never build the option keyboard of a question by hand: `choices(['free' => 'Free', 'pro' => 'Pro'])` (value => label, or a closure of the answers) draws it, matches the reply and stores the value. `asReply()` / `columns(n)` change the layout, `multiple(done: 'Done', min: 1)` collects an array, and `confirm()` asks a yes/no whose answer is a boolean. Keep `keyboard(...)` for what options cannot express (contact, web app, custom layouts); the back and skip buttons are added to whatever keyboard the question has.
- Prompts: a string, a closure receiving the `AnswersBag`, media (`photo()`, `document()`, ...), or `template('conversations.plan', $data)` which renders a Temple8 template as the whole message (it receives `$prompt`, `$answers`, `$choices`, `$parameters`, `$step`, `$steps`). `askUsing()` sends the prompt yourself and gives up the conversation's keyboard handling.
- Other question options: `name()`, `validate($rules, $messages)`, `retry('...')`, `type('photo'|'location'|'contact'|...)`, `cast('int')` / `transform(fn ($value) => ...)`, `optional('Skip', default: null)` / `skipCommand('/skip')` / `default($value)`, `attempts(5)`, `then(fn ($request, $answer, $answers) => ...)`, `back(...)`/`noBack()`, `priority(Priority::Conversation)`.
- Branch with `when()` / `unless()` on a question, and steer from a `then()` callback or a hook by returning `Flow::goTo('name')`, `Flow::repeat()`, `Flow::finish()` or `Flow::cancel()` (inside a class: `$this->goTo(...)`, `$this->repeat()`, `$this->finish()`). Skipped questions leave no answer and are passed over by back navigation too.
- Answers are `Answer` objects: `text()`, `data()` (callback data), `raw()` (the stored value: a choice value, an array for `multiple()`, a bool for `confirm()`), `file()`, `media()`, `download($path, $disk)`, `isSkipped()`; they are `Stringable`.
- Lifecycle hooks: `onStart`, `onAsk`, `onAnswer` (may return a `Flow`), `onSkip`, `onBack` (may return a `Flow`), `onInvalid`, `onCancel` (reasons: `command`, `timeout`, `max_attempts`, `interrupted`, `manual`), `onComplete`. Parameters passed to `Conversation::start('Onboarding', ['plan' => 'pro'])` are read with `$this->parameter('plan')`.
- Rejected answers are explained automatically (the first validation error, or `retry(...)`), and the keyboard of the last prompt is taken back when the flow ends (`clearKeyboard`). A reply keyboard is replaced by every following prompt, so it never outlives the question it belongs to.
- By default a matching regular or step listen interrupts an active conversation (`regular listen > step listen > conversation > fallback`). Use `Priority::Conversation` for questions that must not be interrupted, such as confirmation codes.
- For small, one-off flows use `Conversation::inline(fn (Questioner $q) => ...)->onComplete(...)->start()`, or the single-question forms `Conversation::ask('Your email?', 'email')->validate('email')->onComplete(...)`, `Conversation::choose('Plan?', [...], 'plan')` and `Conversation::confirm('Delete it?', 'sure')` - every question method may be called straight on the builder.

## The Step Manager

The `Step` facade stores the current step per user (keyed by `user()->id`) in the default cache store. Pair it with step listens so each stage has its own handler:

```php
use LaraGram\Support\Facades\Bot;
use LaraGram\Support\Facades\Step;

Bot::onCommand('feedback', function (Request $request) {
    Step::set('awaiting_feedback', 600); // expires after 10 minutes

    $request->sendMessage(chat()->id, 'Tell us what you think. Send /cancel to stop.');
});

Bot::onStep('awaiting_feedback', function (Request $request, string $text) {
    Step::forget();

    Feedback::create(['user_id' => user()->id, 'body' => $text]);

    $request->sendMessage(chat()->id, 'Thanks!');
});
```

- API: `Step::set($step, $ttl)`, `get()`, `pull()`, `forget()`, `hasStep()`, `hasNotStep()`, `is($step)`, `isNot($step)`, and sequences (`startSequence()`, `next()`, `previous()`, `current()`, `endSequence()`) for ordered wizards.
- `Bot::onStep($step, $action, pattern: '{age}', method: 'TEXT')` narrows what a step listen accepts.
- Always give steps a TTL or a way out (`/cancel`), and clear the step when the flow ends.
- Step listens are evaluated after regular listens unless `Bot::enableStepListensPriorityRegister()` is enabled.
