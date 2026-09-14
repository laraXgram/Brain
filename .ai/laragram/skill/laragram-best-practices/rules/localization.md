# Localization Best Practices

## Keep User-Facing Text in Translation Files

Bot replies, keyboard labels, template text, validation messages, and web pages should come from `lang/` once the project supports more than one language (publish defaults with `php laragram lang:publish`).

```php
// lang/en/orders.php
return [
    'created' => 'Order #:id was created.',
    'count' => '{0} You have no orders|{1} You have one order|[2,*] You have :count orders',
];
```

```php
$request->sendMessage(chat()->id, __('orders.created', ['id' => $order->id]));
$label = trans_choice('orders.count', $count, ['count' => $count]);
```

- Use short keys grouped by feature (`orders.created`) or JSON string keys (`lang/fa.json`); follow the style already in the project.
- Temple8 templates and Blade views use `{{ __('orders.created', ['id' => $order->id]) }}` or `@lang(...)`.
- Keep `callback_data` and command names language-independent; translate only what the user reads.

## Set the Locale per Update or Request

- Bots: set the locale in a bot middleware from the stored user preference, falling back to `user()->language_code` (see the bot-development skill).
- Web and Mini Apps: set it in a web middleware from the session, the authenticated user, or the Telegram init data language.
- Jobs, scheduled messages, and broadcasts run without a request: pass the recipient's locale (`__('orders.created', $params, $user->locale)`) or wrap the work with `App::setLocale()` and restore it afterwards.

## Formatting

- Format dates with Tempora in the user's locale and timezone (`$date->locale($locale)->isoFormat('LLL')`), and store timestamps in UTC.
- Right-to-left languages work in Telegram automatically; keep formatting tags balanced and don't mix `parse_mode` markup with unescaped translated text.
