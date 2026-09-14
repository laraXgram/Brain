@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# MTProto

- `laraxgram/mtproto` connects the application to Telegram as a real client (a user account "userbot", or a bot over MTProto). Listeners for MTProto sessions use the `Client` facade and `LaraGram\MTProto\Foundation\ClientRequest`, and are registered under `client:` in `withListener()` (or bound to a session name under `bot:`).
- IMPORTANT: Activate `mtproto-development` whenever you work with MTProto sessions, `Client::` listeners, `ClientRequest`, `client:auth` / `client:start`, `config/mtproto.php`, or MTProto calls from jobs and web requests.
- Sessions are credentials. Never print, commit, or copy session files, `API_ID`, or `API_HASH`, and never run `{{ $assist->commanderCommand('client:auth') }}` or `session:*` commands without the user's explicit request.
