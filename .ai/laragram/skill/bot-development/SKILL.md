---
name: bot-development
description: "Use this skill for LaraGram Telegram bot development. Trigger whenever creating or changing listens (listens/bot.php, Bot::onText, Bot::onCommand, Bot::onCallbackQueryData, Bot::onStep, fallback), bot controllers, bot middleware and scopes, replies through LaraGram\\Request\\Request ($request->sendMessage, editMessageText, answerCallbackQuery, file downloads), inline or reply keyboards (Keyboard, Make), Temple8 templates (app/templates/*.t8.php, template(), @text, @keyboard), Conversations (make:conversation, Conversation::start, Questioner), the Step Manager, Telegram pagination (telegramPaginate), multiple bot connections, anti-flood, or webhooks. Also use when the user mentions a Telegram bot, /start, callback buttons, or a multi-step bot flow."
license: MIT
metadata:
  author: laraxgram
---

# LaraGram Bot Development

LaraGram handles every Telegram update like a request: the update is matched against **listens** (the bot equivalent of routes), passes through middleware, and reaches a closure or controller that replies through the `LaraGram\Request\Request` object. For exact API syntax, verify with `search-docs` (use `packages: ['laraxgram/laraquest']` for Bot API method parameters and types).

## Consistency First

Check `listens/bot.php`, existing controllers in `app/Controllers`, templates in `app/templates`, and conversations in `app/Conversations` before adding anything. Follow the patterns already used (closures vs. controllers, templates vs. inline replies, conversations vs. steps). Don't introduce a second way.

## How to Apply

1. Inspect the current listens with `php laragram listen:list -v` (middleware included) and read the related files.
2. Map the change to the rule index below and read each mapped rule file before editing.
3. Keep listen patterns specific and ordered deliberately: the first matching listen handles the update unless listens are marked as overlapping.
4. Verify with a local update when possible (for example by sending the bot a message in a development environment, or with the `bot_simulate_update` MCP tool when the project provides it), then check `last-error` / `read-log-entries`.
5. Never call `webhook:set`, `webhook:delete`, or send broadcasts to real chats unless the user asks.

## Rule Index

| Concern | Read |
| --- | --- |
| Listen files, verbs, patterns, parameters, names, groups, scopes, overlap, model binding, fallback, rate limiting | [`rules/listening.md`](rules/listening.md) |
| Reading updates, replying, request helpers, connections, anti-flood, file downloads, callback queries | [`rules/requests.md`](rules/requests.md) |
| Inline / reply keyboards and the `Keyboard` / `Make` builder | [`rules/keyboards.md`](rules/keyboards.md) |
| Temple8 templates (`*.t8.php`), directives, components, Telegram pagination | [`rules/templates.md`](rules/templates.md) |
| Multi-step flows: Conversations and the Step Manager | [`rules/conversations-and-steps.md`](rules/conversations-and-steps.md) |

## Decision Rules

- Put Telegram handlers in listen files (`listens/*.php`) and web handlers in `routes/*.php`. Never register a Telegram handler as a web route.
- Use a controller once a listen needs more than a few lines or shares logic with other listens.
- Use Temple8 for messages that combine formatting and keyboards, or that are reused; use `$request->sendMessage()` for short one-off replies.
- Use a Conversation for linear question-and-answer flows with validation; use the Step Manager for free-form state machines.
- Always answer callback queries (`answerCallbackQuery`) so the user's button stops spinning.
- Keep per-user state in the cache or database, never in PHP statics — the application may run under Surge or handle each update in a separate process.
