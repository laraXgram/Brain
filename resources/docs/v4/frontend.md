# Frontend

<a name="introduction"></a>
## Introduction

LaraGram is a backend framework that provides all of the features you need to build modern web applications, such as [routing](/v4/routing), [validation](/v4/validation), [caching](/v4/cache), [queues](/v4/queues), [file storage](/v4/filesystem), and more. However, we believe it's important to offer developers a beautiful full-stack experience, including powerful approaches for building your application's frontend.

There are two primary ways to tackle frontend development when building an application with LaraGram, and which approach you choose is determined by whether you would like to build your frontend by leveraging PHP or by using JavaScript frameworks such as React, Svelte, and Vue. We'll discuss both of these options below so that you can make an informed decision regarding the best approach to frontend development for your application.

<a name="using-php"></a>
## Using PHP

<a name="php-and-blade"></a>
### PHP and Blade

In the past, most PHP applications rendered HTML to the browser using simple HTML templates interspersed with PHP `echo` statements which render data that was retrieved from a database during the request:

```blade
<div>
    <?php foreach ($users as $user): ?>
        Hello, <?php echo $user->name; ?> <br />
    <?php endforeach; ?>
</div>
```

In LaraGram, this approach to rendering HTML can still be achieved using [views](/v4/views) and [Blade](/v4/blade). Blade is an extremely light-weight templating language that provides convenient, short syntax for displaying data, iterating over data, and more:

```blade
<div>
    @foreach ($users as $user)
        Hello, {{ $user->name }} <br />
    @endforeach
</div>
```

When building applications in this fashion, form submissions and other page interactions typically receive an entirely new HTML document from the server and the entire page is re-rendered by the browser. Even today, many applications may be perfectly suited to having their frontends constructed in this way using simple Blade templates.

<a name="growing-expectations"></a>
#### Growing Expectations

However, as user expectations regarding web applications have matured, many developers have found the need to build more dynamic frontends with interactions that feel more polished. In light of this, some developers choose to begin building their application's frontend using JavaScript frameworks such as React, Svelte, and Vue.

Others, preferring to stick with the backend language they are comfortable with, have developed solutions that allow the construction of modern web application UIs while still primarily utilizing their backend language of choice. For example, in the [Rails](https://rubyonrails.org/) ecosystem, this has spurred the creation of libraries such as [Turbo](https://turbo.hotwired.dev/) [Hotwire](https://hotwired.dev/), and [Stimulus](https://stimulus.hotwired.dev/).

<a name="php-starter-kits"></a>
### Starter Kits

<a name="using-react-svelte-or-vue"></a>
## Using React, Svelte, or Vue

Many developers prefer to leverage the power of a JavaScript framework like React, Svelte, or Vue. This allows developers to take advantage of the rich ecosystem of JavaScript packages and tools available via NPM.

However, without additional tooling, pairing LaraGram with React, Svelte, or Vue would leave us needing to solve a variety of complicated problems such as client-side routing, data hydration, and authentication. Client-side routing is often simplified by using opinionated React / Svelte / Vue frameworks such as [Next](https://nextjs.org/) and [Nuxt](https://nuxt.com/); however, data hydration and authentication remain complicated and cumbersome problems to solve when pairing a backend framework like LaraGram with these frontend frameworks.

In addition, developers are left maintaining two separate code repositories, often needing to coordinate maintenance, releases, and deployments across both repositories. While these problems are not insurmountable, we don't believe it's a productive or enjoyable way to develop applications.

<a name="luna"></a>
### Luna

Thankfully, LaraGram offers the best of both worlds. [Luna](https://laraxgram.github.io/v4/luna) bridges the gap between your LaraGram application and your modern React, Svelte, or Vue frontend, allowing you to build full-fledged, modern frontends using React, Svelte, or Vue while leveraging LaraGram routes and controllers for routing, data hydration, and authentication — all within a single code repository. With this approach, you can enjoy the full power of both LaraGram and React / Svelte / Vue without crippling the capabilities of either tool.

After installing Luna into your LaraGram application, you will write routes and controllers like normal. However, instead of returning a Blade template from your controller, you will return an Luna page:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use LaraGram\Luna\Luna;
use LaraGram\Luna\Response;

class UserController extends Controller
{
    /**
     * Show the profile for a given user.
     */
    public function show(string $id): Response
    {
        return Luna::render('users/show', [
            'user' => User::findOrFail($id)
        ]);
    }
}
```

An Luna page corresponds to a React, Svelte, or Vue component, typically stored within the `resources/js/pages` directory of your application. The data given to the page via the `Luna::render` method will be used to hydrate the "props" of the page component:

```jsx
import Layout from '@/layouts/authenticated';
import { Head } from '@laraxgram/react';

export default function Show({ user }) {
    return (
        <Layout>
            <Head title="Welcome" />
            <h1>Welcome</h1>
            <p>Hello {user.name}, welcome to Luna.</p>
        </Layout>
    )
}
```

As you can see, Luna allows you to leverage the full power of React, Svelte, or Vue when building your frontend, while providing a light-weight bridge between your LaraGram powered backend and your JavaScript powered frontend.

#### Server-Side Rendering

If you're concerned about diving into Luna because your application requires server-side rendering, don't worry. Luna offers [server-side rendering support](https://laraxgram.github.io/v4/luna#server-side-rendering). And, when deploying your application via [LaraGram Cloud](https://cloud.LaraGram.com) or [LaraGram Forge](https://forge.LaraGram.com), it's a breeze to ensure that Luna's server-side rendering process is always running.

<a name="luna-starter-kits"></a>
### Starter Kits

If you would like to build your frontend using Luna and React / Svelte / Vue, you can leverage our [React, Svelte, or Vue application starter kits](/v4/starter-kits) to jump-start your application's development. All of these starter kits scaffold your application's backend and frontend authentication flow using Luna, React / Svelte / Vue, [Tailwind](https://tailwindcss.com), and [Vite](https://vitejs.dev) so that you can start building your next big idea.

<a name="bundling-assets"></a>
## Bundling Assets

Regardless of whether you choose to develop your frontend using Blade or React / Svelte / Vue and Luna, you will likely need to bundle your application's CSS into production-ready assets. Of course, if you choose to build your application's frontend with React, Svelte, or Vue, you will also need to bundle your components into browser ready JavaScript assets.

By default, LaraGram utilizes [Vite](https://vitejs.dev) to bundle your assets. Vite provides lightning-fast build times and near instantaneous Hot Module Replacement (HMR) during local development. In all new LaraGram applications, including those using our [starter kits](/v4/starter-kits), you will find a `vite.config.js` file that loads our light-weight LaraGram Vite plugin that makes Vite a joy to use with LaraGram applications.

The fastest way to get started with LaraGram and Vite is by beginning your application's development using [our application starter kits](/v4/starter-kits), which jump-starts your application by providing frontend and backend authentication scaffolding.

> [!NOTE]
> For more detailed documentation on utilizing Vite with LaraGram, please see our [dedicated documentation on bundling and compiling your assets](/v4/vite).
