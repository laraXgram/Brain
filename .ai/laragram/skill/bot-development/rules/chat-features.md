# Media, Formatting, Inline Mode, Payments and Deep Links

Look up any method's parameters with `search-docs` (`packages: ['laraxgram/laraquest']`) before writing the call.

## Sending Media

Pass media as a `file_id` (fastest, no upload), an HTTPS URL, or a local file wrapped in `CURLFile`:

```php
$request->sendPhoto(chat()->id, $product->telegram_file_id, caption: $product->name);

$request->sendDocument(chat()->id, new \CURLFile(storage_path('app/reports/'.$report->file)), caption: 'Your report');

$message = $request->sendVideo(chat()->id, 'https://cdn.example.com/intro.mp4');
$product->update(['telegram_file_id' => $message['result']['video']['file_id']]);
```

- Store the `file_id` Telegram returns and reuse it instead of uploading the same file again. A `file_id` is valid only for the bot that received it.
- Albums: `sendMediaGroup(chat()->id, media: [...])`, where each item is an `InputMedia*` array (`['type' => 'photo', 'media' => $fileId]`). Local files in albums use `attach://name` plus a `CURLFile` parameter with that name.
- Incoming albums arrive as one update per item sharing a `media_group_id`; `Bot::onAlbum(...)` matches them. Collect the items (for example in the cache keyed by `media_group_id`) before processing the album as a whole.
- Bot API responses are arrays (`$response['ok']`, `$response['result']`); check `ok` before reading `result`.

## Formatting Text

- Pick one `parse_mode` for the project (`html` is the easiest to escape) and set it once in `default_parameters` (`config/laraquest.php`).
- Escape every piece of user or database content: `e($text)` / `htmlspecialchars()` for HTML; for MarkdownV2 escape `_*[]()~\`>#+-=|{}.!` with a backslash.
- The helpers `bold()`, `italic()`, `underline()`, `strikethrough()`, `spoiler()`, `code()`, `pre()`, `blockquote()`, `inline_url()`, `mention_user_by_id()`, and `tg_time()` wrap text for `markdownv2` (default) or `html` (`bold($name, 'html')`). They do **not** escape their input.
- Messages are limited to 4096 characters and captions to 1024; split long output or send a document.

## Callback Queries and Editing

- Answer every callback query (`answerCallbackQuery`), optionally with `text` and `show_alert: true`.
- Update the existing message (`editMessageText`, `editMessageCaption`, `editMessageReplyMarkup`, `deleteMessage`) instead of stacking new messages on every button press. Telegram rejects edits that don't change anything ("message is not modified"); skip the call in that case.
- `self_delete()` deletes the incoming user message after handling (useful for keeping menus clean); `self_delete(['TEXT'])` limits it to some update kinds.

## Inline Mode

Enable inline mode for the bot in @BotFather, then answer queries within a few seconds:

```php
Bot::onInlineQuery(function (Request $request) {
    $results = Product::where('name', 'like', '%'.inline_query()->query.'%')->take(20)->get()
        ->map(fn (Product $product) => [
            'type' => 'article',
            'id' => (string) $product->id,
            'title' => $product->name,
            'input_message_content' => ['message_text' => $product->name.' — '.$product->price],
        ])->all();

    $request->answerInlineQuery(inline_query()->id, $results, cache_time: 30, is_personal: true);
});
```

`Bot::onInlineQueryQuery('buy {product}', ...)` matches the query text, and `Bot::onChosenInlineResult(...)` reports the selected result (enable inline feedback in @BotFather). Result ids must be unique strings of at most 64 bytes.

## Payments With Telegram Stars

Digital goods are paid in Telegram Stars (`currency: 'XTR'`, no provider token, a single price item):

```php
$request->sendInvoice(
    chat_id: chat()->id,
    title: 'Pro plan',
    description: '30 days of Pro',
    payload: 'plan:pro:'.Auth::id(),
    currency: 'XTR',
    prices: [['label' => 'Pro plan', 'amount' => 250]],
);

Bot::onPreCheckoutQuery(function (Request $request) {
    $valid = str_starts_with(pre_checkout_query()->invoice_payload, 'plan:');

    $request->answerPreCheckoutQuery(pre_checkout_query()->id, ok: $valid, error_message: $valid ? null : 'This offer has expired.');
});

Bot::onSuccessfulPayment(function (Request $request) {
    $payment = message()->successful_payment;

    Subscription::activate(user()->id, $payment->invoice_payload, $payment->telegram_payment_charge_id);
});
```

- Answer pre-checkout queries within 10 seconds; do the fulfilment only in the successful-payment handler, and make it idempotent by storing `telegram_payment_charge_id`.
- Refund with `refundStarPayment`; `createInvoiceLink` builds a link for Mini Apps (see Luna's `Luna::invoiceLink()` when using Luna).

## Deep Links

`https://t.me/<bot_username>?start=<payload>` opens the bot with `/start <payload>`. Handle payloads with `Bot::onReferral`, which matches the text after `/start `:

```php
Bot::onReferral('ref_{code}', function (Request $request, string $code) {
    Referral::record(user()->id, $code);

    return template('welcome');
});

Bot::onCommand('start', fn () => template('welcome'));
```

Payloads are limited to 64 characters of `A-Z`, `a-z`, `0-9`, `_` and `-`; never put secrets or trusted ids in them without verification.

## Groups, Channels and Membership

- Chat events have their own verbs: `onNewChatMembers`, `onLeftChatMember`, `onChatJoinRequest` (approve with `approveChatJoinRequest`), `onMyChatMember` (the bot was added, blocked, or promoted), and `onChatMember` (requires `chat_member` in `allowed_updates`).
- Bot API errors are returned, not thrown: a user who blocked the bot yields `['ok' => false, 'error_code' => 403, ...]`. Check `ok` when the result matters. Broadcasts handle this automatically: the `TrackChats` middleware and `Broadcast` mark blocked chats unreachable and skip them.
- In groups with privacy mode enabled, the bot only receives commands, replies to its own messages, and mentions. Don't rely on `onText` catching every group message unless privacy mode is disabled.
