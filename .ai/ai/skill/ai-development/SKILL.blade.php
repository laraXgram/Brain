---
name: ai-development
description: "Use this skill for AI features built with laraxgram/ai, the LaraGram AI SDK. Trigger when creating or editing agents (make:agent, Promptable, instructions(), HasTools, HasStructuredOutput, Conversational, RemembersConversations, attributes like Provider/Model/MaxSteps), tools (make:tool, Tool contract, FileStorage, Tools\\Telegram, SimilaritySearch, sub-agents, MCP tools, skills), agent middleware (make:agent-middleware, TelegramContext, LimitUsage), tool approvals, streaming, queueing, or Image, Audio, Transcription, Embeddings, Reranking, Classification, Files, Stores. Also trigger for Telegram AI features: returning prompt()/stream()/queue() responses from a listen, sendTo(), Telegram outputs (Quiz, Poll, Keyboard, RichMessage, Checklist, Location, Document, Invoice, InlineResults), $request->file() attachments, voice transcription, Bot::agent(), inline mode, the Moderate middleware, Stars top-ups, MTProto handlers, and Luna Mini App chats with useChat. Do not use for MCP servers (mcp-development) or for unrelated bot code."
license: MIT
metadata:
  author: laraxgram
---
@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram AI SDK Development

`laraxgram/ai` is the port of the Laravel AI SDK: the same classes, contracts, attributes and methods under the `LaraGram\Ai` namespace, plus Telegram support. Search the documentation (`search-docs` with queries such as `agent structured output`, `telegram output quiz`, `streaming in telegram`, `tool approval telegram`) before writing code, and read `vendor/laraxgram/ai/src` when you need an exact signature. Never invent methods: if something exists in the Laravel AI SDK it is spelled the same way here.

## Generators

```bash
{{ $assist->commanderCommand('make:agent SupportAgent') }}
{{ $assist->commanderCommand('make:agent QuizMaster --structured') }}
{{ $assist->commanderCommand('make:tool LookupOrder') }}
{{ $assist->commanderCommand('make:agent-middleware LogPrompts') }}
```

Agents live in `app/Ai/Agents`, tools in `app/Ai/Tools`, middleware in `app/Ai/Middleware`. Publish the configuration and the conversation migrations with `{{ $assist->commanderCommand('vendor:publish --provider="LaraGram\\Ai\\AiServiceProvider"') }}`, then migrate.

## Agents

```php
use LaraGram\Ai\Attributes\MaxSteps;
use LaraGram\Ai\Attributes\Provider;
use LaraGram\Ai\Concerns\RemembersConversations;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Conversational;
use LaraGram\Ai\Contracts\HasMiddleware;
use LaraGram\Ai\Contracts\HasTools;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Middleware\TelegramContext;
use LaraGram\Ai\Promptable;
use LaraGram\Ai\Tools\Telegram;

#[Provider(Lab::Anthropic)]
#[MaxSteps(8)]
class SupportAgent implements Agent, Conversational, HasTools, HasMiddleware
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return 'You are the support assistant of a Telegram bot. Answer in at most five sentences.';
    }

    public function tools(): iterable
    {
        return [new LookupOrder, ...Telegram::readOnly()];
    }

    public function middleware(): array
    {
        return [new TelegramContext];
    }
}
```

- `prompt()` returns an `AgentResponse` (string-castable), `stream()` a `StreamableAgentResponse`, `queue()` a `QueuedAgentResponse` (`->then()` / `->catch()`).
- Ad hoc agents: `use function LaraGram\Ai\agent;` then `agent('instructions', tools: [...], schema: ...)->prompt(...)`. An anonymous agent is conversational: pass history with `messages:`, never `withMessages()`.
- Structured output: implement `HasStructuredOutput` and return the schema from `schema(JsonSchema $schema)`; read the result like an array.
- Memory: `RemembersConversations` + `forUser($participant)`, `continue($id, as: $participant)`, `continueOrStart()`, `continueLastConversation()`. Telegram users (`user()`), chats (`chat()`) and Mini App users (`tg_user()`) are valid participants (`telegram:user` / `telegram:chat`); no model is required.

