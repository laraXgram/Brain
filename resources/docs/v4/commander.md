# Commander Console

<a name="introduction"></a>
## Introduction

Commander is the command line interface included with LaraGram. Commander exists at the root of your application as the `laragram` script and provides a number of helpful commands that can assist you while you build your application. To view a list of all available Commander commands, you may use the `list` command:

```shell
php laragram list
```

Every command also includes a "help" screen which displays and describes the command's available arguments and options. To view a help screen, precede the name of the command with `help`:

```shell
php laragram help migrate
```

<a name="laragram-armada"></a>
#### LaraGram Armada

If you are using [LaraGram Armada](/armada) as your local development environment, remember to use the `armada` command line to invoke Commander commands. Armada will execute your Commander commands within your application's Docker containers:

```shell
./vendor/bin/armada laragram list
```

<a name="writing-commands"></a>
## Writing Commands

In addition to the commands provided with Commander, you may build your own custom commands. Commands are typically stored in the `app/Console/Commands` directory; however, you are free to choose your own storage location as long as you instruct LaraGram to [scan other directories for Commander commands](#registering-commands).

<a name="generating-commands"></a>
### Generating Commands

To create a new command, you may use the `make:command` Commander command. This command will create a new command class in the `app/Console/Commands` directory. Don't worry if this directory does not exist in your application - it will be created the first time you run the `make:command` Commander command:

```shell
php laragram make:command SendEmails
```

<a name="command-structure"></a>
### Command Structure

After generating your command, you should define the command's signature and description using the `Signature` and `Description` attributes. The `Signature` attribute also allows you to define [your command's input expectations](#defining-input-expectations). The `handle` method will be called when your command is executed. You may place your command logic in this method.

Let's take a look at an example command. Note that we are able to request any dependencies we need via the command's `handle` method. The LaraGram [service container](/v4/container) will automatically inject all dependencies that are type-hinted in this method's signature:

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DripEmailer;
use LaraGram\Console\Attribute\Description;
use LaraGram\Console\Attribute\Signature;
use LaraGram\Console\Command;

#[Signature('mail:send {user}')]
#[Description('Send a marketing email to a user')]
class SendEmails extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DripEmailer $drip): void
    {
        $drip->send(User::find($this->argument('user')));
    }
}
```

> [!NOTE]
> For greater code reuse, it is good practice to keep your console commands light and let them defer to application services to accomplish their tasks. In the example above, note that we inject a service class to do the "heavy lifting" of sending the e-mails.

<a name="exit-codes"></a>
#### Exit Codes

If nothing is returned from the `handle` method and the command executes successfully, the command will exit with a `0` exit code, indicating success. However, the `handle` method may optionally return an integer to manually specify the command's exit code:

```php
$this->error('Something went wrong.');

return 1;
```

If you would like to "fail" the command from any method within the command, you may utilize the `fail` method. The `fail` method will immediately terminate execution of the command and return an exit code of `1`:

```php
$this->fail('Something went wrong.');
```

<a name="closure-commands"></a>
### Closure Commands

Closure-based commands provide an alternative to defining console commands as classes. In the same way that listen closures are an alternative to controllers, think of command closures as an alternative to command classes.

Even though the `listens/console.php` file does not define HTTP listens, it defines console-based entry points (listens) into your application. Within this file, you may define all of your closure-based console commands using the `Commander::command` method. The `command` method accepts two arguments: the [command signature](#defining-input-expectations) and a closure which receives the command's arguments and options:

```php
Commander::command('mail:send {user}', function (string $user) {
    $this->info("Sending email to: {$user}!");
});
```

The closure is bound to the underlying command instance, so you have full access to all of the helper methods you would typically be able to access on a full command class.

<a name="type-hinting-dependencies"></a>
#### Type-Hinting Dependencies

In addition to receiving your command's arguments and options, command closures may also type-hint additional dependencies that you would like resolved out of the [service container](/v4/container):

```php
use App\Models\User;
use App\Support\DripEmailer;
use LaraGram\Support\Facades\Commander;

