# Laraquest (Telegram Bot API)

- Laraquest is LaraGram's Telegram Bot API client. Every Bot API method is available on `LaraGram\Request\Request` (`$request->sendMessage(...)`, `$request->call('methodName', [...])`) with the method's parameters as (named) arguments.
- Look up a method's parameters, return type, and object fields with `search-docs` and `packages: ['laraxgram/laraquest']` (for example `sendMediaGroup` or `InlineKeyboardMarkup`) instead of guessing from memory; the schema matches the installed Bot API version.
- Configure per-method default parameters (such as `parse_mode`) under `default_parameters` in `config/laraquest.php` instead of repeating them on every call.
- Never build `https://api.telegram.org/bot<token>/...` URLs or call the Bot API through the HTTP client; the request object applies the bot connection, anti-flood pacing, the proxy pool, and default parameters.
