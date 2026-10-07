@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram Sentinel

- `laraxgram/sentinel` records every update the bot handles together with the Bot API calls, queries, logs, cache operations, conversation steps, jobs and exceptions it caused, and keeps time-bucketed metrics next to them. The dashboard lives at `/sentinel`.
- IMPORTANT: Activate `sentinel-debugging` whenever the user reports that the bot did something wrong in production, asks why an update failed or was slow, asks about webhook health, flood waits, blocked users, or wants to change what Sentinel records (`config/sentinel.php`, watchers, filters, tags, alerts).
- Read before guessing: an update entry is tagged `chat:<id>`, `user:<id>`, `connection:<name>`, `update:<type>`, `command:/start`, `unhandled`, `failed` and `slow`, and the entries it caused carry the same `chat:`/`user:` tags.
- Never set, delete or drop a webhook from the dashboard, run the playground or a replay in live mode, or clear recorded entries (`{{ $assist->commanderCommand('sentinel:clear') }}`) unless the user asks: each of them touches the live bot or destroys evidence.
- Keep `{{ $assist->commanderCommand('sentinel:check') }}` running under a process manager and `{{ $assist->commanderCommand('sentinel:prune') }}` scheduled daily; without them webhook history, server charts and alerts stay empty and the tables grow forever.
