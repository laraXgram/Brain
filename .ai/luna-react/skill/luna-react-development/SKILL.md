---
name: luna-react-development
description: "Develops Luna React client-side pages in LaraGram applications. Activates when creating or changing React pages under resources/js/Pages, Luna forms (useForm, <Form>), navigation (<Link>, router), <Head>, deferred props (<Deferred>), <WhenVisible>, <InfiniteScroll>, polling, prefetching, persistent layouts, or the React entry point (createLunaApp from @laraxgram/react); or when the user mentions React with Luna."
license: MIT
metadata:
  author: laraxgram
---

# Luna React Development

Luna connects React page components to LaraGram controllers. There is no client-side router: controllers return `Luna::render('Users/Show', $props)`, and Luna swaps page components over XHR. Use `search-docs` (`packages: ['laraxgram/luna', '@laraxgram/react']`) for exact APIs — Luna's API is its own and should not be assumed from other SPA adapters.

## Consistency First

Check `resources/js` for the entry point (`createLunaApp`), the pages directory, layouts, and existing form patterns before adding anything. Follow what the project already uses (`useForm` vs `<Form>`, layout style, TypeScript or JavaScript).

## Pages

- Pages live in `resources/js/Pages` (the name passed to `Luna::render()` maps to the file path, e.g. `Users/Show` → `Pages/Users/Show.jsx`). Create the page file when adding a `Luna::render()` call.
- Props come from the controller; shared props (errors, the authenticated user, the Telegram context in Mini Apps) are available on every page through `usePage()`.
- Set the title and meta tags with `<Head>`.

```jsx
// resources/js/Pages/Users/Show.jsx
import { Head, Link } from '@laraxgram/react'

export default function Show({ user }) {
    return (
        <>
            <Head title={user.name} />
            <h1>{user.name}</h1>
            <Link href="/users">Back to users</Link>
        </>
    )
}
```

## Navigation

- Use `<Link href="...">` for links between Luna pages; it performs a Luna visit instead of a full page load. Use `method="post"` / `as="button"` for non-GET actions, and `prefetch` for likely next pages.
- Navigate programmatically with `router.visit(url)`, `router.get/post/put/patch/delete(url, data, options)`, and refresh props with `router.reload({ only: ['stats'] })`.
- Use a regular `<a>` (or `Luna::location()` on the server) for external URLs and file downloads.
- Keep URLs server-generated when possible (pass `route('users.show', $user)` in props) instead of hard-coding paths in several components.

## Forms

```jsx
import { useForm } from '@laraxgram/react'

export default function Create() {
    const form = useForm({ name: '', email: '' })

    function submit(e) {
        e.preventDefault()
        form.post('/users', { onSuccess: () => form.reset() })
    }

    return (
        <form onSubmit={submit}>
            <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            {form.errors.name && <div>{form.errors.name}</div>}
            <button type="submit" disabled={form.processing}>Create</button>
        </form>
    )
}
```

The declarative alternative collects inputs by `name`:

```jsx
import { Form } from '@laraxgram/react'

<Form action="/users" method="post" resetOnSuccess>
    {({ processing, errors }) => (
        <>
            <input name="name" />
            {errors.name && <span>{errors.name}</span>}
            <button type="submit" disabled={processing}>Create</button>
        </>
    )}
</Form>
```

- After a successful submit, the controller should redirect (`return back()` or `to_route(...)`); validation errors arrive on `form.errors` automatically.
- Send files with `post` and add `_method` for PUT/PATCH (`form.transform(...)` or a `_method` field).
- Precognition (live validation) adds `validate`, `validating`, `touch`, and `forgetError` to forms.

## Props Loaded Later

Deferred props (`Luna::defer()`) render the page first and load the data in a follow-up request:

```jsx
import { Deferred } from '@laraxgram/react'

<Deferred data="posts" fallback={<p>Loading posts…</p>}>
    <PostList posts={posts} />
</Deferred>
```

Use `<WhenVisible>` for props loaded on scroll, `<InfiniteScroll>` with `Luna::scroll()` for paginated lists, and `usePoll(interval)` for periodic refreshes. When using deferred props, show an empty state or skeleton while they load.

## Telegram Mini Apps

When the page runs inside Telegram, activate `luna-tma-development`. The adapter's Telegram bindings are `useTelegramUser()`, `useTelegramSharedContext()`, `useTelegram()`, `useTelegramFormButton(form, options)`, `useTelegramBackButton()`, `useTelegramClosingConfirmation(dirty)`. Prefer these server-validated values over the SDK's `initDataUnsafe`.

## Common Pitfalls

- Creating a `Luna::render()` call without the matching page file (the page renders blank).
- Using `fetch`/`axios` for navigation or form posts instead of `router` / `useForm`, which loses errors, history, and progress handling.
- Returning JSON from a controller that a Luna form posts to; redirect instead.
- Forgetting to run `npm run dev` / `npm run build` after changing pages.
