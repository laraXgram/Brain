# Telegram Reference

## What Each Response Sends

| Returned from a listen | Sent as |
|---|---|
| `prompt()` | Text message(s) from the model's Markdown; rich message in `auto` mode for headings, tables, formulas or long answers |
| `stream()` | Private chats: `sendMessageDraft` / `sendRichMessageDraft` with a stop button, then the final message. Groups and MTProto: one message edited as it grows |
| `queue()` | `typing` now, the answer when the job finishes (`ai.telegram.failure_message` on failure) |
| Structured response with a Telegram output | The output (quiz polls, keyboard message, …) |
| Structured response without an output | The JSON as a code block |
| Response with pending approvals | Approval message with buttons (`lgai:` callback data) |
| `ImageResponse` | `sendPhoto`, or `sendMediaGroup` for several images |
| `AudioResponse` | `sendVoice` (OGG/MP3/M4A) or `sendAudio` |
| `TranscriptionResponse` | Text message |

Callback queries are answered automatically; inline queries receive `answerInlineQuery`.

## Outputs

| Output | Options | Notes |
|---|---|---|
| `Quiz::questions(int)` | `options(int)`, `withExplanation()`, `withoutExplanation()`, `multipleCorrect()`, `anonymous(bool)`, `openFor(seconds)`, `shuffle()` | One `sendPoll` (type quiz) per question |
| `Poll::make()` | `options(min, max)`, `multipleAnswers()`, `anonymous(bool)`, `openFor(seconds)` | |
| `Keyboard::inline()` | `callbacks(['data' => 'meaning'])`, `urls()`, `prefix('ai:')`, `max(6)`, `columns(2)` | Unknown callback data is dropped |
| `Keyboard::reply()` | `max(6)`, `columns(2)` | One-time reply keyboard of suggested answers |
| `RichMessage::make()` | | Always `sendRichMessage` |
| `Checklist::make()` | `max(10)`, `othersCanAddTasks()`, `othersCanMarkTasksAsDone()` | Real checklist only with a business connection, otherwise a rich task list |
| `Location::make()` / `Location::venue()` | | |
| `Document::as('csv')` | | Model writes filename and content |
| `Invoice::stars(int)` / `Invoice::price(int, currency, providerToken)` | `payload()`, `label()` | Price always from the application |
| `InlineResults::make()` | `max(5)`, `cacheFor(seconds, personal)` | Outside inline queries, the first result is sent as text |

All outputs accept `withReplyMarkup($keyboard)`.

## Chats and Connections

`sendTo($chat, $connection)` accepts a chat ID, `@username`, `chat()` / `user()` objects, a bot `Request`, an MTProto `ClientRequest`, or a `LaraGram\Ai\Telegram\Chat`. `$connection` is a bot connection name or `mtproto:session`.

## Configuration (`config/ai.php` → `telegram`)

- `format`: `auto` | `html` | `rich`
- `listens`: register the package listens (approval buttons, draft stop button)
- `failure_message`: sent when a queued answer fails
- `streaming.drafts`, `streaming.can_stop`, `streaming.interval`
- `approvals.ttl`, `approvals.title`, `approvals.rejection`
- `usage.store`: cache store of `LimitUsage`
- `moderation.threshold`, `action` (`delete`, `warn`, `mute`, `ban`), `mute_minutes`, `skip_admins`, `warning`, `categories`