Commander::command('mail:send {user}', function (DripEmailer $drip, string $user) {
    $drip->send(User::find($user));
});
```

<a name="closure-command-descriptions"></a>
#### Closure Command Descriptions

When defining a closure-based command, you may use the `purpose` method to add a description to the command. This description will be displayed when you run the `php laragram list` or `php laragram help` commands:

```php
Commander::command('mail:send {user}', function (string $user) {
    // ...
})->purpose('Send a marketing email to a user');
```

<a name="isolatable-commands"></a>
### Isolatable Commands

> [!WARNING]
> To utilize this feature, your application must be using the `memcached`, `redis`, `dynamodb`, `database`, `file`, or `array` cache driver as your application's default cache driver. In addition, all servers must be communicating with the same central cache server.

Sometimes you may wish to ensure that only one instance of a command can run at a time. To accomplish this, you may implement the `LaraGram\Contracts\Console\Isolatable` interface on your command class:

```php
<?php

namespace App\Console\Commands;

use LaraGram\Console\Command;
use LaraGram\Contracts\Console\Isolatable;

class SendEmails extends Command implements Isolatable
{
    // ...
}
```

When you mark a command as `Isolatable`, LaraGram automatically makes the `--isolated` option available for the command without needing to explicitly define it in the command's options. When the command is invoked with that option, LaraGram will ensure that no other instances of that command are already running. LaraGram accomplishes this by attempting to acquire an atomic lock using your application's default cache driver. If other instances of the command are running, the command will not execute; however, the command will still exit with a successful exit status code:

```shell
php laragram mail:send 1 --isolated
```

If you would like to specify the exit status code that the command should return if it is not able to execute, you may provide the desired status code via the `isolated` option:

```shell
php laragram mail:send 1 --isolated=12
```

<a name="lock-id"></a>
#### Lock ID

By default, LaraGram will use the command's name to generate the string key that is used to acquire the atomic lock in your application's cache. However, you may customize this key by defining an `isolatableId` method on your Commander command class, allowing you to integrate the command's arguments or options into the key:

```php
/**
 * Get the isolatable ID for the command.
 */
public function isolatableId(): string
{
    return $this->argument('user');
}
```

<a name="lock-expiration-time"></a>
#### Lock Expiration Time

By default, isolation locks expire after the command is finished. Or, if the command is interrupted and unable to finish, the lock will expire after one hour. However, you may adjust the lock expiration time by defining an `isolationLockExpiresAt` method on your command:

```php
use DateTimeInterface;
use DateInterval;

/**
 * Determine when an isolation lock expires for the command.
 */
public function isolationLockExpiresAt(): DateTimeInterface|DateInterval
{
    return now()->plus(minutes: 5);
}
```

<a name="defining-input-expectations"></a>
## Defining Input Expectations

When writing console commands, it is common to gather input from the user through arguments or options. LaraGram makes it very convenient to define the input you expect from the user using the `signature` property on your commands. The `signature` property allows you to define the name, arguments, and options for the command in a single, expressive, listen-like syntax.

<a name="arguments"></a>
### Arguments

All user supplied arguments and options are wrapped in curly braces. In the following example, the command defines one required argument: `user`:

```php
/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send {user}';
```

You may also make arguments optional or define default values for arguments:

```php
// Optional argument...
'mail:send {user?}'

