---
name: mcp-development
description: "Use this skill for LaraGram MCP development with laraxgram/mcp. Trigger when creating or editing MCP servers, tools, prompts, resources, resource templates, MCP Apps (including Luna apps), Telegram tool sets (BotApi, BotRuntime, MTProto), tools that broadcast to all users or groups (Broadcast facade), token authentication for MCP clients, subscriptions, elicitation, or MCP clients. Covers: make:mcp-* generators, routes/ai.php registration, attributes, response shapes, streaming, #[AsTool], #[RequiresAbility], Response::confirm/elicit, Mcp::toolsListChanged, mcp:start and mcp:inspector, and Surge considerations. Do not use for generic AI features without MCP."
license: MIT
metadata:
  author: laraxgram
---
@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram MCP Development

`laraxgram/mcp` is LaraGram's Model Context Protocol server and client, a port of Laravel MCP with Telegram-specific additions. It is not covered by the LaraGram website documentation: read the package source in `vendor/laraxgram/mcp/src` (and the generator stubs) when you need an exact signature.

## Quick Reference

### Generators

```bash
{{ $assist->commanderCommand('make:mcp-server ServerName') }}
{{ $assist->commanderCommand('make:mcp-tool ToolName') }}
{{ $assist->commanderCommand('make:mcp-resource ResourceName') }}
{{ $assist->commanderCommand('make:mcp-prompt PromptName') }}
{{ $assist->commanderCommand('make:mcp-app-resource AppName') }}
```

If `routes/ai.php` does not exist, publish it with `{{ $assist->commanderCommand('vendor:publish --tag=ai-routes') }}`.

### Registration

Primitives must be registered on a server, and the server in `routes/ai.php`:

@brainsnippet("Register MCP Servers", "php")
use App\Mcp\Servers\SupportServer;
use LaraGram\Mcp\Facades\Mcp;

Mcp::web('/mcp/support', SupportServer::class)->middleware('auth:citadel');
Mcp::local('support', SupportServer::class);
@endbrainsnippet

Add classes (or tool instances) to the server's `$tools`, `$resources`, and `$prompts` arrays, or build them in `protected function boot(): void`. Configure identity with `#[Name]`, `#[Version]`, and `#[Instructions]`.

### Tools

@brainsnippet("Tool Example", "php")
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Attributes\Description;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Look up an order by its number and return its status.')]
#[IsReadOnly]
class OrderStatus extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'number' => $schema->string()->description('The order number.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate(['number' => 'required|string']);

        $order = Order::where('number', $request->get('number'))->first();

        return $order
            ? Response::structured(['status' => $order->status])
            : Response::error('Order not found.');
    }
}
@endbrainsnippet

- Always add a meaningful `#[Description]`. Add `outputSchema(JsonSchema $schema)` when clients parse structured content.
- Annotations: `#[IsReadOnly]`, `#[IsDestructive]`, `#[IsIdempotent]`, `#[IsOpenWorld]`.
- Existing methods can become tools: mark public methods with `#[AsTool]` and register `CallableTool::fromClass(MyService::class)`.
- Stream progress by returning a `Generator` that yields `Response::notification(...)` before the final content.
- Ask the user for confirmation or input with `Response::confirm('Delete 5 messages?')` / `Response::elicit(...)`, and check `$request->confirmed()` / `$request->declined()` on the follow-up call.

### Resources and Prompts

@brainsnippet("Resource Example", "php")
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Attributes\Description;
use LaraGram\Mcp\Server\Attributes\MimeType;
use LaraGram\Mcp\Server\Attributes\Uri;
use LaraGram\Mcp\Server\Resource;

#[Description('The bot command reference.')]
#[Uri('file://bot/commands')]
#[MimeType('text/markdown')]
class CommandReference extends Resource
{
    public function handle(): Response
    {
        return Response::text('# Commands');
    }
}
@endbrainsnippet

Resource templates implement `HasUriTemplate` and return a `UriTemplate`; template variables are merged into the `Request`. Prompts define `LaraGram\Mcp\Server\Prompts\Argument` arguments and return one or more responses.

### Response Shapes

