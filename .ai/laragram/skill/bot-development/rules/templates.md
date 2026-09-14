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
- `{{ }}` escapes output with `htmlspecialchars`, which suits `parse_mode(html)`. Use `{!! !!}` only for trusted, pre-escaped content. When using MarkdownV2, escape user content for Markdown instead.

## Keyboards

Build keyboards with `@keyboard(type) ... @endKeyboard()`, `@row() ... @endRow`, and `@col($text, callback_data: ..., url: ...)`. The type defaults to `inline`; use `reply`, `remove`, or `force` for other keyboards. `@keyboardOptions(['resize_keyboard' => true])` sets options. Control structures (`@if`, `@foreach`) may wrap rows and columns; empty rows are skipped.

## Control Structures and Reuse

Temple8 supports Blade-style directives: `@if`/`@elseif`/`@else`, `@unless`, `@isset`, `@empty`, `@switch`, `@foreach`/`@forelse` (with `$loop`), `@include`, `@each`, `@once`, `@php`, `@use`, `{{-- comments --}}`, components (`<x-alert/>`, `make:component`), and layouts with `@extends`/`@section`/`@yield`.

- Keep queries and business logic out of templates. Prepare the data in the listen or controller and pass it in.
- A partial that must not send its own request (for example a component) starts with `<!-- !component! -->`.
- Share data with every template through `Template::share()` or a template composer in a service provider.

## Rendering Without Sending

The `bot_render_template` MCP tool (when the project provides it) renders a template for a chat and returns the Bot API calls it would make, without sending anything. Use it to check a template's output.

## Telegram Pagination

Paginate lists with `telegramPaginate` (numbered) or `simpleTelegramPaginate` (previous / next). The page travels in callback data as `paginate:<key>:<page>`, so a paginated screen needs two listens:

```php
Bot::onCommand('users', function () {
    return template('users.index', [
        'paginator' => User::telegramPaginate(10, key: 'users'),
    ]);
});

Bot::onCallbackQueryData('paginate:users:{page}', function (Request $request, $page) {
    return template('users.index', [
        'paginator' => User::telegramPaginate(10, page: $page, key: 'users'),
        'method' => 'editMessageText',
    ]);
});
```

```blade
{{-- app/templates/users/index.t8.php --}}
@isset($method)
    @method($method)
@endisset

@text()
@foreach ($paginator as $user)
{{ $user->first_name }}
@endforeach
@endText

@reply_markup($paginator->keyboard())
```

Adjust the navigation with `labels(previous: ..., next: ...)`, `indicator('{current} / {last}')`, `withoutIndicator()`, `rightToLeft()`, or a custom `keyboardTemplate()`.
