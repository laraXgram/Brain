# Upgrade Guide

- [Upgrading To 4.0 From 3.x](#upgrade-4.0)

<a name="upgrade-4.0"></a>
## Upgrading To 4.0 From 3.x

#### Estimated Upgrade Time: 15 Minutes

> [!NOTE]
> LaraGram 4 is a largely **additive** release with minimal breaking changes to the bot layer. Most of
> the new work is in brand-new components (MTProto, Luna, the web layer). Bumping the dependency plus
> the steps below is usually all that is required.

<a name="php-8.5-required"></a>
### PHP 8.5 Required

**Likelihood Of Impact: High**

LaraGram 4 requires PHP 8.5. Update your environment before upgrading.

<a name="updating-dependencies"></a>
### Updating Dependencies

**Likelihood Of Impact: High**

In `composer.json`, set `laraxgram/laragram` to `^4.0` (and update any first-party packages to their
4.x releases), then run:

```shell
composer update
```

<a name="post-upgrade-steps"></a>
### Post-Upgrade Steps

**Likelihood Of Impact: High**

The new web layer needs a few directories and config files that did not exist in 3.x. Apply the ones
relevant to your application:

**0. Update** `index.php`. Replace the contents of the `public/index.php` file completely with the following value:

```php
<?php

$serverPath = __DIR__."/../vendor/laraxgram/core/src/Foundation/resources/server.php";

$rawInput = file_get_contents('php://input');
$content = json_decode($rawInput, true);

$isBotUpdate = json_last_error() === JSON_ERROR_NONE
    && is_array($content)
    && array_key_exists('update_id', $content);

if ($isBotUpdate) {
    $server = escapeshellarg(json_encode($_SERVER));
    $inputs = escapeshellarg($rawInput);

    $log = "/dev/null";

    popen("php \"{$serverPath}\" {$inputs} {$server} >> {$log} 2>&1 &", "r");
} else {
    require_once $serverPath;
}
```

**1. Migrate the `sessions` table:**

```php
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

**2. Create the storage cache directories.** Views are compiled to `storage/framework` and sessions save to `storage/sessions`:

```shell
mkdir -p storage/framework/{views,sessions}
```

**3. Enable web routing (if used).** To serve HTTP routes, register them in `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
)
```

**4. Add the `routes` and `resources` folders as needed.** Create only what you use — e.g.
`routes/web.php`, `resources/views`, `resources/css`, `resources/js`.

**5. Publish the new config files** from vendor (for example `session.php`):

```shell
php laragram vendor:publish
```

**6. Update the LaraGram installer** so `laragram new` scaffolds 4.x projects:

```shell
composer global require laraxgram/installer
```

<a name="redirects"></a>
### Redirects Documentation

**Likelihood Of Impact: Low**

The standalone `redirects` page is merged into [HTTP responses](/v4/http-responses). Documentation
change only — the helpers are unchanged.

<a name="whats-new"></a>
## Adopting New Features (Optional)

None of this is required to upgrade:

<div class="content-list" markdown="1">

- [**MTProto & User Clients**](/v4/mtproto) — full Telegram client, no Bot API limits.
- [**Luna**](/v4/luna) — React / Vue / Svelte frontends and [Mini Apps](/v4/luna-tma).
- **Web layer** — [routing](/v4/routing), [HTTP requests](/v4/http-requests) /
  [responses](/v4/http-responses), [HTTP client](/v4/http-client), [Blade](/v4/blade),
  [views](/v4/views), [Vite](/v4/vite), [sessions](/v4/session).
- [**Conversations**](/v4/conversations) — declarative multi-step Q&A flows.
- [**API Resources**](/v4/eloquent-resources), [**pagination**](/v4/pagination),
  [**Precognition**](/v4/precognition).

</div>
