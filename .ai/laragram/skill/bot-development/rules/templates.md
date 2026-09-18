# Temple8 Templates

## What a Template Is

A Temple8 template (`app/templates/*.t8.php`) describes **one Telegram API call**: its inputs (text, chat, parse mode, keyboard) are written as directives, and rendering the template sends the call. Create templates with `php laragram make:template orders.summary` and render them with `template()`:

```php
Bot::onCommand('orders', function () {
    return template('orders.summary', [
        'orders' => Order::where('user_id', user()->id)->latest()->take(10)->get(),
    ]);
});
```

```blade
{{-- app/templates/orders/summary.t8.php --}}
@parse_mode(html)

@text()
<b>Your orders</b>
@foreach ($orders as $order)
#{{ $order->id }} — {{ $order->status }}
@endforeach
@endText

@keyboard()
    @foreach ($orders as $order)
        @row()
            @col('View #'.$order->id, callback_data: 'order:show:'.$order->id)
        @endRow
    @endforeach
@endKeyboard()
```

Nested templates use dot notation (`orders.summary` → `app/templates/orders/summary.t8.php`). Template directory names must not contain dots.

## Inputs and the Method

- Every Bot API parameter can be written as a directive: `@text() ... @endText`, `@caption`, `@parse_mode(markdown)`, `@chat_id($chatId)`, `@reply_markup($markup)`, and so on.
- `@chat_id` is optional; it defaults to the chat of the current update. Pass it explicitly when rendering outside an update.
- `@method('editMessageText')` changes the API method; the default is `sendMessage`. For edits, also pass the `message_id`.
- Temple8 echoes `{{ }}` **raw** (the opposite of Blade); `{{{ }}}` escapes with `htmlspecialchars`, and `{!! !!}` is raw as well. Use `{{{ }}}` for anything a user can influence under `parse_mode(html)` or in a rich message. When using MarkdownV2, escape user content for Markdown instead.

## Keyboards

Build keyboards with `@keyboard(type) ... @endKeyboard()`, `@row() ... @endRow`, and `@col($text, callback_data: ..., url: ...)`. The type defaults to `inline`; use `reply`, `remove`, or `force` for other keyboards. `@keyboardOptions(['resize_keyboard' => true])` sets options. Control structures (`@if`, `@foreach`) may wrap rows and columns; empty rows are skipped.

## Control Structures and Reuse

Temple8 supports Blade-style directives: `@if`/`@elseif`/`@else`, `@unless`, `@isset`, `@empty`, `@switch`, `@foreach`/`@forelse` (with `$loop`), `@include`, `@each`, `@once`, `@php`, `@use`, `{{-- comments --}}`, components (`<x-alert/>`, `make:component`), and layouts with `@extends`/`@section`/`@yield`.

- Keep queries and business logic out of templates. Prepare the data in the listen or controller and pass it in.
- A partial that must not send its own request (for example a component or an `@include`d file) starts with `<!-- !component! -->`. The marker is stripped from the output.
- With `@extends`, the layout is the request: `@text`, `@rich`, `@keyboard` and the other inputs belong in the layout, and the child only fills `@section`s.
- Share data with every template through `Template::share()` or a template composer in a service provider.

## Rich Messages

`@rich ... @endrich` builds a `sendRichMessage` payload from simplified HTML (`@richDraft` for `sendRichMessageDraft`). Use it when the message needs structure Telegram's text formatting cannot express: headings, tables, lists, collapsible sections, formulas, maps, grouped media or rich buttons. Plain formatted text stays in `@text`.

```blade
@rich(rtl: true)
    <h1>{{{ $title }}}</h1>

    @richPhoto($chart, caption: 'Sales')

    @richTable($rows, headers: ['Product', 'Units'], bordered: true)

    <row>
        <btn callback="report:full" style="primary">Full report</btn>
    </row>
@endrich
```

