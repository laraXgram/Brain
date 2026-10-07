# Agentic Development

- [Introduction](#introduction)
    - [Why LaraGram for Agentic Development?](#why-laragram)
- [LaraGram Brain](#laragram-brain)
    - [Installation](#installation)
    - [Available Tools](#available-tools)
    - [AI Guidelines](#ai-guidelines)
    - [Agent Skills](#agent-skills)
    - [Documentation Search](#documentation-search)
    - [Project Rules](#project-rules)
- [Trying the Bot Without Telegram](#trying-the-bot-without-telegram)
- [Exposing Your Application to AI Clients](#exposing-your-application)
- [Building AI Features](#building-ai-features)

<a name="introduction"></a>
## Introduction

Writing a Telegram bot with an AI coding agent is a different experience from writing a web application with one: the agent cannot click a button to see whether a flow works, Telegram is a live system you do not want an agent poking at, and bot code is full of conventions — listens, templates, conversations, broadcasts — that an agent has to know before it writes anything.

LaraGram takes that seriously. [LaraGram Brain](/v4/brain) gives your agent the conventions of the framework, the shape of your application, offline documentation for every installed package, and a set of tools that let it run your bot locally without a single call reaching Telegram.

<a name="why-laragram"></a>
### Why LaraGram for Agentic Development?

<div class="content-list" markdown="1">

- **Conventions an agent can follow.** Listens, controllers, templates, conversations and broadcasts each have one obvious way to be written, and Brain ships those conventions as [guidelines](#ai-guidelines) and [skills](#agent-skills) that load into the agent's context.
- **Documentation the agent can search offline.** The `search-docs` tool answers from the documentation of the versions you actually have installed, so the agent does not invent an API from a different major version.
- **A bot it can run.** The [bot runtime tools](#trying-the-bot-without-telegram) list your listens, push a fake update through them, render a template and read a conversation's state — all locally.
- **A debugger it can read.** [Sentinel](/v4/sentinel) records every update with the Bot API calls, queries and exceptions it caused, so an agent can inspect what went wrong instead of guessing.

</div>

<a name="laragram-brain"></a>
## LaraGram Brain

[Brain](/v4/brain) is the package that teaches AI agents about your application. It installs an MCP server, the framework guidelines, and agent skills into your editor of choice.

<a name="installation"></a>
### Installation

Brain is a development dependency:

```shell
composer require laraxgram/brain --dev

php laragram brain:install
```

The installer detects the editors and agents on your machine — Claude Code, Codex, Cursor, Gemini CLI and the others — and asks which of them should receive the MCP server, the guidelines and the skills. Everything it writes is a file in your project, so it is reviewed and committed like the rest of your code.

<a name="available-tools"></a>
### Available Tools

Brain's MCP server exposes tools that answer the questions an agent would otherwise guess at: which packages and versions are installed, what the database looks like, what the last error was, what a URL of this project should be. See [available MCP tools](/v4/brain#available-mcp-tools) for the full list.

<a name="ai-guidelines"></a>
### AI Guidelines

Guidelines are the short, opinionated rules of the framework and of each installed package — how to register a listen, when to use a conversation instead of the step manager, that broadcasts are never a `foreach` over chat ids. Brain composes them from the packages you have installed and the version of each, and writes them where your agent reads them.

Your own conventions belong next to them, in `.ai/guidelines`, and are composed together with the framework's.

<a name="agent-skills"></a>
### Agent Skills

Where guidelines are always in context, a **skill** is loaded when the work calls for it. Brain ships skills for bot development, MTProto clients, Luna and Mini Apps, MCP servers, Commander commands, deployment and framework best practices, each with rule files for the area being touched.

A package of your own may ship its guidelines and skills too; see [AI guidelines](/v4/brain#ai-guidelines) for how a package ships them.

<a name="documentation-search"></a>
### Documentation Search

The `search-docs` tool searches the documentation of the installed packages — the framework, Laraquest's Bot API methods and types, MTProto, Luna, MCP — offline and version-aware. An agent that is about to call a Bot API method should look it up rather than remember it.

<a name="project-rules"></a>
### Project Rules

Decisions that are settled in your project — a naming convention, a constraint, a trap someone already fell into — are recorded as [project rules](/v4/brain#project-rules) in `.ai/rules`. They are committed with the repository, so every agent that works on the project inherits them.

<a name="trying-the-bot-without-telegram"></a>
## Trying the Bot Without Telegram

An agent should never have to message your production bot to find out whether its change works. With [`laraxgram/mcp`](/v4/mcp) installed, Brain exposes the [bot runtime tools](/v4/brain#bot-runtime-tools):

<div class="overflow-auto">

| Tool | What the agent can do |
| --- | --- |
| `bot_listens` | See every registered listen: update type, pattern, name, handler, middleware, connections |
| `bot_simulate_update` | Push a made-up update through the listens and read the Bot API calls the handler made |
| `bot_render_template` | Render a [Temple8 template](/v4/temple8) and read the call it would send |
| `bot_conversation_state` | Inspect the [conversation](/v4/conversations) a user is in, or reset it |

</div>

None of them send anything to Telegram, and they are limited to the `local` environment by default.

> [!NOTE]
> Keep the rule simple for your agents: never run `webhook:set`, `webhook:delete` or a real broadcast, and never message a real chat. Simulate instead, and use `->test($yourChatId)` or `->via('log')` when a [broadcast](/v4/broadcasting#previewing-broadcasts) really has to be tried.

<a name="exposing-your-application"></a>
## Exposing Your Application to AI Clients

The other direction is just as useful: letting an AI client talk to *your* application. [LaraGram MCP](/v4/mcp) builds the server for that — tools, resources and prompts of your own, plus ready-made [Telegram tool sets](/v4/mcp#telegram-tool-sets) for the Bot API and MTProto, with abilities, confirmation of destructive calls and chat allow lists.

A tool that must reach many chats should call the [`Broadcast`](/v4/broadcasting) facade rather than loop over chat ids, so the work is queued, paced and tracked.

<a name="building-ai-features"></a>
## Building AI Features

Agentic development is about writing your application with AI. To put AI *inside* your application — agents your bot talks to, structured answers, transcriptions of the voice messages users send, embeddings of what they wrote — use the [LaraGram AI SDK](/v4/ai-sdk).
