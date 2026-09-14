<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Assists;

class Bot
{
    /**
     * The names of the configured bot connections.
     *
     * @return list<string>
     */
    public function connections(): array
    {
        return array_map('strval', array_keys((array) config('bot.connections', [])));
    }

    public function defaultConnection(): string
    {
        return (string) config('bot.default', 'bot');
    }

    /**
     * Determine if the application serves several bots (or detects the connection by secret token).
     */
    public function usesMultipleBots(): bool
    {
        return count($this->connections()) > 1 || $this->defaultConnection() === 'auto';
    }

    /**
     * How updates are received: "sync" (webhook), "polling", "swoole", "openswoole", ...
     */
    public function updateType(): string
    {
        return (string) config('laraquest.update_type', 'sync');
    }

    public function usesAntiFlood(): bool
    {
        return (bool) config('bot.anti_flood.enabled', false);
    }

    /**
     * The listen files relative to the base path.
     *
     * @return list<string>
     */
    public function listenFiles(): array
    {
        $files = glob(base_path('listens').DIRECTORY_SEPARATOR.'*.php') ?: [];

        return array_values(array_map(fn (string $file): string => 'listens/'.basename($file), $files));
    }

    public function templatesPath(): string
    {
        return $this->relative((string) (config('template.paths.0') ?: app_path('templates')));
    }

    public function conversationsPath(): string
    {
        return $this->relative((string) (config('conversation.path') ?: app_path('Conversations')));
    }

    protected function relative(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_replace(DIRECTORY_SEPARATOR, '/', str_starts_with($path, $base) ? substr($path, strlen($base)) : $path);
    }
}
