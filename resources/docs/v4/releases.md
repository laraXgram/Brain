# Release Notes

<a name="versioning-scheme"></a>

## Versioning Scheme

LaraGram and its other first-party packages follow [Semantic Versioning](https://semver.org). Major framework releases
are released every year (~Q1), while minor and patch releases may be released as often as every week. Minor and patch
releases should **never** contain breaking changes.

When referencing the LaraGram framework or its components from your application or package, you should always use a
version constraint such as `^4.0`, since major releases of LaraGram do include breaking changes. However, we strive to
always ensure you may update to a new major release in one day or less.

<a name="named-arguments"></a>

#### Named Arguments

[Named arguments](https://www.php.net/manual/en/functions.arguments.php#functions.named-arguments) are not covered by
LaraGram's backwards compatibility guidelines. We may choose to rename function arguments when necessary in order to
improve the LaraGram codebase. Therefore, using named arguments when calling LaraGram methods should be done cautiously
and with the understanding that the parameter names may change in the future.

<a name="support-policy"></a>

## Support Policy

For all LaraGram releases, bug fixes are provided for 18 months and security fixes are provided for 2 years. For all
additional libraries, only the latest major release receives bug fixes. In addition, please review the database
versions [supported by LaraGram](/v4/database#introduction).

<div class="overflow-auto">

| Version | PHP (*)   | Release         | Bug Fixes Until    | Security Fixes Until | Status                                 |
|---------|-----------|-----------------|--------------------|----------------------|----------------------------------------|
| 3       | 8.2 - 8.5 | July 17th, 2025 | February 1th, 2027 | July 1th, 2027       | <Badge type="tip" text="Stable" />     |
| 4       | 8.5       | July 16th, 2026 | February 1th, 2028 | July 1th, 2028       | <Badge type="tip" text="Stable " />    |
| 5       | 8.5       | Q2 2027         | Q4 2028            | Q2 2028              | <Badge type="info" text="Upcoming " /> |

</div>

(*) Supported PHP versions

<a name="laragram-4"></a>

## LaraGram 4

LaraGram 4 is the largest release in the framework's history. Where LaraGram 3 focused on being an
excellent Bot API framework, LaraGram 4 grows into a **full-stack framework**: it keeps everything you
already use to build Telegram bots and adds a complete web layer, first-class **Telegram Mini App**
support through **Luna**, and native **MTProto** user-client capabilities — all while preserving a
familiar, LaraGram-flavored developer experience.

Despite the scope of the new features, LaraGram 4 introduces **minimal breaking changes**. The vast
majority of the release is additive, so most applications can upgrade with little more than a
dependency bump. See the [upgrade guide](/v4/upgrade) for the full details.

<a name="php-8-3"></a>

### PHP 8.3

LaraGram 4 requires a minimum PHP version of 8.3 (previously 8.2). The supported range for this
release is PHP 8.3 through 8.5.

<a name="mtproto"></a>

### MTProto & User Clients

The headline feature of LaraGram 4 is native **MTProto** support. MTProto is Telegram's own transport
and cryptographic protocol; connecting through it (rather than the Bot API) means your application
*is* a Telegram client, with the same capabilities a phone or desktop app has.

This unlocks an entirely new class of applications:

<div class="content-list" markdown="1">

- **Full account access** — log in with a phone number and act as a user (a "userbot"), not just a bot.
- **No Bot API limits** — download files larger than 20&nbsp;MB, read full chat history, list every
  dialog, and call methods the Bot API doesn't offer.
- **Rich update stream** — receive typing indicators, read receipts, user status, reactions, stories,
  and hundreds of other update types.
- **Multiple accounts in one process** — run a fleet of user and bot sessions side by side.

</div>

Both user clients (phone + 2FA / QR login) and bot clients (bot token over MTProto) are driven through
the same high-level API, so the listeners and requests you write are identical either way. The MTProto
documentation is split across [Getting Started](/v4/mtproto),
[Authentication](/v4/mtproto-authentication), [Listening](/v4/mtproto-listening),
[Requests](/v4/mtproto-requests), [Chats](/v4/mtproto-chats),
[Media](/v4/mtproto-media), [Features](/v4/mtproto-features), and
[Configuration](/v4/mtproto-configuration).

<a name="luna"></a>

### Luna: Full-Stack Frontends & Telegram Mini Apps

**Luna** is the official adapter that lets you build modern, single-page style frontends for your
LaraGram application using React, Vue, or Svelte — without building an API, without client-side
routing configuration, and without losing the server-side conveniences you already know (routing,
controllers, middleware, validation, and authorization). You write your routes and controllers exactly
as before; instead of returning a Blade view or JSON, a controller returns a Luna *response* naming a
JavaScript page component and the props it should receive.

Luna also makes **Telegram Mini Apps (TMA)** a first-class citizen. A Mini App is a Luna SPA that
additionally speaks the Telegram WebApp protocol: it validates signed init data, shares the Telegram
identity with the frontend, adopts Telegram's theme, and drives native UI such as the back button and
MainButton. The full device-feature surface — CloudStorage, biometrics, location, sensors, popups,
sharing, and invoices — is available as well.

Luna is documented across [Luna](/v4/luna), [Pages](/v4/luna-pages),
[Routing](/v4/luna-routing), [Forms](/v4/luna-forms), [Frontend](/v4/luna-frontend),
[Telegram Mini Apps](/v4/luna-tma), and [Telegram Features](/v4/luna-tma-features).

<a name="web-layer"></a>

### A Complete Web Layer

To support Luna and Mini Apps, LaraGram 4 ships a full HTTP and frontend stack. Much of this will feel
immediately familiar to LaraGram (and Laravel) developers:

<div class="content-list" markdown="1">

- [**Routing**](/v4/routing) and [**URL generation**](/v4/urls) for defining and building
  web endpoints.
- [**HTTP requests**](/v4/http-requests) and [**responses**](/v4/http-responses), plus an
  expressive [**HTTP client**](/v4/http-client) built on Guzzle for outgoing requests.
- [**Blade templates**](/v4/blade) and [**views**](/v4/views) for server-rendered HTML.
- [**Asset bundling with Vite**](/v4/vite) via an official plugin and Blade directive.
- [**HTTP sessions**](/v4/session) with database, Redis, Memcached, and other backends.
- A [**Frontend**](/v4/frontend) guide tying the PHP and JavaScript approaches together, and
  [**starter kits**](/v4/starter-kits) to scaffold authenticated applications quickly.

</div>

<a name="conversations"></a>

### Conversations

Many bots need to ask a user a series of questions — a registration flow, a support ticket, an order
form — and remember every answer. The new [**Conversation**](/v4/conversations) component gives you
a clean, declarative way to build these multi-step question-and-answer flows. You declare the
questions; LaraGram sends them one by one, validates each reply, collects the answers, and hands them
back when the flow completes. State is persisted automatically between updates, so a conversation
survives across the many separate requests a webhook bot receives.

<a name="api-resources-pagination"></a>

### API Resources, Pagination & Precognition

LaraGram 4 rounds out the data and validation layer with several additions ported from the wider
ecosystem:

<div class="content-list" markdown="1">

- [**Eloquent API Resources**](/v4/eloquent-resources) provide an expressive transformation layer
  between your models and JSON responses.
- [**Pagination**](/v4/pagination) integrates with the query builder and Eloquent, with Tailwind
  and Bootstrap views out of the box.
- [**Precognition**](/v4/precognition) lets you anticipate the outcome of a future HTTP request —
  most notably to provide live, frontend validation without duplicating your backend rules. Precognition
  support is built directly into Luna Forms.

</div>

<a name="minimal-breaking-changes"></a>