## Telegram: Return the Response From the Listen

The core rule of the Telegram integration: a response returned from a bot or MTProto listen is sent to the chat of the update, exactly like returning a response from a route. Do not call `sendMessage` yourself with `(string) $response`.

```php
Bot::onText('/ask {q}', fn (string $q) => (new SupportAgent)->forUser(user())->prompt($q));   // Markdown → Telegram formatting
Bot::onText('{q}', fn (string $q) => (new SupportAgent)->stream($q));                          // live draft / edited message
Bot::onText('/essay {t}', fn (string $t) => (new Writer)->queue($t));                          // typing now, answer from the queue
Bot::onText('/draw {p}', fn (string $p) => Image::of($p)->generate());                         // photo / album
Bot::onText('/say {p}', fn (string $p) => Audio::of($p)->generate());                          // voice
Bot::onVoice(fn (Request $r) => Transcription::fromUpload($r->file())->generate());            // transcript
Bot::agent(SupportAgent::class);                                                               // answer every message
```

- Outside a listen use `->sendTo($chatId, $connection)`; `'mtproto:session'` sends through MTProto. Inside a listen, `->reply()` sends without returning.
- Telegram files are uploads: `attachments: [$request->file()]`, `Files\Image::fromUpload($request->file())`, `Transcription::fromUpload($request->file())`. In MTProto handlers pass the `ClientRequest`.
- The models write Markdown; it is converted to Telegram HTML or rich messages (`ai.telegram.format`: `auto`, `html`, `rich`) and split automatically. Never ask the model for Telegram HTML or MarkdownV2.
- A provider call takes seconds: use `queue()` for long work, `stream()` when the user should watch the answer, and never block a webhook in a loop of prompts.

## Telegram Output

Return a Telegram output from `schema()` (or pass it to `agent(schema: ...)`) and the model fills it in:

```php
public function schema(JsonSchema $schema): Quiz
{
    return Quiz::questions(5)->options(4)->withExplanation();
}
```

Outputs (namespace `LaraGram\Ai\Telegram`): `Quiz`, `Poll`, `Keyboard::inline()->callbacks([...])`, `Keyboard::reply()`, `RichMessage`, `Checklist`, `Location` / `Location::venue()`, `Document::as('csv')`, `Invoice::stars($amount)` / `Invoice::price(...)`, `InlineResults`. Keyboards only keep callback data you listed; invoices never let the model set the price. Read `references/telegram.md` for every option.

## Tools and Approvals

- `Tools\Telegram::all()`, `readOnly()`, `messaging()`, `moderation()` act on the current chat; moderation tools require approval.
- Approvable tools (`Approvable` + `InteractsWithApprovals`) returned from a listen produce an Approve / Reject keyboard; the run resumes automatically. This needs a `RemembersConversations` agent. Customize with `Approvals::authorizeUsing()` and `Approvals::messageUsing()`.
- Tool `handle()` returns a string for the model; validate arguments with `$request->validate([...])`.

## Middleware

Agent middleware (`HasMiddleware`) receives a `PendingStep`: `TelegramContext` (user, language, chat, replied message, formatting), `LimitUsage::perUser(50_000, 'day')->topUpWithStars(25, 100_000)` (needs a persistent `ai.telegram.usage.store`). The bot middleware `LaraGram\Ai\Telegram\Middleware\Moderate` (`Moderate::class.':spam,scam'`) classifies group messages; configure `ai.telegram.moderation`.

## Mini Apps

Serve the chat from a `telegram`-protected route with `Vercel::chat($request)` and `->stream($chat)->usingProtocol($chat->protocol())`, and use `useChat({ api: '/chat' })` from `@laraxgram/react`, `@laraxgram/vue3` or `@laraxgram/svelte` in the Mini App.

## Rules

- Keep instructions in the agent class; never build them from raw user text.
- Never log or store provider keys; never send a user's personal data to a provider without a reason the user would expect.
- Use the fakes (`SupportAgent::fake([...])`, `Image::fake()`, `Transcription::fake()`) to run code locally without calling a provider.
- Bedrock needs no AWS SDK: it uses the AWS client in `LaraGram\Filesystem\Aws`.
