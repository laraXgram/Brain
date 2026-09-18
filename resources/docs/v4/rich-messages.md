# Rich Messages

<a name="introduction"></a>
## Introduction

Telegram's `sendRichMessage` method accepts a small HTML document instead of a line of text, so a single message may contain headings, tables, lists, quotations, collapsible sections, formulas, maps, media and buttons. Writing that document by hand is tedious: media has to be registered in a separate array and referenced by a `tg://` link, captions need a five tag sandwich, and every button carries a `type` attribute that repeats what its other attributes already say.

LaraGram gives you a simplified markup for rich messages that you write directly in a [Temple8 template](/v4/temple8):

```blade
@rich
    <h1>Daily Report</h1>

    <p>Hello <b>{{{ $user->first_name }}}</b>, here is today's summary.</p>

    @richPhoto($chart, caption: 'Sales', credit: 'Sales team')

    @richTable($rows, headers: ['Product', 'Units'], bordered: true)

    <row>
        <btn callback="report:full" style="primary">Full report</btn>
    </row>
@endrich
```

Rendering that template sends a `sendRichMessage` call to the current chat, with the markup expanded into the HTML Telegram expects and every file registered in the message's `media` array.

The markup has three layers, and each one is an escape hatch for the layer above it:

<div class="content-list" markdown="1">

- **Directives** such as `@richPhoto` and `@richTable`, for the things that need PHP: media, arrays and options.
- **Short tags** such as `<btn>`, `<spoiler>` and `<math>`, which are sugar for Telegram's `tg-*` tags.
- **Raw Telegram HTML**, which always passes through untouched.

</div>