// Optional argument with default value...
'mail:send {user=foo}'
```

<a name="options"></a>
### Options

Options, like arguments, are another form of user input. Options are prefixed by two hyphens (`--`) when they are provided via the command line. There are two types of options: those that receive a value and those that don't. Options that don't receive a value serve as a boolean "switch". Let's take a look at an example of this type of option:

```php
/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send {user} {--queue}';
```

In this example, the `--queue` switch may be specified when calling the Commander command. If the `--queue` switch is passed, the value of the option will be `true`. Otherwise, the value will be `false`:

```shell
php laragram mail:send 1 --queue
```

<a name="options-with-values"></a>
#### Options With Values

Next, let's take a look at an option that expects a value. If the user must specify a value for an option, you should suffix the option name with a `=` sign:

```php
/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send {user} {--queue=}';
```

In this example, the user may pass a value for the option like so. If the option is not specified when invoking the command, its value will be `null`:

```shell
php laragram mail:send 1 --queue=default
```

You may assign default values to options by specifying the default value after the option name. If no option value is passed by the user, the default value will be used:

```php
'mail:send {user} {--queue=default}'
```

<a name="option-shortcuts"></a>
#### Option Shortcuts

To assign a shortcut when defining an option, you may specify it before the option name and use the `|` character as a delimiter to separate the shortcut from the full option name:

```php
'mail:send {user} {--Q|queue=}'
```

When invoking the command on your terminal, option shortcuts should be prefixed with a single hyphen and no `=` character should be included when specifying a value for the option:

```shell
php laragram mail:send 1 -Qdefault
```

<a name="input-arrays"></a>
### Input Arrays

If you would like to define arguments or options to expect multiple input values, you may use the `*` character. First, let's take a look at an example that specifies such an argument:

```php
'mail:send {user*}'
```

When running this command, the `user` arguments may be passed in order to the command line. For example, the following command will set the value of `user` to an array with `1` and `2` as its values:

```shell
php laragram mail:send 1 2
```

This `*` character can be combined with an optional argument definition to allow zero or more instances of an argument:

```php
'mail:send {user?*}'
```

<a name="option-arrays"></a>
#### Option Arrays

When defining an option that expects multiple input values, each option value passed to the command should be prefixed with the option name:

```php
'mail:send {--id=*}'
```

Such a command may be invoked by passing multiple `--id` arguments:

```shell
php laragram mail:send --id=1 --id=2
```

<a name="input-descriptions"></a>
### Input Descriptions

You may assign descriptions to input arguments and options by separating the argument name from the description using a colon. If you need a little extra room to define your command, feel free to spread the definition across multiple lines:

```php
/**
 * The name and signature of the console command.
 *
 * @var string
 */
protected $signature = 'mail:send
                        {user : The ID of the user}
                        {--queue : Whether the job should be queued}';
```

<a name="prompting-for-missing-input"></a>
### Prompting for Missing Input

If your command contains required arguments, the user will receive an error message when they are not provided. Alternatively, you may configure your command to automatically prompt the user when required arguments are missing by implementing the `PromptsForMissingInput` interface:

```php
<?php

namespace App\Console\Commands;

use LaraGram\Console\Command;
use LaraGram\Contracts\Console\PromptsForMissingInput;

class SendEmails extends Command implements PromptsForMissingInput
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:send {user}';

    // ...
}
```

If LaraGram needs to gather a required argument from the user, it will automatically ask the user for the argument by intelligently phrasing the question using either the argument name or description. If you wish to customize the question used to gather the required argument, you may implement the `promptForMissingArgumentsUsing` method, returning an array of questions keyed by the argument names:

```php
/**
 * Prompt for missing input arguments using the returned questions.
 *
 * @return array<string, string>
 */
protected function promptForMissingArgumentsUsing(): array
{
    return [
        'user' => 'Which user ID should receive the mail?',
    ];
}
```

You may also provide placeholder text by using a tuple containing the question and placeholder:

```php
return [
    'user' => ['Which user ID should receive the mail?', 'E.g. 123'],
];
```

If you would like complete control over the prompt, you may provide a closure that should prompt the user and return their answer:

```php
use App\Models\User;
use function LaraGram\Console\Prompts\search;

// ...

return [
    'user' => fn () => search(
        label: 'Search for a user:',
        placeholder: 'E.g. Taylor Otwell',
        options: fn ($value) => strlen($value) > 0
            ? User::whereLike('name', "%{$value}%")->pluck('name', 'id')->all()
            : []
    ),
];
```

> [!NOTE]
The comprehensive [LaraGram Prompts](/v4/prompts) documentation includes additional information on the available prompts and their usage.

If you wish to prompt the user to select or enter [options](#options), you may include prompts in your command's `handle` method. However, if you only wish to prompt the user when they have also been automatically prompted for missing arguments, then you may implement the `afterPromptingForMissingArguments` method:

```php
use LaraGram\Console\Input\InputInterface;
use LaraGram\Console\Output\OutputInterface;
use function LaraGram\Console\Prompts\confirm;

