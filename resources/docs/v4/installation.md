# Installation

<a name="meet-laragram"></a>
## Meet LaraGram

LaraGram is a professional framework for building advanced Telegram bots, inspired by the powerful Laravel framework.
With its clean syntax and structured design, LaraGram helps you create smart and scalable bots — without getting lost in the technical clutter.

If you're familiar with LaraGram, you'll feel right at home with Laravel — and vice versa. The two are so closely aligned that learning one makes working with the other a breeze. Now’s the time to focus on building something incredible. Let us handle the details.

LaraGram strives to provide an amazing developer experience while providing powerful features such as thorough dependency injection, an expressive database abstraction layer, queues and scheduled jobs, unit and integration testing, and more.

Whether you are new to PHP bot frameworks or have years of experience, LaraGram is a framework that can grow with you. We'll help you take your first steps as a bot developer or give you a boost as you take your expertise to the next level. We can't wait to see what you build.

<a name="why-laragram"></a>
### Why LaraGram?

There are a variety of tools and frameworks available to you when building a bot application. However, we believe LaraGram is the best choice for building modern bot applications.

#### A Progressive Framework

We like to call LaraGram a "progressive" framework. By that, we mean that LaraGram grows with you. If you're just taking your first steps into bot development, LaraGram's vast library of documentation and guides will help you learn the ropes without becoming overwhelmed.

If you're a senior developer, LaraGram gives you robust tools for [dependency injection](/v4/container), [queues](/v4/queues), and more. LaraGram is fine-tuned for building professional bot applications and ready to handle enterprise work loads.

#### A Scalable Framework

LaraGram is incredibly scalable. Thanks to the scaling-friendly nature of PHP and LaraGram's built-in support for fast, distributed cache systems like Redis. In fact, LaraGram applications have been easily scaled to handle hundreds of millions of requests per month.

<a name="creating-a-laragram-project"></a>
## Creating a LaraGram Application

<a name="installing-php"></a>
### Installing PHP and the LaraGram Installer

