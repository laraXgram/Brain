@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# Detection Checklist

Every dimension here is a genuine fork: LaraGram offers two or more valid approaches, the app's choice changes what the next agent writes, and no active project tool can pick for you. Left out on purpose: pure formatting (the project's formatter owns it), any form an installed and enabled Rector rule rewrites to one canonical shape (`$casts` to `casts()`, `$fillable` to attributes, pipe-string rules to arrays, named to anonymous migrations, `$signature` to `#[Signature]`), and framework defaults any agent writes unprompted (`ShouldQueue` jobs, relation return types, `HasFactory`).

Each item gives the fork, then a hint (a grep or dir to spot which side the app takes). Hints are only a start. Read the matched files, never record on a raw count. Apply the ground rules to every verdict: a consistent choice that is a default or a tool's target form is not a pattern. Rows tagged (architecture) are the highest-signal, so record presence and deliberate absence.

---

## A. Bot listens & replies

1. Listen handler style (architecture): closures in `listens/*.php` vs bot controller classes vs invokable controllers.
   - Hint: count `function (` vs `::class` in `listens/*.php`; `ls app/Controllers`.
2. Listen file layout: one `listens/bot.php` vs split files registered in `bootstrap/app.php` (`then:` groups, per-connection files).
   - Hint: `ls listens`; read `withListener(` in `bootstrap/app.php`.
3. Reply style: Temple8 templates (`template()` / `Bot::template()`) vs inline `$request->sendMessage()` calls.
   - Hint: `ls app/templates`; grep `template(` vs `->sendMessage(` in listens and controllers.
4. Update access: `chat()` / `user()` / `message()` helpers vs `$request->message->...` dynamic properties vs `$request->input('message.text')`.
   - Hint: grep `chat()`, `user()`, `->message->`, `->input('message` in `listens/` and `app/Controllers`.
5. Keyboard construction: `Keyboard` / `Make` builder vs Temple8 `@keyboard` directives vs raw `reply_markup` arrays / JSON.
   - Hint: grep `Make::`, `@keyboard`, `inline_keyboard` in `app/` and `listens/`.
6. Multi-step flows (architecture): Conversations (`app/Conversations`) vs Step Manager (`Step::set` + `Bot::onStep`) vs hand-rolled cache state.
   - Hint: `ls app/Conversations`; grep `Step::`, `onStep(`, `Cache::put(` near reply code.
7. Callback data format: prefix scheme (`order:cancel:42`) vs JSON / query-string payloads vs opaque ids.
   - Hint: grep `callbackData(` / `callback_data:` and `onCallbackQueryData(` patterns.
8. Chat scoping: `Bot::scope()` / `outOfScope()` groups vs manual `chat()->type` checks inside handlers.
   - Hint: grep `scope(`, `outOfScope(` vs `->type ===` / `$request->scope()`.
9. Parse mode: HTML vs MarkdownV2 vs none, and whether it is set in `config/laraquest.php` `default_parameters` or per call.
   - Hint: grep `parse_mode` in `config/laraquest.php`, templates, and listens.
10. Sending outside updates: `app('request')` vs an injected wrapper service vs a dedicated job for every outgoing message.
    - Hint: grep `->sendMessage(` in `app/Jobs`, `app/Listeners`, `app/Console`.

## B. Validation & input

11. Validation entry point: inline `$request->validate()` vs Form Request classes vs `Validator::make()` vs Conversation `->validate()` rules.
    - Hint: `ls app/Http/Requests`; grep `->validate(` / `Validator::make(` in `app/`.
12. Custom rule location: invokable rule objects in `app/Rules` vs inline closures vs `Validator::extend()` in a provider. Rule objects are the default `make:rule` path, so record only if the app leans on closures or `Validator::extend` instead. "No rule objects" alone is just no-signal.
    - Hint: `ls app/Rules`; grep `Validator::extend` in `app/Providers`.
13. Typed input retrieval (web): typed getters (`$request->string()`, `->integer()`, `->enum()`, `->date()`) vs raw `$request->input()` / dynamic properties.
    - Hint: grep `->string(` / `->integer(` / `->enum(` vs `->input(` in `app/Http`.
14. Custom messages/attributes: `lang/*/validation.php` vs Form Request `messages()` / `attributes()` methods vs per-question messages.
    - Hint: `ls lang`; grep `function messages`, `function attributes`.

## C. Web controllers & routing

15. Controller shape: invokable single-action (`__invoke`) vs resource controllers vs plain multi-method.
   - Hint: grep `__invoke` in controllers; `Route::resource` / `apiResource` vs verb routes.
16. Business-logic location (architecture): fat controllers vs delegated to Actions / Services / Jobs.
   - Hint: read a few controller methods; `ls app/Actions app/Services`.
