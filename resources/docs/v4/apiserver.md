# LaraGram API Server

- [Introduction](#introduction)
- [Installation](#installation)
    - [Server Prerequisites](#server-prerequisites)
    - [API Credentials](#api-credentials)
    - [Global Installation](#global-installation)
    - [Project Installation](#project-installation)
- [Running the Server](#running-the-server)
    - [Sending Requests](#sending-requests)
    - [Available Options](#available-options)
    - [Local Mode](#local-mode)
    - [Switching From the Cloud Bot API](#switching-from-the-cloud-bot-api)
- [Using the Server With LaraGram](#using-the-server-with-laragram)
    - [Configuration](#configuration)
    - [Starting the Server](#starting-the-server)
- [Storage](#storage)
    - [The Working Directory](#the-working-directory)
    - [Serving Files](#serving-files)
- [Statistics](#statistics)
- [Logging](#logging)
- [Running in Production](#running-in-production)

<a name="introduction"></a>
## Introduction

[LaraGram API Server](https://github.com/laraxgram/apiserver) is a Telegram Bot API server written in PHP and built on top of [LaraGram MTProto](/v4/mtproto). It is a drop-in replacement for the official [telegram-bot-api](https://github.com/tdlib/telegram-bot-api) server: it accepts the same command-line options, serves the same HTTP API, and returns the same responses and error descriptions. Every Bot API method is supported, and `file_id` values are interchangeable with the ones issued by `api.telegram.org`.

Since the server speaks the plain Bot API over HTTP, it is not tied to LaraGram. Any Telegram bot library in any language may use it by pointing its API URL at the server instead of `https://api.telegram.org`.

Running your own Bot API server gives you the same benefits as the official local server, such as uploading files up to 2000 MB, downloading files without a size limit, and receiving updates through webhooks on any IP address and port.

<a name="installation"></a>
## Installation

<a name="server-prerequisites"></a>
### Server Prerequisites

The API Server requires PHP 8.3 or greater along with the following PHP extensions:

- [Swoole](https://github.com/swoole/swoole-src)
- JSON
- Mbstring

Typically, the Swoole extension can be installed via PECL:

```shell
pecl install swoole
```

<a name="api-credentials"></a>
### API Credentials

Like the official server, the API Server logs in to Telegram with an application identifier. Sign in at [my.telegram.org](https://my.telegram.org), open **API development tools**, and create an application. Telegram will give you an `api_id` (a number) and an `api_hash` (a 32 character string). You will pass both of these values to the server when starting it.

> [!WARNING]
> Your `api_hash` is a secret. Never commit it to source control or share it publicly.

<a name="global-installation"></a>
### Global Installation

The most convenient way to install the API Server is as a global Composer package, which makes the `laragram-server` command available everywhere:

```shell
composer global require laraxgram/apiserver
```

Make sure Composer's global `bin` directory is in your system's `$PATH`. You may find the location of this directory by running:

```shell
composer global config bin-dir --absolute
```

Then, verify the installation:

```shell
laragram-server --version
```

<a name="project-installation"></a>
### Project Installation

Alternatively, you may install the API Server as a dependency of a single project. In that case, the server binary is available at `vendor/bin/laragram-server`:

```shell
composer require laraxgram/apiserver

vendor/bin/laragram-server --version
```

> [!NOTE]
> On its first start, the server compiles the MTProto TL schema into the `laraxgram/mtproto` package directory of your Composer installation. The user running the server must have write access to that directory the first time it starts. Later starts reuse the compiled classes.

<a name="running-the-server"></a>
## Running the Server

Start the server by passing your API credentials. The `--local` option enables [local mode](#local-mode), which is what you will want in most cases:

```shell
laragram-server --api-id=123456 --api-hash=0123456789abcdef0123456789abcdef --local
```

Instead of passing the credentials as options, you may define the `API_ID` and `API_HASH` environment variables:

```shell
export API_ID=123456
export API_HASH=0123456789abcdef0123456789abcdef

laragram-server --local
```

By default, the server listens on port `8081` of every local IPv4 address and stores its data in the current directory. You may change these with the `--http-port`, `--http-ip-address`, and `--dir` options:

```shell
laragram-server --local --http-ip-address=127.0.0.1 --http-port=8081 --dir=/var/lib/laragram-server
```

Press `Ctrl+C` to stop the server.

<a name="sending-requests"></a>
### Sending Requests

Requests are sent exactly like they are sent to `api.telegram.org`, using the server's address as the base URL:

```shell
curl http://127.0.0.1:8081/bot<token>/getMe
```

The first request for a bot token logs that bot in. Later requests reuse the same connection, and the bot stays logged in across server restarts. Bots on Telegram's test environment may be used by adding `/test` after the token:

```shell
curl http://127.0.0.1:8081/bot<token>/test/getMe
```

<a name="available-options"></a>
### Available Options

The server accepts the same options as the official `telegram-bot-api` binary. You may display them at any time using the `--help` option:

<div class="overflow-auto">

| Option | Description |
| --- | --- |
| `-h`, `--help` | Display the help text and exit. |
| `--version` | Display the supported Bot API version and exit. |
| `--local` | Enable [local mode](#local-mode). |
| `--api-id` | The application identifier. Defaults to the `API_ID` environment variable. |
| `--api-hash` | The application identifier hash. Defaults to the `API_HASH` environment variable. |
| `-p`, `--http-port` | The HTTP listening port. Defaults to `8081`. |
| `-s`, `--http-stat-port` | The HTTP [statistics](#statistics) port. Disabled by default. |
| `-d`, `--dir` | The server working directory. Defaults to the current directory. |
| `-f`, `--files-dir` | The directory for storing downloaded files. Defaults to the working directory. |
| `-t`, `--temp-dir` | The directory for storing temporary files of uploads. Defaults to the system temporary directory. |
| `--filter` | `<remainder>/<modulo>`: serve only the bots whose `bot_user_id % modulo == remainder`. |
| `--max-webhook-connections` | The default maximum number of webhook connections per bot. Defaults to `100` in local mode and `40` otherwise. |
| `--http-ip-address` | The local IP address to accept HTTP connections on. Defaults to every local IPv4 address. |
| `--http-stat-ip-address` | The local IP address to accept statistics connections on. Defaults to every local IPv4 address. |
| `-l`, `--log` | The path of the [log](#logging) file. Defaults to the standard error stream. |
| `-v`, `--verbosity` | The [log](#logging) verbosity level. |
| `--memory-verbosity` | The memory log verbosity level. Defaults to `3`. |
| `--log-max-file-size` | The maximum size of the log file in bytes. |
| `-u`, `--username` | The effective user name to switch to after start-up. |
| `-g`, `--groupname` | The effective group name to switch to after start-up. |
| `-c`, `--max-connections` | The maximum number of open connections. |
| `--cpu-affinity` | The CPU affinity, as a 64-bit mask. |
| `--main-thread-affinity` | The CPU affinity of the main thread, as a 64-bit mask. |
| `--proxy` | An HTTP proxy for outgoing webhook requests, in the `http://host:port` format. |

</div>

> [!NOTE]
> Some options, such as `--memory-verbosity`, `--log-max-file-size`, and the CPU affinity options, are accepted for compatibility with scripts written for the official server.

<a name="local-mode"></a>
### Local Mode

When the server is started with the `--local` option, it behaves like the official server in local mode. In this mode, the server is able to:

- Download files without a size limit.
- Upload files up to 2000 MB.
- Upload files using their local path and the `file://` URI scheme.
- Use an HTTP URL for the webhook.
- Use any local IP address for the webhook.
- Use any port for the webhook.
- Set `max_webhook_connections` up to `100000`.
- Return the absolute local path as the `file_path` of a `getFile` request, so the file may be read directly from disk without downloading it.

Without `--local`, the server applies the same limits as `api.telegram.org`.

<a name="switching-from-the-cloud-bot-api"></a>
### Switching From the Cloud Bot API

Before a bot can be used with your own server, it must be logged out of the cloud Bot API. Call the [`logOut`](https://core.telegram.org/bots/api#logout) method on `api.telegram.org` once:

```shell
curl https://api.telegram.org/bot<token>/logOut
```

After that, every request of the bot should go to your server. Likewise, to move a bot to a different server, call `logOut` on the current one first. Use the [`close`](https://core.telegram.org/bots/api#close) method instead when you only want to restart the bot on the same server.

<a name="using-the-server-with-laragram"></a>
## Using the Server With LaraGram

<a name="configuration"></a>
### Configuration

The Bot API server used by your LaraGram application is configured in the `api_server` section of the `config/bot.php` configuration file:

```php
'api_server' => [
    'binary' => env("API_BINARY", "telegram-bot-api"),
    'endpoint' => env("API_ENDPOINT", "https://api.telegram.org"),
    'dir' => env("API_DIR", storage_path('app/api-server')),
    'log_dir' => '',
    'ip' => '127.0.0.1',
    'port' => 8081,
    'stat' => [
        'ip' => '',
        'port' => ''
    ],
    'api_id' => env("API_ID", ''),
    'api_hash' => env("API_HASH", ''),
],
```

The `binary` option determines which server program the `start:apiserver` command runs. It defaults to the official `telegram-bot-api` binary. To use the LaraGram API Server instead, set the `API_BINARY` environment variable to `laragram-server`, along with your API credentials and the address of the server:

```ini
API_BINARY=laragram-server
API_ENDPOINT=http://127.0.0.1:8081
API_ID=123456
API_HASH=0123456789abcdef0123456789abcdef
```

If the server is installed in your project rather than globally, use the path to the binary instead:

```ini
API_BINARY=vendor/bin/laragram-server
```

The `endpoint` option is the base URL that your application sends its Bot API requests to, so once it points at your server, every request of your bot goes through it. Remember to [log the bot out](#switching-from-the-cloud-bot-api) of the cloud Bot API first.

> [!NOTE]
> When you create your application using the [LaraGram installer](/v4/installation#creating-an-application), you may select **Local Bot API server** and choose the LaraGram API Server. The installer requires the package in your project and writes all of these environment variables for you.

<div class="overflow-auto">

| Key | Description |
| --- | --- |
| `binary` | The Bot API server program started by `start:apiserver`. Any binary accepting the `telegram-bot-api` options may be used. |
| `endpoint` | The base URL of the Bot API server your application sends requests to. |
| `dir` | The working directory of the server. |
| `log_dir` | The path of the server's log file. Logs go to the console when empty. |
| `ip` | The local IP address the server listens on. |
| `port` | The port the server listens on. |
| `stat` | The IP address and port of the [statistics](#statistics) page. Disabled when empty. |
| `api_id` | Your Telegram application identifier. |
| `api_hash` | Your Telegram application identifier hash. |

</div>

<a name="starting-the-server"></a>
### Starting the Server

Once configured, you may start the server using the `start:apiserver` Commander command. The server always starts in [local mode](#local-mode):

```shell
php laragram start:apiserver
```

The `--binary`, `--host`, `--port`, and `--dir` options may be used to override the configured values for a single run:

```shell
php laragram start:apiserver --binary=laragram-server --host=127.0.0.1 --port=8082
```

The output of the server is shown in your console, and pressing `Ctrl+C` stops it.

During development, the [`dev`](/v4/commander#the-dev-command) command starts the server together with the rest of your application whenever `API_ENDPOINT` points to `localhost` and your API credentials are set, so you rarely need to start it yourself.

<a name="storage"></a>
## Storage

<a name="the-working-directory"></a>
### The Working Directory

The server keeps all of its state in the working directory given by the `--dir` option. Every bot receives a directory of its own, named after its token, that holds its MTProto session, its settings, and the updates that have not been delivered yet. Bots on the test environment use the token followed by `:T`. The registered webhooks are stored in the `webhooks_db.json` file, so they are restored automatically when the server restarts.

```text
working-directory/
├── webhooks_db.json
└── 123456789:AAE.../
    ├── chat_info_db
    ├── options
    ├── tqueue
    └── ...
```

A bot's directory is deleted when the bot logs out, either through the `logOut` method or because Telegram rejected its token. Therefore, the working directory should be persistent and writable by the user running the server, and it should never be shared between two running servers.

> [!WARNING]
> The working directory contains the session of every bot served by the server. Anyone who can read it can act as your bots, so restrict its permissions accordingly.

<a name="serving-files"></a>
### Serving Files

Files fetched by the `getFile` method are stored in the bot's directory, or in the directory given by `--files-dir`. In local mode, `getFile` returns the absolute path of the file, so an application on the same machine can read it directly from disk.

Like the official server, the API Server does not serve the downloaded files over HTTP. Outside of local mode, or when your application runs on another machine, you may serve the files directory with a web server such as Nginx. The following configuration serves files at the same URLs as `api.telegram.org`, which are `/file/bot<token>/<file_path>`:

```nginx
location ~ ^/file/bot([^/]+)/(.*)$ {
    alias /var/lib/laragram-server/$1/$2;
}

location / {
    proxy_pass http://127.0.0.1:8081;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

<a name="statistics"></a>
## Statistics

The server can publish a statistics page with the number of bots, requests and updates per second, memory and CPU usage, and per-bot details such as webhooks and pending updates. To enable it, pass a port to the `--http-stat-port` option:

```shell
laragram-server --local --http-stat-port=8082 --http-stat-ip-address=127.0.0.1
```

The statistics are then available at `http://127.0.0.1:8082`. You may append `?id=<bot_id>` to the URL to view the statistics of a single bot. When starting the server through `start:apiserver`, the statistics address is taken from the `stat` option of the `api_server` configuration.

> [!WARNING]
> The statistics page lists the tokens of your bots. Bind it to a private address and never expose it publicly.

<a name="logging"></a>
## Logging

By default, the server writes its log to the standard error stream. The `--log` option writes it to a file instead; a relative path is resolved against the files directory:

```shell
laragram-server --local --log=/var/log/laragram-server.log --verbosity=3
```

The `--verbosity` option controls how much is logged:

<div class="overflow-auto">

| Level | Logged Messages |
| --- | --- |
| `0` | Fatal errors only. This is the default. |
| `1` | Errors. |
| `2` | Warnings, such as bots logging in and out. |
| `3` | Informational messages. |
| `4` and higher | Debug messages. |

</div>

When a bot fails to log in, the reason is logged at the warning level, so starting the server with `--verbosity=2` or higher is a good first step when a bot does not respond as expected. The verbosity level may also be changed while the server is running by opening the statistics page with the `v` query parameter, for example `http://127.0.0.1:8082/?v=3`.

<a name="running-in-production"></a>
## Running in Production

In production, you should use a process monitor to keep the server running and restart it if it stops. For example, on Linux distributions using systemd, you may create a `/etc/systemd/system/laragram-server.service` unit file:

```ini
[Unit]
Description=LaraGram API Server
After=network-online.target
Wants=network-online.target

[Service]
User=laragram
Group=laragram
Environment=API_ID=123456
Environment=API_HASH=0123456789abcdef0123456789abcdef
ExecStart=/home/laragram/.config/composer/vendor/bin/laragram-server --local --http-ip-address=127.0.0.1 --http-port=8081 --dir=/var/lib/laragram-server --log=/var/log/laragram-server/server.log --verbosity=2
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Adjust the `ExecStart` path to the location of your `laragram-server` binary, create the working and log directories with the correct owner, and then enable and start the service:

```shell
sudo systemctl daemon-reload
sudo systemctl enable --now laragram-server
```

The server responds to `SIGTERM` by closing every bot gracefully, so `systemctl stop` and `systemctl restart` are safe to use.
