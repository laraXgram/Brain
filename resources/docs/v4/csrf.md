# CSRF Protection

- [Introduction](#csrf-introduction)
- [Preventing CSRF Requests](#preventing-csrf-requests)
    - [Origin Verification](#origin-verification)
    - [Excluding URIs](#csrf-excluding-uris)
- [X-CSRF-Token](#csrf-x-csrf-token)
- [X-XSRF-Token](#csrf-x-xsrf-token)
- [Bots, Webhooks and Mini Apps](#bots-and-mini-apps)

<a name="csrf-introduction"></a>
## Introduction

Cross-site request forgeries are a type of malicious exploit whereby unauthorized commands are performed on behalf of an authenticated user. Thankfully, LaraGram makes it easy to protect the [web side](/v4/routing) of your application from cross-site request forgery (CSRF) attacks.

<a name="csrf-explanation"></a>
#### An Explanation of the Vulnerability

In case you're not familiar with cross-site request forgeries, let's discuss an example of how this vulnerability can be exploited. Imagine your application has a `/user/email` route that accepts a `POST` request to change the authenticated user's email address. Most likely, this route expects an `email` input field to contain the email address the user would like to begin using.

Without CSRF protection, a malicious website could create an HTML form that points to your application's `/user/email` route and submits the malicious user's own email address:

```blade
<form action="https://your-application.com/user/email" method="POST">
    <input type="email" value="malicious-email@example.com">
</form>

<script>
    document.forms[0].submit();
</script>
```

If the malicious website automatically submits the form when the page is loaded, the malicious user only needs to lure an unsuspecting user of your application to visit their website and their email address will be changed in your application.

To prevent this vulnerability, we need to inspect every incoming `POST`, `PUT`, `PATCH`, `DELETE`, or `QUERY` request for a secret session value that the malicious application is unable to access.

<a name="preventing-csrf-requests"></a>
## Preventing CSRF Requests

The `LaraGram\Foundation\Http\Middleware\PreventRequestForgery` [middleware](/v4/middleware), which is included in the `web` middleware group by default, protects your application from cross-site request forgeries using a two-layer approach.

First, the middleware checks the browser's `Sec-Fetch-Site` header. Modern browsers automatically set this header on every request, indicating whether it originated from the same origin, the same site, or a cross-site source. If the header indicates the request came from the same origin, the request is allowed immediately without any token verification.

If origin verification does not pass — for example, because the request comes from an older browser that doesn't send the `Sec-Fetch-Site` header or because the connection is not secure — the middleware falls back to traditional CSRF token validation.

LaraGram automatically generates a CSRF "token" for each active [user session](/v4/session) managed by the application. This token is used to verify that the authenticated user is the person actually making the requests to the application. Since this token is stored in the user's session and changes each time the session is regenerated, a malicious application is unable to access it.

The current session's CSRF token can be accessed via the request's session or via the `csrf_token` helper function:

```php
use LaraGram\Http\Request;
use LaraGram\Support\Facades\Route;

Route::get('/token', function (Request $request) {
    $token = $request->session()->token();

    $token = csrf_token();

    // ...
});
```

Anytime you define a "POST", "PUT", "PATCH", or "DELETE" HTML form in your application, you should include a hidden CSRF `_token` field in the form so that the CSRF protection middleware can validate the request. For convenience, you may use the `@csrf` [Blade](/v4/blade) directive to generate the hidden token input field:

```blade
<form method="POST" action="/profile">
    @csrf

    <!-- Equivalent to... -->
    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
</form>
```

The `csrf_field` helper produces the same input from PHP:

```php
echo csrf_field();
```

<a name="origin-verification"></a>
### Origin Verification

As discussed above, LaraGram's request forgery middleware first checks the `Sec-Fetch-Site` header to determine if the request is from the same origin. By default, if this check does not pass, the middleware falls back to CSRF token validation.

However, if you would like to rely solely on origin verification and disable the CSRF token fallback entirely, you may do so using the `preventRequestForgery` method in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->preventRequestForgery(originOnly: true);
})
```

When using origin-only mode, requests that fail origin verification will receive a `403` HTTP response instead of the `419` response typically associated with CSRF token mismatches.

> [!WARNING]
> The `Sec-Fetch-Site` header is only sent by browsers over secure (HTTPS) connections. If your application is not served over HTTPS, origin verification will not be available and the middleware will fall back to CSRF token validation.

If your application needs to accept requests from subdomains (for example, `dashboard.example.com` accepting requests from `example.com`), you may allow same-site requests in addition to same-origin requests:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->preventRequestForgery(allowSameSite: true);
})
```

<a name="csrf-excluding-uris"></a>
### Excluding URIs From CSRF Protection

Sometimes you may wish to exclude a set of URIs from CSRF protection. For example, if a payment provider calls a webhook of yours, that provider has no way of knowing which CSRF token to send.

Typically, you should place these kinds of routes outside of the `web` middleware group that LaraGram applies to all routes in the `routes/web.php` file. However, you may also exclude specific routes by providing their URIs to the `preventRequestForgery` method in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->preventRequestForgery(except: [
        'payments/*',
        'http://example.com/foo/bar',
        'http://example.com/foo/*',
    ]);
})
```

<a name="csrf-x-csrf-token"></a>
## X-CSRF-TOKEN

In addition to checking for the CSRF token as a POST parameter, the `PreventRequestForgery` middleware will also check for the `X-CSRF-TOKEN` request header. You could, for example, store the token in an HTML `meta` tag:

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

Then, you can instruct a library like jQuery to automatically add the token to all request headers. This provides simple, convenient CSRF protection for your AJAX based applications using legacy JavaScript technology:

```js
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});
```

<a name="csrf-x-xsrf-token"></a>
## X-XSRF-TOKEN

LaraGram stores the current CSRF token in an encrypted `XSRF-TOKEN` cookie that is included with each response generated by the framework. You can use the cookie value to set the `X-XSRF-TOKEN` request header.

This cookie is primarily sent as a developer convenience since some JavaScript frameworks and libraries, like Angular and Axios, automatically place its value in the `X-XSRF-TOKEN` header on same-origin requests.

<a name="bots-and-mini-apps"></a>
## Bots, Webhooks and Mini Apps

CSRF protection is a browser concern, so it only applies to the HTTP side of your application:

<div class="content-list" markdown="1">

- **Telegram updates** reach your application through the bot kernel, not through your web routes, so the webhook Telegram calls is never subject to CSRF protection. It is authenticated by the connection's `secret_token` instead — see [multiple bot connections](/v4/requests#multi-connections).
- **Telegram Mini Apps** authenticate with Telegram's signed init data rather than a session cookie. [Luna](/v4/luna-tma) validates that signature for you; when a Mini App posts to a route in the `web` group, the usual CSRF token still applies, and Luna's client reads the `XSRF-TOKEN` cookie and sends it back as the `X-XSRF-TOKEN` header on every [form](/v4/luna-forms) request.
- **Your own API routes** (`routes/api.php`) are stateless and are not part of the `web` group, so they carry no CSRF token. Authenticate them with a token guard instead.

</div>
