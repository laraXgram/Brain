@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# Probe

- Probe is LaraGram's REPL: `{{ $assist->commanderCommand('probe') }}` boots the whole application in the terminal, `{{ $assist->commanderCommand("probe --execute 'User::count();'") }}` runs one snippet, and `{{ $assist->commanderCommand('probe scratch.php') }}` runs a file. Prefer an existing Commander command when one does the same job.
- Probe runs against the real configuration, so a Bot API call such as `sendMessage` reaches Telegram and a write reaches the database. Read, do not write, unless the user asks, and never call a Bot API method from Probe on your own initiative.
- Jobs dispatched with the `dispatch` helper rely on garbage collection and may never be pushed from the shell. Use `Bus::dispatch` or `Queue::push` instead.
- Application classes are aliased automatically (`User::count()` needs no `use`). Vendor classes are not: they are listed in the `alias` array of `config/probe.php`, and `dont_alias` keeps a class from being aliased.
- Only the commands in the `commands` array of `config/probe.php` may be run from inside the shell, in addition to the small built-in allow list. `trust_project` decides whether the local autoloader and configuration are loaded at all; do not change either setting unless asked.
