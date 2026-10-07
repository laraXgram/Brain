# Validation and Forms Best Practices

## Extract Validation When It Improves the Boundary

Use a form request when validation or authorization is substantial, reused, or clearer outside the controller. Inline `$request->validate()` remains appropriate for a small, endpoint-specific rule set.

```php
public function store(StorePostRequest $request): RedirectResponse
{
    $post = Post::create($request->validated());

    return redirect()->route('posts.show', $post);
}
```

A form request's `authorize()` method can enforce access to the operation. Validation establishes the shape and values of input; it does not itself authorize the user.

## Prefer Readable Rule Syntax

Array syntax composes cleanly with rule objects and avoids delimiter issues. Prefer it in new code when it improves readability, while following a consistent local style.

```php
'email' => ['required', 'email', Rule::unique('users')],
```

String syntax remains valid for simple rules:

```php
'email' => 'required|email|unique:users',
```

## Use Only Intended Validated Data

Use `validated()` or `safe()` instead of `$request->all()` when passing request data onward. Then select the fields intended for the operation when the validation rules also cover control fields or nested data.

Unsafe:

```php
Post::create($request->all());
```

Preferred:

```php
$post = Post::create($request->safe()->only(['title', 'body']));
```

Validated data is not automatically safe for mass assignment. Keep model `$fillable` or `$guarded` rules aligned with the operation, and never add a sensitive attribute to validation merely to make mass assignment convenient.

## Express Conditional Rules Clearly

Use conditional rules such as `Rule::when()`, `required_if`, or `exclude_unless` when they make the condition explicit. Choose the simplest form that remains easy to test.

```php
'company_name' => [
    'string',
    'max:255',
    Rule::when(
        $this->input('account_type') === 'business',
        ['required'],
        ['nullable'],
    ),
],
```

## Add Cross-Field Validation After Base Rules

Use a form request's `after()` method for validation that depends on multiple fields or application state. Avoid expensive queries when prerequisite fields have already failed validation.

```php
public function after(): array
{
    return [
        function (Validator $validator) {
            if ($validator->errors()->hasAny(['product_id', 'quantity'])) {
                return;
            }

            $stock = Product::find($this->integer('product_id'))?->stock;

            if ($stock !== null && $this->integer('quantity') > $stock) {
                $validator->errors()->add('quantity', 'Not enough stock.');
            }
        },
    ];
}
```

Validation against mutable state does not prevent a race between validation and persistence. Enforce inventory, uniqueness, and similar invariants with database constraints, atomic updates, or a database transaction as appropriate.

## Reject Requests Carrying Unvalidated Fields

A request with fields nothing validates is usually a mistake or an attempt at one. Add `#[FailOnUnknownFields]` to a form request to reject it, or call `FormRequest::failOnUnknownFields()` in `AppServiceProvider::boot()` and opt individual requests out with `#[FailOnUnknownFields(false)]`. A field is "known" when a rule names it (wildcards and `_confirmation` suffixes included), so a field you accept on purpose needs a rule such as `['nullable']` rather than turning the check off.

## Validate Bot Input Where It Arrives

Updates are input too. Validate a conversation answer with the question's own rules, and validate a callback's parsed parameters before using them — a `callback_data` string is whatever the client sent, and an old keyboard may carry an id the user may no longer access. Authorize the chat or user, do not infer permission from the fact that the button existed.