- Markup: Telegram's rich tags pass through untouched; short tags cover the verbose ones (`<spoiler>`, `<emoji>`, `<time>`, `<math>`, `<ref>`, `<doc>`, `<map>`, `<collage>`, `<slideshow>`, `<thinking>`, `<row>`, `<btn>`). A `<btn>` carries exactly one type attribute (`url`, `callback`, `webapp`, `login`, `inline`, `inline-here`, `inline-chat`, `copy`, `disabled`); a `<row>` holds 1–8 buttons.
- Directives, for what markup cannot do: `@richPhoto`, `@richVideo`, `@richAnimation`, `@richAudio`, `@richVoice`, `@richDocument` (file positional, everything else named) register a `file_id`, path or `InputFile` and write its `tg://` link; `@richMedia` returns only the link for hand placement; `@richTable`, `@richList`, `@richChecklist` build markup from arrays and escape every value.
- `@foreach`, `@if`, `@include`, `<x-component/>` and layouts all work inside a block and land where they are written, so build repeated rows, cells and media in loops. A rich directive used where no block is open throws `RichMessageException`; keep `@rich` in the template that sends the message and let partials contribute markup only.
- Block options: `@rich(rtl: true, skipEntityDetection: true, strict: false, pretty: false)`. Strict mode (on by default) rejects tags Telegram does not document and enforces its limits (32,768 characters, 500 blocks, 16 nesting levels, 50 media, 20 table columns).
- From PHP, `LaraGram\Template\Rich\RichMessage::make()->append(...)->photo(...)->table(...)->toArray()` builds the same payload, and `RichMessage::escape()` escapes a value.
- Check tag and attribute details with `search-docs` ("rich messages") before inventing markup; unsupported tags are rejected at build time.

## Rendering Without Sending

The `bot_render_template` MCP tool renders a template for a chat and returns the Bot API call it would make - text, parse mode, keyboard, rich message - without sending anything. Use it to check a template's output, and `bot_simulate_update` to check the listen that renders it.

## Sending a Template to Many Chats

To send a template to many users or groups, use `Broadcast::users()->template('name', $data)->queue()` (see [`broadcasting.md`](broadcasting.md)), not a loop calling `template()`. The template is rendered for each recipient: `chat()`, `user()` and the default `chat_id` are the recipient, `$recipient` holds the stored chat details, and `->localized()` renders it in the recipient's language. A template name, a path, a `Template` instance or an inline string all work. Keep broadcast templates free of `@chat_id` and pass only arrays and scalars as data.

## Telegram Pagination

Paginate lists with `telegramPaginate` (numbered) or `simpleTelegramPaginate` (previous / next). The page travels in callback data as `paginate:<key>:<page>` (64 bytes in total, so keep the key short), so a paginated screen is two listens: one that sends the first page, and an `onPaginate` that answers the taps and re-renders the same template.

```php
Bot::onCommand('users', function () {
    return template('users.index', [
        'paginator' => User::telegramPaginate(10, key: 'users'),
    ]);
});

Bot::onPaginate('users', function (Request $request, int $page) {
    $request->answerCallbackQuery();

    return template('users.index', [
        'paginator' => User::telegramPaginate(10, page: $page, key: 'users'),
    ]);
});
```

```blade
{{-- app/templates/users/index.t8.php --}}
@text()
@foreach ($paginator as $user)
{{{ $user->first_name }}}
@endforeach
@endText

@paginate($paginator)
```

- `@paginate` attaches the keyboard and picks the method: the first page is sent, a tap edits the message it came from, so the screen stays in place. Don't pass `'method' => 'editMessageText'` by hand and don't build the navigation keyboard yourself; `@reply_markup($paginator->keyboard())` attaches only the keyboard.
- Without a template: `$paginator->heading('Users')->formatUsing(fn ($u) => "#{$u->id} {$u->name}")->render()`. `render('users.index', $data)` renders a template of yours instead.
- Adjust the navigation with `labels(previous: ..., next: ...)`, `indicator('{current} / {last}')`, `withoutIndicator()`, `onEachSide(2)`, `rightToLeft()`, `methods(send: 'sendPhoto', edit: 'editMessageCaption')`, or a custom `keyboardTemplate()`.
- Publish the message and keyboard templates with `php laragram vendor:publish --tag=pagination-templates`; they land in `app/templates/vendor/pagination`.