17. Route handler style: closures in `routes/*.php` vs controller classes.
   - Hint: count `function ()` vs `::class` in `routes/web.php`, `routes/api.php`.
18. Middleware assignment: route/group `->middleware()` vs controller `HasMiddleware::middleware()` vs `#[Middleware]` attribute.
   - Hint: grep `implements HasMiddleware`, `#[Middleware(` in controllers vs `->middleware(` in routes.
19. Route model binding: implicit (type-hinted models) vs explicit `Route::bind` vs manual `findOrFail`.
   - Hint: typed model params in signatures vs `findOrFail(` in controllers; grep `Route::bind`.
20. Rate limiting: named `RateLimiter::for()` + `throttle:name` vs inline `throttle:60,1`.
    - Hint: grep `RateLimiter::for` in providers vs `throttle:` in route files.

## D. Authorization

21. Authorization home: Gates (`Gate::define`) vs Policy classes in `app/Policies`.
    - Hint: `ls app/Policies`; grep `Gate::define` in `app/Providers`.
22. Authorization call site: `$this->authorize()` / `Gate::authorize()` vs `$user->can()` vs `can` middleware vs `#[Authorize]` vs `@can` in Blade.
    - Hint: grep `authorize(`, `->can(`, `middleware('can:`, `#[Authorize(`, `@can(`.

## E. Eloquent & models

23. Mass assignment: `$fillable` allow-list vs `$guarded` block-list.
    - Hint: grep `protected $fillable` / `protected $guarded` in `app/Models`.
24. Accessors/mutators: modern `Attribute` class vs legacy `getXxxAttribute()` / `setXxxAttribute()`. Record a legacy hold, it goes against the tool's grain.
    - Hint: grep `: Attribute` / `Attribute::make` vs `function get[A-Z].*Attribute` in `app/Models`.
25. Primary keys: auto-increment vs `HasUuids` vs `HasUlids`.
    - Hint: grep `HasUuids` / `HasUlids` in `app/Models`; migration `id()` vs `uuid('id')`.
26. Custom casts: dedicated `CastsAttributes` classes (`app/Casts`) vs inline `Attribute` vs built-in cast strings.
    - Hint: `ls app/Casts`; grep `Cast::class`, `AsStringable::class` in models.
27. Data/query layer (architecture): Eloquent directly in controllers vs repositories vs dedicated query objects (e.g. classes exposing `builder(): Builder`).
    - Hint: `ls app/Repositories app/Queries`; see where non-trivial queries are built.
28. Query scopes: local `scope`/`#[Scope]` methods vs dedicated builder classes.
    - Hint: grep `function scope` / `#[Scope]` in models; `ls app/*/Builders`.
29. Model events: observers (`app/Observers`, `#[ObservedBy]`) vs `booted()` closures vs event classes.
    - Hint: `ls app/Observers`; grep `booted`, `::observe`, `#[ObservedBy]`.
30. Eager-load posture: explicit per-query `->with()` vs model-level `$with` defaults. Treat `preventLazyLoading()` separately as a development guard because it can complement either posture.
    - Hint: grep `protected $with`, `->with(`, and separately `preventLazyLoading` in `app/`.

## F. Architecture & organization

31. Action/Service structure (architecture): Action classes (invoked via `handle` / `execute` / `__invoke`) vs service objects vs neither. Cross-check the Step 0 `app/` map: any `Actions`/`Services`/`Pipelines`/`Jobs`-as-actions folder is this pattern, so record how it is invoked.
    - Hint: `ls app/` (the whole tree, not just `Actions`/`Services`); grep the invocation method in the folder you find.
32. DTOs (architecture): readonly data classes vs arrays everywhere.
    - Hint: `ls app/Data`; grep `readonly class` in `app/`.
33. Dependency acquisition: constructor/method injection vs `app()` / `resolve()` / `App::make()` service location.
    - Hint: grep `app(` / `resolve(` / `::make(` in `app/` vs promoted constructor deps.
34. Decoupling: events + listeners vs direct service calls.
    - Hint: `ls app/Events app/Listeners`; grep `event(`, `::dispatch(`.
35. Helper vs facade idiom: global helpers (`config()`, `auth()`, `response()`) vs facades (`Config::`, `Auth::`, `Response::`).
    - Hint: ratio of `config(` vs `Config::` (etc.) across `app/`.
36. Namespace layout (architecture): default `app/` skeleton vs domain/module folders (`app/Domain/**`, modules).
    - Hint: `ls app/`, look for `Domain/`, `Modules/`, bounded-context folders.
