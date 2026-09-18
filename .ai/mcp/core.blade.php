@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram MCP

- `laraxgram/mcp` builds Model Context Protocol servers (tools, resources, prompts, MCP Apps) and clients. Servers are registered in `routes/ai.php` with `Mcp::web()` / `Mcp::local()`.
- IMPORTANT: Activate `mcp-development` whenever you create or change MCP servers, tools, resources, prompts, MCP Apps (including Luna apps), Telegram tool sets (`BotApi`, `BotRuntime`, `MTProto`), MCP authentication, or MCP clients.
- Generate primitives with `{{ $assist->commanderCommand('make:mcp-tool') }}`, `make:mcp-resource`, `make:mcp-prompt`, `make:mcp-server`, and `make:mcp-app-resource` instead of writing them by hand.
- Never expose destructive Bot API or MTProto methods, or tokens and session data, through an MCP server without authentication, abilities, and the user's explicit request.
- A tool that messages many chats must use the `Broadcast` facade (queued, paced, confirmed with `Response::confirm`), never a loop of Bot API calls.
