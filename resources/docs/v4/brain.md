# LaraGram Brain

<a name="introduction"></a>
## Introduction

LaraGram Brain accelerates AI-assisted development by providing the essential guidelines and agent skills that help AI agents write high-quality LaraGram applications that adhere to LaraGram best practices.

Brain also provides a powerful LaraGram ecosystem documentation API that combines a built-in MCP tool with an extensive knowledge base containing over 17,000 pieces of LaraGram-specific information, all enhanced by semantic search capabilities using embeddings for precise, context-aware results. Brain instructs AI agents like Claude Code and Cursor to use this API to learn about the latest LaraGram features and best practices.

<a name="installation"></a>
## Installation

LaraGram Brain is a development tool for your LaraGram application and is installed via Composer:

```shell
composer require laraxgram/brain --dev
```

Next, install the MCP server and coding guidelines:

```shell
php laragram brain:install
```

The `brain:install` command will generate the relevant agent guideline and skill files for the coding agents you selected during the installation process.

Once LaraGram Brain has been installed, you're ready to start coding with Cursor, Claude Code, or your AI agent of choice.

> [!NOTE]
> Feel free to add the generated MCP configuration file (`.mcp.json`), guideline files (`CLAUDE.md`, `AGENTS.md`, `junie/`, etc.), and the `Brain.json` configuration file to your application's `.gitignore`, as these files are automatically regenerated when running `brain:install` and `brain:update`.

<a name="set-up-your-agents"></a>
### Set Up Your Agents

```text tab=Cursor
1. Open the command palette (`Cmd+Shift+P` or `Ctrl+Shift+P`)
2. Press `enter` on "/open MCP Settings"
3. Turn the toggle on for `LaraGram-Brain`
```

```text tab=Claude Code
Claude Code support is typically enabled automatically. If you find it isn't, open a shell in the project's directory and run the following command:

claude mcp add -s local -t stdio LaraGram-Brain php laragram brain:mcp
```

```text tab=Codex
Codex support is typically enabled automatically. If you find it isn't, open a shell in the project's directory and run the following command:

codex mcp add LaraGram-Brain -- php "laragram" "brain:mcp"
```

```text tab=Gemini CLI
Gemini CLI support is typically enabled automatically. If you find it isn't, open a shell in the project's directory and run the following command:

gemini mcp add -s project -t stdio LaraGram-Brain php laragram brain:mcp
```

```text tab=GitHub Copilot (VS Code)
1. Open the command palette (`Cmd+Shift+P` or `Ctrl+Shift+P`)
2. Press `enter` on "MCP: List Servers"
3. Arrow to `LaraGram-Brain` and press `enter`
4. Choose "Start server"
```

```text tab=Junie
1. Press `shift` twice to open the command palette
2. Search "MCP Settings" and press `enter`
3. Check the box next to `LaraGram-Brain`
4. Click "Apply" at the bottom right
```

<a name="keeping-Brain-resources-updated"></a>
### Keeping Brain Resources Updated

You may want to periodically update your local Brain resources (AI guidelines and skills) to ensure they reflect the latest versions of the LaraGram ecosystem packages you have installed. To do so, you can use the `brain:update` Artisan command.

```shell
php laragram brain:update
```

You may also automate this process by adding it to your Composer "post-update-cmd" scripts:

```json
{
  "scripts": {
    "post-update-cmd": [
      "@php laragram brain:update --ansi"
    ]
  }
}
```

By default, the `brain:update` command will only update the existing Brain resources already published within your application. If you would like Brain to scan your application for any newly installed packages and offer to publish their corresponding guidelines and skills, you may use the `--discover` option:

```shell
php laragram brain:update --discover
```

<a name="mcp-server"></a>
## MCP Server

LaraGram Brain provides an MCP (Model Context Protocol) server that exposes tools for AI agents to interact with your LaraGram application. These tools give agents the ability to inspect your application's structure, query the database, execute code, and try your bot out — listing listens, running fake updates through them, rendering templates and reading conversation state — without a single call reaching Telegram.

<a name="available-mcp-tools"></a>
### Available MCP Tools

