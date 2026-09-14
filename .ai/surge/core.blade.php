@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram Surge

- This application can run under LaraGram Surge: the application boots once and stays in memory across many bot updates and web requests. Code that stores request-specific data in static properties, singletons, or long-lived objects leaks between users.
- IMPORTANT: Activate `surge-development` when writing service providers, singletons, static state, concurrent tasks, ticks, Swoole tables, the `surge` cache store, background processes, or when debugging behavior that differs between `{{ $assist->commanderCommand('serve') }}` and `{{ $assist->commanderCommand('surge:start') }}`.
- After changing code, the running server keeps the old code in memory: use `{{ $assist->commanderCommand('surge:start --watch') }}` in development and `{{ $assist->commanderCommand('surge:reload') }}` after deploying. `surge:reload` does not restart Surge background processes such as the MTProto pump; restart the server for those.