@brainsnippet("MCP Responses", "php")
Response::text('Text content');
Response::error('Error message');
Response::structured(['key' => 'value']);
Response::json(['key' => 'value']);
Response::image($bytes, 'image/png');
Response::audio($bytes, 'audio/mp3');
Response::fromStorage('path/file.png', disk: 'public');
Response::resourceLink(uri: 'file:///report.json', name: 'report');
Response::blob($bytes);
Response::view('mcp.dashboard', $data);
Response::confirm('Proceed?');
@endbrainsnippet

## Telegram Tool Sets

Generate tools from the Telegram APIs instead of writing one class per method:

@brainsnippet("Telegram Tool Sets", "php")
use LaraGram\Mcp\Server\Tools\ToolSearch;
use LaraGram\Mcp\Telegram\BotApi;
use LaraGram\Mcp\Telegram\BotRuntime;
use LaraGram\Mcp\Telegram\MTProto;

protected function boot(): void
{
    $this->tools = [
        // Bot API methods, called through the bot request (anti-flood, proxy, connections)
        ToolSearch::class => BotApi::tools()->preset('messaging')->withoutDestructive()->confirmDestructive()->all(),

        // Local development: list listens, simulate updates, render templates (nothing is sent)
        ...BotRuntime::tools()->all(),

        // MTProto session tools (login, security and payment methods are never exposed)
        ...MTProto::tools()->session('support')->readOnly()->all(),
    ];

    $this->resources = [...BotApi::resources(), ...MTProto::resources()];
}
@endbrainsnippet

- Bot API tool sets support `only()`, `except()`, `preset()`, `readOnly()`, `withoutDestructive()`, `allowChats()`, `connection()`, `requireAbilities()`, and `confirmDestructive()`. Keep large catalogs behind `ToolSearch`.
- `BotRuntime` tools (`bot_listens`, `bot_simulate_update`, `bot_render_template`, `bot_conversation_state`) are only enabled in the `local` environment unless `inEnvironments()` / `inAllEnvironments()` is used. LaraGram Brain already exposes this tool set to the editor's agent, so register it in a server of your own only when another client needs it.
- MTProto tools forward calls to the session's pump process when it runs under Surge, so they never open a second connection on the same session. Restrict peers with `allowPeers()` and file paths with `allowFilesFrom()`.

## Broadcasting From Tools

Bot API tool sets call one chat at a time. When a tool must reach many chats (all users, all groups, an audience), call the `Broadcast` facade from a custom tool instead of letting the model loop over chat ids. Broadcasts are queued, paced, and tracked, so the tool returns immediately with an id.

@brainsnippet("Broadcast Tool", "php")
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Attributes\Description;
use LaraGram\Mcp\Server\Attributes\RequiresAbility;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsDestructive;
use LaraGram\Support\Facades\Broadcast;

#[Description('Send an announcement to every user of the bot. Returns the broadcast id.')]
#[IsDestructive]
#[RequiresAbility('broadcasts:send')]
class AnnounceToUsers extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()->description('The announcement, in HTML.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate(['text' => 'required|string|max:4096']);

        $broadcast = Broadcast::users()->sendMessage($request->get('text'), 'HTML');

        $recipients = $broadcast->count();

        if (! $request->confirmed()) {
            return Response::confirm("Send this announcement to {$recipients} users?");
        }

        $id = $broadcast->send();

        return Response::structured(['broadcast_id' => $id, 'recipients' => $recipients]);
    }
}
@endbrainsnippet

- Always mark broadcast tools `#[IsDestructive]`, require an ability, and confirm with the audience size before sending.
- Expose progress with a separate `#[IsReadOnly]` tool returning `Broadcast::progress($id)?->toArray()` (or `Broadcast::recent()`), and cancellation with `Broadcast::cancel($id)`.
- Let tool arguments choose filters (`language`, `activeSince`, `tagged`, `membersOf`) and scheduling (`at`, `between`) rather than accepting raw chat id lists, and offer a preview tool that calls `->test($chatId)` on the operator's own chat.
- Tools can edit or undo a broadcast that used `->recallable()` with `Broadcast::sent($id)->editMessageText(...)` and `Broadcast::recall($id)` (which deletes what was sent, unpins, unbans, and so on); mark those `#[IsDestructive]` too.
- Pass `->bot($connection)` in multi-bot applications: MCP requests have no incoming update to pick the bot from.
- Never add broadcast tools to a server exposed to untrusted clients, and never broadcast to real chats while testing a server unless the user asks.

