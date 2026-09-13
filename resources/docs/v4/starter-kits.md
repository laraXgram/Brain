# Starter Kits

<a name="introduction"></a>
## Introduction

To give you a head start building your new LaraGram application, we are happy to offer application starter kits. These starter kits give you a head start on building your next LaraGram application, and include the routes, controllers, and views you need to register and authenticate your application's users.

While you are welcome to use these starter kits, they are not required. You are free to build your own application from the ground up by simply installing a fresh copy of LaraGram. Either way, we know you will build something great!

<a name="creating-an-application"></a>
## Creating an Application Using a Starter Kit

To create a new LaraGram application using one of our starter kits, you should first [install PHP and the LaraGram CLI tool](/v4/installation#installing-php). If you already have PHP and Composer installed, you may install the LaraGram installer CLI tool via Composer:

```shell
composer global require laraxgram/installer
```

Then, create a new LaraGram application using the LaraGram installer CLI. The LaraGram installer will prompt you to select your preferred starter kit:

```shell
laragram new my-app
```

After creating your LaraGram application, you only need to install its frontend dependencies via NPM and start the LaraGram development server:

```shell
cd my-app
npm install && npm run build
composer run dev
```

Once you have started the LaraGram development server, your application will be accessible in your web browser at [http://localhost:9000](http://localhost:9000).

<a name="available-starter-kits"></a>
## Available Starter Kits

<a name="react"></a>
### React

Our React starter kit provides a robust, modern starting point for building LaraGram applications with a React frontend using [Luna.js](https://laraxgram.github.io/v4/luna).

Luna allows you to build modern, single-page React applications using classic server-side routing and controllers. This lets you enjoy the frontend power of React combined with the incredible backend productivity of LaraGram and lightning-fast Vite compilation.

The React starter kit utilizes React 19, TypeScript, Tailwind, and the [shadcn/ui](https://ui.shadcn.com) component library.

<a name="svelte"></a>
### Svelte

Our Svelte starter kit provides a robust, modern starting point for building LaraGram applications with a Svelte frontend using [Luna.js](https://laraxgram.github.io/v4/luna).

Luna allows you to build modern, single-page Svelte applications using classic server-side routing and controllers. This lets you enjoy the frontend power of Svelte combined with the incredible backend productivity of LaraGram and lightning-fast Vite compilation.

The Svelte starter kit utilizes Svelte 5, TypeScript, Tailwind, and the [shadcn-svelte](https://www.shadcn-svelte.com/) component library.

<a name="vue"></a>
### Vue

Our Vue starter kit provides a great starting point for building LaraGram applications with a Vue frontend using [Luna.js](https://laraxgram.github.io/v4/luna).

Luna allows you to build modern, single-page Vue applications using classic server-side routing and controllers. This lets you enjoy the frontend power of Vue combined with the incredible backend productivity of LaraGram and lightning-fast Vite compilation.

The Vue starter kit utilizes the Vue Composition API, TypeScript, Tailwind, and the [shadcn-vue](https://www.shadcn-vue.com/) component library.

<a name="starter-kit-customization"></a>
## Starter Kit Customization

<a name="react-customization"></a>
### React

Our React starter kit is built with Luna, React 19, Tailwind 4, and [shadcn/ui](https://ui.shadcn.com). As with all of our starter kits, all of the backend and frontend code exists within your application to allow for full customization.

The majority of the frontend code is located in the `resources/js` directory. You are free to modify any of the code to customize the appearance and behavior of your application:

```text
resources/js/
├── components/    # Reusable React components
├── hooks/         # React hooks
├── layouts/       # Application layouts
├── lib/           # Utility functions and configuration
├── pages/         # Page components
└── types/         # TypeScript definitions
```

To publish additional shadcn components, first [find the component you want to publish](https://ui.shadcn.com). Then, publish the component using `npx`:

```shell
npx shadcn@latest add switch
```

In this example, the command will publish the Switch component to `resources/js/components/ui/switch.tsx`. Once the component has been published, you can use it in any of your pages:

```jsx
import { Switch } from "@/components/ui/switch"

const MyPage = () => {
  return (
    <div>
      <Switch />
    </div>
  );
};

export default MyPage;
```

<a name="react-available-layouts"></a>
#### Available Layouts

The React starter kit includes two different primary layouts for you to choose from: a "sidebar" layout and a "header" layout. The sidebar layout is the default, but you can switch to the header layout by modifying the layout that is imported at the top of your application's `resources/js/layouts/app-layout.tsx` file:

```js
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout'; // [!code --]
import AppLayoutTemplate from '@/layouts/app/app-header-layout'; // [!code ++]
```

<a name="react-sidebar-variants"></a>
#### Sidebar Variants

The sidebar layout includes three different variants: the default sidebar variant, the "inset" variant, and the "floating" variant. You may choose the variant you like best by modifying the `resources/js/components/app-sidebar.tsx` component:

```text
<Sidebar collapsible="icon" variant="sidebar"> // [!code --]
<Sidebar collapsible="icon" variant="inset"> // [!code ++]
```

<a name="react-authentication-page-layout-variants"></a>
#### Authentication Page Layout Variants

The authentication pages included with the React starter kit, such as the login page and registration page, also offer three different layout variants: "simple", "card", and "split".

To change your authentication layout, modify the layout that is imported at the top of your application's `resources/js/layouts/auth-layout.tsx` file:

```js
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout'; // [!code --]
import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout'; // [!code ++]
```

<a name="svelte-customization"></a>
### Svelte

Our Svelte starter kit is built with Luna, Svelte 5, Tailwind, and [shadcn-svelte](https://www.shadcn-svelte.com/). As with all of our starter kits, all of the backend and frontend code exists within your application to allow for full customization.

The majority of the frontend code is located in the `resources/js` directory. You are free to modify any of the code to customize the appearance and behavior of your application:

```text
resources/js/
├── components/    # Reusable Svelte components
├── layouts/       # Application layouts
├── lib/           # Utility functions and configuration and Svelte rune modules
├── pages/         # Page components
└── types/         # TypeScript definitions
```

To publish additional shadcn-svelte components, first [find the component you want to publish](https://www.shadcn-svelte.com). Then, publish the component using `npx`:

```shell
npx shadcn-svelte@latest add switch
```

In this example, the command will publish the Switch component to `resources/js/components/ui/switch/switch.svelte`. Once the component has been published, you can use it in any of your pages:

```svelte
<script lang="ts">
    import { Switch } from '@/components/ui/switch'
</script>

<div>
    <Switch />
</div>
```

<a name="svelte-available-layouts"></a>
#### Available Layouts

The Svelte starter kit includes two different primary layouts for you to choose from: a "sidebar" layout and a "header" layout. The sidebar layout is the default, but you can switch to the header layout by modifying the layout that is imported at the top of your application's `resources/js/layouts/AppLayout.svelte` file:

```js
import AppLayout from '@/layouts/app/AppSidebarLayout.svelte'; // [!code --]
import AppLayout from '@/layouts/app/AppHeaderLayout.svelte'; // [!code ++]
```

<a name="svelte-sidebar-variants"></a>
#### Sidebar Variants

The sidebar layout includes three different variants: the default sidebar variant, the "inset" variant, and the "floating" variant. You may choose the variant you like best by modifying the `resources/js/components/AppSidebar.svelte` component:

```text
<Sidebar collapsible="icon" variant="sidebar"> // [!code --]
<Sidebar collapsible="icon" variant="inset"> // [!code ++]
```

<a name="svelte-authentication-page-layout-variants"></a>
#### Authentication Page Layout Variants

The authentication pages included with the Svelte starter kit, such as the login page and registration page, also offer three different layout variants: "simple", "card", and "split".

To change your authentication layout, modify the layout that is imported at the top of your application's `resources/js/layouts/AuthLayout.svelte` file:

```js
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.svelte'; // [!code --]
import AuthLayout from '@/layouts/auth/AuthSplitLayout.svelte'; // [!code ++]
```

<a name="vue-customization"></a>
### Vue

Our Vue starter kit is built with Luna, Vue 3 Composition API, Tailwind, and [shadcn-vue](https://www.shadcn-vue.com/). As with all of our starter kits, all of the backend and frontend code exists within your application to allow for full customization.

The majority of the frontend code is located in the `resources/js` directory. You are free to modify any of the code to customize the appearance and behavior of your application:

```text
resources/js/
├── components/    # Reusable Vue components
├── composables/   # Vue composables / hooks
├── layouts/       # Application layouts
├── lib/           # Utility functions and configuration
├── pages/         # Page components
└── types/         # TypeScript definitions
```

To publish additional shadcn-vue components, first [find the component you want to publish](https://www.shadcn-vue.com). Then, publish the component using `npx`:

```shell
npx shadcn-vue@latest add switch
```

In this example, the command will publish the Switch component to `resources/js/components/ui/Switch.vue`. Once the component has been published, you can use it in any of your pages:

```vue
<script setup lang="ts">
import { Switch } from '@/components/ui/switch'
</script>

<template>
    <div>
        <Switch />
    </div>
</template>
```

<a name="vue-available-layouts"></a>
#### Available Layouts

The Vue starter kit includes two different primary layouts for you to choose from: a "sidebar" layout and a "header" layout. The sidebar layout is the default, but you can switch to the header layout by modifying the layout that is imported at the top of your application's `resources/js/layouts/AppLayout.vue` file:

```js
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'; // [!code --]
import AppLayout from '@/layouts/app/AppHeaderLayout.vue'; // [!code ++]
```

<a name="vue-sidebar-variants"></a>
#### Sidebar Variants

The sidebar layout includes three different variants: the default sidebar variant, the "inset" variant, and the "floating" variant. You may choose the variant you like best by modifying the `resources/js/components/AppSidebar.vue` component:

```text
<Sidebar collapsible="icon" variant="sidebar"> // [!code --]
<Sidebar collapsible="icon" variant="inset"> // [!code ++]
```

<a name="vue-authentication-page-layout-variants"></a>
#### Authentication Page Layout Variants

The authentication pages included with the Vue starter kit, such as the login page and registration page, also offer three different layout variants: "simple", "card", and "split".

To change your authentication layout, modify the layout that is imported at the top of your application's `resources/js/layouts/AuthLayout.vue` file:

```js
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue'; // [!code --]
import AuthLayout from '@/layouts/auth/AuthSplitLayout.vue'; // [!code ++]
```

<a name="luna-ssr"></a>
### Luna SSR

The React, Svelte, and Vue starter kits are compatible with Luna's server-side rendering capabilities. To build an Luna SSR compatible bundle for your application, run the `build:ssr` command:

```shell
npm run build:ssr
```

For convenience, a `composer dev:ssr` command is also available. This command will start the LaraGram development server and Luna SSR server after building an SSR compatible bundle for your application, allowing you to test your application locally using Luna's server-side rendering engine:

```shell
composer dev:ssr
```

<a name="community-maintained-starter-kits"></a>
### Community Maintained Starter Kits

When creating a new LaraGram application using the LaraGram installer, you may provide any community maintained starter kit available on Packagist to the `--using` flag:

```shell
laragram new my-app --using=example/starter-kit
```

<a name="creating-starter-kits"></a>
#### Creating Starter Kits

To ensure your starter kit is available to others, you will need to publish it to [Packagist](https://packagist.org). Your starter kit should define its required environment variables in its `.env.example` file, and any necessary post-installation commands should be listed in the `post-create-project-cmd` array of the starter kit's `composer.json` file.

<a name="faqs"></a>
### Frequently Asked Questions

<a name="faq-upgrade"></a>
#### How do I upgrade?

Every starter kit gives you a solid starting point for your next application. With full ownership of the code, you can tweak, customize, and build your application exactly as you envision. However, there is no need to update the starter kit itself.

<a name="faq-enable-email-verification"></a>
#### How do I enable email verification?

Email verification can be added by uncommenting the `MustVerifyEmail` import in your `App/Models/User.php` model and ensuring the model implements the `MustVerifyEmail` interface:

```php
<?php

namespace App\Models;

use LaraGram\Contracts\Auth\MustVerifyEmail;
// ...

class User extends Authenticatable implements MustVerifyEmail
{
    // ...
}
```

After registration, users will receive a verification email. To restrict access to certain routes until the user's email address is verified, add the `verified` middleware to the routes:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Luna::render('dashboard');
    })->name('dashboard');
});
```

> [!NOTE]
> Email verification is not required when using the [WorkOS](#workos) variant of the starter kits.

<a name="faq-modify-email-template"></a>
#### How do I modify the default email template?

You may want to customize the default email template to better align with your application's branding. To modify this template, you should publish the email views to your application with the following command:

```
php laragram vendor:publish --tag=laragram-mail
```

This will generate several files in `resources/views/vendor/mail`. You can modify any of these files as well as the `resources/views/vendor/mail/themes/default.css` file to change the look and appearance of the default email template.
