# LaraGram AI SDK

- [Introduction](#introduction)
- [Installation](#installation)
    - [Configuration](#configuration)
    - [Custom Base URLs](#custom-base-urls)
    - [OpenAI-Compatible Providers](#openai-compatible-providers)
    - [Amazon Bedrock](#amazon-bedrock)
    - [On-Demand Providers](#on-demand-providers)
    - [Provider Support](#provider-support)
- [Agents](#agents)
    - [Prompting](#prompting)
    - [Conversation Context](#conversation-context)
    - [Structured Output](#structured-output)
    - [Attachments](#attachments)
    - [Streaming](#streaming)
    - [Broadcasting](#broadcasting)
    - [Queueing](#queueing)
    - [Tools](#tools)
    - [Deferred Tool Loading](#deferred-tool-loading)
    - [File Storage Tools](#file-storage-tools)
    - [MCP Tools](#mcp-tools)
    - [Skills](#skills)
    - [Provider Tools](#provider-tools)
    - [Sub-Agents](#sub-agents)
    - [Middleware](#middleware)
    - [Anonymous Agents](#anonymous-agents)
    - [Agent Configuration](#agent-configuration)
    - [Provider Options](#provider-options)
    - [Prompt Caching](#prompt-caching)
- [Telegram](#telegram)
    - [Responding to Updates](#responding-to-updates)
    - [Streaming in Telegram](#streaming-in-telegram)
    - [Queued Answers](#queued-answers)
    - [Telegram Files](#telegram-files)
    - [Telegram Output](#telegram-output)
    - [Telegram Tools](#telegram-tools)
    - [Approving Tools in Telegram](#approving-tools-in-telegram)
    - [Answering Every Message](#answering-every-message)
    - [Conversations With Telegram Users](#conversations-with-telegram-users)
    - [Telegram Context](#telegram-context)
    - [Usage Limits](#usage-limits)
    - [Moderation](#moderation)
    - [Inline Mode](#inline-mode)
    - [Mini Apps](#mini-apps)
- [Human Tool Approval](#human-tool-approval)
    - [Complete Approval Flow](#complete-approval-flow)
- [Images](#images)
- [Audio (TTS)](#audio)
- [Transcription (STT)](#transcription)
- [Text Summarization](#text-summarization)
- [Embeddings](#embeddings)
    - [Multimodal Embeddings](#multimodal-embeddings)
    - [Querying Embeddings](#querying-embeddings)
    - [Caching Embeddings](#caching-embeddings)
- [Reranking](#reranking)
- [Classification](#classification)
    - [Yes or No Decisions](#yes-or-no-decisions)
    - [Choosing From Collections](#choosing-from-collections)
- [Files](#files)
- [Vector Stores](#vector-stores)
    - [Adding Files to Stores](#adding-files-to-stores)
- [Usage](#usage)
- [Failover](#failover)
- [Events](#events)

<a name="introduction"></a>
## Introduction

The [LaraGram AI SDK](https://github.com/laraxgram/ai) provides a unified, expressive API for interacting with AI providers such as OpenAI, Anthropic, Gemini, and more. With the AI SDK, you can build intelligent agents with tools and structured output, generate images, synthesize and transcribe audio, create vector embeddings, and much more — all using a consistent, LaraGram-friendly interface.

The AI SDK is at home in a bot. A response returned from a [listen](/v4/listening) is sent to the chat, the photos, documents and voice messages users send become attachments, answers stream into the chat as they are written, and an agent may answer with a quiz, a poll, a keyboard or an invoice instead of text. The [Telegram](#telegram) section covers all of it, over the Bot API and over MTProto alike.

```php
use App\Ai\Agents\SupportAgent;
use LaraGram\Support\Facades\Bot;

Bot::onText('{question}', fn (string $question) => (new SupportAgent)->stream($question));
```

<a name="installation"></a>
## Installation

You can install the LaraGram AI SDK via Composer:

```shell
composer require laraxgram/ai
```

Next, you should publish the AI SDK configuration and migration files using the `vendor:publish` Commander command:

```shell
php laragram vendor:publish --provider="LaraGram\Ai\AiServiceProvider"
```

Finally, you should run your application's database migrations. This will create a `agent_conversations` and `agent_conversation_messages` table that the AI SDK uses to power its conversation storage:

```shell
php laragram migrate
```

<a name="configuration"></a>
### Configuration

You may define your AI provider credentials in your application's `config/ai.php` configuration file or as environment variables in your application's `.env` file:

```ini
ANTHROPIC_API_KEY=
AZURE_OPENAI_API_KEY=
COHERE_API_KEY=
DEEPSEEK_API_KEY=
ELEVENLABS_API_KEY=
GEMINI_API_KEY=
GROQ_API_KEY=
MISTRAL_API_KEY=
OLLAMA_API_KEY=
OPENAI_API_KEY=
OPENAI_COMPATIBLE_API_KEY=
OPENAI_COMPATIBLE_URL=
OPENROUTER_API_KEY=
JINA_API_KEY=
TYPESAFE_API_KEY=
VOYAGEAI_API_KEY=
XAI_API_KEY=
```

The default models used for text, images, audio, transcription, and embeddings may also be configured in your application's `config/ai.php` configuration file.

<a name="custom-base-urls"></a>
### Custom Base URLs

By default, the LaraGram AI SDK connects directly to each provider's public API endpoint. However, you may need to route requests through a different endpoint - for example, when using a proxy service to centralize API key management, implement rate limiting, or route traffic through a corporate gateway.

You may configure custom base URLs by adding a `url` parameter to your provider configuration:

```php
'providers' => [
    'openai' => [
        'driver' => 'openai',
        'key' => env('OPENAI_API_KEY'),
        'url' => env('OPENAI_URL'),
    ],

    'anthropic' => [
        'driver' => 'anthropic',
        'key' => env('ANTHROPIC_API_KEY'),
        'url' => env('ANTHROPIC_BASE_URL'),
    ],
],
```

This is useful when routing requests through a proxy service (such as LiteLLM or Azure OpenAI Gateway) or using alternative endpoints.

Custom base URLs are supported for the following providers: OpenAI, Anthropic, Gemini, Groq, Cohere, DeepSeek, xAI, and OpenRouter.

<a name="openai-compatible-providers"></a>
### OpenAI-Compatible Providers

If you are using an OpenAI-compatible API, such as LM Studio, vLLM, Together, Fireworks, or a local gateway, you may configure an `openai-compatible` provider. The `url` option is required, while the `key` option is optional and will be sent as a bearer token when present:

```php
'providers' => [
    'local' => [
        'driver' => 'openai-compatible',
        'url' => env('LOCAL_AI_URL'),
        'key' => env('LOCAL_AI_API_KEY'),
    ],
],
```

Once configured, you may use the named provider like any other provider:

```php
agent()->prompt('What is LaraGram?', provider: 'local', model: 'local-model');
```

You may also configure a default text model for the provider so that you do not need to pass a model explicitly:

```php
'local' => [
    'driver' => 'openai-compatible',
    'url' => env('LOCAL_AI_URL'),
    'key' => env('LOCAL_AI_API_KEY'),
    'models' => [
        'text' => [
            'default' => env('LOCAL_AI_MODEL'),
        ],
    ],
],
```

You may add custom HTTP headers to every outgoing request for the provider by defining a `headers` array in its configuration. This is useful when an endpoint requires an additional identifying or authentication header beyond the bearer token:

```php
'local' => [
    'driver' => 'openai-compatible',
    'url' => env('LOCAL_AI_URL'),
    'key' => env('LOCAL_AI_API_KEY'),
    'headers' => [
        'X-Tenant-Id' => env('LOCAL_AI_TENANT_ID'),
    ],
],
```

OpenAI-compatible providers support text generation, streaming, tools, structured output, image attachments, embeddings, and transcription. If your endpoint requires additional request body fields, provide them using [provider options](#provider-options).

<a name="openai-compatible-embeddings"></a>
#### OpenAI-Compatible Embeddings

Since arbitrary endpoints have no known models, you must configure a default embeddings model to use `embeddings()` with an OpenAI-compatible provider. You may also configure a fixed dimensions value; if omitted, the request is sent without a `dimensions` parameter and the model's native dimensions are used.

```php
'local' => [
    'driver' => 'openai-compatible',
    'url' => env('LOCAL_AI_URL'),
    'key' => env('LOCAL_AI_API_KEY'),
    'models' => [
        'embeddings' => [
            'default' => 'text-embedding-qwen3-embedding-0.6b',
            'dimensions' => 1024, // optional
        ],
    ],
],
```

<a name="openai-compatible-transcriptions"></a>
#### OpenAI-Compatible Transcriptions

Likewise, you must configure a default transcription model to use `Transcription` with an OpenAI-compatible provider. The audio will be uploaded to the endpoint's `/audio/transcriptions` route as a standard multipart request:

```php
'local' => [
    'driver' => 'openai-compatible',
    'url' => env('LOCAL_AI_URL'),
    'key' => env('LOCAL_AI_API_KEY'),
    'models' => [
        'transcription' => [
            'default' => 'whisper-1',
        ],
    ],
],
```

> [!NOTE]
> OpenAI-compatible and Groq providers do not support diarization. Invoking the `diarize` method when using these providers will throw an exception.

<a name="amazon-bedrock"></a>
### Amazon Bedrock

The `bedrock` provider calls Amazon Bedrock with LaraGram's own AWS client, so no AWS SDK is required. It authenticates with the first of the following that is configured:

<div class="content-list" markdown="1">

- A Bedrock API key, in the `AWS_BEARER_TOKEN_BEDROCK` environment variable.
- An access key, in the `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and optional `AWS_SESSION_TOKEN` environment variables.
- The default credential chain: the environment, a web identity token, the shared `~/.aws/credentials` and `~/.aws/config` files, the ECS container credentials, and the EC2 instance profile.

</div>

To call Bedrock as another IAM role, give the role's ARN in the `assume_role` option; the role is assumed through STS with the credentials above, and its temporary credentials are refreshed before they expire:

```php
'bedrock' => [
    'driver' => 'bedrock',
    'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
    'assume_role' => [
        'arn' => env('AWS_BEDROCK_ASSUME_ROLE_ARN'),
        'external_id' => env('AWS_BEDROCK_ASSUME_ROLE_EXTERNAL_ID'),
    ],
],
```

<a name="on-demand-providers"></a>
### On-Demand Providers

Sometimes you may need to use provider credentials that are not defined in your application's configuration file, such as API keys that are stored in your database for each tenant of a multi-tenant application. You may use the `Ai::build` method to create a provider from a configuration array. The array should have the same structure as a provider entry in your application's `config/ai.php` configuration file:

```php
use App\Ai\Agents\SalesCoach;
use LaraGram\Ai\Ai;

$response = (new SalesCoach)->prompt('Analyze this sales transcript...', provider: [
    Ai::build([
        'driver' => 'anthropic',
        'key' => $user->anthropic_key,
    ]),
]);
```

On-demand providers may be used anywhere a provider is accepted, including [failover](#failover) lists and when generating images, audio, transcriptions, and embeddings. To specify a model for an on-demand provider, include a `models` array in its configuration:

```php
$response = (new SalesCoach)->prompt('Analyze this sales transcript...', provider: [
    Ai::build(['driver' => 'anthropic', 'key' => $user->anthropic_key]),
    Ai::build([
        'driver' => 'openai',
        'key' => $user->openai_key,
        'models' => ['text' => ['default' => 'gpt-6']],
    ]),
]);
```

When an agent should always use an on-demand provider, you may return the provider from the agent's `provider` method. Since the provider is rebuilt when the agent is used, this approach also works well for [queued](#queueing) agents:

```php
use LaraGram\Ai\Ai;
use LaraGram\Ai\Providers\Provider;

public function provider(): Provider
{
    return Ai::build($this->user->aiConfiguration());
}
```

> [!NOTE]
> You should pass on-demand providers within an array, as shown in the examples above. If you give an on-demand provider a `name` in its configuration array, the name may not match a built-in provider or a provider defined in your `config/ai.php` configuration file.

<a name="provider-support"></a>
### Provider Support

The AI SDK supports a variety of providers across its features. The following table summarizes which providers are available for each feature:

<div class="overflow-auto">

| Feature | Providers |
|---|---|
| Text | OpenAI, OpenAI Compatible, Anthropic, Gemini, Azure, Bedrock, Groq, xAI, DeepSeek, Mistral, Ollama, OpenRouter |
| Images | OpenAI, Gemini, xAI, Azure, Bedrock, OpenRouter |
| TTS | OpenAI, ElevenLabs, Gemini, Mistral, OpenRouter |
| STT | OpenAI, OpenAI Compatible, ElevenLabs, Groq, Mistral, Gemini, OpenRouter |
| Embeddings | OpenAI, OpenAI Compatible, Gemini, Azure, Bedrock, Cohere, Mistral, Jina, VoyageAI, Ollama, OpenRouter |
| Reranking | Cohere, Jina, VoyageAI, Bedrock, OpenRouter |
| Classification | TypeSafe, OpenRouter |
| Files | OpenAI, Anthropic, Gemini, Azure, OpenRouter |

</div>

The `LaraGram\Ai\Enums\Lab` enum may be used to reference providers throughout your code instead of using plain strings:

```php
use LaraGram\Ai\Enums\Lab;

Lab::Anthropic;
Lab::OpenAI;
Lab::OpenAiCompatible;
Lab::Gemini;
// ...
```

<a name="agents"></a>
## Agents

Agents are the fundamental building block for interacting with AI providers in the LaraGram AI SDK. Each agent is a dedicated PHP class that encapsulates the instructions, conversation context, tools, and output schema needed to interact with a large language model. Think of an agent as a specialized assistant — a sales coach, a document analyzer, a support bot — that you configure once and prompt as needed throughout your application.

You can create an agent via the `make:agent` Commander command:

```shell
php laragram make:agent SalesCoach

php laragram make:agent SalesCoach --structured
```

Within the generated agent class, you can define the system prompt / instructions, message context, available tools, and output schema (if applicable):

```php
<?php

namespace App\Ai\Agents;

use App\Ai\Tools\RetrievePreviousTranscripts;
use App\Models\History;
use App\Models\User;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Conversational;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Contracts\HasTools;
use LaraGram\Ai\Messages\Message;
use LaraGram\Ai\Promptable;
use Stringable;

class SalesCoach implements Agent, Conversational, HasTools, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $user) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a sales coach, analyzing transcripts and providing feedback and an overall sales strength score.';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        return History::where('user_id', $this->user->id)
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->map(function ($message) {
                return new Message($message->role, $message->content);
            })->all();
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new RetrievePreviousTranscripts,
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback' => $schema->string()->required(),
            'score' => $schema->integer()->min(1)->max(10)->required(),
        ];
    }
}
```

<a name="prompting"></a>
### Prompting

To prompt an agent, first create an instance using the `make` method or standard instantiation, then call `prompt`:

```php
$response = (new SalesCoach)
    ->prompt('Analyze this sales transcript...');

return (string) $response;
```

The `make` method resolves your agent from the container, allowing automatic dependency injection. You may also pass arguments to the agent's constructor:

```php
$agent = SalesCoach::make(user: $user);
```

By passing additional arguments to the `prompt` method, you may override the default provider, model, or HTTP timeout when prompting:

```php
$response = (new SalesCoach)->prompt(
    'Analyze this sales transcript...',
    provider: Lab::Anthropic,
    model: 'claude-sonnet-5',
    timeout: 120,
);
```

<a name="raw-http-responses"></a>
#### Raw HTTP Responses

Every response returned from a text-generating agent exposes the raw HTTP response from the underlying provider API call via a `raw` property. This gives you access to provider-specific information that isn't part of the AI SDK's generic response - rate-limit headers, request IDs, or other exact payload fields:

```php
$response = (new SalesCoach)->prompt('Analyze this sales transcript...');

$response->raw; // LaraGram\Http\Client\Response|null

$response->raw->header('X-RateLimit-Remaining-Requests');
$response->raw->json('id');
```

In a tool-call loop, each step retains the raw response of its own request:

```php
foreach ($response->steps as $step) {
    $step->raw?->header('X-RateLimit-Remaining-Requests');
}
```

> [!NOTE]
> The `raw` property is `null` when streaming a response, when using the Bedrock provider (whose calls are signed and sent by LaraGram's native AWS client), and on faked responses unless one is provided explicitly via `withRawResponse`.

<a name="conversation-context"></a>
### Conversation Context

If your agent implements the `Conversational` interface, you may use the `messages` method to return the previous conversation context, if applicable:

```php
/**
 * Get the list of messages comprising the conversation so far.
 */
public function messages(): iterable
{
    return $this->user->history()
        ->latest()
        ->limit(50)
        ->get()
        ->reverse()
        ->map(fn ($message) => new Message(
            $message->role, $message->content,
        ))->all();
}
```

If your agent does not implement the `Conversational` interface, you may use the `withMessages` method to provide the conversation history for a single run, such as a history posted by your application's frontend:

```php
use LaraGram\Ai\Messages\Message;

$response = (new SalesCoach)
    ->withMessages([
        new Message('user', 'Analyze this sales transcript...'),
        new Message('assistant', 'The rep never asked for the close.'),
    ])
    ->prompt('What should they say next time?');
```

Agents that implement the `Conversational` interface load their own history, so combining the two approaches will throw a `LogicException`.

<a name="remembering-conversations"></a>
#### Remembering Conversations

> **Warning:** Before using the `RemembersConversations` trait, you should publish and run the AI SDK migrations using the `vendor:publish` Commander command. These migrations will create the necessary database tables to store conversations.

If you would like LaraGram to automatically store and retrieve conversation history for your agent, you may use the `RemembersConversations` trait. This trait provides a simple way to persist conversation messages to the database without manually implementing the `Conversational` interface:

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Ai\Concerns\RemembersConversations;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Conversational;
use LaraGram\Ai\Promptable;

class SalesCoach implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return 'You are a sales coach...';
    }
}
```

When using the `RemembersConversations` trait, do not manually define a `messages` method in your agent class. If a `messages` method is present, it will take precedence over the trait's implementation and conversation history will not be loaded from the database.

To start a new conversation for a user, call the `forUser` method before prompting:

```php
$response = (new SalesCoach)->forUser($user)->prompt('Hello!');

$conversationId = $response->conversationId;
```

In a bot, the Telegram user or chat of the update may be the participant, so no user model is needed — see [conversations with Telegram users](#conversations-with-telegram-users):

```php
$response = (new SalesCoach)->forUser(user())->prompt('Hello!');
```

The conversation ID is returned on the response and can be stored for future reference. If you would like to retrieve all of a user's conversations using Eloquent, you may add the `HasConversations` trait to your user model:

```php
<?php

namespace App\Models;

use LaraGram\Foundation\Auth\User as Authenticatable;
use LaraGram\Ai\Concerns\HasConversations;

class User extends Authenticatable
{
    use HasConversations;
}
```

Once the trait has been added to your model, you may retrieve and query the user's conversations via the `conversations` relationship:

```php
$conversations = $user->conversations()
    ->latest('updated_at')
    ->paginate(20);
```

To continue an existing conversation, use the `continue` method:

```php
$response = (new SalesCoach)
    ->continue($conversationId, as: $user)
    ->prompt('Tell me more about that.');
```

The `continueOrStart` method may be used to continue the given conversation, or start a new conversation if the given ID is `null`:

```php
$response = (new SalesCoach)
    ->continueOrStart($conversationId, as: $user)
    ->prompt('Hello!');
```

When using the `RemembersConversations` trait, previous messages are automatically loaded and included in the conversation context when prompting. New messages (both user and assistant) are automatically stored after each interaction. Each response also contains the IDs of the conversation and messages that were stored:

```php
$response->conversationId;
$response->userMessageId;
$response->assistantMessageId;
```

<a name="conversation-participants"></a>
#### Conversation Participants

Although users are the most common conversation participants, conversations may belong to any Eloquent model. Use the `forParticipant` method to start a conversation for another type of model:

```php
$response = (new SalesCoach)
    ->forParticipant($team)
    ->prompt('Review our latest sales results.');
```

The participant's morph class and primary key are stored with the conversation. Therefore, models of different types that have the same primary key, such as `User` ID `1` and `Team` ID `1`, have separate conversation histories. The `forUser` method is an alias for `forParticipant`.

You may continue the participant's most recent conversation with the agent using the `continueLastConversation` method. Conversations are scoped to the agent, so only conversations that the agent participated in will be continued:

```php
$response = (new SalesCoach)
    ->continueLastConversation($team)
    ->prompt('Tell me more about that.');
```

When continuing a specific conversation, pass the participant to the `continue` method:

```php
$response = (new SalesCoach)
    ->continue($conversationId, as: $team)
    ->prompt('Tell me more about that.');
```

The `HasConversations` trait may be added to any Eloquent model that participates in conversations. The resulting `conversations` relationship is a polymorphic relationship scoped to that model's type and primary key. You may also access the participant that owns a conversation through its inverse relationship:

```php
$conversations = $team->conversations;

$participant = $conversation->participant;
```

If your application uses multiple participant model types, you should consider defining an [Eloquent morph map](/v4/eloquent-relationships#custom-polymorphic-types) so that stored participant types are not coupled to your model class names.

> [!WARNING]
> The `continue` and `continueOrStart` methods do not verify that the given participant owns the conversation. Your application should authorize access to the conversation before continuing it.

<a name="inspecting-stored-conversations"></a>
#### Inspecting Stored Conversations

When displaying a conversation to your users, you often need details such as message IDs, timestamps, and tool calls. You may resolve the conversation store from the service container to read the stored messages without querying the AI SDK's tables directly:

```php
use LaraGram\Ai\Contracts\ConversationStore;

$store = app(ConversationStore::class);
```

Messages are paginated newest first using a cursor and are returned as `StoredMessage` instances, which contain each message's ID, timestamps, usage, metadata, and attachments:

```php
$messages = $store->paginateConversationMessages($conversationId, perPage: 25);

foreach ($messages as $message) {
    $message->id;
    $message->role;
    $message->content;
    $message->createdAt;
    $message->usage;
    $message->status;
}
```

Each turn, which consists of a user prompt and the assistant's reply, is stored as a list of steps. A step is a single request to the provider, so a turn in which the model calls tools will contain several steps. Each tool result is recorded on the tool call that produced it. The `toolCalls`, `providerToolCalls`, and `toolResults` methods flatten these steps in order, so you do not need to traverse them yourself:

```php
$message->steps;

$message->toolCalls();
$message->providerToolCalls();
$message->toolResults();
```

A tool call contains a `result` once it has been executed. Tool calls that contain an `approval_reason` but no `result` are still awaiting a [tool approval](#human-tool-approval).

The `status` property contains a `LaraGram\Ai\Enums\MessageStatus` instance. A turn that failed partway through is stored as `Failed` along with the steps it had already completed, so tool calls that ran before the failure remain in the history. When the conversation continues, any tool call without a recorded result is sent to the model marked as interrupted, since LaraGram cannot determine whether it ran.

Before continuing a conversation via an ID provided by your application's frontend, you should verify that the conversation was stored for the given participant:

```php
abort_unless($store->conversationBelongsTo(
    $conversationId, $user->getMorphClass(), $user->getKey()
), 403);
```

If the most recent turn is paused awaiting [tool approval](#human-tool-approval), you may render its pending tool calls after a page reload without resuming the run:

```php
foreach ($store->pendingApprovalsFor($conversationId) as $approval) {
    // $approval->id, $approval->tool, $approval->arguments, $approval->reason...
}
```

These methods are defined by the `PaginatesConversations`, `VerifiesConversationOwnership`, and `ResolvesPendingApprovals` contracts. The included database store implements all three, while a custom store may implement only the contracts it needs.

<a name="structured-output"></a>
### Structured Output

If you would like your agent to return structured output, implement the `HasStructuredOutput` interface, which requires that your agent define a `schema` method:

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Promptable;

class SalesCoach implements Agent, HasStructuredOutput
{
    use Promptable;

    // ...

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->required(),
        ];
    }
}
```

When prompting an agent that returns structured output, you can access the returned `StructuredAgentResponse` like an array:

```php
$response = (new SalesCoach)->prompt('Analyze this sales transcript...');

return $response['score'];
```

<a name="structured-output-nested-objects"></a>
#### Nested Objects

To define nested structured output, use the `object` method with a closure:

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Promptable;

class SalesCoach implements Agent, HasStructuredOutput
{
    use Promptable;

    // ...

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->required(),
            'metadata' => $schema->object(fn ($schema) => [
                'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
                'language' => $schema->string()->required(),
            ])->required(),
        ];
    }
}
```

<a name="structured-output-arrays-of-objects"></a>
#### Arrays of Objects

If your agent should return a list of structured items, combine the `array` and `object` methods:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'feedback' => $schema->array()
            ->items(
                $schema->object(fn ($schema) => [
                    'comment' => $schema->string()->required(),
                    'score' => $schema->integer()->required(),
                ])
            )
            ->required(),
    ];
}
```

If a value may match one of several schemas, use the `anyOf` method:

```php
public function schema(JsonSchema $schema): array
{
    return [
        'content' => $schema->anyOf([
            $schema->object(fn ($schema) => [
                'type' => $schema->string()->enum(['article'])->required(),
                'title' => $schema->string()->required(),
            ]),
            $schema->object(fn ($schema) => [
                'type' => $schema->string()->enum(['image'])->required(),
                'url' => $schema->string()->required(),
            ]),
        ])->required(),
    ];
}
```

<a name="attachments"></a>
### Attachments

When prompting, you may also pass attachments with the prompt to allow the model to inspect images and documents:

```php
use App\Ai\Agents\SalesCoach;
use LaraGram\Ai\Files;

$response = (new SalesCoach)->prompt(
    'Analyze the attached sales transcript...',
    attachments: [
        Files\Document::fromStorage('transcript.pdf'), // Attach a document from a filesystem disk...
        Files\Document::fromPath('/home/laragram/transcript.md'), // Attach a document from a local path...
        $request->file('transcript'), // Attach an uploaded file...
    ]
);
```

Likewise, the `LaraGram\Ai\Files\Image` class may be used to attach images to a prompt:

```php
use App\Ai\Agents\ImageAnalyzer;
use LaraGram\Ai\Files;

$response = (new ImageAnalyzer)->prompt(
    'What is in this image?',
    attachments: [
        Files\Image::fromStorage('photo.jpg'), // Attach an image from a filesystem disk...
        Files\Image::fromPath('/home/laragram/photo.jpg'), // Attach an image from a local path...
        $request->file('photo'), // Attach an uploaded file...
    ]
);
```

In a bot, the file of the update may be attached directly, just like an uploaded file — see [Telegram files](#telegram-files):

```php
Bot::onPhoto(fn (Request $request) => (new ImageAnalyzer)->prompt('What is in this image?', attachments: [
    $request->file(),
]));
```

<a name="streaming"></a>
### Streaming

You may stream an agent's response by invoking the `stream` method. Returned from a listen, the answer is [written into the chat](#streaming-in-telegram) as the model produces it. The returned `StreamableAgentResponse` may also be returned from a route to automatically send a streaming response (SSE) to the client:

```php
use App\Ai\Agents\SalesCoach;

Route::get('/coach', function () {
    return (new SalesCoach)->stream('Analyze this sales transcript...');
});
```

The `then` method may be used to provide a closure that will be invoked when the entire response has been streamed to the client:

```php
use App\Ai\Agents\SalesCoach;
use LaraGram\Ai\Responses\StreamedAgentResponse;

Route::get('/coach', function () {
    return (new SalesCoach)
        ->stream('Analyze this sales transcript...')
        ->then(function (StreamedAgentResponse $response) {
            // $response->text, $response->events, $response->usage...
        });
});
```

Alternatively, you may iterate through the streamed events manually:

```php
$stream = (new SalesCoach)->stream('Analyze this sales transcript...');

foreach ($stream as $event) {
    // ...
}
```

The response also contains the model's reasoning and any sources it cited. Both are stored with the assistant message when using the `RemembersConversations` trait:

```php
use LaraGram\Ai\Responses\StreamedAgentResponse;

(new SalesCoach)
    ->stream('Analyze this sales transcript...')
    ->then(function (StreamedAgentResponse $response) {
        $response->reasoning; // '' unless the model returned reasoning text...
        $response->meta->citations;
    });
```

Reasoning is also available on responses returned by the `prompt` method.

<a name="streaming-using-the-vercel-ai-sdk-protocol"></a>
<a name="stream-protocols"></a>
#### Stream Protocols

By default, streamed responses use the AI SDK's own event format. However, you may use a frontend streaming protocol instead, which allows you to pair your agent with an existing chat interface rather than building your own.

You may stream the events using the [Vercel AI SDK stream protocol](https://ai-sdk.dev/docs/ai-sdk-ui/stream-protocol) by invoking the `usingVercelDataProtocol` method on the streamable response:

```php
use App\Ai\Agents\SalesCoach;

Route::get('/coach', function () {
    return (new SalesCoach)
        ->stream('Analyze this sales transcript...')
        ->usingVercelDataProtocol();
});
```

You may pass a message ID if your application's frontend assigns its own:

```php
->usingVercelDataProtocol($request->string('messageId'));
```

Alternatively, the `usingAgentUserInteractionProtocol` method may be used to stream using the [Agent User Interaction (AG-UI) protocol](https://docs.ag-ui.com):

```php
Route::post('/coach', function (Request $request) {
    return (new SalesCoach)
        ->forUser($request->user())
        ->stream($request->string('prompt'))
        ->usingAgentUserInteractionProtocol();
});
```

The `threadId` and `runId` arguments are optional and default to the conversation ID and the invocation ID:

```php
->usingAgentUserInteractionProtocol(
    threadId: $request->input('threadId'),
    runId: $request->input('runId'),
);
```

To use a protocol that the AI SDK does not implement, you may pass your own `LaraGram\Ai\Streaming\Protocols\StreamProtocol` implementation to the `usingProtocol` method:

```php
use App\Ai\Protocols\CustomProtocol;

return (new SalesCoach)
    ->stream('Analyze this sales transcript...')
    ->usingProtocol(new CustomProtocol);
```

<a name="chat-requests"></a>
<a name="frontend-integration"></a>
#### Frontend Integration

Chat interfaces built with libraries such as Vercel's `useChat` or CopilotKit already render messages, tool calls, and approval prompts, so your application only needs to handle the requests they send. Each request contains the conversation history, the newest user message, and any tool approval responses.

The `Vercel::chat` and `AgentUserInteraction::chat` methods convert such a request into an object that may be passed directly to an agent's `stream` method:

```php
use LaraGram\Ai\Vercel\Vercel;

Route::post('/chat', function (Request $request) {
    $chat = Vercel::chat($request);

    return (new SupportAgent)
        ->withMessages($chat->history())
        ->stream($chat)
        ->usingProtocol($chat->protocol());
});
```

If the request contains [approval decisions](#human-tool-approval), the agent will resume using those decisions. Otherwise, the agent is prompted with the request's newest user message and attachments. The `protocol` method returns the protocol used by the client.

> [!NOTE]
> Agents that implement the `Conversational` interface load their own history, so the `withMessages` method may be omitted.

The `AgentUserInteraction::chat` method provides the same API for AG-UI clients, in addition to the request's thread and run IDs:

```php
use LaraGram\Ai\AgentUserInteraction\AgentUserInteraction;

$chat = AgentUserInteraction::chat($request);

$chat->threadId();
$chat->runId();
```

You may also convert stored messages back into the format a client expects, allowing the client to display a previous conversation, such as after a page reload:

```php
$messages = $conversation->messages()->oldest()->get();

return ['messages' => Vercel::toUiMessages($messages)];
```

The `AgentUserInteraction::toClientState` method performs the same conversion for AG-UI clients, in addition to returning any pending approval interrupts:

```php
return AgentUserInteraction::toClientState($messages);
```

In a Telegram Mini App built with Luna, the `useChat` function of Luna's adapters is such a client, and authenticates the request with the Mini App's init data. See [Mini Apps](#mini-apps).

<a name="broadcasting"></a>
### Broadcasting

You may broadcast streamed events in a few different ways. First, you can simply invoke the `broadcast` or `broadcastNow` method on a streamed event:

```php
use App\Ai\Agents\SalesCoach;
use LaraGram\Broadcasting\Channel;

$stream = (new SalesCoach)->stream('Analyze this sales transcript...');

foreach ($stream as $event) {
    $event->broadcast(new Channel('channel-name'));
}
```

Or, you can invoke an agent's `broadcastOnQueue` method to queue the agent operation and broadcast the streamed events as they are available:

```php
(new SalesCoach)->broadcastOnQueue(
    'Analyze this sales transcript...',
    new Channel('channel-name'),
);
```

<a name="skipping-oversized-events"></a>
#### Skipping Oversized Events

Some broadcasting platforms limit WebSocket messages to around 10KB. Data-heavy stream events, like large tool results, can exceed this limit and cause broadcasting to fail. You may exclude specific event types from broadcasting using the `WithoutBroadcasting` attribute:

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Ai\Attributes\WithoutBroadcasting;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasTools;
use LaraGram\Ai\Promptable;
use LaraGram\Ai\Streaming\Events\ToolCall;
use LaraGram\Ai\Streaming\Events\ToolResult;

#[WithoutBroadcasting(ToolCall::class, ToolResult::class)]
class SearchAgent implements Agent, HasTools
{
    use Promptable;

    // ...
}
```

The excluded events are never broadcast, but they are still persisted to the `agent_conversation_messages` table, so your frontend can load the full tool data after the stream completes. This works for both queued (`broadcastOnQueue`) and synchronous (`broadcast` / `broadcastNow`) broadcasting.

<a name="queueing"></a>
### Queueing

Using an agent's `queue` method, you may prompt the agent, but allow it to process the response in the background, keeping your application feeling fast and responsive. The `then` and `catch` methods may be used to register closures that will be invoked when a response is available or if an exception occurs:

```php
use LaraGram\Http\Request;
use LaraGram\Ai\Responses\AgentResponse;
use Throwable;

Route::post('/coach', function (Request $request) {
    (new SalesCoach)
        ->queue($request->input('transcript'))
        ->then(function (AgentResponse $response) {
            // ...
        })
        ->catch(function (Throwable $e) {
            // ...
        });

    return back();
});
```

Returned from a listen, a queued prompt shows the "typing" action right away and sends the answer to the chat once it is ready. See [queued answers](#queued-answers).

<a name="tools"></a>
### Tools

Tools may be used to give agents additional functionality that they can utilize while responding to prompts. Tools can be created using the `make:tool` Commander command:

```shell
php laragram make:tool RandomNumberGenerator
```

The generated tool will be placed in your application's `app/Ai/Tools` directory. Each tool contains a `handle` method that will be invoked by the agent when it needs to utilize the tool:

```php
<?php

namespace App\Ai\Tools;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Tools\Request;
use Stringable;

class RandomNumberGenerator implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'This tool may be used to generate cryptographically secure random numbers.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        return (string) random_int($request['min'], $request['max']);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'min' => $schema->integer()->min(0)->required(),
            'max' => $schema->integer()->required(),
        ];
    }
}
```

Once you have defined your tool, you may return it from the `tools` method of any of your agents:

```php
use App\Ai\Tools\RandomNumberGenerator;

/**
 * Get the tools available to the agent.
 *
 * @return Tool[]
 */
public function tools(): iterable
{
    return [
        new RandomNumberGenerator,
    ];
}
```

<a name="runtime-tool-overrides"></a>
#### Runtime Tool Overrides

The `withTools` method may be used to replace the tools declared by an agent instance. This is useful for per-tenant or feature-flagged tool sets:

```php
$response = (new SupportAgent)
    ->withTools([new LookupOrder])
    ->prompt('Where is order 12345?');
```

You may also pass a closure, which receives the agent's declared tools, allowing you to append to or filter them:

```php
$response = (new SupportAgent)
    ->withTools(fn (array $tools) => [...$tools, new LookupOrder])
    ->prompt('Where is order 12345?');
```

<a name="validating-tool-arguments"></a>
#### Validating Tool Arguments

Although your tool's schema constrains the arguments a model may provide, you may validate the incoming arguments using the request's `validate` method:

```php
public function handle(Request $request): Stringable|string
{
    $validated = $request->validate([
        'city' => 'required|string',
        'days' => 'required|integer|max:7',
    ]);

    return $this->forecast($validated['city'], $validated['days']);
}
```

When validation fails, the validation messages are returned to the model as the tool's result, allowing it to correct the arguments and call the tool again.

<a name="repairing-tool-calls"></a>
#### Repairing Tool Calls

Use the `RepairToolCalls` attribute to let an agent recover when a model calls an unknown local tool. LaraGram returns the failed call to the model with the names of the available local tools, allowing it to correct the call:

```php
use LaraGram\Ai\Attributes\RepairToolCalls;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasTools;
use LaraGram\Ai\Promptable;

#[RepairToolCalls]
class SupportAgent implements Agent, HasTools
{
    use Promptable;

    // ...
}
```

When LaraGram derives the maximum number of steps automatically, this attribute adds one step for the repaired call. Explicit `MaxSteps` limits are unchanged.

<a name="similarity-search"></a>
#### Similarity Search

The `SimilaritySearch` tool allows agents to search for documents similar to a given query using vector embeddings stored in your database. This is useful for retrieval-augmented generation (RAG) when you want to give agents access to search your application's data.

The simplest way to create a similarity search tool is using the `usingModel` method with an Eloquent model that has vector embeddings:

```php
use App\Models\Document;
use LaraGram\Ai\Tools\SimilaritySearch;

public function tools(): iterable
{
    return [
        SimilaritySearch::usingModel(Document::class, 'embedding'),
    ];
}
```

The first argument is the Eloquent model class, and the second argument is the column containing the vector embeddings.

You may also provide a minimum similarity threshold between `0.0` and `1.0` and a closure to customize the query:

```php
SimilaritySearch::usingModel(
    model: Document::class,
    column: 'embedding',
    minSimilarity: 0.7,
    limit: 10,
    query: fn ($query) => $query->where('published', true),
),
```

For more control, you may create a similarity search tool with a custom closure that returns the search results:

```php
use App\Models\Document;
use LaraGram\Ai\Tools\SimilaritySearch;

public function tools(): iterable
{
    return [
        new SimilaritySearch(using: function (string $query) {
            return Document::query()
                ->where('user_id', $this->user->id)
                ->whereVectorSimilarTo('embedding', $query)
                ->limit(10)
                ->get();
        }),
    ];
}
```

You may customize the tool's description using the `withDescription` method:

```php
SimilaritySearch::usingModel(Document::class, 'embedding')
    ->withDescription('Search the knowledge base for relevant articles.'),
```

<a name="deferred-tool-loading"></a>
### Deferred Tool Loading

By default, every tool an agent exposes is sent to the provider with each request. When an agent provides a large number of tools, this consumes tokens and may reduce the accuracy of the model's tool selection. Using the `ToolSearch` provider tool with OpenAI or Anthropic, you may defer tool definitions so that the provider only loads them when they are needed:

```php
use App\Ai\Tools\RefundOrder;
use App\Ai\Tools\SearchInvoices;
use App\Ai\Tools\Weather;
use LaraGram\Ai\Providers\Tools\ToolSearch;

public function tools(): iterable
{
    return [
        new Weather,
        new ToolSearch(tools: [
            new SearchInvoices,
            new RefundOrder,
        ]),
    ];
}
```

The wrapped tools do not require any modification. The provider will search for and load them when they are relevant to the prompt, after which the agent may call them like any other tool.

When using Anthropic, the `strategy` argument may be used to determine how the provider should search for deferred tools. The supported strategies are `regex` (default) and `bm25`:

```php
new ToolSearch(tools: [new SearchInvoices], strategy: 'bm25'),
```

When using Anthropic, additional provider-specific options may be passed to the search tool using the `withProviderOptions` method:

```php
(new ToolSearch(tools: [new SearchInvoices]))
    ->withProviderOptions(['cache_control' => ['type' => 'ephemeral']]),
```

> [!WARNING]
> Providers that do not support tool search will throw an exception rather than silently discarding the deferred tools. In addition, Anthropic requires that at least one tool is provided outside of the `ToolSearch` wrapper.

<a name="file-storage-tools"></a>
### File Storage Tools

The `FileStorage` tool factory allows you to give agents access to a LaraGram [filesystem disk](/v4/filesystem). The `all` method returns tools that allow the agent to list, read, inspect, generate URLs for, write, delete, and copy files on the given disk:

```php
use LaraGram\Ai\Tools\FileStorage;

public function tools(): iterable
{
    return FileStorage::all('local');
}
```

If your agent should only be able to inspect files, use the `readOnly` method:

```php
return FileStorage::readOnly('local');
```

These methods return an `LaraGram\Support\Collection`, allowing you to further filter the tools that are provided to the agent:

```php
use LaraGram\Ai\Tools\Filesystem\DeleteFile;

return FileStorage::all('s3')
    ->reject(fn ($tool) => $tool instanceof DeleteFile);
```

Similarly, the [`Telegram`](#telegram-tools) tool factory gives agents tools that read and act on the chat of the current update.

<a name="mcp-tools"></a>
### MCP Tools

If your application uses [LaraGram MCP](/v4/mcp), you may give your agents tools exposed by [Model Context Protocol](https://modelcontextprotocol.io) servers. Using the [LaraGram MCP client](/v4/mcp#client), you may connect to a remote or local MCP server and pass its tools directly to your agent.

> [!NOTE]
> MCP tools require the [LaraGram MCP](/v4/mcp) package to be installed in your application.

Because an MCP client's `tools` method returns a collection, spread it into your agent's `tools` array using the `...` operator:

```php
use App\Ai\Tools\RandomNumberGenerator;
use LaraGram\Mcp\Client;

/**
 * Get the tools available to the agent.
 *
 * @return Tool[]
 */
public function tools(): iterable
{
    return [
        ...Client::web('https://mcp.example.com')
            ->withToken($token)
            ->tools(),

        new RandomNumberGenerator,
    ];
}
```

The AI SDK automatically wraps each MCP tool so the agent can call it like any other tool. You may also use a [named MCP client](/v4/mcp#named-clients):

```php
use LaraGram\Mcp\Facades\Mcp;

public function tools(): iterable
{
    return [
        ...Mcp::client('github')->tools(),
    ];
}
```

Or connect to a [local MCP server](/v4/mcp#client-connecting):

```php
use LaraGram\Mcp\Client;

public function tools(): iterable
{
    return [
        ...Client::local('php', ['laragram', 'mcp:start'])->tools(),
    ];
}
```

For more information on creating and authenticating MCP clients, including bearer tokens and OAuth, consult the [MCP client documentation](/v4/mcp#client).

<a name="skills"></a>
### Skills

[Agent Skills](https://agentskills.io) are folders of instructions and supporting files that teach an agent how to perform a specific task. Because skills follow an open standard, the same skill may be shared between your application's agents and the coding agents you use to build your application.

Each skill is a directory within your application's `resources/skills` directory that contains a `SKILL.md` file. Any other files in the directory, such as reference documents, are bundled with the skill:

```text
resources/skills/
└── refund-policy/
    ├── SKILL.md
    └── references/EDGE-CASES.md
```

The `SKILL.md` file begins with YAML frontmatter containing the skill's `name` and a `description` of when the skill should be used, followed by the skill's instructions:

```markdown
---
name: refund-policy
description: Use when a customer asks for a refund or disputes a charge.
---

# Refund Policy

Customers may request a full refund within 30 days of purchase...
```

If the `name` is omitted, the name of the skill's directory will be used. Skills without a `description` are ignored.

To give an agent access to your skills, implement the `HasSkills` interface and return your skill sources from the `skills` method:

```php
use LaraGram\Ai\Contracts\HasSkills;
use LaraGram\Ai\Skills\Skill;

class SupportAgent implements Agent, HasSkills
{
    use Promptable;

    public function skills(): iterable
    {
        return [
            resource_path('skills'),
            base_path('.agents/skills'),
            new Skill('house-style', 'Use when you write copy for a customer.', view('skills.house-style')),
            fn () => $this->user->team->skills->map(
                fn ($skill) => new Skill($skill->name, $skill->description, $skill->instructions)
            ),
        ];
    }
}
```

Sources may be directories, `Skill` instances, or closures returning skills, which are only invoked once the agent needs them. When two skills share a name, the source listed first takes precedence.

The agent receives a `LoadSkill` tool that lists each skill's name and description, loading a skill's full instructions and bundled text files only when a prompt calls for it. Binary files and files larger than 256 KB are not returned.

<a name="provider-tools"></a>
### Provider Tools

Provider tools are special tools implemented natively by AI providers, offering capabilities like web searching, URL fetching, and file searching. Unlike regular tools, provider tools are executed by the provider itself rather than your application.

Provider tools can be returned by your agent's `tools` method.

<a name="web-search"></a>
#### Web Search

The `WebSearch` provider tool allows agents to search the web for real-time information. This is useful for answering questions about current events, recent data, or topics that may have changed since the model's training cutoff.

**Supported providers:** Anthropic, OpenAI, Azure, Gemini, xAI, OpenRouter

```php
use LaraGram\Ai\Providers\Tools\WebSearch;

public function tools(): iterable
{
    return [
        new WebSearch,
    ];
}
```

You may configure the web search tool to limit the number of searches or restrict results to specific domains:

```php
(new WebSearch)->max(5)->allow(['laraxgram.github.io', 'php.net']),
```

To refine search results based on user location, use the `location` method:

```php
(new WebSearch)->location(
    city: 'New York',
    region: 'NY',
    country: 'US'
);
```

<a name="web-fetch"></a>
#### Web Fetch

The `WebFetch` provider tool allows agents to fetch and read the contents of web pages. This is useful when you need the agent to analyze specific URLs or retrieve detailed information from known web pages.

**Supported providers:** Anthropic, Gemini, OpenRouter

```php
use LaraGram\Ai\Providers\Tools\WebFetch;

public function tools(): iterable
{
    return [
        new WebFetch,
    ];
}
```

You may configure the web fetch tool to limit the number of fetches or restrict to specific domains:

```php
(new WebFetch)->max(3)->allow(['laraxgram.github.io']),
```

<a name="file-search"></a>
#### File Search

The `FileSearch` provider tool allows agents to search through [files](#files) stored in [vector stores](#vector-stores). This enables retrieval-augmented generation (RAG) by allowing the agent to search your uploaded documents for relevant information.

**Supported providers:** OpenAI, Gemini, xAI

```php
use LaraGram\Ai\Providers\Tools\FileSearch;

public function tools(): iterable
{
    return [
        new FileSearch(stores: ['store_id']),
    ];
}
```

You may provide multiple vector store IDs to search across multiple stores:

```php
new FileSearch(stores: ['store_1', 'store_2']);
```

If your files have [metadata](#adding-files-to-stores), you may filter the search results by providing a `where` argument. For simple equality filters, pass an array:

```php
new FileSearch(stores: ['store_id'], where: [
    'author' => 'Taylor Otwell',
    'year' => 2026,
]);
```

For more complex filters, you may pass a closure that receives a `FileSearchQuery` instance:

```php
use LaraGram\Ai\Providers\Tools\FileSearchQuery;

new FileSearch(stores: ['store_id'], where: fn (FileSearchQuery $query) =>
    $query->where('author', 'Taylor Otwell')
        ->whereNot('status', 'draft')
        ->whereIn('category', ['news', 'updates'])
);
```

<a name="code-execution"></a>
#### Code Execution

The `CodeExecution` provider tool allows agents to run code in a sandbox hosted by the AI provider. This is useful for performing calculations and analyzing data.

**Supported providers:** Anthropic, OpenAI, Azure, Gemini, xAI

```php
use LaraGram\Ai\Providers\Tools\CodeExecution;

public function tools(): iterable
{
    return [new CodeExecution];
}
```

When using OpenAI or Azure, you may make [stored files](#files) available to the sandbox via provider options:

```php
(new CodeExecution)->withProviderOptions([
    'container' => ['type' => 'auto', 'file_ids' => ['file_123']],
]);
```

<a name="sub-agents"></a>
### Sub-Agents

Agents may also be returned from another agent's `tools` method. When an agent is returned as a tool, the parent agent may delegate a specific task to the sub-agent and use the sub-agent's response while answering the original prompt. This is useful when a general-purpose agent needs access to specialized agents with their own instructions, tools, model configuration, or provider preferences.

For example, a customer support agent could delegate refund eligibility questions to a dedicated refunds agent:

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasTools;
use LaraGram\Ai\Promptable;

class CustomerSupportAgent implements Agent, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return 'You help customers with account, order, and billing questions. Delegate refund policy questions to the refunds specialist.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new RefundsAgent,
        ];
    }
}
```

To customize how the sub-agent is exposed to the parent agent, implement the `CanActAsTool` interface on the sub-agent and define a tool-facing name and description:

```php
use LaraGram\Ai\Attributes\Provider;
use LaraGram\Ai\Contracts\CanActAsTool;
use LaraGram\Ai\Enums\Lab;

#[Provider(Lab::Anthropic)]
class RefundsAgent implements Agent, CanActAsTool, HasTools
{
    /**
     * Get the agent's tool name.
     */
    public function name(): string
    {
        return 'refunds_specialist';
    }

    /**
     * Get the agent's tool description.
     */
    public function description(): string
    {
        return 'Determine whether an order is eligible for a refund and explain the next step.';
    }

    // ...
}
```

If a sub-agent does not implement `CanActAsTool`, LaraGram will use the agent's class basename as the tool name and a generic description that asks the parent agent to pass a clear, self-contained task description. Each sub-agent invocation runs in isolation and does not receive the parent agent's conversation history.

When the parent agent is [streaming](#streaming), its sub-agents stream as well. The parent agent emits `ToolResult` events containing the text the sub-agent has produced so far. These events are marked as preliminary and are followed by the tool call's final result, so you may skip them when iterating events manually:

```php
use LaraGram\Ai\Streaming\Events\ToolResult;

foreach ($stream as $event) {
    if ($event instanceof ToolResult && $event->preliminary) {
        continue;
    }

    // ...
}
```

Response values such as `text`, `usage`, and `toolResults` ignore preliminary events. The [Vercel protocol](#stream-protocols) renders them as native streaming tool output, so `useChat` displays the progress without any custom code, while the AG-UI protocol reports them as activity snapshots. The completed response's text, reasoning, citations, and usage include those of the sub-agent.

<a name="middleware"></a>
### Middleware

Agents support middleware, allowing you to intercept and modify each generation step before it is sent to the provider. Middleware is invoked once per step, so a run that takes three steps will invoke it three times. Middleware can be created using the `make:agent-middleware` Commander command:

```shell
php laragram make:agent-middleware LogPrompts
```

The generated middleware will be placed in your application's `app/Ai/Middleware` directory. To add middleware to an agent, implement the `HasMiddleware` interface and define a `middleware` method that returns an array of middleware classes:

```php
<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\LogPrompts;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasMiddleware;
use LaraGram\Ai\Promptable;

class SalesCoach implements Agent, HasMiddleware
{
    use Promptable;

    // ...

    /**
     * Get the agent's middleware.
     */
    public function middleware(): array
    {
        return [
            new LogPrompts,
        ];
    }
}
```

Each middleware class should define a `handle` method that receives a `PendingStep` and a `Closure` that passes the step to the next middleware:

```php
<?php

namespace App\Ai\Middleware;

use Closure;
use LaraGram\Support\Facades\Log;
use LaraGram\Ai\PendingStep;

class LogPrompts
{
    /**
     * Handle the pending generation step.
     */
    public function handle(PendingStep $step, Closure $next)
    {
        Log::info('Prompting agent', ['model' => $step->model]);

        return $next($step);
    }
}
```

In addition to the `provider`, `model`, `instructions`, `messages`, and `tools` that are about to be sent, the step exposes the steps that have already completed, their combined usage, and the progress of the run:

```php
$step->steps;
$step->usage;

$step->number;
$step->isFirstStep();
$step->isFinalStep;
```

The `withModel`, `withInstructions`, `withMessages`, `withTools`, `onlyTools`, `withoutTools`, `withToolChoice`, `withMaxTokens`, and `withProviderOptions` methods each return a copy of the step. For example, you may remove an expensive tool once the agent has used it:

```php
public function handle(PendingStep $step, Closure $next)
{
    if (! $step->isFirstStep()) {
        $step = $step->withoutTools('SearchDocumentation');
    }

    return $next($step);
}
```

Or, you may keep a long tool calling loop within the context window by summarizing the middle of the conversation:

```php
use App\Ai\Agents\Summarizer;
use LaraGram\Ai\Messages\UserMessage;

public function handle(PendingStep $step, Closure $next)
{
    if (count($step->messages) > 40) {
        $summary = (new Summarizer)->prompt(
            collect(array_slice($step->messages, 1, -10))->map->content->implode("\n"),
        )->text;

        $step = $step->withMessages([
            $step->messages[0],
            new UserMessage("Summary of the conversation so far: {$summary}"),
            ...array_slice($step->messages, -10),
        ]);
    }

    return $next($step);
}
```

Messages passed to the `withMessages` method only change what is sent for the current step. Later steps and the stored conversation continue to use the full, unsummarized history.

You may use the `then` method to execute code once the model has answered the step, before its tool calls are executed. This works for both synchronous and streaming responses:

```php
use LaraGram\Ai\Gateway\StepResponse;

public function handle(PendingStep $step, Closure $next)
{
    return $next($step)->then(function (StepResponse $response) {
        Log::info('Agent responded', ['text' => $response->text]);
    });
}
```

Middleware must return the result of `$next`, or its own `StepResponse` to answer the step without invoking the model, such as when serving a cached response. Returning any other value will throw a `LogicException`.

The AI SDK ships agent middleware for bots: [`TelegramContext`](#telegram-context), which tells the model who it is talking to, and [`LimitUsage`](#usage-limits), which limits how many tokens each Telegram user may spend.

<a name="anonymous-agents"></a>
### Anonymous Agents

Sometimes you may want to quickly interact with a model without creating a dedicated agent class. You can create an ad-hoc, anonymous agent using the `agent` function:

```php
use function LaraGram\Ai\{agent};

$response = agent(
    instructions: 'You are an expert at software development.',
    messages: [],
    tools: [],
)->prompt('Tell me about LaraGram');
```

Anonymous agents may also produce structured output:

```php
use LaraGram\Contracts\JsonSchema\JsonSchema;

use function LaraGram\Ai\{agent};

$response = agent(
    schema: fn (JsonSchema $schema) => [
        'number' => $schema->integer()->required(),
    ],
)->prompt('Generate a random number less than 100');
```

<a name="agent-configuration"></a>
### Agent Configuration

You may configure text generation options for an agent using PHP attributes. The following attributes are available:

- `MaxSteps`: The maximum number of steps the agent may take when using tools.
- `MaxTokens`: The maximum number of tokens the model may generate.
- `Model`: The model the agent should use.
- `Provider`: The AI provider (or providers for failover) to use for the agent.
- `Temperature`: The sampling temperature to use for generation (0.0 to 1.0).
- `Timeout`: The HTTP timeout in seconds for agent requests (default: 60).
- `TopP`: The nucleus sampling probability to use for generation (0.0 to 1.0).
- `UseCheapestModel`: Use the provider's cheapest text model for cost optimization.
- `UseSmartestModel`: Use the provider's most capable text model for complex tasks.

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Ai\Attributes\MaxSteps;
use LaraGram\Ai\Attributes\MaxTokens;
use LaraGram\Ai\Attributes\Model;
use LaraGram\Ai\Attributes\Provider;
use LaraGram\Ai\Attributes\Temperature;
use LaraGram\Ai\Attributes\Timeout;
use LaraGram\Ai\Attributes\TopP;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Promptable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-5')]
#[MaxSteps(10)]
#[MaxTokens(4096)]
#[Temperature(0.7)]
#[Timeout(120)]
#[TopP(0.9)]
class SalesCoach implements Agent
{
    use Promptable;

    // ...
}
```

The `UseCheapestModel` and `UseSmartestModel` attributes allow you to automatically select the most cost-effective or most capable model for a given provider without specifying a model name. This is useful when you want to optimize for cost or capability across different providers:

```php
use LaraGram\Ai\Attributes\UseCheapestModel;
use LaraGram\Ai\Attributes\UseSmartestModel;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Promptable;

#[UseCheapestModel]
class SimpleSummarizer implements Agent
{
    use Promptable;

    // Will use the cheapest model (for example, Haiku)...
}

#[UseSmartestModel]
class ComplexReasoner implements Agent
{
    use Promptable;

    // Will use the most capable model (e.g., Opus)...
}
```

> [!NOTE]
> The underlying model selected by `UseCheapestModel` and `UseSmartestModel` may change between releases of the LaraGram AI SDK as providers release new models. Switching models can introduce behavioral changes, deprecated parameters, and significant cost differences. If you need a stable, predictable model and pricing, specify the model explicitly using the `Model` attribute.

<a name="provider-options"></a>
### Provider Options

If your agent needs to pass provider-specific options (such as OpenAI reasoning effort or penalty settings), implement the `HasProviderOptions` contract and define a `providerOptions` method:

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasProviderOptions;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Promptable;

class SalesCoach implements Agent, HasProviderOptions
{
    use Promptable;

    // ...

    /**
     * Get provider-specific generation options.
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::OpenAI => [
                'reasoning' => ['effort' => 'low'],
                'frequency_penalty' => 0.5,
                'presence_penalty' => 0.3,
            ],
            Lab::Anthropic => [
                'thinking' => ['budget_tokens' => 1024],
                'cache_control' => ['type' => 'ephemeral'],
            ],
            default => [],
        };
    }
}
```

The `providerOptions` method receives the provider currently being used (`Lab` enum or string), allowing you to return different options per provider. This is especially useful when using [failover](#failover), since each fallback provider can receive its own configuration.

The Anthropic example above also enables [prompt caching](#prompt-caching) via `cache_control`.

The [image](#images), [audio](#audio), [transcription](#transcription), [embedding](#embeddings), and [reranking](#reranking) builders accept provider options as well:

```php
use LaraGram\Ai\Audio;

$audio = Audio::of('I love building bots with LaraGram.')
    ->withProviderOptions(['speed' => 1.25])
    ->generate();
```

You may also pass a closure instead of an array, which will receive the provider currently being used.

<a name="custom-http-headers"></a>
#### Custom HTTP Headers

Headers configured for a provider within your application's `config/ai.php` configuration file are sent with every request that provider makes. To send headers on a per-request basis, such as metadata used by an AI gateway, you may use the `withHeaders` method, which is available on the image, audio, transcription, embedding, and reranking builders, as well as on [file uploads](#files):

```php
use LaraGram\Ai\Embeddings;

$embeddings = Embeddings::for($chunks)
    ->withHeaders(['cf-aig-metadata' => json_encode(['team' => $team->id])])
    ->withProviderOptions(['dimensions' => 1024])
    ->generate();
```

Headers may also be given as a closure, which receives the provider currently being used. Headers are not included in the request body and do not affect [embedding cache keys](#caching-embeddings).

<a name="prompt-caching"></a>
### Prompt Caching

Most providers cache repeated prompt prefixes automatically and bill the cached portion at a discount. OpenAI, Gemini, Groq, DeepSeek, and xAI require no configuration, and you may inspect the savings via the response's usage:

```php
$response->usage->cacheReadInputTokens;
$response->usage->cacheWriteInputTokens;
```

Both of these counts are subsets of the input token total, which is discussed further in the [usage documentation](#usage).

The `anthropic` and `bedrock` providers only cache when asked. The `CacheInstructions` and `CacheToolDefinitions` attributes place a cache breakpoint at the end of your agent's instructions and tool definitions, so every conversation reads that prefix from the cache instead of writing it again:

```php
use LaraGram\Ai\Attributes\CacheInstructions;
use LaraGram\Ai\Attributes\CacheToolDefinitions;

#[CacheInstructions]
#[CacheToolDefinitions]
class SalesCoach implements Agent
{
    use Promptable;

    // ...
}
```

If your instructions change on every request, such as when they embed the current date, use `CacheToolDefinitions` alone. Caching a prefix that changes on every request creates a new cache entry each time, so you pay to write it to the cache without ever reusing it.

Providers that do not support these attributes ignore them, so an agent may safely declare them while using [failover](#failover).

Cached prefixes are retained for five minutes by default. Anthropic may retain them for an hour if you pass a TTL to the attribute:

```php
#[CacheInstructions('1h')]
#[CacheToolDefinitions('1h')]
```

Alternatively, Anthropic's automatic caching may be enabled via a top-level `cache_control` [provider option](#provider-options). This places a single breakpoint after the last block of the request, so the breakpoint advances as the conversation grows and each turn reads the previous turns from the cache. Both mechanisms may be combined.

> [!WARNING]
> Because providers build prompts in the order tools, instructions, and messages, caching instructions for an hour also requires caching tool definitions for an hour. Mixing the two throws an `InvalidArgumentException`.

<a name="telegram"></a>
## Telegram

Everything above works the same in a bot as in a web application. On top of it, the AI SDK speaks Telegram: a response returned from a [listen](/v4/listening) is sent to the chat of the update, the files users send become attachments, answers stream into the chat as they are written, and an agent's structured output may be a quiz, a poll, a keyboard or any other Telegram message.

All of it works over the [Bot API](/v4/requests) and over [MTProto](/v4/mtproto) sessions, without changing your agents.

<a name="responding-to-updates"></a>
### Responding to Updates

In a web application, you return a response from a route to send it to the browser. In a bot, you return it from a listen to send it to the chat:

```php
use App\Ai\Agents\SupportAgent;
use LaraGram\Ai\Audio;
use LaraGram\Ai\Image;
use LaraGram\Ai\Transcription;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Bot;

Bot::onText('/ask {question}', function (string $question) {
    return (new SupportAgent)->prompt($question);
});

Bot::onText('/draw {prompt}', fn (string $prompt) => Image::of($prompt)->generate());

Bot::onText('/say {text}', fn (string $text) => Audio::of($text)->generate());

Bot::onVoice(fn (Request $request) => Transcription::fromUpload($request->file())->generate());
```

The response replies to the message that triggered the listen. Each kind of response is sent the way you would expect:

<div class="overflow-auto">

| Returned | Sent as |
|---|---|
| `prompt()` | A text message, formatted from the model's Markdown |
| `stream()` | A message that is written while the model answers — see [streaming in Telegram](#streaming-in-telegram) |
| `queue()` | A "typing" action now, and the answer once the queued job has finished |
| A [Telegram output](#telegram-output) | A quiz, poll, keyboard, rich message, location, file, invoice or inline results |
| `Image::of()->generate()` | A photo, or an album when several images were generated |
| `Audio::of()->generate()` | A voice message |
| `Transcription::...->generate()` | The transcript as a text message |

</div>

When the update is a callback query, the query is answered as well, so the button stops showing its loading state. When it is an inline query, the answer is sent as an [inline result](#inline-mode).

Returned from an HTTP route, the same responses behave exactly as they always have.

<a name="sending-outside-a-listen"></a>
#### Sending Outside a Listen

From a queued job, a scheduled command or a web route, send any of these responses to a chat with the `sendTo` method. The chat may be a chat ID, a username, a Telegram chat or user object such as the result of the `chat()` helper, or an update:

```php
(new DailyDigest)->prompt('Summarize what happened today.')->sendTo($group->chat_id);

Image::of('A cat reading a newspaper')->generate()->sendTo('@my_channel');
```

The second argument names the [bot connection](/v4/requests#multi-connections) that sends the response. To send through an [MTProto](/v4/mtproto) session instead, pass the session name prefixed with `mtproto:`:

```php
$response->sendTo($chatId, 'second_bot');

$response->sendTo($peer, 'mtproto:main');
```

Inside a listen, the `reply` method sends a response to the current chat without returning it, which is useful when a listen sends more than one response:

```php
Bot::onText('/report', function () {
    (new ReportAgent)->prompt('Write the weekly report.')->reply();

    return Image::of('A chart of this week\'s sales')->generate();
});
```

<a name="message-formatting"></a>
#### Message Formatting

Models answer in Markdown. Before an answer is sent, the AI SDK converts it into the formatting Telegram shows: bold, italic, strike-through, spoilers, inline code and code blocks, links, quotes and lists. A text longer than Telegram allows is split into several messages, never in the middle of a word or a code block.

Telegram's [rich messages](/v4/rich-messages) go further, showing headings, tables and formulas as a document. The `ai.telegram.format` option chooses between the two:

```php
'telegram' => [
    'format' => env('AI_TELEGRAM_FORMAT', 'auto'),
],
```

`html` always sends regular messages, `rich` always sends rich messages, and `auto`, the default, sends a rich message only when the answer contains a heading, a table or a formula, or is too long for a single message. When a bot cannot send rich messages, `auto` falls back to regular messages.

<a name="streaming-in-telegram"></a>
### Streaming in Telegram

Returning a [stream](#streaming) from a listen shows the answer while the model writes it:

```php
Bot::onText('{question}', fn (string $question) => (new SupportAgent)->stream($question));
```

In private chats, the answer is streamed as a message draft: the user watches it being written, with a button to stop the generation, and the finished answer is sent as a regular message. In groups, and over MTProto, the answer is sent as soon as the model starts and edited as it grows. When the model reasons before answering, the draft shows its thoughts until the answer begins.

The streaming behavior may be adjusted in your application's `config/ai.php` configuration file:

```php
'streaming' => [
    'drafts' => true,       // Use message drafts in private chats...
    'can_stop' => true,     // Show the button that stops the generation...
    'interval' => null,     // Seconds between updates; 0.4 for drafts and 1.5 for edits by default...
],
```

Edits are paced to stay within Telegram's rate limits. A stream may also be sent outside of a listen using `sendTo`, and the `then` callback is invoked once the answer is complete, just like when streaming over HTTP:

```php
(new SupportAgent)
    ->stream($question)
    ->then(fn (StreamedAgentResponse $response) => Log::info('Answered', ['tokens' => $response->usage->outputTokens]))
    ->sendTo($chatId);
```

<a name="queued-answers"></a>
### Queued Answers

A provider may take a while to answer. Returning a [queued](#queueing) prompt from a listen shows the "typing" action right away, finishes the webhook request, and sends the answer from the queue worker once it is ready:

```php
Bot::onText('/essay {topic}', fn (string $topic) => (new Writer)->queue("Write an essay about {$topic}."));
```

The same is true of queued images, audio and transcriptions. If the job fails, the message in the `ai.telegram.failure_message` option, when set, is sent to the chat. Outside of a listen, call `sendTo` on the queued response:

```php
(new Writer)->queue($prompt)->sendTo($chatId);
```

<a name="telegram-files"></a>
### Telegram Files

The files users send to your bot are attachments, exactly like uploaded files in a web application. Pass the file of the update, `$request->file()`, wherever the SDK accepts an upload:

```php
use App\Ai\Agents\PhotoDescriber;
use LaraGram\Ai\Files;
use LaraGram\Ai\Transcription;

Bot::onPhoto(function (Request $request) {
    return (new PhotoDescriber)->prompt('What is in this photo?', attachments: [
        $request->file(),
    ]);
});

Bot::onDocument(function (Request $request) {
    $document = Files\Document::fromUpload($request->file());

    return (new ContractReviewer)->prompt('Review this contract.', attachments: [$document]);
});

Bot::onVoice(function (Request $request) {
    $question = Transcription::fromUpload($request->file())->generate()->text;

    return (new SupportAgent)->prompt($question);
});
```

Photos are attached at their largest size, every item of an album is attached, and voice messages, videos and documents become audio, video and document attachments. The same files may be [embedded](#multimodal-embeddings), [classified](#classification), [stored with the provider](#files) or added to a [vector store](#adding-files-to-stores).

In an [MTProto](/v4/mtproto) handler, pass the request itself; the media of the update is downloaded through the session, so files larger than the Bot API's 20 MB download limit work too:

```php
Client::onPhoto(function (ClientRequest $request) {
    return (new PhotoDescriber)->prompt('What is in this photo?', attachments: [$request]);
});
```

<a name="telegram-output"></a>
### Telegram Output

A [structured](#structured-output) agent may produce a Telegram message instead of an array. Return one of the Telegram output types from the agent's `schema` method; the model fills in the content, and returning the response from a listen sends it:

```php
<?php

namespace App\Ai\Agents;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Promptable;
use LaraGram\Ai\Telegram\Quiz;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class QuizMaster implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): string
    {
        return 'You write accurate, fun quiz questions in the language of the topic.';
    }

    /**
     * Get the agent's structured output.
     */
    public function schema(JsonSchema $schema): Quiz
    {
        return Quiz::questions(5)->options(4)->withExplanation();
    }
}
```

```php
Bot::onText('/quiz {topic}', fn (string $topic) => (new QuizMaster)->prompt("A quiz about {$topic}"));
```

The response is still a structured response, so the data the model wrote may be read like an array before it is sent:

```php
$response = (new QuizMaster)->prompt('Persian poetry');

$response['questions'][0]['question'];

$response->sendTo($chatId);
```

[Anonymous agents](#anonymous-agents) accept an output as their schema:

```php
use LaraGram\Ai\Telegram\Poll;

use function LaraGram\Ai\agent;

return agent('You run polls for a book club.', schema: Poll::make()->options(3, 5))
    ->prompt('Ask what we should read next month.');
```

The following outputs are available in the `LaraGram\Ai\Telegram` namespace:

<div class="overflow-auto">

| Output | Sent as | Options |
|---|---|---|
| `Quiz::questions(5)` | One quiz poll per question | `options(4)`, `withExplanation()`, `multipleCorrect()`, `anonymous(false)`, `openFor(60)`, `shuffle()` |
| `Poll::make()` | A regular poll | `options(2, 6)`, `multipleAnswers()`, `anonymous(false)`, `openFor(300)` |
| `Keyboard::inline()` | A message with inline buttons | `callbacks([...])`, `urls()`, `prefix('ai:')`, `max(6)`, `columns(2)` |
| `Keyboard::reply()` | A message with suggested answers | `max(6)`, `columns(2)` |
| `RichMessage::make()` | A rich message | |
| `Checklist::make()` | A checklist, or a rich message with a task list | `max(10)`, `othersCanAddTasks()`, `othersCanMarkTasksAsDone()` |
| `Location::make()` | A location | |
| `Location::venue()` | A venue with a title and an address | |
| `Document::as('csv')` | A generated file | |
| `Invoice::stars(50)` | An invoice paid with Telegram Stars | `payload('...')`, `label('...')` |
| `Invoice::price(990, 'USD', $token)` | An invoice paid through a payment provider | `payload('...')`, `label('...')` |
| `InlineResults::make()` | Results of an inline query | `max(5)`, `cacheFor(60)` |

</div>

Every output accepts a keyboard of your own, such as one built with the [keyboard builder](/v4/keyboards), through the `withReplyMarkup` method.

<a name="keyboards"></a>
#### Keyboards

An inline keyboard lets the model offer the user a choice. Pass the callback data the model may use, with what each one means; buttons with any other data are dropped, so the model can never invent an action your bot does not handle:

```php
use LaraGram\Ai\Telegram\Keyboard;

public function schema(JsonSchema $schema): Keyboard
{
    return Keyboard::inline()->callbacks([
        'plan:basic' => 'Subscribe to the basic plan',
        'plan:pro' => 'Subscribe to the pro plan',
        'support' => 'Talk to a human',
    ]);
}
```

```php
Bot::onCallbackQueryData('plan:{plan}', [SubscriptionController::class, 'subscribe']);
```

Without callbacks, each inline button sends its label, prefixed with `ai:`. The `urls` method allows the model to add link buttons, and `Keyboard::reply()` suggests answers the user may tap instead of typing.

<a name="invoices"></a>
#### Invoices

The model writes the title and the description of an invoice, but never its price: the amount is always given by your application:

```php
use LaraGram\Ai\Telegram\Invoice;

public function schema(JsonSchema $schema): Invoice
{
    return Invoice::stars($this->product->price)->payload('product:'.$this->product->id);
}
```

<a name="telegram-tools"></a>
### Telegram Tools

The `Telegram` tool factory gives an agent tools that act on the chat of the current update, in the same way the [file storage tools](#file-storage-tools) act on a disk:

```php
use LaraGram\Ai\Tools\Telegram;

public function tools(): iterable
{
    return Telegram::all();
}
```

<div class="overflow-auto">

| Method | Tools |
|---|---|
| `Telegram::readOnly()` | `GetChat`, `GetChatMember`, `GetChatAdministrators` |
| `Telegram::messaging()` | `SendMessage`, `SendPoll`, `ReactToMessage` |
| `Telegram::moderation()` | `PinMessage`, `DeleteMessage`, `BanChatMember`, `RestrictChatMember` |
| `Telegram::all()` | All of the above |

</div>

The moderation tools act on the message the user replied to, or its author, unless the model names another, and each of them requires [human approval](#human-tool-approval). You may change that like for any approvable tool:

```php
use LaraGram\Ai\Tools\Telegram\DeleteMessage;

return [
    ...Telegram::readOnly(),
    (new DeleteMessage)->withoutApproval(),
];
```

To act on a chat other than the current one, pass it to the factory or the tool, along with the bot connection when it is not the default:

```php
Telegram::messaging($group->chat_id, connection: 'announcements');
```

<a name="approving-tools-in-telegram"></a>
### Approving Tools in Telegram

When a response that waits for a [tool approval](#human-tool-approval) is returned from a listen, the chat receives a message describing each pending tool call, with buttons to approve or reject it. Once every call is decided, the run continues and its answer is sent to the chat — no route or controller is needed:

```php
use LaraGram\Ai\Concerns\RemembersConversations;
use LaraGram\Ai\Contracts\Conversational;
use LaraGram\Ai\Contracts\HasTools;

class GroupModerator implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function tools(): iterable
    {
        return Telegram::moderation();
    }

    // ...
}
```

```php
Bot::onText('/mod {request}', fn (string $request) => (new GroupModerator)->forUser(user())->prompt($request));
```

Approvals in Telegram require an agent that [remembers its conversations](#remembering-conversations), since the run is resumed when a button is pressed. By default, only the user whose message started the run may decide; the `authorizeUsing` method lets you decide who may, such as the administrators of a group. The `messageUsing` method customizes the approval message:

```php
use LaraGram\Ai\Telegram\Approvals;

Approvals::authorizeUsing(function (?object $user, array $approval) {
    return in_array($user?->id, config('bot.moderators'));
});

Approvals::messageUsing(function (Collection $approvals, array $decisions) {
    return '⚠️ The assistant wants to: '.$approvals->pluck('tool')->implode(', ');
});
```

The buttons work for bots running over MTProto as well. Unanswered approvals expire after the number of seconds in the `ai.telegram.approvals.ttl` option.

<a name="answering-every-message"></a>
### Answering Every Message

A bot that is an assistant answers every message with the same agent. The `agent` listen registers such a listen in one line:

```php
Bot::agent(SupportAgent::class);
```

The text or caption of each message is the prompt, photos and documents are attached, voice messages are transcribed, and the answer is streamed into the chat. Agents that [remember their conversations](#remembering-conversations) continue the last conversation of the user in private chats, and of the group in groups. A pattern limits the listen to matching messages, and like any listen it may be given middleware or a scope:

```php
Bot::agent(SupportAgent::class, '/ask {question}');

Bot::agent(GroupHelper::class)->scope(['group', 'supergroup'])->middleware('throttle:ai');
```

<a name="conversations-with-telegram-users"></a>
### Conversations With Telegram Users

The [`RemembersConversations`](#remembering-conversations) trait accepts Telegram users and chats as participants, so a bot does not need a user model to remember what was said:

```php
$response = (new SupportAgent)->forUser(user())->prompt($text);

$response = (new GroupHelper)->continueLastConversation(chat())->prompt($text);
```

Telegram users are stored with the `telegram:user` participant type and their Telegram ID, and chats with the `telegram:chat` type. The user of a [Mini App](#mini-apps), `tg_user()`, is the same participant, so a conversation started in the bot may be continued in the Mini App.

<a name="telegram-context"></a>
### Telegram Context

The `TelegramContext` [middleware](#middleware) tells the model where the conversation takes place: who the user is, the language of their Telegram app, whether the chat is private or a group, the message they replied to, and which formatting Telegram shows:

```php
use LaraGram\Ai\Middleware\TelegramContext;

public function middleware(): array
{
    return [new TelegramContext];
}
```

Pass `language: false` or `formatting: false` to leave out the language hint or the formatting hint.

<a name="usage-limits"></a>
### Usage Limits

Every answer costs tokens, so a public bot should limit how many each user may spend. The `LimitUsage` middleware counts the tokens each Telegram user spends per hour, day, week or month, and stops answering once they are spent:

```php
use LaraGram\Ai\Middleware\LimitUsage;

public function middleware(): array
{
    return [
        LimitUsage::perUser(50_000, 'day')
            ->message('You have used today\'s assistant quota. See you tomorrow!')
            ->topUpWithStars(stars: 25, tokens: 100_000),
    ];
}
```

With `topUpWithStars`, a user who runs out also receives an invoice for more tokens, paid with Telegram Stars. The payment is confirmed and the tokens are credited automatically; payments for your own invoices are not touched. Usage is kept in the cache store named by the `ai.telegram.usage.store` option, which should be a persistent store such as `database` or `redis`, since purchased tokens are kept there.

<a name="moderation"></a>
### Moderation

The `Moderate` bot middleware [classifies](#classification) every message of a group and acts on the ones that break its rules:

```php
use LaraGram\Ai\Telegram\Middleware\Moderate;

Bot::middleware(Moderate::class)->group(function () {
    // ...
});

Bot::middleware(Moderate::class.':spam,scam')->group(function () {
    // ...
});
```

The `spam`, `scam`, `toxic` and `nsfw` categories are built in, and you may describe your own. Messages in private chats and messages from the group's administrators are not checked. What happens to a flagged message is configured in your application's `config/ai.php` configuration file:

```php
'moderation' => [
    'threshold' => 0.8,            // The probability at which a message is flagged...
    'action' => 'delete',          // delete, warn, mute or ban...
    'mute_minutes' => 60,
    'skip_admins' => true,
    'warning' => 'This message breaks the rules of the group.',
    'categories' => [
        'offtopic' => 'Messages unrelated to PHP programming.',
    ],
],
```

Each flagged message dispatches a `LaraGram\Ai\Events\MessageFlagged` event with the update, the flagged categories and their probabilities, and the action that was taken.

<a name="inline-mode"></a>
### Inline Mode

Returned from an inline query listen, an answer is shown as an inline result the user may send to any chat:

```php
Bot::onInlineQuery(fn (Request $request) => (new Explainer)->prompt($request->inline_query->query));
```

To offer the user several results to choose from, use the `InlineResults` [output](#telegram-output):

```php
use LaraGram\Ai\Telegram\InlineResults;

return agent('Suggest replies to the message the user describes.', schema: InlineResults::make()->max(5))
    ->prompt($request->inline_query->query);
```

<a name="mini-apps"></a>
### Mini Apps

A [Telegram Mini App](/v4/luna-tma) built with Luna talks to an agent over HTTP. The `useChat` function of Luna's React, Vue and Svelte adapters speaks the [stream protocol](#stream-protocols) of the AI SDK, sends the Mini App's init data with each request, and keeps the messages, the status and any [tool approvals](#human-tool-approval) of the chat:

```php
use App\Ai\Agents\SupportAgent;
use LaraGram\Ai\Vercel\Vercel;
use LaraGram\Http\Request;

Route::middleware('telegram')->post('/chat', function (Request $request) {
    $chat = Vercel::chat($request);

    return (new SupportAgent)
        ->continueLastConversation(tg_user())
        ->stream($chat)
        ->usingProtocol($chat->protocol());
});
```

```jsx
import { useChat } from '@laraxgram/react'

export default function Assistant() {
    const { messages, status, sendMessage, stop, addToolApprovalResponse } = useChat({ api: '/chat' })

    return (
        <>
            {messages.map((message) => (
                <div key={message.id} className={message.role}>
                    {message.parts.map((part, index) =>
                        part.type === 'text' ? <p key={index}>{part.text}</p> : null,
                    )}
                </div>
            ))}

            <form onSubmit={(e) => { e.preventDefault(); sendMessage(e.target.prompt.value); e.target.reset() }}>
                <input name="prompt" disabled={status === 'streaming'} />
            </form>
        </>
    )
}
```

`sendMessage` accepts a string, or an object with `text` and `files`. `regenerate` asks again, `stop` stops the answer, and `addToolApprovalResponse({ id, approved })` answers a pending approval; once every pending approval is answered, the chat continues automatically. In Vue, the messages, status and error are refs; in Svelte, they are reactive properties of the returned object.

Since the user of a Mini App is the same [participant](#conversations-with-telegram-users) as in the bot, the Mini App continues the conversation the user had with your bot.

<a name="human-tool-approval"></a>
## Human Tool Approval

> [!WARNING]
> Tool approval requires the paused turn's history to be available when the run is resumed. You should either use a `Conversational` agent, such as one using the `RemembersConversations` trait, or provide the history from your application's frontend using the [`withMessages` method](#conversation-context). Agents that do neither will throw an `ApprovalNotResumableException` when a tool pauses.

Tools that perform sensitive or irreversible actions may require human approval before they are executed. To make a tool approvable, implement the `Approvable` contract and use the `InteractsWithApprovals` trait. Approvable tools require approval by default:

```php
<?php

namespace App\Ai\Tools;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Support\Facades\Storage;
use LaraGram\Ai\Concerns\InteractsWithApprovals;
use LaraGram\Ai\Contracts\Approvable;
use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Tools\Request;
use Stringable;

class DeleteFile implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Delete a file from storage.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        Storage::delete($request['path']);

        return "Deleted [{$request['path']}].";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->required(),
        ];
    }
}
```

To determine whether approval is needed based on the tool call's arguments, define a `needsApproval` method on the tool. This method may return a boolean or an `Approval` instance that includes a reason for the approval request:

```php
use LaraGram\Ai\Approvals\Approval;

/**
 * Determine whether the tool needs approval for the given request.
 */
protected function needsApproval(Request $request): Approval|bool
{
    return str_starts_with($request['path'], 'temporary/')
        ? false
        : Approval::required('This will permanently delete a file.');
}
```

You may override a tool's approval requirement when returning it from an agent's `tools` method:

```php
public function tools(): iterable
{
    return [
        (new SendNotification)->withoutApproval(),
        (new DeleteFile)->requireApproval('Deletion review required.'),
    ];
}
```

When an approvable tool is called, the agent pauses before executing it. You may inspect the response's pending approvals, which contain each tool call's ID, tool name, arguments, and approval reason:

```php
$response = (new FileAssistant)
    ->forUser($user)
    ->prompt('Delete the old invoice.');

if ($response->hasPendingApprovals()) {
    foreach ($response->pendingApprovals as $approval) {
        // $approval->id
        // $approval->tool
        // $approval->arguments
        // $approval->reason
    }
}
```

To resume the agent, continue the conversation and provide a `Decisions` instance containing a decision for each pending tool call. Decisions may approve the call, reject it, or edit its arguments before execution:

```php
use LaraGram\Ai\Approvals\Decision;
use LaraGram\Ai\Approvals\Decisions;

$response = (new FileAssistant)
    ->continue($conversationId, as: $user)
    ->prompt(Decisions::from([
        'call_abc' => Decision::approve(),
        'call_ghi' => Decision::reject('The invoice must be retained.'),
    ]));
```

> [!IMPORTANT]
> Paused turns are matched by their conversation and pending tool calls, not by the participant that paused them. Therefore, your application should authorize access to the conversation before resuming it, as demonstrated in the [complete approval flow](#complete-approval-flow), or verify access using the conversation store's `conversationBelongsTo` method.

The boolean values `true` and `false` may be used as shorthand for approval and rejection. Every pending tool call must receive a decision. Unknown, missing, or previously resolved tool call IDs will cause an `ApprovalMismatchException` to be thrown. You may provide a default for calls without an explicit decision using the `approveRemaining` or `rejectRemaining` methods:

```php
$decisions = Decisions::from([
    'call_abc' => true,
])->rejectRemaining('Not approved.');

$response = (new FileAssistant)
    ->continue($conversationId, as: $user)
    ->prompt($decisions);
```

A rejection with a result, such as `Decision::reject('Not approved.')`, is returned to the model so it may continue responding. A rejection without a result stops the generation loop after recording the rejection.

Tool approval is supported by the `prompt`, `stream`, `queue`, `broadcast`, `broadcastNow`, and `broadcastOnQueue` methods.

During streaming and broadcasting, a pause is represented by a `tool_approval_request` event. When using the [Vercel AI SDK stream protocol](#stream-protocols), approval requests and results are emitted using the protocol's native tool approval parts, and the Agent User Interaction protocol reports them as interrupts.

Clients that use either protocol post their decisions along with the rest of the conversation, so a [chat request](#frontend-integration) may be passed directly to the agent:

```php
$chat = Vercel::chat($request);

return (new FileAssistant)
    ->continue($conversationId, as: $request->user())
    ->stream($chat)
    ->usingProtocol($chat->protocol());
```

When a paused turn is resumed, the resumed steps are merged into that turn, so each turn is stored as a single assistant message. The response's `assistantMessageId` contains the ID of the paused message, and that message's usage includes both the pause and the resume.

For queued agents, the resulting response is passed to the `then` callback, and LaraGram also dispatches a `ToolApprovalRequested` event.

LaraGram stores the result of an approved tool before asking the model to continue. If generation then fails, the approval has already been resolved. Continue the conversation with a normal text prompt instead of submitting the same approval decisions again.

In a bot, returning a paused response from a listen asks the chat for approval with buttons, and resumes the run once every call is decided. See [approving tools in Telegram](#approving-tools-in-telegram).

<a name="complete-approval-flow"></a>
### Complete Approval Flow

The following route demonstrates a complete approval flow, accepting either a new text prompt or approval decisions from the chat screen. This example assumes the application's `User` model uses the `HasConversations` trait:

```php
use App\Ai\Agents\FileAssistant;
use LaraGram\Http\Request;
use LaraGram\Support\Facades\Gate;
use LaraGram\Support\Facades\Route;
use LaraGram\Validation\Rule;
use LaraGram\Ai\Approvals\Decision;
use LaraGram\Ai\Approvals\Decisions;
use LaraGram\Ai\Models\Conversation;

Route::post('/chat/{conversation}', function (Request $request, Conversation $conversation) {
    Gate::authorize('view', $conversation);

    $validated = $request->validate([
        'message' => ['nullable', 'string', 'required_without:decisions', 'prohibits:decisions'],
        'decisions' => ['nullable', 'array', 'required_without:message', 'prohibits:message'],
        'decisions.*.action' => ['required_with:decisions', Rule::in(['approve', 'reject'])],
        'decisions.*.result' => ['nullable', 'string'],
    ]);

    $prompt = isset($validated['decisions'])
        ? Decisions::from(collect($validated['decisions'])->map(
            fn (array $decision) => match ($decision['action']) {
                'approve' => Decision::approve(),
                'reject' => Decision::reject($decision['result'] ?? null),
            }
        )->all())
        : $validated['message'];

    $response = (new FileAssistant)
        ->continue($conversation->id, as: $request->user())
        ->prompt($prompt);

    return [
        'conversation_id' => $response->conversationId,
        'status' => $response->hasPendingApprovals() ? 'awaiting_approval' : 'complete',
        'message' => $response->text,
        'approvals' => $response->pendingApprovals,
    ];
})->middleware('auth');
```

When the response status is `awaiting_approval`, the chat screen should render the pending approvals and submit the user's choices to the same endpoint using the tool call ID as each decision's key. Otherwise, the screen may submit a plain `message` value:

```json
{
    "decisions": {
        "call_abc": {
            "action": "approve"
        },
        "call_def": {
            "action": "reject",
            "result": "The invoice must be retained."
        }
    }
}
```

<a name="images"></a>
## Images

The `LaraGram\Ai\Image` class may be used to generate images using the `openai`, `gemini`, or `xai` providers:

```php
use LaraGram\Ai\Image;

$image = Image::of('A donut sitting on the kitchen counter')->generate();

$rawContent = (string) $image;
```

The `square`, `portrait`, and `landscape` methods may be used to control the aspect ratio of the image, while the `quality` method may be used to guide the model on final image quality (`high`, `medium`, `low`). The `timeout` method may be used to specify the HTTP timeout in seconds:

```php
use LaraGram\Ai\Image;

$image = Image::of('A donut sitting on the kitchen counter')
    ->quality('high')
    ->landscape()
    ->timeout(120)
    ->generate();
```

You may attach reference images using the `attachments` method:

```php
use LaraGram\Ai\Files;
use LaraGram\Ai\Image;

$image = Image::of('Update this photo of me to be in the style of an impressionist painting.')
    ->attachments([
        Files\Image::fromStorage('photo.jpg'),
        // Files\Image::fromPath('/home/laragram/photo.jpg'),
        // Files\Image::fromUrl('https://example.com/photo.jpg'),
        // $request->file('photo'),
    ])
    ->landscape()
    ->generate();
```

Some providers may generate multiple images in a single request. OpenAI, Azure, and xAI accept an `n` [provider option](#provider-options), and the response will contain every image that was returned:

```php
$response = Image::of('A donut sitting on the kitchen counter')
    ->withProviderOptions(['n' => 4])
    ->generate();

count($response);           // 4
$response->images;          // A collection of generated images...
$response->firstImage();    // The first generated image...
```

Returned from a listen, the generated image is sent to the chat as a photo, or as an album when the provider generated several:

```php
Bot::onText('/draw {prompt}', fn (string $prompt) => Image::of($prompt)->generate());
```

Generated images may be easily stored on the default disk configured in your application's `config/filesystems.php` configuration file:

```php
$image = Image::of('A donut sitting on the kitchen counter');

$path = $image->store();
$path = $image->storeAs('image.jpg');
$path = $image->storePublicly();
$path = $image->storePubliclyAs('image.jpg');
```

Image generation may also be queued:

```php
use LaraGram\Ai\Image;
use LaraGram\Ai\Responses\ImageResponse;

Image::of('A donut sitting on the kitchen counter')
    ->portrait()
    ->queue()
    ->then(function (ImageResponse $image) {
        $path = $image->store();

        // ...
    });
```

<a name="audio"></a>
## Audio

The `LaraGram\Ai\Audio` class may be used to generate audio from the given text:

```php
use LaraGram\Ai\Audio;

$audio = Audio::of('I love building bots with LaraGram.')->generate();

$rawContent = (string) $audio;
```

You may also generate audio from a string using the `toAudio` method available via LaraGram's `Stringable` class:

```php
use LaraGram\Support\Str;

$audio = Str::of('I love building bots with LaraGram.')->toAudio();
```

The `male`, `female`, and `voice` methods may be used to determine the voice of the generated audio:

```php
$audio = Audio::of('I love building bots with LaraGram.')
    ->female()
    ->generate();

$audio = Audio::of('I love building bots with LaraGram.')
    ->voice('voice-id-or-name')
    ->generate();
```

Similarly, the `instructions` method may be used to dynamically coach the model on how the generated audio should sound:

```php
$audio = Audio::of('I love building bots with LaraGram.')
    ->female()
    ->instructions('Said like a pirate')
    ->generate();
```

Returned from a listen, the generated audio is sent to the chat as a voice message:

```php
Bot::onText('/say {text}', fn (string $text) => Audio::of($text)->generate());
```

Generated audio may be easily stored on the default disk configured in your application's `config/filesystems.php` configuration file:

```php
$audio = Audio::of('I love building bots with LaraGram.')->generate();

$path = $audio->store();
$path = $audio->storeAs('audio.mp3');
$path = $audio->storePublicly();
$path = $audio->storePubliclyAs('audio.mp3');
```

Audio generation may also be queued:

```php
use LaraGram\Ai\Audio;
use LaraGram\Ai\Responses\AudioResponse;

Audio::of('I love building bots with LaraGram.')
    ->queue()
    ->then(function (AudioResponse $audio) {
        $path = $audio->store();

        // ...
    });
```

<a name="transcription"></a>
## Transcriptions

The `LaraGram\Ai\Transcription` class may be used to generate a transcript of the given audio:

```php
use LaraGram\Ai\Transcription;

$transcript = Transcription::fromPath('/home/laragram/audio.mp3')->generate();
$transcript = Transcription::fromStorage('audio.mp3')->generate();
$transcript = Transcription::fromUpload($request->file('audio'))->generate();

return (string) $transcript;
```

The voice messages and audio files your bot receives may be transcribed directly, and a transcription returned from a listen is sent to the chat as text:

```php
Bot::onVoice(fn (Request $request) => Transcription::fromUpload($request->file())->generate());
```

The `diarize` method may be used to indicate you would like the response to include the diarized transcript in addition to the raw text transcript, allowing you to access the segmented transcript by speaker:

```php
$transcript = Transcription::fromStorage('audio.mp3')
    ->diarize()
    ->generate();
```

Transcription generation may also be queued:

```php
use LaraGram\Ai\Transcription;
use LaraGram\Ai\Responses\TranscriptionResponse;

Transcription::fromStorage('audio.mp3')
    ->queue()
    ->then(function (TranscriptionResponse $transcript) {
        // ...
    });
```

<a name="text-summarization"></a>
## Text Summarization

You may summarize text using the `summarize` method available via LaraGram's `Stringable` class. By default, the summary will contain no more than three sentences and will be generated using the configured provider's cheapest text model:

```php
use LaraGram\Support\Str;

$summary = Str::of($article)->summarize();
```

You may specify the maximum number of sentences, provider, model, and timeout used to generate the summary. The `Str` class also offers a static version of the method:

```php
use LaraGram\Ai\Enums\Lab;

$summary = Str::of($article)->summarize(
    sentences: 4,
    provider: Lab::Anthropic,
    model: 'claude-sonnet-5',
    timeout: 30,
);

$summary = Str::summarize($article, sentences: 4);
```

<a name="embeddings"></a>
## Embeddings

You may easily generate vector embeddings for any given string using the new `toEmbeddings` method available via LaraGram's `Stringable` class:

```php
use LaraGram\Support\Str;

$embeddings = Str::of('Napa Valley has great wine.')->toEmbeddings();
```

Alternatively, you may use the `Embeddings` class to generate embeddings for multiple inputs at once:

```php
use LaraGram\Ai\Embeddings;

$response = Embeddings::for([
    'Napa Valley has great wine.',
    'LaraGram is a PHP framework for Telegram bots.',
])->generate();

$response->embeddings; // [[0.123, 0.456, ...], [0.789, 0.012, ...]]
```

You may specify the dimensions and provider for the embeddings:

```php
$response = Embeddings::for(['Napa Valley has great wine.'])
    ->dimensions(1536)
    ->generate(Lab::OpenAI, 'text-embedding-3-small');
```

<a name="multimodal-embeddings"></a>
### Multimodal Embeddings

In addition to strings, the `Embeddings::for` method accepts image, audio, document, and video inputs, allowing you to generate embeddings for non-text content. Gemini supports image, audio, document, and video embeddings, while VoyageAI supports image and video embeddings:

```php
use LaraGram\Ai\Embeddings;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Files\Video;

$response = Embeddings::for([
    'A vineyard at sunset.',
    Image::fromStorage('vineyard.jpg'),
    Video::fromPath('/home/laragram/tour.mp4'),
])->generate(Lab::Gemini);
```

Multimodal inputs use the same [file classes used for attachments](#attachments). These files may be created from a local path, a filesystem disk, a remote URL, or Base64-encoded content. Images, documents, and videos may also be created from uploaded files, while documents may be created from raw string content:

```php
use LaraGram\Ai\Files\Audio;
use LaraGram\Ai\Files\Document;
use LaraGram\Ai\Files\Image;
use LaraGram\Ai\Files\Video;

Image::fromPath('/home/laragram/photo.jpg');
Image::fromStorage('photo.jpg');
Image::fromUpload($request->file('photo'));

Audio::fromPath('/home/laragram/clip.mp3');
Audio::fromStorage('clip.mp3');
Audio::fromUpload($request->file('clip.mp3'));

Video::fromPath('/home/laragram/video.mp4');
Video::fromStorage('video.mp4');
Video::fromUpload($request->file('video'));

Document::fromUrl('https://example.com/report.pdf');
Document::fromString('LaraGram is a PHP framework for Telegram bots.', 'text/plain');
Document::fromUpload($request->file('report'));
```

> [!NOTE]
> VoyageAI does not allow remote URL media and Base64-encoded media to be mixed in a single request. Local, stored, and uploaded files are sent as Base64-encoded content, and text inputs may be combined with either media source. Consult your provider's documentation to determine which multimodal models and inputs are available.

<a name="querying-embeddings"></a>
### Querying Embeddings

Once you have generated embeddings, you will typically store them in a `vector` column in your database for later querying. LaraGram provides native support for vector columns on PostgreSQL via the `pgvector` extension and MariaDB. To get started, define a `vector` column in your migration, specifying the number of dimensions:

```php
Schema::ensureVectorExtensionExists();

Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');
    $table->vector('embedding', dimensions: 1536);
    $table->timestamps();
});
```

You may also add a vector index to speed up similarity searches. When calling `index` on a vector column, LaraGram will automatically create an HNSW index with cosine distance:

```php
$table->vector('embedding', dimensions: 1536)->index();
```

On your Eloquent model, you should cast the vector column using the `AsVector` cast:

```php
use LaraGram\Database\Eloquent\Casts\AsVector;

protected function casts(): array
{
    return [
        'embedding' => AsVector::class,
    ];
}
```

To query for similar records, use the `whereVectorSimilarTo` method. This method filters results by a minimum cosine similarity (between `0.0` and `1.0`, where `1.0` is identical) and orders the results by similarity:

```php
use App\Models\Document;

$documents = Document::query()
    ->whereVectorSimilarTo('embedding', $queryEmbedding, minSimilarity: 0.4)
    ->limit(10)
    ->get();
```

The `$queryEmbedding` may be an array of floats or a plain string. When a string is given, LaraGram will automatically generate embeddings for it:

```php
$documents = Document::query()
    ->whereVectorSimilarTo('embedding', 'best wineries in Napa Valley')
    ->limit(10)
    ->get();
```

If you need more control, you may use the lower-level `whereVectorDistanceLessThan`, `selectVectorDistance`, and `orderByVectorDistance` methods independently:

```php
$documents = Document::query()
    ->select('*')
    ->selectVectorDistance('embedding', $queryEmbedding, as: 'distance')
    ->whereVectorDistanceLessThan('embedding', $queryEmbedding, maxDistance: 0.3)
    ->orderByVectorDistance('embedding', $queryEmbedding)
    ->limit(10)
    ->get();
```

If you would like to give an agent the ability to perform similarity searches as a tool, check out the [Similarity Search](#similarity-search) tool documentation.

> [!NOTE]
> Vector queries are currently supported on PostgreSQL connections using the `pgvector` extension, or on MariaDB 11.7 or later using its native vector support.

<a name="caching-embeddings"></a>
### Caching Embeddings

Embedding generation can be cached to avoid redundant API calls for identical inputs. To enable caching, set the `ai.caching.embeddings.cache` configuration option to `true`:

```php
'caching' => [
    'embeddings' => [
        'cache' => true,
        'store' => env('CACHE_STORE', 'database'),
        'individually' => true,
        // ...
    ],
],
```

When caching is enabled, embeddings are cached for 30 days. The cache key is based on the provider, model, dimensions, and input content, ensuring that identical requests return cached results while different configurations generate fresh embeddings.

By default, each input's embedding is cached under its own key, so a later request may hit the cache for inputs it has seen before even when the set of inputs or their order has changed. To instead cache the entire set of inputs under a single key, set the `ai.caching.embeddings.individually` configuration option to `false`.

You may also enable caching for a specific request using the `cache` method, even when global caching is disabled:

```php
$response = Embeddings::for(['Napa Valley has great wine.'])
    ->cache()
    ->generate();
```

You may specify a custom cache duration in seconds:

```php
$response = Embeddings::for(['Napa Valley has great wine.'])
    ->cache(seconds: 3600) // Cache for 1 hour
    ->generate();
```

The `toEmbeddings` Stringable method also accepts a `cache` argument:

```php
// Cache with default duration...
$embeddings = Str::of('Napa Valley has great wine.')->toEmbeddings(cache: true);

// Cache for a specific duration...
$embeddings = Str::of('Napa Valley has great wine.')->toEmbeddings(cache: 3600);
```

<a name="reranking"></a>
## Reranking

Reranking allows you to reorder a list of documents based on their relevance to a given query. This is useful for improving search results by using semantic understanding:

The `LaraGram\Ai\Reranking` class may be used to rerank documents:

```php
use LaraGram\Ai\Reranking;

$response = Reranking::of([
    'Django is a Python web framework.',
    'LaraGram is a PHP framework for Telegram bots.',
    'React is a JavaScript library for building user interfaces.',
])->rerank('PHP frameworks');

// Access the top result...
$response->first()->document; // "LaraGram is a PHP framework for Telegram bots."
$response->first()->score;    // 0.95
$response->first()->index;    // 1 (original position)
```

The `limit` method may be used to restrict the number of results returned, while the `timeout` method may be used to specify the HTTP timeout in seconds, which defaults to 30:

```php
$response = Reranking::of($documents)
    ->limit(5)
    ->timeout(60)
    ->rerank('search query');
```

<a name="reranking-collections"></a>
### Reranking Collections

For convenience, LaraGram collections may be reranked using the `rerank` macro. The first argument specifies which field(s) to use for reranking, and the second argument is the query:

```php
// Rerank by a single field...
$posts = Post::all()
    ->rerank('body', 'LaraGram tutorials');

// Rerank by multiple fields (sent as JSON)...
$reranked = $posts->rerank(['title', 'body'], 'LaraGram tutorials');

// Rerank using a closure to build the document...
$reranked = $posts->rerank(
    fn ($post) => $post->title.': '.$post->body,
    'LaraGram tutorials'
);
```

You may also limit the number of results and specify a provider:

```php
$reranked = $posts->rerank(
    by: 'content',
    query: 'LaraGram tutorials',
    limit: 10,
    provider: Lab::Cohere,
    timeout: 60,
);
```

<a name="classification"></a>
## Classification

> [!WARNING]
> Classification is currently experimental and its API may change in future minor releases of the AI SDK.

Classification allows you to ask a fixed set of questions about a given string or array of data and receive a typed answer, backed by a probability, for each question instead of free-form text. This is useful for routing, moderation, and scoring, where you need to compare an answer against a threshold or make assertions about it in your tests.

The `LaraGram\Ai\Classification` class may be used to classify content. Each question is given a key, and the corresponding answer may be retrieved from the response using that key:

```php
use LaraGram\Ai\Classification;
use LaraGram\Ai\Classification\Boolean;
use LaraGram\Ai\Classification\Choice;
use LaraGram\Ai\Classification\Score;

$result = Classification::of($supportRequest)
    ->questions([
        'urgent' => new Boolean('Does this request need an immediate response?', [
            'true' => 'Explicitly time-sensitive',
            'false' => 'No urgency expressed',
        ]),
        'department' => new Choice('Which team should handle this request?', [
            'billing' => 'Payments, invoices, and refunds',
            'technical' => 'Bugs, outages, and integrations',
            'sales' => 'Pricing, plans, and upgrades',
        ]),
        'frustration' => new Score('How frustrated is the customer?', [
            'Calm',
            'Frustrated',
            'Very angry',
        ]),
    ])
    ->classify();
```

`Boolean` questions return the probability that the answer is "true". The `isTrue` method may be used to determine whether that probability meets a given threshold, which defaults to `0.5`:

```php
$result['urgent']->probability;             // 0.94
$result['urgent']->isTrue(threshold: 0.8);  // true
```

`Choice` questions return one of the given options along with the probability of each option. The `confidence` property indicates how certain the provider is across the full set of probabilities, and will be `null` when the provider is unable to measure it:

```php
$result['department']->choice;                      // 'technical'
$result['department']->probabilityOf('technical');  // 0.87
$result['department']->probabilities;               // ['billing' => 0.08, 'technical' => 0.87, 'sales' => 0.05]
$result['department']->confidence;                  // 0.82
```

`Score` questions return a position on the ordered levels that were provided. The `score` property is probability-weighted and may fall between two levels, while the `level` and `label` methods describe the most probable level:

```php
$result['frustration']->score;          // 1.24, the probability-weighted level
$result['frustration']->level();        // 1, the most probable level
$result['frustration']->label();        // 'Frustrated'
$result['frustration']->normalized();   // 0.62, the score as a fraction of the highest level
$result['frustration']->probabilities;  // [0.12, 0.52, 0.36]
```

The criteria given to a `Boolean` question, the option descriptions given to a `Choice` question, and the levels given to a `Score` question may each be an array when a single sentence is not sufficient. `Choice` questions require at least two options, while `Score` questions require at least two levels.

The response may be iterated, counted, and accessed as an array. In addition, the `answer` method may be used to retrieve a single answer, while the `collect` method returns all of the answers as a [collection](/v4/collections):

```php
$result->answer('urgent');
$result->collect();

$result->usage;
$result->meta->provider;
```

<a name="yes-or-no-decisions"></a>
### Yes or No Decisions

For a single yes or no decision, you may use the `decide` method available via LaraGram's `Stringable` class, which returns a boolean instead of a full response. You may describe what a "yes" and a "no" mean, and specify the probability the answer must reach, which defaults to `0.5`:

```php
use LaraGram\Support\Str;

if (Str::of($message)->decide('Is this spam?')) {
    // ...
}

$spam = Str::of($message)->decide('Is this spam?', criteria: [
    'true' => 'Unsolicited bulk mail.',
    'false' => 'A genuine message from a customer.',
], threshold: 0.9);
```

<a name="choosing-from-collections"></a>
### Choosing From Collections

To quickly choose a single item from a list of options, you may use the `decide` method available on LaraGram's `Collection` class. The method accepts a question and the text to classify, and returns the chosen item from the collection.

Collections of strings and enums may be used directly, while other items should be named using the `by` argument. You may also provide a field, array of fields, or closure via the `describe` argument to give the model more detail about each option:

```php
$department = collect(['billing', 'technical', 'sales'])
    ->decide('Which team should handle this request?', $ticket->body);

$priority = collect(Priority::cases())
    ->decide('How urgent is this request?', $ticket->body);

$department = Department::all()->decide(
    'Which department should handle this request?',
    $ticket->body,
    by: 'name',
    describe: 'description',
);
```

The description defined via the `describe` argument serves as the criteria the model uses to determine whether the text matches that option, which is helpful when an option's name alone is ambiguous. When a closure is provided to the `describe` argument, it receives each item and should return a string or array describing it:

```php
$priority = collect(Priority::cases())->decide(
    'How urgent is this request?',
    $ticket->body,
    describe: fn (Priority $priority) => $priority->description(),
);
```

When a collection of strings is keyed by strings, its keys are used as the options and its values as their descriptions, and the chosen key is returned. If a `threshold` is given and the probability of the chosen option is below it, `null` is returned:

```php
$department = collect([
    'billing' => 'Payments, invoices, and refunds',
    'technical' => 'Bugs, outages, and integrations',
])->decide('Which team should handle this request?', $ticket->body, threshold: 0.6) ?? 'triage';
```

<a name="files"></a>
## Files

The `LaraGram\Ai\Files` class or the individual file classes may be used to store files with your AI provider for later use in conversations. This is useful for large documents or files you want to reference multiple times without re-uploading:

```php
use LaraGram\Ai\Files\Document;
use LaraGram\Ai\Files\Image;

// Store a file from a local path...
$response = Document::fromPath('/home/laragram/document.pdf')->put();
$response = Image::fromPath('/home/laragram/photo.jpg')->put();

// Store a file that is stored on a filesystem disk...
$response = Document::fromStorage('document.pdf', disk: 'local')->put();
$response = Image::fromStorage('photo.jpg', disk: 'local')->put();

// Store a file that is stored on a remote URL...
$response = Document::fromUrl('https://example.com/document.pdf')->put();
$response = Image::fromUrl('https://example.com/photo.jpg')->put();

return $response->id;
```

You may also store raw content or uploaded files:

```php
use LaraGram\Ai\Files;
use LaraGram\Ai\Files\Document;

// Store raw content...
$stored = Document::fromString('Hello, World!', 'text/plain')->put();

// Store an uploaded file...
$stored = Document::fromUpload($request->file('document'))->put();
```

Once a file has been stored, you may reference the file when generating text via agents instead of re-uploading the file:

```php
use App\Ai\Agents\SalesCoach;
use LaraGram\Ai\Files;

$response = (new SalesCoach)->prompt(
    'Analyze the attached sales transcript...',
    attachments: [
        Files\Document::fromId('file-id') // Attach a stored document...
    ]
);
```

To retrieve a previously stored file, use the `get` method on a file instance:

```php
use LaraGram\Ai\Files\Document;

$file = Document::fromId('file-id')->get();

$file->id;
$file->mimeType();
```

To delete a file from the provider, use the `delete` method:

```php
Document::fromId('file-id')->delete();
```

By default, the `Files` class uses the default AI provider configured in your application's `config/ai.php` configuration file. For most operations, you may specify a different provider using the `provider` argument:

```php
$response = Document::fromPath(
    '/home/laragram/document.pdf'
)->put(provider: Lab::Anthropic);
```

You may pass provider-specific upload options using the `withProviderOptions` method. For example, you may set OpenAI's file `purpose`:

```php
use LaraGram\Ai\Files\Document;

$response = Document::fromPath('/home/laragram/knowledge.txt')
    ->withProviderOptions(['purpose' => 'assistants'])
    ->put();
```

To scope options per provider, pass a closure that receives the current provider:

```php
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Files\Document;

$response = Document::fromPath('/home/laragram/training.jsonl')
    ->withProviderOptions(fn (Lab|string $provider) => match ($provider) {
        Lab::OpenAI => ['purpose' => 'fine-tune'],
        default => [],
    })
    ->put();
```

<a name="using-stored-files-in-conversations"></a>
### Using Stored Files in Conversations

Once a file has been stored with a provider, you may reference it in agent conversations using the `fromId` method on the `Document` or `Image` classes:

```php
use App\Ai\Agents\DocumentAnalyzer;
use LaraGram\Ai\Files;
use LaraGram\Ai\Files\Document;

$stored = Document::fromPath('/path/to/report.pdf')->put();

$response = (new DocumentAnalyzer)->prompt(
    'Summarize this document.',
    attachments: [
        Document::fromId($stored->id),
    ],
);
```

Similarly, stored images may be referenced using the `Image` class:

```php
use LaraGram\Ai\Files;
use LaraGram\Ai\Files\Image;

$stored = Image::fromPath('/path/to/photo.jpg')->put();

$response = (new ImageAnalyzer)->prompt(
    'What is in this image?',
    attachments: [
        Image::fromId($stored->id),
    ],
);
```

<a name="vector-stores"></a>
## Vector Stores

Vector stores allow you to create searchable collections of files that can be used for retrieval-augmented generation (RAG). The `LaraGram\Ai\Stores` class provides methods for creating, retrieving, and deleting vector stores:

```php
use LaraGram\Ai\Stores;

// Create a new vector store...
$store = Stores::create('Knowledge Base');

// Create a store with additional options...
$store = Stores::create(
    name: 'Knowledge Base',
    description: 'Documentation and reference materials.',
    expiresWhenIdleFor: days(30),
);

return $store->id;
```

To retrieve an existing vector store by its ID, use the `get` method:

```php
use LaraGram\Ai\Stores;

$store = Stores::get('store_id');

$store->id;
$store->name;
$store->fileCounts;
$store->ready;
```

To delete a vector store, use the `delete` method on the `Stores` class or the store instance:

```php
use LaraGram\Ai\Stores;

// Delete by ID...
Stores::delete('store_id');

// Or delete via a store instance...
$store = Stores::get('store_id');

$store->delete();
```

<a name="adding-files-to-stores"></a>
### Adding Files to Stores

Once you have a vector store, you may add [files](#files) to it using the `add` method. Files added to a store are automatically indexed for semantic searching using the [file search provider tool](#file-search):

```php
use LaraGram\Ai\Files\Document;
use LaraGram\Ai\Stores;

$store = Stores::get('store_id');

// Add a file that has already been stored with the provider...
$document = $store->add('file_id');
$document = $store->add(Document::fromId('file_id'));

// Or, store and add a file in one step...
$document = $store->add(Document::fromPath('/path/to/document.pdf'));
$document = $store->add(Document::fromStorage('manual.pdf'));
$document = $store->add($request->file('document'));

$document->id;
$document->fileId;
```

> [!NOTE]
> Typically, when adding previously stored files to vector stores, the returned document ID will match the file's previously assigned ID; however, some vector storage providers may return a new, different "document ID". Therefore, it's recommended that you always store both IDs in your database for future reference.

When adding a file to a Gemini store, LaraGram waits for the import to finish so that the document is searchable once the call returns. A `LaraGram\Ai\Exceptions\AiException` will be thrown if the import fails or exceeds five minutes, so you may wish to add Gemini files from a [queued job](/v4/queues).

You may attach metadata to files when adding them to a store. This metadata can later be used to filter search results when using the [file search provider tool](#file-search):

```php
$store->add(Document::fromPath('/path/to/document.pdf'), metadata: [
    'author' => 'Taylor Otwell',
    'department' => 'Engineering',
    'year' => 2026,
]);
```

To remove a file from a store, use the `remove` method:

```php
$store->remove('file_id');
```

Removing a file from a vector store does not remove it from the provider's [file storage](#files). To remove a file from the vector store and delete it permanently from file storage, use the `deleteFile` argument:

```php
$store->remove('file_abc123', deleteFile: true);
```

<a name="usage"></a>
## Usage

Every response contains a `usage` property containing the token counts reported by the provider. The input and output counts are totals, so tokens counted as cached or reasoning tokens are also included in the total they belong to:

```php
$response = (new SalesCoach)->prompt('Analyze this sales transcript...');

$response->usage->inputTokens;
$response->usage->outputTokens;
$response->usage->totalTokens();
```

Text generation returns a `LaraGram\Ai\Responses\Data\TextUsage` instance, which breaks these totals down further. Each of these values will be `null`, rather than `0`, when the provider does not report it:

```php
$response->usage->cacheReadInputTokens; // Subset of the input tokens read from a prompt cache...
$response->usage->cacheWriteInputTokens; // Subset of the input tokens written to a prompt cache...
$response->usage->reasoningTokens; // Subset of the output tokens spent on reasoning...

$response->usage->uncachedInputTokens(); // Input tokens that were neither read from nor written to the cache...
```

Cache reads, cache writes, and uncached input are billed at different rates, so you should price these three counts separately instead of using the input total alone.

The remaining capabilities return a usage object containing the counts specific to them:

<div class="overflow-auto">

| Capability | Usage object | Adds |
|---|---|---|
| Text, classification | `TextUsage` | Cache read, cache write, and reasoning tokens |
| Images | `ImageUsage` | `imageInputTokens` and `imageOutputTokens` |
| Transcription | `TranscriptionUsage` | `audioSeconds`, the duration of the transcribed audio |
| Reranking | `RerankingUsage` | `searchUnits`, which some providers bill instead of tokens |
| Audio, embeddings | `Usage` | |

</div>

Not every provider reports every count, and counts that a provider does not report will be `null`:

```php
use LaraGram\Ai\Image;
use LaraGram\Ai\Transcription;

Image::of('A donut sitting on the kitchen counter')->generate()->usage->imageOutputTokens;

Transcription::fromPath('/home/laragram/meeting.mp3')->generate()->usage->audioSeconds;
```

<a name="failover"></a>
## Failover

When prompting or generating other media, you may provide an array of providers / models to automatically failover to a backup provider / model if a service interruption or rate limit is encountered on the primary provider:

```php
use App\Ai\Agents\SalesCoach;
use LaraGram\Ai\Enums\Lab;
use LaraGram\Ai\Image;

$response = (new SalesCoach)->prompt(
    'Analyze this sales transcript...',
    provider: [Lab::OpenAI, Lab::Anthropic],
);

$image = Image::of('A donut sitting on the kitchen counter')
    ->generate(provider: [Lab::Gemini, Lab::xAI]);
```

Failover only occurs when a `FailoverableException` is thrown — such as a rate limit (`RateLimitedException`), an overloaded or unavailable provider (`ProviderOverloadedException`), or insufficient credits (`InsufficientCreditsException`). Ordinary errors, like a validation or bad request error, will not trigger failover.

When you pass a plain list of providers, such as `[Lab::OpenAI, Lab::Anthropic]`, each provider uses its default model. To specify a particular model for each provider in the failover chain, pass an associative array keyed by the provider, using the `Lab` enum's `value` as the key (enum cases cannot be used directly as PHP array keys):

```php
use LaraGram\Ai\Enums\Lab;

$response = (new SalesCoach)->prompt(
    'Analyze this sales transcript...',
    provider: [
        Lab::Gemini->value => 'gemini-3-flash-preview',
        Lab::DeepSeek->value => 'deepseek-v4-pro',
    ],
);
```

<a name="events"></a>
## Events

The LaraGram AI SDK dispatches a variety of [events](/v4/events), including:

- `AddingFileToStore`
- `AgentFailed`
- `AgentFailedOver`
- `AgentPrompted`
- `AgentStreamed`
- `AudioGenerated`
- `Classified`
- `Classifying`
- `CreatingStore`
- `EmbeddingsGenerated`
- `FileAddedToStore`
- `FileDeleted`
- `FileRemovedFromStore`
- `FileStored`
- `GeneratingAudio`
- `GeneratingEmbeddings`
- `GeneratingImage`
- `GeneratingTranscription`
- `ImageGenerated`
- `MessageFlagged`
- `InvokingTool`
- `PromptingAgent`
- `ProviderFailedOver`
- `RemovingFileFromStore`
- `Reranked`
- `Reranking`
- `StartingStep`
- `StepCompleted`
- `StepFailed`
- `StoreCreated`
- `StoreDeleted`
- `StoringFile`
- `StreamingAgent`
- `ToolApprovalRequested`
- `ToolApprovalResolved`
- `ToolFailed`
- `ToolInvoked`
- `TranscriptionGenerated`

You can listen to any of these events to log or store AI SDK usage information.
