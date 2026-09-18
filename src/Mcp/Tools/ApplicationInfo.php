<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Tools;

use LaraGram\Brain\Discovery\Package;
use LaraGram\Brain\Discovery\ProjectManager;
use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Registrar;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaraGram\MTProto\Foundation\ClientManager;
use LaraGram\Support\Facades\DB;
use Throwable;

#[IsReadOnly]
class ApplicationInfo extends Tool
{
    public function __construct(protected ProjectManager $project)
    {
        //
    }

    /**
     * The tool's description.
     */
    protected string $description = 'Get comprehensive application information including PHP version, LaraGram version, database engine, all installed packages with their versions, the bot connections and update handling, MTProto sessions, Surge, Luna / Telegram Mini App and MCP server details. Tokens, secrets and session keys are never included. You should use this tool on each new chat, and use the package & version data to write version specific code for the packages that exist.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        return Response::json(array_filter([
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'laragram_version' => app()->version(),
            'environment' => app()->environment(),
            'database_engine' => rescue(fn () => DB::connection()->getDriverName(), null, report: false),
            'packages' => $this->project->php()->packages()
                ->concat($this->project->js()->packages())
                ->map(fn (Package $package): array => [
                    'roster_name' => PackageRegistry::rosterName($package->name()),
                    'version' => $package->version(),
                    'package_name' => $package->name(),
                ]),
            'bot' => $this->safely(fn (): array => $this->bot()),
            'mtproto' => $this->safely(fn (): ?array => $this->mtproto()),
            'surge' => $this->safely(fn (): ?array => $this->surge()),
            'luna' => $this->safely(fn (): ?array => $this->luna()),
            'web' => $this->safely(fn (): array => $this->web()),
            'mcp_servers' => $this->safely(fn (): ?array => $this->mcpServers()),
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    protected function bot(): array
    {
        $connections = [];

        foreach ((array) config('bot.connections', []) as $name => $connection) {
            $connection = (array) $connection;

            $connections[] = [
                'name' => $name,
                'username' => ($connection['username'] ?? '') ?: null,
                'webhook_url' => ($connection['url'] ?? '') ?: null,
                'has_token' => ! empty($connection['token']),
                'has_secret_token' => ! empty($connection['secret_token']),
                'allowed_updates' => $connection['allowed_updates'] ?? ['*'],
            ];
        }

        $listens = base_path('listens');

        return [
            'default_connection' => config('bot.default'),
            'connections' => $connections,
            'update_type' => config('laraquest.update_type', 'sync'),
            'request_mode' => config('laraquest.default_mode', 'curl'),
            'anti_flood' => [
                'enabled' => (bool) config('bot.anti_flood.enabled', false),
                'store' => config('bot.anti_flood.store'),
            ],
            'broadcasting' => $this->broadcasting(),
            'api_server_endpoint' => config('bot.api_server.endpoint'),
            'listen_files' => is_dir($listens)
                ? array_map(fn (string $file): string => 'listens/'.basename($file), glob($listens.DIRECTORY_SEPARATOR.'*.php') ?: [])
                : [],
            'templates_path' => is_dir(app_path('templates')) ? 'app/templates' : null,
            'conversations_path' => is_dir(app_path('Conversations')) ? 'app/Conversations' : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function broadcasting(): ?array
    {
        if (! class_exists(\LaraGram\Broadcasting\BroadcastManager::class)) {
            return null;
        }

        $bootstrap = base_path('bootstrap/app.php');
        $bootstrap = is_file($bootstrap) ? (string) file_get_contents($bootstrap) : '';

        return [
            'default_connection' => config('broadcasting.default'),
            'drivers' => array_map(
                fn ($connection) => $connection['driver'] ?? null,
                (array) config('broadcasting.connections', [])
            ),
            'channels_file' => is_file(base_path('listens/channels.php')) ? 'listens/channels.php' : null,
            'with_broadcasting' => str_contains($bootstrap, 'withBroadcasting('),
            'tracks_chats' => str_contains($bootstrap, 'TrackChats'),
            'store' => config('broadcasting.store'),
            'store_driver' => config('broadcasting.stores.'.config('broadcasting.store').'.driver'),
            'tracks_members' => (bool) config('broadcasting.tracking.members', true),
            'recallable_by_default' => (bool) config('broadcasting.recall.enabled', false),
            'audiences' => app()->bound(\LaraGram\Broadcasting\Telegram\AudienceRegistry::class)
                ? array_keys(app(\LaraGram\Broadcasting\Telegram\AudienceRegistry::class)->all()->all())
                : [],
            'progress_store' => config('broadcasting.progress.store') ?? config('cache.default'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mtproto(): ?array
    {
        if (! app()->bound('mtproto.manager')) {
            return null;
        }

        /** @var ClientManager $manager */
        $manager = app('mtproto.manager');

        return [
            'configured_sessions' => array_keys((array) config('mtproto.sessions', [])),
            'authorized_sessions' => $manager->authorizedSessions(),
            'driver' => config('mtproto.driver'),
            'use_pump' => (bool) config('mtproto.use_pump'),
            'surge_autostart' => (bool) config('mtproto.surge.autostart'),
            'surge_isolation' => config('mtproto.surge.isolation'),
            'rpc_enabled' => (bool) config('mtproto.rpc.enabled'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function surge(): ?array
    {
        if (config('surge') === null) {
            return null;
        }

        return [
            'server' => config('surge.server'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function luna(): ?array
    {
        if (config('luna') === null) {
            return null;
        }

        $guards = array_keys(array_filter(
            (array) config('auth.guards', []),
            fn (mixed $guard): bool => is_array($guard) && ($guard['driver'] ?? null) === 'telegram',
        ));

        return [
            'ssr_enabled' => (bool) config('luna.ssr.enabled', false),
            'pages_path' => is_dir(resource_path('js/Pages')) ? 'resources/js/Pages' : (is_dir(resource_path('js/pages')) ? 'resources/js/pages' : null),
            'telegram_guards' => $guards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function web(): array
    {
        return [
            'app_url' => config('app.url'),
            'web_routes' => is_file(base_path('routes/web.php')),
            'api_routes' => is_file(base_path('routes/api.php')),
        ];
    }

    /**
     * @return array<int, string>|null
     */
    protected function mcpServers(): ?array
    {
        if (! app()->bound(Registrar::class)) {
            return null;
        }

        return array_values(array_filter(
            array_keys(app(Registrar::class)->servers()),
            fn (string $server): bool => $server !== 'laragram-brain',
        ));
    }

    protected function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