// ...

/**
 * Perform actions after the user was prompted for missing arguments.
 */
protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
{
    $input->setOption('queue', confirm(
        label: 'Would you like to queue the mail?',
        default: $this->option('queue')
    ));
}
```

<a name="command-io"></a>
## Command I/O

<a name="retrieving-input"></a>
### Retrieving Input

While your command is executing, you will likely need to access the values for the arguments and options accepted by your command. To do so, you may use the `argument` and `option` methods. If an argument or option does not exist, `null` will be returned:

```php
/**
 * Execute the console command.
 */
public function handle(): void
{
    $userId = $this->argument('user');
}
```

If you need to retrieve all of the arguments as an `array`, call the `arguments` method:

```php
$arguments = $this->arguments();
```

Options may be retrieved just as easily as arguments using the `option` method. To retrieve all of the options as an array, call the `options` method:

```php
// Retrieve a specific option...
$queueName = $this->option('queue');

// Retrieve all options as an array...
$options = $this->options();
```

You may use the `input` method to retrieve a command's arguments and options as an `LaraGram\Console\CommandInput` instance, which provides the same typed accessors that are available on HTTP requests and other data containers:

```php
use App\Enums\ReportType;

/**
 * Execute the console command.
 */
public function handle(): void
{
    $input = $this->input()->date('from');

    // ...
}
```

The `input` method may also be used to retrieve a single input value from either the arguments or options:

```php
$queue = $this->input('queue', 'default');
```

<a name="prompting-for-input"></a>
### Prompting for Input

> [!NOTE]
> [LaraGram Prompts](/v4/prompts) is a PHP package for adding beautiful and user-friendly forms to your command-line applications, with browser-like features including placeholder text and validation.

In addition to displaying output, you may also ask the user to provide input during the execution of your command. The `ask` method will prompt the user with the given question, accept their input, and then return the user's input back to your command:

```php
/**
 * Execute the console command.
 */
public function handle(): void
{
    $name = $this->ask('What is your name?');

    // ...
}
```

The `ask` method also accepts an optional second argument which specifies the default value that should be returned if no user input is provided:

```php
$name = $this->ask('What is your name?', 'Taylor');
```

The `secret` method is similar to `ask`, but the user's input will not be visible to them as they type in the console. This method is useful when asking for sensitive information such as passwords:

```php
$password = $this->secret('What is the password?');
```

<a name="asking-for-confirmation"></a>
#### Asking for Confirmation

If you need to ask the user for a simple "yes or no" confirmation, you may use the `confirm` method. By default, this method will return `false`. However, if the user enters `y` or `yes` in response to the prompt, the method will return `true`.

```php
if ($this->confirm('Do you wish to continue?')) {
    // ...
}
```

If necessary, you may specify that the confirmation prompt should return `true` by default by passing `true` as the second argument to the `confirm` method:

```php
if ($this->confirm('Do you wish to continue?', true)) {
    // ...
}
```

<a name="auto-completion"></a>
#### Auto-Completion

The `anticipate` method can be used to provide auto-completion for possible choices. The user can still provide any answer, regardless of the auto-completion hints:

```php
$name = $this->anticipate('What is your name?', ['Taylor', 'Dayle']);
```

Alternatively, you may pass a closure as the second argument to the `anticipate` method. The closure will be called each time the user types an input character. The closure should accept a string parameter containing the user's input so far, and return an array of options for auto-completion:

```php
use App\Models\Address;