<div class="overflow-auto">

| Name                 | Notes                                                                                                       |
| -------------------- | ----------------------------------------------------------------------------------------------------------- |
| Application Info     | Read PHP & LaraGram versions, database engine, list of ecosystem packages with versions, and Eloquent models |
| Browser Logs         | Read logs and errors from the browser                                                                       |
| Database Connections | Inspect available database connections, including the default connection                                    |
| Database Query       | Execute a query against the database                                                                        |
| Database Schema      | Read the database schema                                                                                    |
| Get Absolute URL     | Convert relative path URIs to absolute so agents generate valid URLs                                        |
| Last Error           | Read the last error from the application's log files                                                        |
| Read Log Entries     | Read the last N log entries                                                                                 |
| Record Rule          | Record a durable [project rule](#project-rules) into `.ai/rules` so future agents inherit it                |
| Search Docs          | Query the LaraGram hosted documentation API service to retrieve documentation based on installed packages    |
| Probe                | Evaluate PHP inside the booted application, the way [Probe](/v4/commander#probe) does                        |

</div>

<a name="bot-runtime-tools"></a>
### Bot Runtime Tools

When [`laraxgram/mcp`](/v4/mcp) is installed, Brain also exposes its bot runtime tool set, which lets an agent try your bot out locally. None of these tools send anything to Telegram:

<div class="overflow-auto">

| Name                     | Notes                                                                                                         |
| ------------------------ | ------------------------------------------------------------------------------------------------------------- |
| `bot_listens`            | List the registered listens: update type, pattern, name, handler, middleware and bot connections              |
| `bot_simulate_update`    | Run an update through the listens like a real webhook call, and report the matched listen and every Bot API call the handlers made |
| `bot_render_template`    | Render a Temple8 template (rich messages and keyboards included) for a chat and report the calls it would make |
| `bot_conversation_state` | Inspect the conversation a user is in, or forget it to reset them                                              |

</div>

They are enabled only in the `local` environment, and may be turned off with the `brain.bot_runtime_tools` configuration option.

<a name="manually-registering-the-mcp-server"></a>
### Manually Registering the MCP Server

Sometimes you may need to manually register the LaraGram Brain MCP server with your editor of choice. You should register the MCP server using the following details:

<table>
<tr><td><strong>Command</strong></td><td><code>php</code></td></tr>
<tr><td><strong>Args</strong></td><td><code>laragram brain:mcp</code></td></tr>
</table>

JSON example:

```json
{
    "mcpServers": {
        "LaraGram-Brain": {
            "command": "php",
            "args": ["laragram", "brain:mcp"]
        }
    }
}
```

<a name="ai-guidelines"></a>
## AI Guidelines

AI guidelines are composable instruction files that are loaded upfront to provide AI agents with essential context about LaraGram ecosystem packages. These guidelines contain core conventions, best practices, and framework-specific patterns that help agents generate consistent, high-quality code.

<a name="available-ai-guidelines"></a>
### Available AI Guidelines

LaraGram Brain includes AI guidelines for the following packages and frameworks. The `core` guidelines provide generic, generalized advice to the AI for the given package that is applicable across all versions.

<div class="overflow-auto">

| Package           | Versions Supported     |
| ----------------- | ---------------------- |
| Core & Brain      | core                   |
| LaraGram Framework | core, 10.x, 11.x, 12.x, 13.x |
| Livewire          | core, 2.x, 3.x, 4.x    |
| Flux UI           | core, free, pro        |
| Folio             | core                   |
| Herd              | core                   |
| Inertia LaraGram   | core, 1.x, 2.x, 3.x    |
| Inertia React     | core, 1.x, 2.x, 3.x    |
| Inertia Vue       | core, 1.x, 2.x, 3.x    |
| Inertia Svelte    | core, 1.x, 2.x, 3.x    |
| MCP               | core                   |
| Pennant           | core                   |
| Pest              | core, 3.x, 4.x         |
| PHPUnit           | core                   |
| Pint              | core                   |
| Sail              | core                   |
| Tailwind CSS      | core, 3.x, 4.x         |
| Livewire Volt     | core                   |
| Wayfinder         | core                   |
| Enforce Tests     | conditional            |

</div>

> **Note:** To keep your AI guidelines up-to-date, see the [Keeping Brain Resources Updated](#keeping-Brain-resources-updated) section.

<a name="adding-custom-ai-guidelines"></a>
### Adding Custom AI Guidelines

To augment LaraGram Brain with your own custom AI guidelines, add `.blade.php` or `.md` files to your application's `.ai/guidelines/*` directory. These files will automatically be included with LaraGram Brain's guidelines when you run `brain:install`.

<a name="overriding-Brain-ai-guidelines"></a>
### Overriding Brain AI Guidelines

You can override Brain's built-in AI guidelines by creating your own custom guidelines with matching file paths. When you create a custom guideline that matches an existing Brain guideline path, Brain will use your custom version instead of the built-in one.

For example, to override Brain's "Inertia React v2 Form Guidance" guidelines, create a file at `.ai/guidelines/inertia-react/2/forms.blade.php`. When you run `brain:install`, Brain will include your custom guideline instead of the default one.

<a name="third-party-package-ai-guidelines"></a>
### Third-Party Package AI Guidelines

If you maintain a third-party package and would like Brain to include AI guidelines for it, you can do so by adding a `resources/brain/guidelines/core.blade.php` file to your package. When users of your package run `php laragram brain:install`, Brain will automatically load your guidelines.

AI guidelines should provide a short overview of what your package does, outline any required file structure or conventions, and explain how to create or use its main features (with example commands or code snippets). Keep them concise, actionable, and focused on best practices so AI can generate correct code for your users. Here is an example:

```php
## Package Name

This package provides [brief description of functionality].

### Features

- Feature 1: [clear & short description].
- Feature 2: [clear & short description]. Example usage:

@verbatim
<code-snippet name="How to use Feature 2" lang="php">
$result = PackageName::featureTwo($param1, $param2);
</code-snippet>
@endverbatim
```

<a name="agent-skills"></a>
## Agent Skills

[Agent Skills](https://agentskills.io/home) are lightweight, targeted knowledge modules that agents can activate on-demand when working on specific domains. Unlike guidelines, which are loaded upfront, skills allow detailed patterns and best practices to be loaded only when relevant, reducing context bloat and improving the relevance of AI-generated code.

When you run `brain:install` and select skills as a feature, skills are automatically installed based on the packages detected in your `composer.json`. For example, if your project includes `livewire/livewire`, the `livewire-development` skill will be installed automatically. Skills included with Brain, such as `infer-conventions`, are installed regardless of which packages you have.

<a name="available-skills"></a>
### Available Skills

<div class="overflow-auto">

| Skill                      | Package        |
| -------------------------- | -------------- |
| fluxui-development         | Flux UI        |
| folio-routing              | Folio          |
| infer-conventions          | Brain          |
| inertia-react-development  | Inertia React  |
| inertia-svelte-development | Inertia Svelte |
| inertia-vue-development    | Inertia Vue    |
| livewire-development       | Livewire       |
| mcp-development            | MCP            |
| pennant-development        | Pennant        |
| pest-testing               | Pest           |
| tailwindcss-development    | Tailwind CSS   |
| volt-development           | Volt           |
| wayfinder-development      | Wayfinder      |

</div>

> **Note:** To keep your skills up-to-date, see the [Keeping Brain Resources Updated](#keeping-Brain-resources-updated) section.

<a name="custom-skills"></a>
### Custom Skills

To create your own custom skills, add a `SKILL.md` file to your application's `.ai/skills/{skill-name}/` directory. When you run `brain:update`, your custom skills will be installed alongside Brain's built-in skills.

For example, to create a custom skill for your application's domain logic:

```
.ai/skills/creating-invoices/SKILL.md
```

<a name="overriding-skills"></a>
### Overriding Skills

You can override Brain's built-in skills by creating your own custom skills with matching names. When you create a custom skill that matches an existing Brain skill name, Brain will use your custom version instead of the built-in one.

For example, to override Brain's `livewire-development` skill, create a file at `.ai/skills/livewire-development/SKILL.md`. When you run `brain:update`, Brain will include your custom skill instead of the default one.

<a name="third-party-package-skills"></a>
### Third-Party Package Skills

If you maintain a third-party package and would like Brain to include skills for it, you can do so by adding a `resources/brain/skills/{skill-name}/SKILL.md` file to your package (a `SKILL.blade.php` file is rendered first, so a skill may adapt itself to the application it is installed in). When users of your package run `php laragram brain:install`, Brain will automatically install your skills based on user preference.

Brain Skills support the [Agent Skills format](https://agentskills.io/what-are-skills) and should be structured as a folder containing a `SKILL.md` file with YAML frontmatter and Markdown instructions. The `SKILL.md` file must include required frontmatter (`name` and `description`) and can optionally include scripts, templates, and reference materials.

Skills should outline any required file structure or conventions, and explain how to create or use its main features (with example commands or code snippets). Keep them concise, actionable, and focused on best practices so AI can generate correct code for your users:

```markdown
---
name: package-name-development
description: Build and work with PackageName features, including components and workflows.
---

# Package Name Development

## When to use this skill
Use this skill when working with PackageName features...

## Features

- Feature 1: [clear & short description].
- Feature 2: [clear & short description]. Example usage:

$result = PackageName::featureTwo($param1, $param2);
```

<a name="guidelines-vs-skills"></a>
## Guidelines vs. Skills

LaraGram Brain provides two distinct ways to give AI agents context about your application: **guidelines** and **skills**.

**Guidelines** are loaded upfront when the AI agent starts, providing essential context about LaraGram conventions and best practices that apply broadly across your codebase.

**Skills** are activated on-demand when working on specific tasks, containing detailed patterns for particular domains (like Livewire components or Pest tests). Loading skills only when relevant reduces context bloat and improves code quality.

<div class="overflow-auto">

| Aspect      | Guidelines                        | Skills                           |
| ----------- | --------------------------------- | -------------------------------- |
| **Loaded**  | Upfront, always present           | On-demand, when relevant         |
| **Scope**   | Broad, foundational               | Focused, task-specific           |
| **Purpose** | Core conventions & best practices | Detailed implementation patterns |

</div>

Both guidelines and skills describe the LaraGram ecosystem. To capture the conventions of your own application, you should use [project rules](#project-rules).

<a name="project-rules"></a>
## Project Rules

While guidelines and skills teach agents how to write LaraGram, project rules teach them how to write your application. A rule is anything you would otherwise need to explain again in every new session:

<div class="content-list" markdown="1">

- Decisions made along the way by you, your agents, or your teammates.
- Style guidelines and preferences that are difficult to get an agent to follow.
- Traps and constraints that can't be inferred from the surrounding code.

</div>

Rules are stored as Markdown files within your application's `.ai/rules` directory and should be committed to source control. Unlike an agent's own memory, which is personal and session-scoped, your rules are shared with your team and with every agent that works on your application.

Each rule file declares the file globs it applies to within its frontmatter:

```markdown
---
paths:
  - app/Http/Controllers/**
---

# Http Controllers

## Extend BaseController for tenant scoping

All controllers must extend `App\Http\Controllers\BaseController`, which applies the
current tenant's query scope. Extending LaraGram's base controller directly will leak
data across tenants.
```

In addition, Brain maintains an `.ai/rules/index.md` file which maps globs to their rule files. Agents are instructed to consult this index before planning or editing any file, so a rule is only loaded when it is relevant:

```markdown
# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Http/Controllers/** | .ai/rules/controllers.md |
| app/Models/** | .ai/rules/models.md |
```

> [!NOTE]
> Unlike the `.mcp.json` and generated guideline files, the `.ai/rules` directory should be committed to source control so that your rules are shared with your team.

<a name="recording-rules"></a>
### Recording Rules

To record a rule, you may simply ask your agent to remember it:

```text
Remember that all money values are stored as integer cents, never as floats.
```

The agent will invoke Brain's `record-rule` MCP tool with a `glob`, a short `title`, and a `note`. Brain will then file the rule under the matching area, creating the rule file if needed, and update the index.

You should always record rules using the `record-rule` tool rather than creating rule files by hand. Brain regenerates `.ai/rules/index.md` as part of recording a rule, and agents rely on that index to discover which rules apply to the file they are working on. A rule file that is added manually will not be discovered until the index is next regenerated.

<a name="inferring-your-applications-conventions"></a>
### Inferring Your Application's Conventions

Recording rules one at a time works well going forward; however, an existing application already contains years of conventions. The `infer-conventions` skill will bootstrap your rules from the code you have already written. To get started, ask your agent to use the skill:

```text
Use the infer-conventions skill
```

The skill will sweep your application across a checklist of LaraGram convention dimensions, including validation, controllers, authorization, models, architecture, testing, frontend, database, and console, followed by an open-ended pass for patterns such as base classes, shared traits, and module layouts.

The skill documents what your code actually does rather than what it should do. It records only well-supported, non-default conventions, skips framework defaults and anything Pint or Rector already enforces, and reports genuinely mixed patterns instead of recording them. Before writing any rules, the skill will present each convention it discovered, along with its supporting evidence, for your approval. If you would like the skill to record all discovered conventions without confirmation, you may tell it to "yolo".

<a name="disabling-project-rules"></a>
### Disabling Project Rules

Project rules are enabled by default. To disable them entirely, define the following environment variable. This removes the `record-rule` MCP tool and stops Brain from managing the `.ai/rules` directory:

```ini
Brain_RULES_ENABLED=false
```

<a name="documentation-api"></a>
## Documentation API

LaraGram Brain includes a Documentation API that provides AI agents with access to an extensive knowledge base containing over 17,000 pieces of LaraGram-specific information. The API uses semantic search with embeddings to deliver precise, context-aware results.

The `Search Docs` MCP tool allows agents to query the LaraGram hosted documentation API service to retrieve documentation based on your installed packages. Brain's AI guidelines and skills will automatically instruct your coding agent to use this API.

<div class="overflow-auto">

| Package           | Versions Supported |
| ----------------- | ------------------ |
| LaraGram Framework | 10.x, 11.x, 12.x, 13.x |
| Filament          | 2.x, 3.x, 4.x, 5.x |
| Flux UI           | 2.x Free, 2.x Pro  |
| Inertia           | 1.x, 2.x           |
| Livewire          | 1.x, 2.x, 3.x, 4.x |
| Nova              | 4.x, 5.x           |
| Pest              | 3.x, 4.x           |
| Tailwind CSS      | 3.x, 4.x           |

</div>

<a name="extending-Brain"></a>
## Extending Brain

Brain works with many popular IDEs and AI agents out of the box. If your coding tool isn't supported yet, you can create your own agent and integrate it with Brain.

<a name="adding-support-for-other-ides-ai-agents"></a>
### Adding Support for Other IDEs / AI Agents

To add support for a new IDE or AI agent, create a class that extends `LaraGram\Brain\Install\Agents\Agent` and implement one or more of the following contracts depending on what you need:

- `LaraGram\Brain\Contracts\SupportsGuidelines` - Adds support for AI guidelines.
- `LaraGram\Brain\Contracts\SupportsMcp` - Adds support for MCP.
- `LaraGram\Brain\Contracts\SupportsSkills` - Adds support for Agent Skills.

<a name="writing-the-agent"></a>
#### Writing the Agent

```php
<?php

declare(strict_types=1);

namespace App;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Agents\Agent;

class CustomAgent extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    // Your implementation...
}
```

For an example implementation, see [ClaudeCode.php](https://github.com/LaraGram/Brain/blob/main/src/Install/Agents/ClaudeCode.php).

<a name="registering-the-agent"></a>
#### Registering the Agent

Register your custom agent in the `boot` method of your application's `App\Providers\AppServiceProvider`:

```php
use LaraGram\Brain\Brain;

public function boot(): void
{
    Brain::registerAgent('customagent', CustomAgent::class);
}
```

Once registered, your agent will be available for selection when running `php laragram brain:install`.