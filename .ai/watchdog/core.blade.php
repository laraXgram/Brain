@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# Watchdog

- Watchdog shows application logs in the terminal in real time (`{{ $assist->commanderCommand('watchdog') }}`, also started by `{{ $assist->composerCommand('run dev') }}`), can report errors to Telegram chats (`report` in `config/watchdog.php`), and provides a `/log` manager panel inside Telegram for admins.
- When debugging, prefer the `last-error` and `read-log-entries` tools, or ask the user to check the Watchdog output. Do not enable Telegram reporting or add chats/admins to the Watchdog configuration without the user's request.
