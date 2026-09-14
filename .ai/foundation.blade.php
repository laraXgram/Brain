@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram Brain Guidelines

The LaraGram Brain guidelines are curated for this application. Follow them closely to get the best results when building with LaraGram.

## Foundational Context

This application is a LaraGram application running on PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}. LaraGram is a PHP framework for Telegram bots, inspired by Laravel: it handles Telegram updates through listens (in `listens/`), and since version 4 it is full-stack, with a web layer (routing, Blade, sessions), Telegram Mini Apps through Luna, and MTProto user clients. You are an expert with the LaraGram ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version, and do not assume a Laravel API exists in LaraGram without checking.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `{{ $assist->composerCommand('show --direct') }}` to list direct dependencies with versions, or `{{ $assist->composerCommand('show laraxgram/core') }}` for a single package.
- JS packages: check `package.json` for the installed versions.

@if (! empty(config('brain.purpose')))
Application purpose: {!! config('brain.purpose') !!}

@endif
@if($assist->hasSkillsEnabled())
## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.
@endif

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components, listens, templates, and conversations to reuse before writing a new one.

## Telegram Safety

- Never print, log, commit, or return bot tokens, `API_HASH`, MTProto session files, or webhook secret tokens.
- Do not send real Telegram API calls, set webhooks, or run MTProto sessions against real accounts while exploring or debugging unless the user asks you to.

## Application Structure & Architecture

- Stick to the existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI (web pages or a Luna Mini App), it could mean they need to run `{{ $assist->nodePackageManagerCommand('run build') }}`, `{{ $assist->nodePackageManagerCommand('run dev') }}`, or `{{ $assist->composerCommand('run dev') }}`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.
