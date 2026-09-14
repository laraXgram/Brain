---
name: luna-vue-development
description: "Develops Luna Vue 3 client-side pages in LaraGram applications. Activates when creating or changing Vue pages under resources/js/Pages, Luna forms (useForm, <Form>), navigation (<Link>, router), <Head>, deferred props (<Deferred>), <WhenVisible>, <InfiniteScroll>, polling, prefetching, persistent layouts, or the Vue entry point (createLunaApp from @laraxgram/vue3); or when the user mentions Vue with Luna."
license: MIT
metadata:
  author: laraxgram
---

# Luna Vue Development

Luna connects Vue page components to LaraGram controllers. There is no client-side router: controllers return `Luna::render('Users/Show', $props)`, and Luna swaps page components over XHR. Use `search-docs` (`packages: ['laraxgram/luna', '@laraxgram/vue3']`) for exact APIs — Luna's API is its own and should not be assumed from other SPA adapters.

## Consistency First

Check `resources/js` for the entry point (`createLunaApp`), the pages directory, layouts, and existing form patterns before adding anything. Follow what the project already uses (`useForm` vs `<Form>`, layout style, TypeScript or JavaScript).

## Pages

- Pages live in `resources/js/Pages` (the name passed to `Luna::render()` maps to the file path, e.g. `Users/Show` → `Pages/Users/Show.vue`). Create the page file when adding a `Luna::render()` call.
- Props come from the controller; shared props (errors, the authenticated user, the Telegram context in Mini Apps) are available on every page through `usePage()`.
- Set the title and meta tags with `<Head>`.

```vue
<!-- resources/js/Pages/Users/Show.vue -->
<script setup>
import { Head, Link } from '@laraxgram/vue3'

defineProps({ user: Object })
</script>

<template>
    <Head :title="user.name" />
    <h1>{{ user.name }}</h1>
    <Link href="/users">Back to users</Link>
</template>
```

## Navigation

- Use `<Link href="...">` for links between Luna pages; it performs a Luna visit instead of a full page load. Use `method="post"` / `as="button"` for non-GET actions, and `prefetch` for likely next pages.
- Navigate programmatically with `router.visit(url)`, `router.get/post/put/patch/delete(url, data, options)`, and refresh props with `router.reload({ only: ['stats'] })`.
- Use a regular `<a>` (or `Luna::location()` on the server) for external URLs and file downloads.
- Keep URLs server-generated when possible (pass `route('users.show', $user)` in props) instead of hard-coding paths in several components.

## Forms

```vue
<script setup>
import { useForm } from '@laraxgram/vue3'

const form = useForm({ name: '', email: '' })

function submit() {
    form.post('/users', { onSuccess: () => form.reset() })
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.name" />
        <div v-if="form.errors.name">{{ form.errors.name }}</div>
        <button type="submit" :disabled="form.processing">Create</button>
    </form>
</template>
```

The declarative alternative collects inputs by `name`:

```vue
<Form action="/users" method="post" reset-on-success #default="{ processing, errors }">
    <input name="name" />
    <span v-if="errors.name">{{ errors.name }}</span>
    <button type="submit" :disabled="processing">Create</button>
</Form>
```

- After a successful submit, the controller should redirect (`return back()` or `to_route(...)`); validation errors arrive on `form.errors` automatically.
- Send files with `post` and add `_method` for PUT/PATCH (`form.transform(...)` or a `_method` field).
- Precognition (live validation) adds `validate`, `validating`, `touch`, and `forgetError` to forms.

## Props Loaded Later

Deferred props (`Luna::defer()`) render the page first and load the data in a follow-up request:

```vue
<Deferred data="posts">
    <template #fallback>
        <p>Loading posts…</p>
    </template>

    <PostList :posts="posts" />
</Deferred>
```

Use `<WhenVisible>` for props loaded on scroll, `<InfiniteScroll>` with `Luna::scroll()` for paginated lists, and `usePoll(interval)` for periodic refreshes. When using deferred props, show an empty state or skeleton while they load.

## Telegram Mini Apps

When the page runs inside Telegram, activate `luna-tma-development`. The adapter's Telegram bindings are `useTelegramUser()` and `useTelegramSharedContext()` (reactive refs), `useTelegram()`, `useTelegramFormButton(form, options)`, `useTelegramBackButton()`, `useTelegramClosingConfirmation(dirtyRef)`. Prefer these server-validated values over the SDK's `initDataUnsafe`.

## Common Pitfalls

- Creating a `Luna::render()` call without the matching page file (the page renders blank).
- Using `fetch`/`axios` for navigation or form posts instead of `router` / `useForm`, which loses errors, history, and progress handling.
- Returning JSON from a controller that a Luna form posts to; redirect instead.
- Forgetting to run `npm run dev` / `npm run build` after changing pages.