$name = $this->anticipate('What is your address?', function (string $input) {
    return Address::whereLike('name', "{$input}%")
        ->limit(5)
        ->pluck('name')
        ->all();
});
```

<a name="multiple-choice-questions"></a>
#### Multiple Choice Questions

If you need to give the user a predefined set of choices when asking a question, you may use the `choice` method. You may set the array index of the default value to be returned if no option is chosen by passing the index as the third argument to the method:

```php
$name = $this->choice(
    'What is your name?',
    ['Taylor', 'Dayle'],
    $defaultIndex
);
```

In addition, the `choice` method accepts optional fourth and fifth arguments for determining the maximum number of attempts to select a valid response and whether multiple selections are permitted:

```php
$name = $this->choice(
    'What is your name?',
    ['Taylor', 'Dayle'],
    $defaultIndex,
    $maxAttempts = null,
    $allowMultipleSelections = false
);
```

<a name="writing-output"></a>
### Writing Output

To send output to the console, you may use the `line`, `newLine`, `info`, `comment`, `question`, `warn`, `alert`, and `error` methods. Each of these methods will use appropriate ANSI colors for their purpose. For example, let's display some general information to the user. Typically, the `info` method will display in the console as green colored text:

```php
/**
 * Execute the console command.
 */
public function handle(): void
{
    // ...

    $this->info('The command was successful!');
}
```

To display an error message, use the `error` method. Error message text is typically displayed in red:

```php
$this->error('Something went wrong!');
```

You may use the `line` method to display plain, uncolored text:

```php
$this->line('Display this on the screen');
```

You may use the `newLine` method to display a blank line:

```php
// Write a single blank line...
$this->newLine();

// Write three blank lines...
$this->newLine(3);
```

<a name="tables"></a>
#### Tables

The `table` method makes it easy to correctly format multiple rows / columns of data. All you need to do is provide the column names and the data for the table and LaraGram will automatically calculate the appropriate width and height of the table for you:

```php
use App\Models\User;

$this->table(
    ['Name', 'Email'],
    User::all(['name', 'email'])->toArray()
);
```

<a name="progress-bars"></a>
#### Progress Bars

For long running tasks, it can be helpful to show a progress bar that informs users how complete the task is. Using the `withProgressBar` method, LaraGram will display a progress bar and advance its progress for each iteration over a given iterable value:

```php
use App\Models\User;

$users = $this->withProgressBar(User::all(), function (User $user) {
    $this->performTask($user);
});
```

Sometimes, you may need more manual control over how a progress bar is advanced. First, define the total number of steps the process will iterate through. Then, advance the progress bar after processing each item:

```php
$users = App\Models\User::all();

$bar = $this->output->createProgressBar(count($users));

$bar->start();

foreach ($users as $user) {
    $this->performTask($user);

    $bar->advance();
}

$bar->finish();
```

<a name="registering-commands"></a>
## Registering Commands

By default, LaraGram automatically registers all commands within the `app/Console/Commands` directory. However, you can instruct LaraGram to scan other directories for Commander commands using the `withCommands` method in your application's `bootstrap/app.php` file:

```php
->withCommands([
    __DIR__.'/../app/Domain/Orders/Commands',
])
```

If necessary, you may also manually register commands by providing the command's class name to the `withCommands` method:

```php
use App\Domain\Orders\Commands\SendEmails;

->withCommands([
    SendEmails::class,
])
```

When Commander boots, all the commands in your application will be resolved by the [service container](/v4/container) and registered with Commander.

<a name="programmatically-executing-commands"></a>
## Programmatically Executing Commands

Sometimes you may wish to execute an Commander command outside of the CLI. For example, you may wish to execute an Commander command from a listen or controller. You may use the `call` method on the `Commander` facade to accomplish this. The `call` method accepts either the command's signature name or class name as its first argument, and an array of command parameters as the second argument. The exit code will be returned:

```php
use LaraGram\Support\Facades\Commander;
use LaraGram\Support\Facades\Bot;