Before creating your first LaraGram application, make sure that your local machine has [PHP](https://php.net), [Composer](https://getcomposer.org), and [the LaraGram installer](https://github.com/laraxgram/installer) installed.

If you don't have PHP and Composer installed on your local machine, the following commands will install PHP, Composer, and the LaraGram installer on macOS, Windows, or Linux:

::: code-group

```shell [macOS]
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.4)"
```

```shell [Windows PowerShell]
# Run as administrator...
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))
```

```shell [Linux]
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"
```
:::

After running one of the commands above, you should restart your terminal session. To update PHP, Composer, and the LaraGram installer after installing them via `php.new`, you can re-run the command in your terminal.

If you already have PHP and Composer installed, you may install the LaraGram installer via Composer:

```shell
composer global require laraxgram/installer
```

<a name="creating-an-application"></a>
### Creating an Application

After you have installed PHP, Composer, and the LaraGram installer, you're ready to create a new LaraGram application:

```shell
laragram new example-app
```

The installer asks all of its questions up front, then installs and configures the application without interrupting you. First, it asks which parts of LaraGram you would like to configure. Only the Bot API is selected by default:

| Feature | What the installer asks and configures |
| --- | --- |
| Bot API | Your bot token, which is verified with Telegram so the bot's username and ID are written to your `.env` file as well, the URL Telegram should send updates to, and whether the webhook should be set once the application is installed. |
| Local Bot API server | Which [Bot API server](/v4/apiserver) you would like to run, the official `telegram-bot-api` binary or the LaraGram API Server, and your Telegram API ID and hash. The bot is pointed at the local server, which also accepts plain HTTP webhook URLs. The LaraGram API Server is installed with Composer. |
| MTProto | Installs [MTProto](/v4/mtproto) for userbot / client development. When a local Bot API server is configured too, MTProto may use the same API ID and hash or its own. |
| Telegram Mini App | Which [starter kit](/v4/starter-kits) you would like to build your Mini App with: React, Vue, or Svelte. |
| LaraGram Brain | Installs [LaraGram Brain](/v4/brain) to improve AI assisted coding. |
| API routes | Creates the `routes/api.php` file for your application's API. |
| LaraGram Surge | Which server [Surge](/v4/surge) should use. MTProto and the LaraGram API Server run on Surge with Swoole, so Surge is configured for them automatically. |

Finally, the installer asks which database your application will use. Only the drivers whose PDO extension is installed may be selected, and SQLite is used by default.

Every question may be skipped by pressing Enter, and you may also provide the values later in your application's `.env` file. Once the application has been created, the installer displays the commands you need to start it. Typically, you will start all of the processes your application needs, such as the development server, the queue worker, and your local Bot API server, using the [`dev`](/v4/commander#the-dev-command) Commander command:

```shell
cd example-app
php laragram dev
```

Once you have started the development server, your application will be accessible at `http://localhost:9000` for set webhook. Next, you're ready to [start taking your next steps into the LaraGram ecosystem](#next-steps). Of course, you may also want to [configure a database](#databases-and-migrations).

<a name="installer-options"></a>
#### Installer Options

Each question of the installer may also be answered using an option, which allows you to create applications without any interaction, for example from a script:

```shell
laragram new example-app --token=123456:ABC-DEF --bot-url=https://example.com --webhook --database=mysql --surge=swoole --git -n
```

| Option | Description |
| --- | --- |
| `--token` | The Telegram bot token from @BotFather. |
| `--bot-url` | The URL Telegram should send updates to. It must use HTTPS, unless a local Bot API server is configured. |
| `--webhook` | Set the bot webhook after installation. Requires `--token` and `--bot-url`, and is not available with a local Bot API server, since the server is not running yet. |
| `--api-server` | Run a local Bot API server: `official` (default) or `laragram`. |
| `--api-id`, `--api-hash` | Your Telegram API credentials from [my.telegram.org](https://my.telegram.org), used by the local Bot API server and MTProto. |
| `--mtproto` | Install MTProto for userbot / client development. |
| `--client-api-id`, `--client-api-hash` | Separate credentials for MTProto, next to a local Bot API server. They are stored as `CLIENT_API_ID` and `CLIENT_API_HASH`. |
| `--react`, `--vue`, `--svelte` | Install the given Mini App starter kit. |
| `--using` | Install a community maintained starter kit. |
| `--brain` | Install [LaraGram Brain](/v4/brain). |
| `--api` | Create the API routes file. |
| `--surge` | Install LaraGram Surge with the given server: `swoole` (default), `roadrunner`, or `frankenphp`. |
| `--database` | The database driver: `sqlite`, `mysql`, `mariadb`, `pgsql`, or `sqlsrv`. |
| `--npm`, `--pnpm`, `--bun`, `--yarn`, `--no-node` | Choose the package manager that installs and builds the frontend dependencies, or skip them. |
| `--git`, `--branch` | Initialize a Git repository with the given default branch. |
| `--github`, `--organization` | Create a repository on GitHub using the `gh` CLI and push the application to it. |
| `--dev` | Install the latest development release. |
| `--force` | Install even if the directory already exists. |

When the installer is executed by an AI coding agent, it automatically runs without interaction and reports its result as JSON.

<a name="initial-configuration"></a>
## Initial Configuration

All of the configuration files for the LaraGram framework are stored in the `config` directory. Each option is documented, so feel free to look through the files and get familiar with the options available to you.

LaraGram needs almost no additional configuration out of the box. You are free to get started developing! However, you may wish to review the `config/app.php` file and its documentation. It contains several options such as `url` and `locale` that you may wish to change according to your application.

<a name="environment-based-configuration"></a>
### Environment Based Configuration

Since many of LaraGram's configuration option values may vary depending on whether your application is running on your local machine or on a production web server, many important configuration values are defined using the `.env` file that exists at the root of your application.

Your `.env` file should not be committed to your application's source control, since each developer / server using your application could require a different environment configuration. Furthermore, this would be a security risk in the event an intruder gains access to your source control repository, since any sensitive credentials would be exposed.

> [!NOTE]
> For more information about the `.env` file and environment based configuration, check out the full [configuration documentation](/v4/configuration#environment-configuration).

<a name="config-bot-connections"></a>
### Config Bot Connection

You can easily create multiple connections for your bot. To do this, define your bot in the `config/bot.php` file under the connections section.
Simply set your token and url, then run the following command:
```shell
php laragram webhook:set
```

Additionally, you can customize the `endpoint` in the `api_server` section based on your needs.
Or, by setting `api_id` and `api_hash`, you can start your own API server by running:
```shell
php laragram start:apiserver
```

The command runs the official `telegram-bot-api` binary by default. You may also use the [LaraGram API Server](/v4/apiserver) by setting the `API_BINARY` environment variable to `laragram-server`.

<a name="long-polling"></a>
### Long Polling

A webhook needs a public HTTPS URL. While developing, or on a server Telegram cannot reach, your bot may fetch its updates with long polling instead. Start the development server with the `--polling` option, and it polls the bot next to serving your web routes:

```shell
php laragram serve --polling
```

Or run the poller on its own:

```shell
php laragram poll
```

Each update is handled in its own process, exactly like a webhook update. The poller handles up to 10 updates at the same time; pass `--concurrency=1` to handle them one by one, in the order they arrived. The command polls the `default` connection, or every connection when the default is `auto`; pick connections with `--connection` (or `-c`):

```shell
php laragram poll -c first -c second
```

Telegram does not deliver updates through `getUpdates` while a webhook is set, so the poller refuses to start for a connection that has one. Run `php laragram webhook:delete` first, or pass `--delete-webhook`. Pass `--drop-pending` to skip the updates that arrived while the bot was offline. When you stop the poller with `Ctrl+C`, it waits for the running updates and confirms them to Telegram, so they are not delivered again.

> [!NOTE]
> When [Surge](/v4/surge#long-polling) serves your application, start it with `php laragram surge:start --polling` instead: the updates are handed to Surge's workers.

<a name="databases-and-migrations"></a>
### Databases and Migrations

Now that you have created your LaraGram application, you probably want to store some data in a database. By default, your application's `.env` configuration file specifies that LaraGram will be interacting with an SQLite database.

During the creation of the application, LaraGram created a `database/database.sqlite` file for you, and ran the necessary migrations to create the application's database tables.

If you prefer to use another database driver such as MySQL or PostgreSQL, you can update your `.env` configuration file to use the appropriate database. For example, if you wish to use MySQL, update your `.env` configuration file's `DB_*` variables like so:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laragram
DB_USERNAME=root
DB_PASSWORD=
```

If you choose to use a database other than SQLite, you will need to create the database and run your application's [database migrations](/v4/migrations):

```shell
php laragram migrate
```

<a name="how-to-use-this-documentation"></a>
## How to Use This Documentation

The LaraGram documentation is organized in the sidebar into sections that follow your journey as a bot developer — from your very first `/start` handler to advanced, large-scale applications. You do **not** need to read every section. Pick the ones that match what you are building.

Here is what each section covers and when to read it:

<div class="content-list" markdown="1">

- **Prologue** — Release notes, upgrade guides, and contribution notes. Skim it when you are upgrading LaraGram or want to know what changed.
- **Getting Started** — Installation, configuration, directory structure, and deployment. **Start here.** Everyone should read this section first.
- **Architecture Concept** — How LaraGram works under the hood: the request lifecycle, the service container, service providers, and facades. Read this once you want to understand *why* things work the way they do.
- **The Basics** — The essentials of building a bot: listening for updates, controllers, requests, keyboards, templates, and more. **If you are building a simple bot, this is the section for you** — most bots never need anything beyond it.
- **Digging Deeper** — Powerful add-ons for more capable bots: conversations, queues, caching, events, scheduling, localization, and other supporting components. Reach for these as your bot grows and you need more than the basics.
- **MTProto** — Building with the MTProto (user-account) client instead of the Bot API. Read this only if you need MTProto features such as acting as a user, large media, or advanced chat management.
- **TMAs & SPAs** — Building Telegram Mini Apps and single-page apps with Luna. Read this if your bot ships an in-Telegram web interface.
- **Web** — The HTTP side of LaraGram: routing, controllers, views, sessions, and authentication for a browser-facing application or dashboard. Read this if your project serves web pages, not just Telegram updates.
- **Security** — Authentication, authorization, encryption, and hashing. Read this whenever you store user data or gate access to features.
- **Database** — Connecting to a database, the query builder, migrations, seeding, and pagination. Read this as soon as your bot needs to persist data.
- **Eloquent ORM** — LaraGram's expressive ActiveRecord implementation: models, relationships, collections, mutators, and serialization. Read this to work with your data as objects instead of raw queries.
- **Packages** — Extending LaraGram and building reusable packages. Read this when you want to share functionality across projects or with the community.

</div>

In short: read **Getting Started**, then **The Basics** to build a simple bot. Add **Database** and **Eloquent ORM** when you need to store data, **Digging Deeper** for advanced features, and the remaining sections (MTProto, TMAs & SPAs, Web, Security, Packages) as your specific needs call for them.

<a name="next-steps"></a>
## Next Steps

Now that you have created your LaraGram application, you may be wondering what to learn next. First, we strongly recommend becoming familiar with how LaraGram works by reading the following documentation:

<div class="content-list" markdown="1">

- [Request Lifecycle](/v4/lifecycle)
- [Configuration](/v4/configuration)
- [Directory Structure](/v4/structure)
- [Service Container](/v4/container)
- [Facades](/v4/facades)

</div>

How you want to use LaraGram will also dictate the next steps on your journey. There are a variety of ways to use LaraGram, and we'll explore two primary use cases for the framework below.
