---
name: laragram-best-practices
description: "Apply this skill whenever writing, reviewing, or refactoring LaraGram PHP code outside of the bot listening layer itself. This includes models, migrations, Eloquent queries, form requests and validation, policies, jobs and queues, events, scheduled commands, caching, configuration, error handling, HTTP client calls, web routes and controllers, Blade views, service classes, and architectural decisions. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns (including webhook secrets and bot credentials), broadcasts and slow bot work that belongs in a queue, and LaraGram code reviews. For listens, keyboards, Temple8 templates, conversations, and steps, use the bot-development skill."
license: MIT
metadata:
  author: laraxgram
---

# LaraGram Best Practices

Best practices for LaraGram applications, organized as an index of rule files. Each rule file teaches what to do and why. For exact API syntax, verify with `search-docs`.

## Consistency First

Before applying any rule, check what the application already does. LaraGram offers multiple valid approaches, and the best choice is the one the codebase already uses, even if another pattern would be theoretically better. Inconsistency is worse than a suboptimal pattern.

Check sibling files, related listens, controllers, and models for established patterns. If one exists, follow it. Don't introduce a second way. These rules are defaults for when no pattern exists yet, not overrides.

LaraGram is inspired by Laravel and shares much of its API, but it is not Laravel. Mail, notifications, and several Laravel packages do not exist in LaraGram. Confirm an API exists (with `search-docs` or in `vendor/laraxgram/core/src`) before using it.

## How to Apply

1. Check the changed files, nearby code, and project configuration for established patterns. Deviate only for a correctness or security defect, and call the deviation out.
2. Map every affected concern to the rule index below. Read each mapped rule file before editing. Skip unrelated rule files.
3. Make the smallest coherent change. Keep the application's architecture and naming instead of introducing a second pattern for the same job.
4. Verify version-sensitive LaraGram APIs for the installed version with `search-docs`, or inspect the installed framework when it is unavailable.
5. Verify the change the way the project does: run the relevant Commander command, send the bot a test update in a local environment, or open the page, and check `last-error` / `read-log-entries`.
6. Re-read the diff against every mapped rule before finishing.

## Rule Index

Cross-cutting changes often need more than one rule file.

| Concern | Read |
| --- | --- |
| Query count, eager loading, indexes, large datasets | [`rules/db-performance.md`](rules/db-performance.md) |
| Subqueries, aggregates, complex ordering and query plans | [`rules/advanced-queries.md`](rules/advanced-queries.md) |
| Models, relationships, scopes, casts | [`rules/eloquent.md`](rules/eloquent.md) |
| Authentication, authorization, webhook secrets, bot credentials, input safety, uploads | [`rules/security.md`](rules/security.md) |
| Form Requests and validation rules | [`rules/validation.md`](rules/validation.md) |
| Web routes, controllers, route binding, resources, middleware | [`rules/routing.md`](rules/routing.md) |
| Schema changes, columns, foreign keys, indexes | [`rules/migrations.md`](rules/migrations.md) |
| Jobs, retries, uniqueness, batches, broadcasts, slow bot work | [`rules/queue-jobs.md`](rules/queue-jobs.md) |
| Cache lifetime, invalidation, locks, memoization | [`rules/caching.md`](rules/caching.md) |
| Outbound HTTP requests, retries, timeouts | [`rules/http-client.md`](rules/http-client.md) |
| Exceptions, reporting, rendering, log context | [`rules/error-handling.md`](rules/error-handling.md) |
| Events and event listeners | [`rules/events.md`](rules/events.md) |
| Scheduled tasks and overlap protection | [`rules/scheduling.md`](rules/scheduling.md) |
| Collections, lazy iteration, bulk operations | [`rules/collections.md`](rules/collections.md) |
| Blade components, attributes, composers | [`rules/blade-views.md`](rules/blade-views.md) |
| Environment values and application configuration | [`rules/config.md`](rules/config.md) |
| Naming, helpers, file boundaries, PHP style | [`rules/style.md`](rules/style.md) |
| Actions, services, dependencies, application structure | [`rules/architecture.md`](rules/architecture.md) |
| Listens, keyboards, Temple8 templates, conversations, steps | the `bot-development` skill |

## Decision Rules

- Prefer framework features and existing application abstractions over new helpers or dependencies.
- Avoid speculative abstractions. Extract code when it creates a clear domain boundary or removes meaningful duplication.
- Keep database access out of Blade views and Temple8 templates, and prevent hidden N+1 queries across listens, controllers, resources, jobs, and serialization.
- Keep webhook updates fast: answer the user, then queue slow work.