Bot::onText('send {user}', function (string $user) {
    $exitCode = Commander::call('mail:send', [
        'user' => $user, '--queue' => 'default'
    ]);

    // ...
});
```

Alternatively, you may pass the entire Commander command to the `call` method as a string:

```php
Commander::call('mail:send 1 --queue=default');
```

<a name="passing-array-values"></a>
#### Passing Array Values

If your command defines an option that accepts an array, you may pass an array of values to that option:

```php
use LaraGram\Support\Facades\Commander;
use LaraGram\Support\Facades\Bot;

Bot::onText('mail', function () {
    $exitCode = Commander::call('mail:send', [
        '--id' => [5, 13]
    ]);
});
```

<a name="passing-boolean-values"></a>
#### Passing Boolean Values

If you need to specify the value of an option that does not accept string values, such as the `--force` flag on the `migrate:refresh` command, you should pass `true` or `false` as the value of the option:

```php
$exitCode = Commander::call('migrate:refresh', [
    '--force' => true,
]);
```

<a name="queueing-laragram-commands"></a>
#### Queueing Commander Commands

Using the `queue` method on the `Commander` facade, you may even queue Commander commands so they are processed in the background by your [queue workers](/v4/queues). Before using this method, make sure you have configured your queue and are running a queue listener:

```php
use LaraGram\Support\Facades\Commander;
use LaraGram\Support\Facades\Bot;

Bot::onText('send {user}', function (string $user) {
    Commander::queue('mail:send', [
        'user' => $user, '--queue' => 'default'
    ]);

    // ...
});
```

Using the `onConnection` and `onQueue` methods, you may specify the connection or queue the Commander command should be dispatched to:

```php
Commander::queue('mail:send', [
    'user' => 1, '--queue' => 'default'
])->onConnection('redis')->onQueue('commands');
```

<a name="calling-commands-from-other-commands"></a>
### Calling Commands From Other Commands

Sometimes you may wish to call other commands from an existing Commander command. You may do so using the `call` method. This `call` method accepts the command name and an array of command arguments / options:

```php
/**
 * Execute the console command.
 */
public function handle(): void
{
    $this->call('mail:send', [
        'user' => 1, '--queue' => 'default'
    ]);

    // ...
}
```

If you would like to call another console command and suppress all of its output, you may use the `callSilently` method. The `callSilently` method has the same signature as the `call` method:

```php
$this->callSilently('mail:send', [
    'user' => 1, '--queue' => 'default'
]);
```

<a name="signal-handling"></a>
## Signal Handling

As you may know, operating systems allow signals to be sent to running processes. For example, the `SIGTERM` signal is how operating systems ask a program to terminate gracefully. If you wish to listen for signals in your Commander console commands and execute code when they occur, you may use the `trap` method:

```php
/**
 * Execute the console command.
 */
public function handle(): void
{
    $this->trap(SIGTERM, fn () => $this->shouldKeepRunning = false);

    while ($this->shouldKeepRunning) {
        // ...
    }
}
```

To listen for multiple signals at once, you may provide an array of signals to the `trap` method:

```php
$this->trap([SIGTERM, SIGQUIT], function (int $signal) {
    $this->shouldKeepRunning = false;

    dump($signal); // SIGTERM / SIGQUIT
});
```

<a name="stub-customization"></a>
## Stub Customization

The Commander console's `make` commands are used to create a variety of classes, such as controllers, jobs, migrations, and tests. These classes are generated using "stub" files that are populated with values based on your input. However, you may want to make small changes to files generated by Commander. To accomplish this, you may use the `stub:publish` command to publish the most common stubs to your application so that you can customize them:

```shell
php laragram stub:publish
```

The published stubs will be located within a `stubs` directory in the root of your application. Any changes you make to these stubs will be reflected when you generate their corresponding classes using Commander's `make` commands.

<a name="events"></a>
## Events

Commander dispatches three events when running commands: `LaraGram\Console\Events\CommanderStarting`, `LaraGram\Console\Events\CommandStarting`, and `LaraGram\Console\Events\CommandFinished`. The `CommanderStarting` event is dispatched immediately when Commander starts running. Next, the `CommandStarting` event is dispatched immediately before a command runs. Finally, the `CommandFinished` event is dispatched once a command finishes executing.