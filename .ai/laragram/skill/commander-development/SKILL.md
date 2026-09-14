---
name: commander-development
description: "Writes LaraGram Commander (CLI) code. Activate when creating or changing console commands (make:command, app/Console/Commands, #[Signature], #[Description], closure commands with Commander::command in listens/console.php), command arguments and options, interactive prompts (LaraGram\\Console\\Prompts: text, select, confirm, search, form, spin, progress, table), command output, Isolatable commands, calling commands programmatically (Commander::call, queue), scheduling (Schedule facade, withSchedule, schedule:run, schedule:work, withoutOverlapping, onOneServer), running shell processes (Process facade, pools), or bot maintenance commands such as broadcasts, cleanups, and webhook tooling."
license: MIT
metadata:
  author: laraxgram
---

# Commander Development

Commander is LaraGram's console (`php laragram`). Commands, schedules, and processes follow the same patterns as Laravel's Artisan but live under `LaraGram\` namespaces. Use `search-docs` (`commander`, `prompts`, `scheduling`, `processes`) for exact options.

## Commands

Generate classes with `php laragram make:command SendDigest` (created in `app/Console/Commands`, registered automatically):

```php
namespace App\Console\Commands;

use App\Services\DigestService;
use LaraGram\Console\Attribute\Description;
use LaraGram\Console\Attribute\Signature;
use LaraGram\Console\Command;

#[Signature('digest:send {chat? : Only send to this chat id} {--dry-run : Show the recipients without sending}')]
#[Description('Send the daily digest to subscribed chats')]
class SendDigest extends Command
{
    public function handle(DigestService $digests): int
    {
        $chats = $this->argument('chat') ? [(int) $this->argument('chat')] : $digests->subscribedChats();

        if ($this->option('dry-run')) {
            $this->table(['Chat'], array_map(fn ($chat) => [$chat], $chats));

            return self::SUCCESS;
        }

        $this->withProgressBar($chats, fn (int $chat) => $digests->queueFor($chat));

        return self::SUCCESS;
    }
}
```

- Attributes come from `LaraGram\Console\Attribute` (singular). The `$signature` / `$description` properties also work; follow the style the project already uses.
- Signature syntax: `{arg}`, `{arg?}`, `{arg=default}`, `{arg*}`, `{--flag}`, `{--option=}`, `{--option=default}`, `{--O|option}`, descriptions after ` : `.
- Keep `handle()` thin: resolve services from the container (method injection) and put logic in services, jobs, or actions.
- Exit codes: return `self::SUCCESS` / `self::FAILURE`, or call `$this->fail('message')`.
- Output: `info`, `error`, `warn`, `line`, `newLine`, `table`, `withProgressBar`, and `$this->components->task(...)` / `->twoColumnDetail(...)`.
- Implement `LaraGram\Contracts\Console\Isolatable` for commands that must not run twice at once (`--isolated`).
- Extra command directories or classes are registered with `->withCommands([...])` in `bootstrap/app.php`.

## Closure Commands

Small commands and schedules live in `listens/console.php`:

```php
use LaraGram\Support\Facades\Commander;

Commander::command('users:prune-inactive {--days=90}', function (UserPruner $pruner) {
    $count = $pruner->prune((int) $this->option('days'));

    $this->info("Pruned {$count} users.");
})->purpose('Delete users who blocked the bot long ago');
```

## Prompts

Use prompt functions for interactive input, and always give non-interactive fallbacks (arguments or options) so the command works in scripts, CI, and for agents running with `--no-interaction`:

```php
use function LaraGram\Console\Prompts\confirm;
use function LaraGram\Console\Prompts\select;
use function LaraGram\Console\Prompts\spin;
use function LaraGram\Console\Prompts\text;

$connection = $this->option('connection') ?? select('Which bot?', array_keys(config('bot.connections')));
$message = text('Announcement text', required: true, validate: ['message' => 'max:4096']);

if (confirm("Send to all users of {$connection}?", default: false)) {
    spin(fn () => BroadcastAnnouncement::dispatch($connection, $message), 'Queueing...');
}
```

Other prompts: `password`, `textarea`, `multiselect`, `suggest`, `search`, `multisearch`, `pause`, `form()->...->submit()`, `note` / `info` / `warning` / `error` / `alert`, `table`, and `progress`. Commands can implement `PromptsForMissingInput` to ask for missing required arguments automatically.

## Calling Commands

`Commander::call('digest:send', ['chat' => 42, '--dry-run' => true])` returns the exit code; `Commander::output()` returns its output; `Commander::queue(...)` runs it on the queue. Inside another command use `$this->call(...)` / `$this->callSilently(...)`. Don't call long-running or interactive commands from listens or web requests; dispatch a job instead.

## Scheduling

Define schedules in `listens/console.php` (or `->withSchedule(fn (Schedule $schedule) => ...)` in `bootstrap/app.php`):

```php
use LaraGram\Support\Facades\Schedule;

Schedule::command('digest:send')->dailyAt('08:00')->timezone('Asia/Tehran')->withoutOverlapping()->onOneServer();
Schedule::job(new CleanExpiredSteps)->hourly();
Schedule::call(fn () => Order::expired()->delete())->everyFifteenMinutes();
```

- Production runs `* * * * * cd /path && php laragram schedule:run >> /dev/null 2>&1`; locally use `php laragram schedule:work`. Inspect with `schedule:list`, run one task with `schedule:test`, and add `schedule:interrupt` to deploys when sub-minute tasks exist.
- Scheduled tasks have no incoming update: `chat()`, `user()`, and `Auth::user()` are null. Pass chat ids explicitly and pick the bot connection (`$request->connection('shop-bot')`).
- Keep tasks short; dispatch queued jobs for Bot API broadcasts and let anti-flood pace them (`antiFloodWith('broadcast')`).
- `withoutOverlapping()` and `onOneServer()` need a shared cache store (Redis or database).

## Processes

```php
use LaraGram\Support\Facades\Process;

$result = Process::path(base_path())->timeout(120)->run(['git', 'rev-parse', 'HEAD']);

if ($result->failed()) {
    $this->error($result->errorOutput());
}
```

Pass commands as arrays to avoid shell injection, never interpolate user input into command strings, and use `Process::pool()` / `Process::concurrently()` for parallel work. `start()` runs asynchronously; call `wait()` before relying on the result.

## Safety

- Never create commands that print tokens, `API_HASH`, sessions, or `.env` values.
- Commands that change the live bot (webhooks, broadcasts, MTProto session actions) should require confirmation (`confirm()` or a `--force` option) and support `--dry-run` when practical.
