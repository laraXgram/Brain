# Contribution Guide

<a name="bug-reports"></a>
## Bug Reports

To encourage active collaboration, LaraGram strongly encourages pull requests, not just bug reports. Pull requests will only be reviewed when marked as "ready for review" (not in the "draft" state) and all tests for new features are passing. Lingering, non-active pull requests left in the "draft" state will be closed after a few days.

However, if you file a bug report, your issue should contain a title and a clear description of the issue. You should also include as much relevant information as possible and a code sample that demonstrates the issue. The goal of a bug report is to make it easy for yourself - and others - to replicate the bug and develop a fix.

Remember, bug reports are created in the hope that others with the same problem will be able to collaborate with you on solving it. Do not expect that the bug report will automatically see any activity or that others will jump to fix it. Creating a bug report serves to help yourself and others start on the path of fixing the problem. If you want to chip in, you can help out by fixing [any bugs listed in our issue trackers](https://github.com/issues?q=is%3Aopen+is%3Aissue+label%3Abug+user%3Alaragram). You must be authenticated with GitHub to view all of LaraGram's issues.

If you notice improper DocBlock, PHPStan, or IDE warnings while using LaraGram, do not create a GitHub issue. Instead, please submit a pull request to fix the problem.

The LaraGram source code is managed on GitHub, and there are repositories for each of the LaraGram projects:

<div class="content-list" markdown="1">

- [LaraGram Application](https://github.com/laraxgram/laragram)
- [LaraGram Documentation](https://github.com/laraxgram/docs)
- [LaraGram Surge](https://github.com/laraxgram/surge)
- [LaraGram Tempora](https://github.com/laraxgram/tempora)


</div>

<a name="support-questions"></a>
## Support Questions

LaraGram's GitHub issue trackers are not intended to provide LaraGram help or support. Instead, use one of the following channels:

<div class="content-list" markdown="1">

- [GitHub Discussions](https://github.com/laraxgram/core/discussions)
- [StackOverflow](https://stackoverflow.com/questions/tagged/laragram)
- [Telegram Chat](https://t.me/LaraGramChat)

</div>

<a name="core-development-discussion"></a>
## Core Development Discussion

You may propose new features or improvements of existing LaraGram behavior in the LaraGram core repository's [GitHub discussion board](https://github.com/laragram/core/discussions). If you propose a new feature, please be willing to implement at least some of the code that would be needed to complete the feature.

<a name="which-branch"></a>
## Which Branch?

**All** bug fixes should be sent to the latest version that supports bug fixes (currently `3.x`). Bug fixes should **never** be sent to the `master` branch unless they fix features that exist only in the upcoming release.

**Minor** features that are **fully backward compatible** with the current release may be sent to the latest stable branch (currently `3.x`).

**Major** new features or features with breaking changes should always be sent to the `master` branch, which contains the upcoming release.

<a name="security-vulnerabilities"></a>
## Security Vulnerabilities

If you discover a security vulnerability within LaraGram, please send an email to LaraXGram at <a href="mailto:laraxgram@gmail.com">laraxgram@gmail.com</a>. All security vulnerabilities will be promptly addressed.

<a name="coding-style"></a>
## Coding Style

LaraGram follows the [PSR-2](https://github.com/php-fig/fig-standards/blob/v4/accepted/PSR-2-coding-style-guide.md) coding standard and the [PSR-4](https://github.com/php-fig/fig-standards/blob/v4/accepted/PSR-4-autoloader.md) autoloading standard.

<a name="phpdoc"></a>
### PHPDoc

Below is an example of a valid LaraGram documentation block. Note that the `@param` attribute is followed by two spaces, the argument type, two more spaces, and finally the variable name:

```php
/**
 * Register a binding with the container.
 *
 * @param  string|array  $abstract
 * @param  \Closure|string|null  $concrete
 * @param  bool  $shared
 * @return void
 *
 * @throws \Exception
 */
public function bind($abstract, $concrete = null, $shared = false)
{
    // ...
}
```

When the `@param` or `@return` attributes are redundant due to the use of native types, they can be removed:

```php
/**
 * Execute the job.
 */
public function handle(AudioProcessor $processor): void
{
    //
}
```

However, when the native type is generic, please specify the generic type through the use of the `@param` or `@return` attributes:

```php
/**
 * Get the attachments for the message.
 *
 * @return array<int, \LaraGram\Mail\Mailables\Attachment>
 */
public function attachments(): array
{
    return [
        Attachment::fromStorage('/path/to/file'),
    ];
}
```

<a name="styleci"></a>
### StyleCI

Don't worry if your code styling isn't perfect! [StyleCI](https://styleci.io/) will automatically merge any style fixes into the LaraGram repository after pull requests are merged. This allows us to focus on the content of the contribution and not the code style.

<a name="code-of-conduct"></a>
## Code of Conduct

The LaraGram code of conduct is derived from the Ruby code of conduct. Any violations of the code of conduct may be reported to LaraXGram (laraxgram@gmail.com):

<div class="content-list" markdown="1">

- Participants will be tolerant of opposing views.
- Participants must ensure that their language and actions are free of personal attacks and disparaging personal remarks.
- When interpreting the words and actions of others, participants should always assume good intentions.
- Behavior that can be reasonably considered harassment will not be tolerated.

</div>
