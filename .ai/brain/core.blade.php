@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram Brain
@if($assist->hasMcpEnabled())

## Tools

- LaraGram Brain is an MCP server with tools designed specifically for this application. Prefer Brain tools over manual alternatives like shell commands or file reads.
- Use `application-info` at the start of a task to learn the installed LaraGram packages and their versions.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in probe.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `last-error` and `read-log-entries` to read recent application errors, for example after sending the bot a test update.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs (web routes, webhook URLs, Mini App URLs). Always use this before sharing a URL with the user.
@if (config('brain.browser_logs_watcher', true) !== false)
- Use `browser-logs` to read browser logs, errors, and exceptions from web pages and Telegram Mini Apps. Only recent logs are useful, ignore old entries.
@endif

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on LaraGram ecosystem APIs, behavior, configuration, or version-specific syntax. It searches the LaraGram documentation for the installed version and the Telegram Bot API methods and types, offline. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant, e.g. `['laraxgram/laraquest']` for Bot API methods and types, `['laraxgram/mtproto']`, or `['laraxgram/luna']`.
- Use multiple broad, topic-based queries: `['rate limiting', 'listening rate limiting', 'throttle']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `step listeners`, not `laragram 4 step listeners`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"inline keyboard"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `conversation "validate answer"`.
4. Use multiple queries for OR logic: `queries=["conversations", "step manager"]`.
@endif

@if(config('brain.rules.enabled', true))
## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (listens, templates, frontend, components) also live there, under `.ai/rules/brain` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
@if($assist->hasMcpEnabled())
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `listens/**` or `app/Conversations/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.
@endif

@endif
## Commander

- LaraGram's console is called Commander. Run commands directly via the command line (e.g., `{{ $assist->commanderCommand('listen:list') }}`). Use `{{ $assist->commanderCommand('list') }}` to discover available commands and `{{ $assist->commanderCommand('[command] --help') }}` to check parameters. Pass `--no-interaction` to commands that could prompt.
- Inspect bot listens with `{{ $assist->commanderCommand('listen:list') }}`. Use `-v` to show middleware and filter with `--pattern=admin`, `--except-vendor`, `--only-vendor`.
- Inspect web routes (when the application serves HTTP) with `{{ $assist->commanderCommand('route:list') }}`.
- Read configuration values using dot notation: `{{ $assist->commanderCommand('config:show bot.default') }}`, `{{ $assist->commanderCommand('config:show database.default') }}`. Or read config files directly from the `config/` directory.
- Inspect the webhook with `{{ $assist->commanderCommand('webhook:info') }}`. Only run `webhook:set`, `webhook:delete`, or `webhook:drop` when the user asks, because they change the live bot.

## Probe

- Execute PHP in the application context for debugging and exploring code. Do not create or change records without user approval, and do not call Telegram API methods from probe unless asked. Prefer existing Commander commands over custom probe code.
@if($assist->hasMcpEnabled() && config('brain.probe_tool_enabled', true))
- Use the `probe` MCP tool (when it is available) to execute PHP code instead of the CLI. It avoids shell escaping issues and runs the snippet in the LaraGram application context.
@endif
- On the command line, always use single quotes to prevent shell expansion: `{{ $assist->commanderCommand("probe --execute 'Your::code();'") }}`
  - Double quotes for PHP strings inside: `{{ $assist->commanderCommand("probe --execute 'User::where(\"status\", \"active\")->count();'") }}`