> [!NOTE]
> Rich messages require a bot that can call `sendRichMessage`. Everything on this page also works with `sendRichMessageDraft` through the [`@richDraft`](#drafts) directive.

<a name="writing-a-rich-message"></a>
## Writing a Rich Message

A rich message is written between the `@rich` and `@endrich` directives. Everything between them is collected as markup, so the usual Temple8 syntax — echoes, loops, conditions, includes and components — works exactly as it does anywhere else:

```blade
{{-- app/templates/report.t8.php --}}

@rich
    <h1>Daily Report</h1>

    <p>Hello <b>{{{ $user->first_name }}}</b> 👋</p>

    <hr/>

    <p>Nothing broke. <spoiler>Yet.</spoiler></p>
@endrich
```

The template chooses its own method as usual, so a `@method` directive still wins; without one, a `@rich` block sends `sendRichMessage`. The recipient is the current chat unless a `@chat_id` input says otherwise, exactly like any other template:

```blade
@chat_id($group->chat_id)

@rich
    <p>Posted to another chat.</p>
@endrich
```

<a name="block-options"></a>
### Block Options

The `@rich` directive accepts the options Telegram allows for the message itself, as named arguments:

```blade
@rich(rtl: true, skipEntityDetection: true)
    <p>سلام دنیا</p>
@endrich
```

The array form is accepted as well, using Telegram's own names:

```blade
@rich(['is_rtl' => true, 'skip_entity_detection' => true])
```

Two more options control how LaraGram treats your markup rather than how Telegram renders it. Both are discussed below: `strict` in [strict mode](#strict-mode) and `pretty` in [whitespace](#whitespace).

<a name="drafts"></a>
### Drafts

A draft message is written with `@richDraft`, which sends `sendRichMessageDraft` and unlocks the tags that are only valid in a draft, such as `<thinking>`:

```blade
@richDraft
    <thinking>Reading the changelog…</thinking>
@endrichDraft
```

Using a draft-only tag inside a plain `@rich` block raises an exception instead of being sent.

<a name="text-and-structure"></a>
## Text and Structure

Every tag Telegram documents works verbatim; no directive is needed for text:

```blade
@rich
    <h1>Heading 1</h1>
    <h4>Smaller heading</h4>

    <p><b>bold</b> <i>italic</i> <u>underline</u> <s>strike</s>
       <code>inline</code> <mark>marked</mark> <sub>sub</sub> <sup>sup</sup></p>

    <pre><code class="language-php">echo 'hello';</code></pre>

    <blockquote>
        To be or not to be<cite>Shakespeare</cite>
    </blockquote>

    <blockquote expandable>
        A very long quotation…<cite>Someone</cite>
    </blockquote>

    <aside>A pull quote, centered<cite>The Author</cite></aside>

    <details open>
        <summary>Show details</summary>
        <p>Hidden content with <b>full rich formatting</b>.</p>
    </details>

    <footer>Generated automatically.</footer>
@endrich
```

Links, mentions and in-message anchors are plain `<a>` tags, and `<ref>` marks the target of a footnote:

```blade
<a href="https://t.me/laraxgram">website</a>
<a href="tg://user?id=777000">mention</a>

<a name="chapter-2"></a>
<a href="#chapter-2">jump to chapter 2</a>

<ref name="note-1">footnoted text</ref>
<a href="#note-1">see note</a>
```

<a name="short-tags"></a>
### Short Tags

The following tags are shorthand for Telegram's `tg-*` tags. None of the short names collide with a tag Telegram already supports, and writing the `tg-*` form directly still works:

| Simplified | Telegram |
|---|---|
| `<spoiler>x</spoiler>` | `<tg-spoiler>x</tg-spoiler>` |
| `<emoji id="536…">👍</emoji>` | `<tg-emoji emoji-id="536…">👍</tg-emoji>` |
| `<time unix="1647531900" format="wDT">tomorrow</time>` | `<tg-time …>` |
| `<math>x^2 + y^2</math>` | `<tg-math>x^2 + y^2</tg-math>` |
| `<math block>E = mc^2</math>` | `<tg-math-block>E = mc^2</tg-math-block>` |
| `<ref name="n1">x</ref>` | `<tg-reference name="n1">x</tg-reference>` |
| `<doc src="…"/>` | `<tg-document src="…"></tg-document>` |
| `<map lat="35.7" lon="51.4" zoom="14"/>` | `<tg-map lat="35.7" long="51.4" zoom="14"/>` |
| `<collage>…</collage>` | `<tg-collage>…</tg-collage>` |
| `<slideshow>…</slideshow>` | `<tg-slideshow>…</tg-slideshow>` |
| `<thinking>…</thinking>` | `<tg-thinking>…</tg-thinking>` |
| `<row>…</row>` | `<tg-button-row>…</tg-button-row>` |
| `<btn …>…</btn>` | `<tg-button type="…" …>…</tg-button>` |

> [!NOTE]
> Telegram's map attribute is `long`. The `<map>` tag accepts both `lon` and `long`.

<a name="buttons"></a>
## Buttons

Buttons live in a `<row>`, and the attribute you give a `<btn>` determines its type, so the `type` attribute of the raw Telegram tag is never written by hand:

```blade
@rich
    <p>Pick one:</p>

    <row align="center">
        <btn url="https://t.me/laraxgram" style="success">Website</btn>
        <btn callback="menu:open" style="primary">Menu</btn>
    </row>

    <row>
        <btn webapp="https://example.com/app" style="danger">Mini App</btn>
        <btn copy="TOKEN-123">Copy token</btn>
    </row>
@endrich
```

| Attribute | Button type | Telegram attribute |
|---|---|---|
| `url="…"` | `url` | `url` |
| `callback="…"` | `callback_data` | `data` |
| `webapp="…"` | `web_app` | `url` |
| `login="…"` | `login_url` | `url` (+ `forward-text`, `request-write-access`) |
| `inline="…"` | `switch_inline_query` | `query` |
| `inline-here="…"` | `switch_inline_query_current_chat` | `query` |
| `inline-chat="…"` | `switch_inline_query_chosen_chat` | `query` (+ `users` `bots` `groups` `channels`) |
| `copy="…"` | `copy_text` | `text` |
| `disabled` | `disabled` | — |

The `style` attribute passes through as written (`primary`, `success`, `danger`, `link`). A row holds between one and eight buttons, and each button carries exactly one type attribute; breaking either rule raises an exception while the message is being built rather than at Telegram.

Buttons may also sit inline inside a paragraph:

```blade
<p>Confirm? <btn callback="yes">Yes</btn> <btn callback="no">No</btn></p>
```

<a name="media"></a>
## Media

Media that Telegram can fetch by URL needs no directive at all:

```blade
<img src="https://example.com/cover.jpg"/>
<video src="https://example.com/clip.mp4"></video>
<audio src="https://example.com/song.mp3"></audio>
<doc src="https://example.com/report.zip"/>
```

Captions are attributes, so the `<figure>` / `<figcaption>` / `<cite>` sandwich Telegram expects is written for you:

```blade
<img src="https://example.com/cover.jpg" caption="On the roof" credit="NASA" spoiler/>
```

Write `<figure>` yourself when the caption needs rich formatting of its own.

<a name="media-directives"></a>
### Media Directives

A `file_id`, a local file or an `InputFile` cannot be referenced by an attribute: Telegram wants it in the message's `media` array, linked from the markup by a `tg://` link. The media directives do both:

```blade
@rich
    @richPhoto($cover, caption: 'Cover', credit: 'NASA', spoiler: true)

    <p>And a video:</p>
    @richVideo(storage_path('app/clip.mp4'), caption: 'The clip')

    @richDocument($invoice, caption: 'Invoice #42')
    @richAudio($track)
    @richVoice($voiceNote)
    @richAnimation($gif)
@endrich
```

`@richPhoto($cover)` writes an `<img src="tg://photo?id=m1"/>` where the directive stands and registers the file in the payload:

```json
{"id": "m1", "media": {"type": "photo", "media": "AgACAgQAAx…"}}
```

Ids are allocated `m1`, `m2`, … per message. Each directive takes its file positionally and everything else by name, because the media type itself travels as a named argument:

```blade
@richPhoto($file, caption: 'Cover')      {{-- correct --}}
@richPhoto($file, 'Cover')               {{-- wrong: collides with the media type --}}
```

To place a file by hand, `@richMedia` registers it and echoes only its link, which may be dropped straight into an attribute:

```blade
<figure>
    <img src="@richMedia($cover, type: 'photo', id: 'cover')"/>
    <figcaption>Hand-placed, with a <b>rich</b> caption<cite>NASA</cite></figcaption>
</figure>
```

<a name="collages-and-maps"></a>
### Collages, Slideshows and Maps

Grouped media is written as markup, with the directives inside it:

```blade
<collage>
    @richPhoto($a)
    @richPhoto($b)
    <img src="https://example.com/c.jpg"/>
</collage>

<slideshow>
    @richPhoto($a)
    @richVideo($b)
    <figcaption>Trip photos</figcaption>
</slideshow>

<map lat="35.6892" lon="51.3890" zoom="14" width="600" height="400"/>
```

<a name="lists-and-tables"></a>
## Lists and Tables

Static lists and tables are plain markup:

```blade
<ul>
    <li>first</li>
    <li>second</li>
</ul>

<ol start="3" type="a" reversed>
    <li>third</li>
</ol>

<table bordered striped compact>
    <caption>Q3 sales</caption>
    <tr><th>Product</th><th>Units</th><th>Revenue</th></tr>
    <tr><td align="left">Widget</td><td align="center">120</td><td align="right">$4,200</td></tr>
</table>
```

When the content comes from PHP, the directives build the same markup from an array and escape every value:

```blade
@richList($tags)
@richList($steps, ordered: true, type: 'a', start: 3)
@richChecklist(['Write the docs' => true, 'Ship it' => false])

@richTable($rows,
    headers: ['Product', 'Units', 'Revenue'],
    align:   ['left', 'center', 'right'],
    bordered: true, striped: true, compact: true,
    caption: 'Q3 sales')
```

`@richList` and `@richTable` escape their items, so they cannot carry formatting. When the cells or items need markup of their own, write the markup form and loop over it, as described next.

<a name="loops-and-conditions"></a>
## Loops, Conditions and Composition

A rich block is ordinary template output, so every Temple8 directive works inside it and lands exactly where it is written. Loops and conditions may wrap markup, may sit inside a table or a list, and may even choose between whole sections of the message:

```blade
@rich
    <h2>Orders</h2>

    @if($orders->isEmpty())
        <p>No orders today.</p>
    @else
        <table bordered striped compact>
            <caption>Q3 sales</caption>
            <tr><th>Product</th><th>Units</th></tr>

            @foreach($orders as $order)
                <tr>
                    <td>{{{ $order->product }}}</td>
                    <td align="right">{{ $order->units }}</td>
                </tr>
            @endforeach
        </table>

        <ul>
            @foreach($orders as $order)
                <li><b>{{{ $order->product }}}</b> — {{{ $order->total }}}</li>
            @endforeach
        </ul>
    @endif

    <footer>Generated at <time unix="{{ time() }}" format="DT">now</time></footer>
@endrich
```

The rich directives may be used in a loop or a condition as well. Each pass writes its markup where the directive stands and registers its own media:

```blade
@rich
    <collage>
        @foreach($photos as $photo)
            @richPhoto($photo->file_id, caption: $photo->title)
        @endforeach
    </collage>

    @if($summary)
        @richTable($summary, headers: ['Product', 'Units'])
    @endif
@endrich
```

<a name="rich-message-partials"></a>
### Partials, Components and Layouts

A rich message may be assembled from more than one file. An `@include`, a [component](/v4/temple8#components) and a [layout](/v4/temple8#building-layouts) each render in a scope of their own, and their output is placed into the message where they were written — including any rich directives they use:

```blade
{{-- app/templates/report.t8.php --}}

@rich
    <h2>Gallery</h2>

    @foreach($photos as $photo)
        <x-photo-card :photo="$photo"/>
    @endforeach
@endrich
```

```blade
{{-- app/templates/components/photo-card.t8.php --}}
<!-- !component! -->

<p><b>{{{ $photo->title }}}</b></p>

@richPhoto($photo->file_id, caption: $photo->title)
```

Two rules keep this predictable:

<div class="content-list" markdown="1">

- Every template that is included by another one must begin with the `<!-- !component! -->` marker, so it contributes markup instead of sending a message of its own. See [disable request](/v4/temple8#disable-request).
- The `@rich` block itself belongs to the template that sends the message. A partial only writes markup and rich directives; it does not open a block of its own.

</div>

When a layout sends the message, the `@rich` block lives in the layout and the child fills its sections:

```blade
{{-- app/templates/layouts/report.t8.php --}}

@rich
    <h1>@yield('title')</h1>

    @section('body')
        <p>Nothing to report.</p>
    @show

    <footer>{{{ config('app.name') }}}</footer>
@endrich
```

```blade
{{-- app/templates/reports/daily.t8.php --}}

@extends('layouts.report')

@section('title', 'Daily report')

@section('body')
    @richTable($rows, headers: ['Product', 'Units'])
@endsection
```

Using a rich directive where no block is open raises a `RichMessageException` naming the directive, which is usually a partial that was rendered on its own instead of from inside a `@rich` block.

<a name="escaping"></a>
## Escaping User Input

Temple8 echoes <code v-pre>{{ }}</code> **raw** and <code v-pre>{{{ }}}</code> **escaped** — the opposite of Blade. That convention is unchanged inside a rich block:

```blade
<p>{{{ $user->first_name }}}</p>   {{-- safe: markup in the name becomes text --}}
<p>{{ $user->first_name }}</p>     {{-- raw: a name containing <b> becomes bold --}}
```

Use <code v-pre>{{{ }}}</code> for anything a user can influence. Two things limit the damage of a slip: every directive that takes PHP data — `@richTable`, `@richList`, `@richChecklist` and the `caption` and `credit` of the media directives — escapes its input already, and [strict mode](#strict-mode) rejects tags Telegram does not document. Neither covers a value that injects a *supported* tag, which is why <code v-pre>{{{ }}}</code> is the rule and not the suggestion.

You may also escape a value explicitly:

```php
use LaraGram\Template\Rich\RichMessage;

$safe = RichMessage::escape($value);
```

<a name="whitespace"></a>
## Whitespace

Templates are indented and Telegram treats text nodes literally, so the markup is normalised before it is sent: whitespace between block level tags is removed, runs of whitespace inside inline content collapse to a single space, and the content of `<pre>`, `<code>`, `<math>` and `<tg-math-block>` is preserved byte for byte.

Pass `pretty: false` to send the buffer exactly as the template produced it:

```blade
@rich(pretty: false)
    <pre>  indented exactly like this  </pre>
@endrich
```

<a name="strict-mode"></a>
## Strict Mode

By default the markup is validated while the message is built. Tags Telegram does not document, unclosed tags, mismatched closing tags and draft-only tags outside a draft raise an exception instead of reaching Telegram. Pass `strict: false` to let unknown tags through untouched:

```blade
@rich(strict: false)
    <custom-tag>…</custom-tag>
@endrich
```

Telegram's own limits are enforced at the same time: 32,768 characters of text, 500 blocks, 16 levels of nesting, 50 media attachments, 20 table columns and 1–8 buttons per row. Exceeding one raises a `RichMessageLimitException` naming the limit.

All of these exceptions extend `LaraGram\Template\Rich\Exceptions\RichMessageException`:

| Exception | Raised when |
|---|---|
| `UnsupportedRichTagException` | A tag Telegram does not support is used in strict mode |
| `InvalidRichMarkupException` | The markup is malformed, or a button or row is invalid |
| `RichMessageLimitException` | A Telegram limit is exceeded |

<a name="building-rich-messages-in-php"></a>
## Building Rich Messages in PHP

Rich messages are not tied to templates. The `RichMessage` class builds the same payload from PHP, which is convenient in jobs, commands and tests:

```php
use LaraGram\Template\Rich\RichMessage;

$message = RichMessage::make()
    ->rtl()
    ->append('<h1>Daily Report</h1>')
    ->photo($chart, caption: 'Sales')
    ->table($rows, headers: ['Product', 'Units'], bordered: true)
    ->list(['First', 'Second']);

$request->sendRichMessage($chatId, $message->toArray());
```

`append()` adds markup, `text()` adds escaped text, and `photo()`, `video()`, `animation()`, `audio()`, `voice()`, `document()`, `list()`, `checklist()` and `table()` mirror the directives. Each of those has a `build…()` counterpart — `buildTable()`, `buildList()`, `buildChecklist()` and `buildAttachment()` — that returns the markup instead of appending it, which is what the directives use to write themselves into the surrounding markup.

<a name="broadcasting-rich-messages"></a>
## Broadcasting Rich Messages

A template that builds a rich message may be [broadcast](/v4/broadcasting#templates) to an entire audience, and it is rendered once per recipient, so loops, conditions, components and translations all see that recipient:

```php
use LaraGram\Support\Facades\Broadcast;

Broadcast::users()
    ->language('fa')
    ->template('reports.daily', ['rows' => $rows])
    ->localized()
    ->queue();
```
