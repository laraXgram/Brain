# Conversations and Steps

Webhook bots handle every update in a fresh request, so multi-message flows need persisted state. LaraGram offers two tools; both store state in the cache, so use a shared, persistent store (such as `redis`) in production.

| Use | When |
| --- | --- |
| **Conversation** | A linear list of questions with validation, typed answers, skip/back/cancel, and a completion callback |
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

            $questioner->ask('How old are you?')
                ->name('age')
                ->validate('required|integer|between:1,120');

            $questioner->ask('Send your profile photo')->name('avatar')->type('photo');
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
- Question options: `name()`, `validate($rules, $messages)`, `type('photo'|'location'|'contact'|...)`, `keyboard(...)`, media prompts (`photo()`, `document()`, ...), `askUsing(fn (Request $request) => ...)`, `skipCommand('/skip')`, `attempts(5)`, `back(...)`/`noBack()`, `priority(Priority::Conversation)`.
- Answers are `Answer` objects: `text()`, `data()` (callback data), `file()`, `media()`, `download($path, $disk)`, `isSkipped()`; they are `Stringable`.
- Lifecycle hooks: `onStart`, `onAsk`, `onAnswer`, `onSkip`, `onBack`, `onInvalid`, `onCancel` (reasons: `command`, `timeout`, `max_attempts`, `interrupted`, `manual`), `onComplete`.
- By default a matching regular or step listen interrupts an active conversation (`regular listen > step listen > conversation > fallback`). Use `Priority::Conversation` for questions that must not be interrupted, such as confirmation codes.
- For small, one-off flows use `Conversation::inline(fn (Questioner $q) => ...)->onComplete(...)->start()` or `Conversation::ask('What is your name?', 'name')->onComplete(...)`.

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
