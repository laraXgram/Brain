---
name: bot-development
description: "Use this skill for LaraGram Telegram bot development. Trigger whenever creating or changing listens (listens/bot.php, Bot::onText, Bot::onCommand, Bot::onCallbackQueryData, Bot::onStep, Bot::onReferral, Bot::onInlineQuery, fallback), bot controllers, bot middleware and scopes, the bot auth guard, can('administrator') chat-member checks, media uploads, inline mode, Telegram Stars payments, deep links, replies through LaraGram\\Request\\Request ($request->sendMessage, editMessageText, answerCallbackQuery, file downloads), inline or reply keyboards (Keyboard, Make), Temple8 templates (app/templates/*.t8.php, template(), @text, @keyboard), Conversations (make:conversation, Conversation::start, Questioner), the Step Manager, Telegram pagination (telegramPaginate), multiple bot connections, anti-flood, or webhooks. Also use when the user mentions a Telegram bot, /start, callback buttons, or a multi-step bot flow."
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
4. Verify without touching Telegram: the `bot_listens` MCP tool lists the registered listens, `bot_simulate_update` runs a fake update through the listens and returns the Bot API calls the handlers made, `bot_render_template` shows what a template would send, and `bot_conversation_state` inspects an active conversation (all local-only, when Brain exposes them). Then check `last-error` / `read-log-entries`.
5. Never call `webhook:set`, `webhook:delete`, or send broadcasts to real chats unless the user asks.

## Rule Index

| Concern | Read |
| --- | --- |
| Listen files, verbs, patterns, parameters, names, groups, scopes, overlap, model binding, fallback, rate limiting | [`rules/listening.md`](rules/listening.md) |
| Reading updates, replying, request helpers, connections, anti-flood, file downloads, callback queries | [`rules/requests.md`](rules/requests.md) |
| Inline / reply keyboards and the `Keyboard` / `Make` builder | [`rules/keyboards.md`](rules/keyboards.md) |
| Temple8 templates (`*.t8.php`), directives, components, Telegram pagination | [`rules/templates.md`](rules/templates.md) |
| Multi-step flows: Conversations and the Step Manager | [`rules/conversations-and-steps.md`](rules/conversations-and-steps.md) |
| Bot controllers, bot middleware (`$middleware->bot()`), controller middleware, rate limiting users | [`rules/middleware-and-controllers.md`](rules/middleware-and-controllers.md) |
| The `bot` auth guard, registering users, policies and `can`, chat-member status (`can('administrator')`), per-user locale | [`rules/users-and-permissions.md`](rules/users-and-permissions.md) |
| Sending media and albums, text formatting and escaping, editing messages, inline mode, Telegram Stars payments, deep links, group and membership events | [`rules/chat-features.md`](rules/chat-features.md) |

## Decision Rules

- Put Telegram handlers in listen files (`listens/*.php`) and web handlers in `routes/*.php`. Never register a Telegram handler as a web route.
- Use a controller once a listen needs more than a few lines or shares logic with other listens.
- Use Temple8 for messages that combine formatting and keyboards, or that are reused; use `$request->sendMessage()` for short one-off replies.
- Use a Conversation for linear question-and-answer flows with validation; use the Step Manager for free-form state machines.
- Always answer callback queries (`answerCallbackQuery`) so the user's button stops spinning.
- Keep per-user state in the cache or database, never in PHP statics — the application may run under Surge or handle each update in a separate process.
- Bot API calls return arrays (`['ok' => ..., 'result' => ...]`) and do not throw on Telegram errors; check `ok` when the result matters.
- Each webhook update runs in its own background PHP process (or a Surge task worker) after Telegram's request is answered, so long handlers pile up processes and their errors only appear in the logs. Queue slow work such as broadcasts, exports, and third-party API calls.
