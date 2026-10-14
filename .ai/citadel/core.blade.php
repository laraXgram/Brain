@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram Citadel

- `laraxgram/citadel` is the API authentication package: personal access tokens, SPA sessions and an OAuth2 server (Laravel Sanctum and Passport merged) behind one `citadel` guard and one `LaraGram\Citadel\HasApiTokens` trait, plus Telegram sign-in (Mini App init data, Login Widget, device approval in the bot).
- IMPORTANT: Activate `citadel-development` whenever the user works on API tokens, protecting `routes/api.php` or MCP servers, token abilities or OAuth scopes, OAuth clients and grants, SPA or Mini App API authentication, or Telegram-based sign-in.
- Protect API routes with `auth:citadel` and abilities with the `abilities:` / `ability:` middleware. Never use `auth:sanctum`, a `passport` guard driver, or `Laravel\Sanctum` / `Laravel\Passport` classes: they do not exist in LaraGram.
- OAuth scopes must be registered with `Citadel::tokensCan([...])`; the OAuth server also needs `{{ $assist->commanderCommand('citadel:keys') }}`. Personal access tokens need neither.
- Schedule `{{ $assist->commanderCommand('citadel:purge') }}` daily, and never print or log a plain-text token after it was shown to the user once.
