# LaraGram Armada

- [Introduction](#introduction)
- [Installation and Setup](#installation)
    - [Configuring a Shell Alias](#configuring-a-shell-alias)
    - [Adding Services](#adding-services)
    - [Using Devcontainers](#using-devcontainers)
- [Starting and Stopping Armada](#starting-and-stopping-armada)
- [Executing Commands](#executing-commands)
- [Telegram Bot API Servers](#telegram-bot-api-servers)
    - [Choosing a Server](#choosing-a-server)
    - [Credentials and Logging Out](#credentials-and-logging-out)
    - [Managing the Server](#managing-the-server)
    - [Local Files](#local-files)
    - [Server Images](#server-images)
- [Receiving Updates](#receiving-updates)
- [Running the Application](#running-the-application)
    - [Surge](#surge)
    - [Background Processes](#background-processes)
    - [MTProto](#mtproto)
    - [Luna](#luna)
    - [Sentinel](#sentinel)
- [Interacting With Databases](#interacting-with-databases)
- [PHP and Node Versions](#php-and-node-versions)
- [Debugging With Xdebug](#debugging-with-xdebug)
- [Customization](#customization)
- [Production](#production)
    - [Generating the Production Files](#generating-the-production-files)
    - [Deploying](#deploying)
    - [Container Roles](#container-roles)

<a name="introduction"></a>
## Introduction

LaraGram Armada is a command-line interface for working with LaraGram's Docker environment. It gives you PHP, Composer, Node, your databases, and a Telegram Bot API server without installing anything on your machine except Docker.

Armada is built around a `compose.yaml` file stored in the root of your project and an `armada` script installed in `vendor/bin`. It supports macOS, Linux, and Windows through [WSL2](https://docs.microsoft.com/en-us/windows/wsl/about), and PHP 8.3, 8.4, and 8.5.

Armada is derived from [Laravel Sail](https://github.com/laravel/sail), and adds what a Telegram application needs: the official and LaraGram Bot API servers, long polling and webhook delivery, and the MTProto, Luna, Surge, Sentinel, and Watchdog processes. It can also generate the [Docker files for deploying](#production) your application.

<a name="installation"></a>
## Installation and Setup

Install Armada as a development dependency, then run the `armada:install` command:

```shell
composer require laraxgram/armada --dev

php laragram armada:install
```

> [!NOTE]
> When you create your application using the [LaraGram installer](/v4/installation#creating-an-application), you may select **LaraGram Armada**. The installer requires Armada and runs the `armada:install` command for you, passing the database, Bot API server, and Surge choices you made during the installation.

The command asks which database and cache services you want, which [Bot API server](#telegram-bot-api-servers) the bot should use, how it should [receive updates](#receiving-updates), and which [processes](#background-processes) should run. It then writes `compose.yaml`, updates your `.env` file, and prepares the images.

You may answer the same questions with options, which is useful in scripts:

```shell
php laragram armada:install \
    --with=mysql,redis \
    --bot-api=official \
    --server=surge \
    --updates=webhook \
    --processes=queue,schedule,watchdog \
    --php=8.5 \
    --no-interaction
```

<div class="overflow-auto">

| Option | Values |
| --- | --- |
| `--with` | `mysql`, `pgsql`, `mariadb`, `mongodb`, `redis`, `valkey`, `memcached`, `docker-proxy`, or `none` |
| `--bot-api` | `cloud`, `official`, or `laragram` |
| `--server` | `serve` or `surge`. Defaults to `surge` when Surge is installed. |
| `--updates` | `polling` or `webhook` |
| `--processes` | A comma-separated list of [processes](#background-processes), or `none` |
| `--php` | `8.3`, `8.4`, or `8.5` |
| `--devcontainer` | Also create a [devcontainer](#using-devcontainers) configuration |

</div>

<a name="configuring-a-shell-alias"></a>
### Configuring a Shell Alias

Armada's commands are invoked through `vendor/bin/armada`. To shorten them, add an alias to your shell's configuration file, such as `~/.zshrc` or `~/.bashrc`:

```shell
alias armada='sh $([ -f armada ] && echo armada || echo vendor/bin/armada)'
```

The remaining examples on this page assume this alias exists.

<a name="adding-services"></a>
### Adding Services

To add services to an existing installation, use the `armada:add` command:

```shell
php laragram armada:add valkey,mongodb
```

The same command changes the Bot API server, the application server, the update method, or the processes. Only the settings you pass are changed:

```shell
php laragram armada:add --bot-api=laragram
php laragram armada:add --processes=queue,schedule,mtproto
```

<a name="using-devcontainers"></a>
### Using Devcontainers

Pass the `--devcontainer` option to the `armada:install` command to publish a `.devcontainer/devcontainer.json` file:

```shell
php laragram armada:install --devcontainer
```

<a name="starting-and-stopping-armada"></a>
## Starting and Stopping Armada

To start all of the containers, run the `up` command. Add `-d` to run them in the background:

```shell
armada up

armada up -d
```

Stop them with `Ctrl+C`, or with the `stop` command when they run in the background:

```shell
armada stop
```

Unknown commands are passed to Docker Compose, so `armada ps`, `armada logs -f`, and `armada down` work as expected.

<a name="executing-commands"></a>
## Executing Commands

Your application's commands should be run inside the containers, where the correct PHP version and services are available:

```shell
armada laragram migrate
armada laragram webhook:info
armada probe
armada watchdog

armada php --version
armada composer require laraxgram/surge
armada npm run build
```

<div class="overflow-auto">

| Command | Description |
| --- | --- |
| `armada laragram ...` | Run a [Commander](/v4/commander) command. `armada lg` is a shorthand. |
| `armada probe` | Start a [Probe](https://github.com/laraxgram/probe) session. |
| `armada watchdog` | Tail the application logs with [Watchdog](/v4/watchdog). |
| `armada php ...` | Run PHP. |
| `armada composer ...` | Run Composer. |
| `armada node`, `npm`, `npx`, `pnpm`, `pnpx`, `yarn`, `bun`, `bunx` | Run Node tools. |
| `armada bin ...` | Run a binary from `vendor/bin`. |
| `armada run ...` | Run any command in the application container. |
| `armada shell` | Start a shell in the application container. `armada root-shell` starts one as root. |

</div>

<a name="telegram-bot-api-servers"></a>
## Telegram Bot API Servers

<a name="choosing-a-server"></a>
### Choosing a Server

Armada can run your own Bot API server next to your application, in a container named `bot-api`:

<div class="overflow-auto">

| Value | Server |
| --- | --- |
| `cloud` | No container. The bot talks to `api.telegram.org`. |
| `official` | Telegram's official [telegram-bot-api](https://github.com/tdlib/telegram-bot-api) server. |
| `laragram` | The [LaraGram API Server](/v4/apiserver). |

</div>

Both servers listen on port `8081` in [local mode](/v4/apiserver#local-mode), so you may switch between them at any time. Switching is safe for your code because the API is the same:

```shell
php laragram armada:add --bot-api=official
```

When a server is selected, Armada sets `API_ENDPOINT=http://bot-api:8081` in your `.env` file. The port is also published on `127.0.0.1` of your machine; you may change it with the `FORWARD_BOT_API_PORT` variable.

<a name="credentials-and-logging-out"></a>
### Credentials and Logging Out

Both servers need an `API_ID` and `API_HASH` from [my.telegram.org](https://my.telegram.org). Armada asks for them during installation, or you may add them to your `.env` file later:

```ini
API_ID=123456
API_HASH=0123456789abcdef0123456789abcdef
```

A bot must be logged out of the cloud Bot API once before it can use your own server. Armada reads `BOT_TOKEN` from your `.env` file and does this for you:

```shell
armada bot-api logout
```

> [!WARNING]
> After logging out, a bot cannot log back in to `api.telegram.org` for 10 minutes.

<a name="managing-the-server"></a>
### Managing the Server

```shell
armada bot-api status
armada bot-api logs
armada bot-api restart
```

Set the `BOT_API_VERBOSITY` variable to `2` or higher when a bot does not respond as expected; login failures are logged at the warning level.

Each server also publishes its [statistics](/v4/apiserver#statistics) on port `8082` inside the Docker network. This port is never published on your machine because the page lists your bot tokens. When [Sentinel](/v4/sentinel) is installed, Armada sets `SENTINEL_API_SERVER_STATS_URL` so its **API Server** page works without any further configuration.

<a name="local-files"></a>
### Local Files

In local mode, `getFile` returns an absolute path instead of a download URL. Armada mounts the server's data at `/var/lib/bot-api` in both the `bot-api` and application containers, and runs both as your user, so your application can read the returned path directly and upload files using `file://` paths in that directory.

The LaraGram API Server may also [stream files](/v4/apiserver#streaming-files) at their `/file/` URLs, or [run without file storage](/v4/apiserver#running-without-file-storage) at all. Armada passes the same environment variables that `start:apiserver` reads to the `bot-api` container:

```ini
API_STREAM_FILES=true
API_STREAM_READ_AHEAD=4
API_FILE_STORAGE=false
```

Restart the server after changing them:

```shell
armada restart bot-api
```

<a name="server-images"></a>
### Server Images

Telegram does not publish a Docker image for its server, so LaraGram builds one from the official source and publishes it, along with the LaraGram API Server, to the GitHub Container Registry:

- `ghcr.io/laraxgram/telegram-bot-api`
- `ghcr.io/laraxgram/apiserver`

Armada pulls these images. If an image cannot be pulled, it is built from source instead. Compiling the official server takes between 15 and 40 minutes and several gigabytes of memory the first time.

Because `armada build` rebuilds every service that has a build section, prefer naming the service when you only want to rebuild your application:

```shell
armada build laragram.test
```

<a name="receiving-updates"></a>
## Receiving Updates

The `ARMADA_UPDATES` variable in your `.env` file controls how the bot receives updates:

- `polling` runs the bot with long polling next to the server. It needs no public URL, so it is the default when using the cloud Bot API.
- `webhook` expects Telegram to call your application.

A [local Bot API server](#telegram-bot-api-servers) can reach your application inside the Docker network, so webhooks work without a tunnel or a public URL. In this case Armada sets `BOT_URL=http://laragram.test`, and you only need to register the webhook once:

```shell
armada laragram webhook:set
```

With the cloud Bot API, webhooks require a public HTTPS URL that forwards to your application, set as `BOT_URL`.

> [!NOTE]
> Long polling fails while the bot has a webhook. Remove it first with `armada laragram webhook:delete`.

<a name="running-the-application"></a>
## Running the Application

The application is served on port `80` of the container, which is published on the port given by the `APP_PORT` variable. You may change the settings below in your `.env` file, then apply them with `armada up -d`.

<a name="surge"></a>
### Surge

The `ARMADA_SERVER` variable chooses the server, either `serve` or `surge`. When [Surge](/v4/surge) is installed it is selected automatically. Armada watches your files and reloads the workers when the `chokidar` Node package is installed in your project:

```shell
armada npm install --save-dev chokidar
```

<a name="background-processes"></a>
### Background Processes

The `ARMADA_PROCESSES` variable is a comma-separated list of processes that run next to the server, in the same container. Their output appears in `armada logs`, and each is restarted if it crashes.

```ini
ARMADA_PROCESSES=queue,schedule,watchdog
```

<div class="overflow-auto">

| Process | Command |
| --- | --- |
| `queue` | `queue:listen --tries=1 --timeout=0` |
| `schedule` | `schedule:work` |
| `watchdog` | `watchdog --timeout=0` |
| `mtproto` | `client:start --all` |
| `ssr` | `luna:start-ssr` |
| `sentinel-queue` | `sentinel:queue` |
| `sentinel-check` | `sentinel:check` |
| `vite` | The `dev` script of your `package.json`, using the project's package manager |

</div>

During installation, Armada only offers the processes that match the packages your project has installed.

Supervisor runs these processes, so you may inspect and control them with `supervisorctl` inside the application container:

```shell
armada root-shell

supervisorctl status
supervisorctl restart queue
```

> [!NOTE]
> `php laragram dev` is not needed under Armada. It binds to `localhost` inside the container, while the processes above already run the same commands.

<a name="mtproto"></a>
### MTProto

Armada's images include every extension [MTProto](/v4/mtproto) needs. Sessions should be authorized interactively before the client starts:

```shell
armada laragram client:auth
armada restart
```

Under Surge, the MTProto client runs as a Surge process, so the `mtproto` process is not offered. Otherwise, the `mtproto` process runs `client:start --all`.

<a name="luna"></a>
### Luna

Run the Vite development server with the `vite` process or with `armada npm run dev -- --host`. It is published on the port given by the `VITE_PORT` variable. To use server-side rendering, build the SSR bundle and add the `ssr` process:

```shell
armada npm run build
```

Telegram only opens Mini Apps over HTTPS, so testing inside the Telegram client requires a public HTTPS URL that forwards to your application.

<a name="sentinel"></a>
### Sentinel

Add `sentinel-check` to record server metrics, and `sentinel-queue` when your application uses a Redis queue. Do not combine `sentinel-queue` with `queue`.

Sentinel's [Armada page](/v4/sentinel#armada) shows every container of your project, their health, resource usage, and logs, and lets you start, stop, and restart containers and background processes. It reaches the Docker daemon through the `docker-proxy` service, a [socket proxy](https://github.com/Tecnativa/docker-socket-proxy) that only allows what the page needs:

```shell
php laragram armada:add docker-proxy
```

Adding the service sets `SENTINEL_DOCKER_HOST` to `tcp://docker-proxy:2375` in your `.env` file.

> [!WARNING]
> Anything that can reach the `docker-proxy` service can restart your containers and run commands in them. Keep it on Armada's internal network, never publish its port, and protect the Sentinel dashboard with its [authorization](/v4/sentinel#dashboard-authorization) in production.

<a name="interacting-with-databases"></a>
## Interacting With Databases

Armada creates a persistent volume for each database, and configures your `.env` file to use it. A `testing` database is also created for MySQL, MariaDB, and PostgreSQL.

```shell
armada mysql
armada mariadb
armada psql
armada mongodb
armada redis
armada valkey
```

From your machine, you may connect through `localhost` and the published port, which is changed with variables such as `FORWARD_DB_PORT` and `FORWARD_REDIS_PORT`. To remove the volumes and start over, run `armada down -v`.

<a name="php-and-node-versions"></a>
## PHP and Node Versions

Choose the PHP version when installing:

```shell
php laragram armada:install --php=8.4
```

To change it later, update the `PHP_VERSION` argument and `image` of the `laragram.test` service in your `compose.yaml` file, then rebuild. The same `args` section accepts `NODE_VERSION` and `PHP_EXTENSIONS`, a space-separated list of additional extensions:

```yaml
laragram.test:
    build:
        args:
            WWWGROUP: '${WWWGROUP}'
            PHP_VERSION: '8.4'
            NODE_VERSION: '22'
            PHP_EXTENSIONS: 'ldap soap'
    image: armada-8.4/app
```

```shell
armada build --no-cache laragram.test
armada up -d
```

<a name="debugging-with-xdebug"></a>
## Debugging With Xdebug

Xdebug is included but disabled. Enable it in your `.env` file before starting Armada:

```ini
ARMADA_XDEBUG_MODE=develop,debug,coverage
```

To debug a Commander command, use the `debug` command:

```shell
armada debug migrate
```

On Linux hosts running a Docker version older than 20.10, set `ARMADA_XDEBUG_CONFIG="client_host=<your-ip>"`.

<a name="customization"></a>
## Customization

Publish Armada's Docker files to the `docker` directory of your project and rebuild:

```shell
armada laragram armada:publish
armada build --no-cache laragram.test
```

The `compose.yaml` file is updated to use the published files. You may set `SUPERVISOR_PHP_COMMAND` on the service to replace the command that serves the application entirely. To combine Compose files, set the `ARMADA_FILES` variable to a colon-separated list of paths.

<a name="production"></a>
## Production

<a name="generating-the-production-files"></a>
### Generating the Production Files

The `armada:production` command creates a multi-stage `Dockerfile` in `docker/production`, a `.dockerignore` file, and a `compose.production.yaml` file:

```shell
php laragram armada:production
```

It accepts `--with`, `--bot-api`, `--server`, `--updates`, `--roles`, and `--php`. The server is either `surge` or `fpm`, which runs Nginx and PHP-FPM. Use `--force` to overwrite an existing Compose file.

The production image contains your code, its Composer dependencies, and your built assets, without development tools such as Xdebug. It runs as an unprivileged user, and OPcache does not check for file changes.

<a name="deploying"></a>
### Deploying

The production file reads your `.env` file. Passwords and Bot API credentials are required, and no database port is published. Set your production values, then build your image and start the containers:

```shell
armada prod build app
armada prod up -d
```

Every Armada command works with the `prod` prefix:

```shell
armada prod laragram migrate --force
armada prod laragram webhook:set
armada prod logs -f app
```

Set `ARMADA_MIGRATE=1` to run migrations each time the web container starts. Caches are built automatically on start, so `.env` is read when a container starts rather than when the image is built. Rebuild and run `armada prod up -d` to deploy a new version.

The application is published on the port given by the `APP_PORT` variable, `8000` by default. Armada does not terminate TLS: place a reverse proxy in front of it when your bot uses the cloud Bot API, because Telegram only delivers webhooks over HTTPS, or for Mini Apps. With a local Bot API server, webhooks are delivered inside the Docker network and `BOT_URL` is set to `http://app:8000` for you.

> [!WARNING]
> The `armada-bot-api` volume contains the session of every bot, and `armada-storage` contains your MTProto sessions. Back them up and restrict access to the Docker host accordingly.

<a name="container-roles"></a>
### Container Roles

Each role runs in its own container from the same image, selected by the `ARMADA_ROLE` variable. The command chooses roles based on your installed packages, and `--roles` overrides them:

<div class="overflow-auto">

| Role | Command |
| --- | --- |
| `queue` | `queue:work --tries=3 --max-time=3600` |
| `sentinel-queue` | `sentinel:queue` |
| `schedule` | `schedule:work` |
| `poller` | `poll` |
| `mtproto` | `client:start --all` |
| `ssr` | `luna:start-ssr` |
| `sentinel-check` | `sentinel:check` |

</div>

Authorize MTProto sessions before starting the `mtproto` role, with `armada prod laragram client:auth`. [Watchdog](/v4/watchdog) needs no container in production: its Telegram reporting runs inside your application.
