# Keyboard Builder

<a name="introduction"></a>

## Introduction

LaraGram is equipped with an advanced and flexible keyboard builder, allowing you to easily create various types of
Telegram keyboards with a clean and professional structure.

```php
use LaraGram\Support\Facades\Keyboard;
use LaraGram\Keyboard\Make;

$keyboard = Keyboard::inlineKeyboardMarkup(
    Make::row(
        Make::callbackData("OK", 'ok_btn'),
        Make::callbackData("Cancel", 'cancel_btn'),
    )
)->get();
```

<a name="keyboard-types"></a>

## Keyboard Types

LaraGram supports several types of keyboards that you can create using the keyboard builder:

#### Reply Keyboards

Reply keyboards are displayed below the message input field on the user's device. They can contain multiple rows of
buttons and are persistent until removed.

```php
use LaraGram\Support\Facades\Keyboard;

$keyboard = Keyboard::replyKeyboardMarkup(...$row);
```

#### Inline Keyboards

Inline keyboards are attached directly to messages and allow users to interact with the bot by pressing buttons within
the message itself. These keyboards are ideal for interactive content.

```php
use LaraGram\Support\Facades\Keyboard;

$keyboard = Keyboard::inlineKeyboardMarkup(...$row);
```

#### Force Reply

The force reply keyboard prompts the user to reply to a specific message, useful for collecting user input in a
conversational manner.

```php
use LaraGram\Support\Facades\Keyboard;

$keyboard = Keyboard::forceReply($input_field_placeholder = '', $selective = false);
```

#### Remove Keyboard

This option allows you to hide the current keyboard, returning the user to the standard input field.

```php
use LaraGram\Support\Facades\Keyboard;

$keyboard = Keyboard::replyKeyboardRemove($selective = false);
```

<a name="available-methods"></a>

## Available Methods

The keyboard builder provides a fluent interface for constructing keyboards. Below are the key methods available:

### Set Options

To set keyboard options, you can do the following:

```php
$keyboard->setOption('resize_keyboard', false);
// Or
$keyboard->setOptions([
    'resize_keyboard' => false,
    'one_time_keyboard' => true
]);
```

### Manage Row

You can easily add another row to the row from your keyboard, or even delete or edit them:

```php
// Add
$keyboard->appendRow(Make::row(
    //
));

$keyboard->prependRow(Make::row(
    //
));

// Remove
$keyboard->removeRow($index);

// Edit
$keyboard->editRow(
    Make::row(
        //
    ),
    $index
);
```

### Manage Col

Similarly, you can add, edit, or delete a new column anywhere in the rows:

```php
// Add
$keyboard->appendCol(
    Make::callbackData('OK', 'ok_btn'),
    $row_index
);

$keyboard->prependCol(
    Make::callbackData('OK', 'ok_btn'),
    $row_index
);

// Remove
$keyboard->removeCol($row_index, $col_index);

// Edit
$keyboard->editCol(
    Make::row(
        //
    ),
    $row_index,
    $col_index
);
```

### Get Keyboard

Finally, you have an object from the keyboard builder that you need to convert into a JSON or array to send to Telegram,
this can be done easily with the get method.

```php
$keyboard = Keyboard::inlineKeyboardMarkup(
    Make::row(
        Make::callbackData("OK", 'ok_btn'),
        Make::callbackData("Cancel", 'cancel_btn'),
    )
);

$jsonFormat  = $keyboard->get();
$arrayFormat = $keyboard->get(true);
```

## Button Type

The types of keyboards supported by Telegram can be done as simply as calling a method, you can call them from the
`LaraGram\Keyboard\Make` class:

```php
$keyboard = Keyboard::inlineKeyboardMarkup(
    Make::row(
        Make::callbackData("OK", 'ok_btn'),
        Make::copyText("Copy", 'Hello, LaraGram!'),
    )
);
```

### Available Buttons

| Button Type                    | Parameters                                                               |
|--------------------------------|--------------------------------------------------------------------------|
| `url`                          | `text`, `url`                                                            |
| `callbackData`                 | `text`, `callback_data`                                                  |
| `loginUrl`                     | `text`, `url`, `?forward_text`, `?bot_username`, `?request_write_access` |
| `switchInlineQuery`            | `text`, `switch_inline_query`                                            |
| `switchInlineQueryCurrentChat` | `text`, `switch_inline_query_current_chat`                               |
| `switchInlineQueryChosenChat`  | `text`, `?query`, `?options `                                            |
| `copyText`                     | `text`, `copy`                                                           |
| `text`                         | `text`                                                                   |
| `pay`                          | `text`                                                                   |
| `requestUsers`                 | `text`, `?id`, `?max_quantity`, `?options`                               |
| `requestChat`                  | `text`, `?id`, `?options`                                                |
| `requestContact`               | `text`                                                                   |
| `requestLocation`              | `text`                                                                   |
| `requestPoll`                  | `text`, `?type `                                                         |
| `webApp`                       | `text`, `url`                                                            |

<a name="use-in-templates"></a>

## Use In Templates

In your [Template templates](temple8.md#keyboard-builder), you can easily render keyboards using the `@keyboard`, `@row`, and `@col` directives:

```blade
@keyboard()
    @row()
        @col('first', callback_data: 'first_btn')
        @col('second', callback_data: 'second_btn')
    @endRow
    @row()
        @col('return', callback_data: 'return_btn') 
    @endRow
@endKeyboard()
```

To specify the keyboard type, simply pass the desired type as an argument to the `@keyboard` directive. By default, the keyboard type is set to `inlineKeyboardMarkup`.

```blade
@keyboard(reply)

@endKeyboard()
```

<a name="available-keyboard-types"></a>
#### Available Keyboard Types

| directive parameter name | type                 |
|--------------------------|----------------------|
| `reply`                  | replyKeyboardMarkup  |
| `remove`                 | replyKeyboardRemove  |
| `force`                  | forceReply           |
| `inline`                 | inlineKeyboardMarkup |

This allows for seamless integration of keyboards into your bot's responses within templates.

### Dynamic Keyboards

The `@keyboard` directive is compiled imperatively, so you may freely place control structures such as `@if`, `@foreach`, and `@switch` between the `@row` and `@col` directives to build a keyboard dynamically:

```blade
@keyboard()
    @foreach ($products as $product)
        @row()
            @col($product->name, callback_data: 'buy:' . $product->id)

            @if ($product->discounted)
                @col('🔥 ' . $product->discount . '%', callback_data: 'sale:' . $product->id)
            @endif
        @endRow
    @endforeach

    @row()
        @col('Close', callback_data: 'close_btn')
    @endRow
@endKeyboard()
```

Empty rows are skipped automatically, so a `@row` whose `@col` directives are all guarded by a condition that evaluates to `false` will not be added to the keyboard.

### Keyboard Options In Templates

You may set keyboard options such as `resize_keyboard` or `one_time_keyboard` using the `@keyboardOptions` directive. The given array is forwarded to the keyboard builder's [`setOptions`](#available-methods) method:

```blade
@keyboard(reply)
    @keyboardOptions(['resize_keyboard' => true, 'one_time_keyboard' => true])

    @row()
        @col('Share contact', request_contact: true)
    @endRow
@endKeyboard()
```