37. Enums: backed vs pure; case naming; where they live.
    - Hint: `ls app/Enums`; grep `enum .*: string`, `enum .*: int`.

## G. Web & Luna frontend

@if($assist->hasPackage('laraxgram/luna'))
This app ships Luna, so the items below apply.
@else
No Luna package is installed. Confirm from `routes/` and `resources/views` whether the app serves web pages before sweeping, and treat the Luna dimensions as not applicable.
@endif

38. Frontend stack: Blade-only vs Luna (React/Vue/Svelte) vs Luna as a Telegram Mini App vs no web layer.
    - Hint: `composer.json` + `package.json`; `ls resources/js/Pages`, `resources/views`; grep `telegram` middleware in `routes/`.
39. Blade composition: class `<x-*>` components vs anonymous components (`@props`) vs `@include` partials.
    - Hint: `ls app/View/Components`; grep `<x-`, `@include` in `resources/views`.
@if($assist->hasPackage('laraxgram/luna'))
40. Luna forms: `useForm` helper vs `<Form>` component, and Mini App submission through `useTelegramFormButton` vs regular buttons.
    - Hint: grep `useForm(`, `<Form`, `useTelegramFormButton` in `resources/js`.
41. Mini App identity: `tg_user()` / `telegram()` helpers vs `auth('telegram')` guard vs ids from the request body.
    - Hint: grep `tg_user(`, `telegram()`, `auth('telegram')` in `app/Http`.
@endif
42. Localization: short keys (`lang/*/*.php` + `__('messages.welcome')`) vs JSON string keys (`lang/*.json` + `__('Full sentence')`), including bot replies and templates.
    - Hint: `ls lang`; grep dotted `__('` vs sentence keys in `app/`, `listens/`, `app/templates`.

## H. Database & migrations

43. Foreign keys: `foreignId()->constrained()` vs `foreignIdFor(Model::class)` vs manual `foreign()->references()->on()`.
    - Hint: grep `foreignId(`, `foreignIdFor(`, `->foreign(` in `database/migrations`.
44. `down()` methods: real reverse logic vs omitted / one-way migrations.
    - Hint: grep `function down` vs the migration count.
45. Enum storage: DB `enum()` column vs `string()` + PHP-enum cast on the model.
    - Hint: grep `->enum(` in migrations vs string columns cast to enums.
46. Transactions: `DB::transaction(fn ...)` closure vs manual `beginTransaction` / `commit` / `rollBack`.
    - Hint: grep `DB::transaction`, `beginTransaction` in `app/`.
47. Idempotent writes: `upsert` / `updateOrCreate` / `firstOrCreate` vs find-then-save.
    - Hint: grep `upsert(`, `updateOrCreate(`, `firstOrCreate(` in `app/`.

## I. Responses & API resources

48. Response shape: API Resource classes vs `response()->json()` vs returning models/arrays directly.
    - Hint: `ls app/Http/Resources`; grep `JsonResource`, `->json(` in controllers.
49. Resource relationship inclusion: `whenLoaded()` guards vs unconditional relationship access. Do not count ordinary scalar attributes as rivals to conditional relationships, and evaluate general `when()` fields separately.
    - Hint: compare relationship fields using `whenLoaded(` with unconditional relationship property access in `app/Http/Resources`.
50. Pagination contracts: within comparable endpoint categories, length-aware `paginate()` vs `simplePaginate()` vs `cursorPaginate()`. These have different totals, navigation, ordering, and performance contracts, so record only a stable path-scoped API policy, never a project-wide majority.
    - Hint: grep those in `app/`, then group matches by endpoint type and client contract before comparing them.
51. Web redirects/URLs: `route('name')` vs `url('/path')` vs `action([...])`.
    - Hint: grep `route('`, `url('/`, `action([` in `app/Http` and views.

## J. Strings, collections & dates

52. Iteration idiom: `collect()->map()->filter()` pipelines vs `array_map` / `foreach`.
    - Hint: grep `collect(`, `->map(` vs `array_map`, `foreach` density in `app/`.
53. String API: fluent `Str::of()->...` (Stringable) vs static `Str::` vs native (`trim`, `strtoupper`).
    - Hint: grep `Str::of(` vs `Str::` vs native string funcs.
54. Dates: compare equivalent construction call styles (`now()` / `today()` helpers vs `Tempora::`) separately from the application's mutable/immutable date policy.
    - Hint: grep `now(` and `Tempora::` for call style; separately inspect immutable date usage for mutability policy.

---

Genuine forks only. Every row survived the "no tool can decide this, and it isn't the default" filter. Give each applicable dimension exactly one verdict: pattern, conflict, default, no-signal, tooling-owned, or already-recorded. The rows tagged (architecture) are where the highest-value rules come from.