## Authentication and Authorization

- Protect web servers with route middleware (for example `auth:citadel` tokens), or configure OAuth with `Mcp::oauthRoutes()` when LaraGram Passport is installed.
- Require token abilities per primitive with `#[RequiresAbility('orders:read')]`; unauthorized primitives are hidden and refused.
- Users can receive a token from the bot itself: register `Bot::onCommand('mcp {action?}', \LaraGram\Mcp\Telegram\Auth\IssueAccessToken::class)` and configure it with `IssueAccessToken::using(...)`.
- `shouldRegister(Request $request)` controls discovery, but it must not be the only authorization check for sensitive operations.

## Subscriptions and Notifications

When the server's capabilities enable `listChanged`, clients may hold a `subscriptions/listen` stream. Publish changes from anywhere (listens, jobs, the MTProto pump) with `Mcp::toolsListChanged()`, `Mcp::resourcesListChanged()`, or `Mcp::resourceUpdated($uri)`. Configure the shared cache store and timeouts in `config/mcp.php` under `subscriptions`.

## MCP Apps

IMPORTANT: Read `references/app.md` before building or changing an MCP App; it covers `<x-mcp::app>`, the `createMcpApp` client API, `#[AppMeta]` (CSP, permissions, libraries), visibility, host theming, Luna apps, and common patterns.

Generate apps with `make:mcp-app-resource`, register them in `$resources`, and render with `Response::view(...)`. Link a tool with `#[RendersApp(resource: AppResource::class)]`, and configure CSP, permissions, and libraries with `#[AppMeta]`. For a React, Vue, or Svelte app, extend `LaraGram\Mcp\Luna\LunaAppResource` and build the single-file bundle with the `lunaMcpApp()` Vite plugin from `@laraxgram/vite`.

## Running and Debugging

```bash
# Mcp::local('support', ...) — stdio server started by an MCP client
{{ $assist->commanderCommand('mcp:start support') }}

# Coroutine stdio transport (Swoole), required for subscriptions over stdio
{{ $assist->commanderCommand('mcp:start support --coroutine') }}

# Interactive inspector for a web or local server
{{ $assist->commanderCommand('mcp:inspector mcp/support') }}
```

`mcp:start` waits for an MCP client on stdin; do not run it expecting interactive output. Under Surge, long `subscriptions/listen` streams hold a worker until they close, so keep `mcp.subscriptions.timeout` below Surge's `max_execution_time`.

## MCP Client

@brainsnippet("MCP Client", "php")
use LaraGram\Mcp\Client;

$client = Client::web('https://mcp.example.com')->withToken($token)->withTimeout(30)->connect();

$tools = $client->tools();
$result = $client->callTool('tool-name', ['key' => 'value']);

$client->disconnect();
@endbrainsnippet

Use `Client::local('php', ['laragram', 'mcp:start', 'handle'])` for a local stdio server. Register named clients with `Mcp::registerClient(...)` and resolve them with `Mcp::client(...)`.

## Critical Imports

@brainsnippet("Correct Imports", "php")
use LaraGram\Mcp\Request;           // NOT LaraGram\Mcp\Server\Request
use LaraGram\Mcp\Response;          // NOT LaraGram\Mcp\Server\Response
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Contracts\JsonSchema\JsonSchema;
@endbrainsnippet

## Common Pitfalls

- Wrong imports: `LaraGram\Mcp\Server\Request` (wrong) vs `LaraGram\Mcp\Request` (correct); `Laravel\...` or `Illuminate\...` namespaces do not exist.
- Forgetting to register primitives on the server or the server in `routes/ai.php`.
- Omitting a meaningful `#[Description]`.
- Exposing destructive Bot API or MTProto methods without `withoutDestructive()`, `confirmDestructive()`, or abilities.
- Looping over chats inside a tool instead of using `Broadcast` (the MCP request times out and Telegram rate limits the bot).
- Wrong response pattern: `new Response()` instead of `Response::text()`.
