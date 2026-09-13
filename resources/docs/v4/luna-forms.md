# Luna: Forms

<a name="introduction"></a>
## Introduction

Forms are where a Luna app sends data *back* to the server. You could do this with a raw [router visit](/v4/luna-routing#manual-visits), but Luna gives you two purpose-built tools that handle the tedious parts — tracking field state, submitting, wiring up [validation](/v4/validation) errors, showing progress, and resetting:

<div class="content-list" markdown="1">

- The **`useForm`** helper — a stateful form object you drive imperatively.
- The **`<Form>`** component — a declarative wrapper that submits its fields for you.

</div>

Both integrate with LaraGram's server-side [validation](/v4/validation) and, optionally, [Precognition](/v4/precognition) for live validation.

<a name="the-useform-helper"></a>
## The useForm Helper

`useForm` returns a form object holding your data, error state, and submission helpers. Give it an initial data shape:

```jsx
import { useForm } from '@laraxgram/react'

function CreateUser() {
    const form = useForm({
        name: '',
        email: '',
    })

    function submit(e) {
        e.preventDefault()
        form.post('/users')
    }

    return (
        <form onSubmit={submit}>
            <input
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
            />
            {form.errors.name && <div>{form.errors.name}</div>}

            <button type="submit" disabled={form.processing}>
                Create
            </button>
        </form>
    )
}
```

You can also pass a method and URL up front so the form knows how to submit itself — useful with the [Telegram MainButton](/v4/luna-tma#main-button-and-forms):

```js
const form = useForm('post', '/users', { name: '', email: '' })
form.submit() // Uses the bound method + URL.
```

<a name="form-state"></a>
### Form State & Methods

The form object exposes everything you need to render and control the form:

| Member | Description |
|--------|-------------|
| `data` | The current field values. |
| `setData(key, value)` | Update a field (also accepts an object or updater callback). |
| `errors` | Validation errors, keyed by field. |
| `hasErrors` | Whether any errors are present. |
| `setError` / `clearErrors` | Set or clear errors manually. |
| `processing` | `true` while a submit is in flight. |
| `progress` | Upload progress (for [file uploads](#file-uploads)). |
| `isDirty` | Whether data differs from the initial/last-submitted values. |
| `wasSuccessful` | The last submit succeeded. |
| `recentlySuccessful` | `true` briefly after success — handy for a "Saved ✓" flash. |
| `reset(...fields)` | Reset fields to their defaults (all fields if none named). |
| `resetAndClearErrors(...fields)` | Reset and clear errors together. |
| `transform(cb)` | Transform the data just before submit. |
| `submit` / `get` / `post` / `put` / `patch` / `delete` | Submit the form. |
| `cancel()` | Cancel an in-flight submit. |

<a name="submitting"></a>
### Submitting

Each verb accepts a URL and the same [visit options](/v4/luna-routing#manual-visits) as the router, including lifecycle callbacks:

```js
form.post('/users', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: (errors) => console.log(errors),
    onFinish: () => {},
})
```

<a name="transforming-data"></a>
### Transforming Data Before Submit

Use `transform` to shape the payload without mutating your form state — for example, combining fields or adding a computed value:

```js
form.transform((data) => ({
    ...data,
    name: data.name.trim(),
})).post('/users')
```

<a name="validation-errors"></a>
## Validation Errors

Server-side [validation](/v4/validation) needs no special handling. Validate in the controller as usual; when validation fails, LaraGram redirects back with the errors, and Luna surfaces them on `form.errors` automatically:

```php
public function store(Request $request)
{
    $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
    ]);

    // ...
}
```

```jsx
{form.errors.email && <p className="error">{form.errors.email}</p>}
```

Errors are also shared globally, so you can read them via the [page object](/v4/luna-routing#the-page-object) (`usePage().props.errors`) even outside a `useForm`.

<a name="file-uploads"></a>
## File Uploads

Include a `File` anywhere in your form data and Luna automatically encodes the request as `multipart/form-data` and reports upload progress:

```jsx
const form = useForm({
    name: '',
    avatar: null,
})

<input
    type="file"
    onChange={(e) => form.setData('avatar', e.target.files[0])}
/>

{form.progress && (
    <progress value={form.progress.percentage} max="100" />
)}
```

> [!NOTE]
> Because HTML forms can't natively send `PUT`/`PATCH`/`DELETE` with files, submit file uploads via `post` and add a `_method` field (`form.setData('_method', 'put')`) for LaraGram's [method spoofing](/v4/routing#form-method-spoofing).

<a name="the-form-component"></a>
## The Form Component

When you don't need imperative control, the `<Form>` component is the least-boilerplate option. It collects its child inputs by `name` and submits them to the given `action`:

```jsx
import { Form } from '@laraxgram/react'

<Form action="/users" method="post" resetOnSuccess>
    {({ processing, errors }) => (
        <>
            <input name="name" />
            {errors.name && <span>{errors.name}</span>}

            <input name="email" />
            {errors.email && <span>{errors.email}</span>}

            <button type="submit" disabled={processing}>Create</button>
        </>
    )}
</Form>
```

The render-prop exposes the same state as `useForm` (`processing`, `errors`, `isDirty`, …). Useful props:

| Prop | Description |
|------|-------------|
| `action` | The submit URL. |
| `method` | HTTP verb. |
| `transform` | Transform data before submit. |
| `options` | Visit options passed through to the request. |
| `onSuccess` / `onError` | Lifecycle callbacks. |
| `resetOnSuccess` / `resetOnError` | Reset fields after the outcome. |
| `disableWhileProcessing` | Disable the form's controls during submit. |

Access the form context from a nested component with `useFormContext()`. Vue and Svelte export the equivalent `<Form>` and `useFormContext`.

<a name="precognition"></a>
## Live Validation with Precognition

Luna's forms integrate with LaraGram [Precognition](/v4/precognition) for real-time, server-authoritative validation — validate a field on blur using the *same* rules as your final submit, with no duplicated client logic. The form object gains `validate`, `validating`, `touch`, and `forgetError`:

```jsx
const form = useForm({ email: '' })

<input
    value={form.data.email}
    onChange={(e) => form.setData('email', e.target.value)}
    onBlur={() => form.validate('email')}
/>
{form.validating && <span>Checking…</span>}
{form.errors.email && <span>{form.errors.email}</span>}
```

On the server, add the `Precognition` middleware to the route and use a [form request](/v4/precognition); Luna sends a precognitive request that runs validation without executing the controller body. See the [Precognition documentation](/v4/precognition) for the full setup.

<a name="next"></a>
## Next Steps

Configure your framework, layouts, and build pipeline in [Frontend Setup](/v4/luna-frontend), or start building a [Telegram Mini App](/v4/luna-tma) — where forms drive the native MainButton.
