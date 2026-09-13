<?php

declare(strict_types=1);

namespace LaraGram\Brain\Contracts;

/**
 * Contract for agents that support MCP (Model Context Protocol).
 */
interface SupportsMcp
{
    /**
     * Whether to use absolute paths for MCP commands.
     */
    public function useAbsolutePathForMcp(): bool;

    /**
     * Get the PHP executable path for this MCP client.
     */
    public function getPhpPath(bool $forceAbsolutePath = false): string;

    /**
     * Get the Commander ("laragram" script) path for this MCP client.
     */
    public function getCommanderPath(bool $forceAbsolutePath = false): string;

    /**
     * Install an MCP server configuration in this IDE.
     *
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    public function installMcp(string $key, string $command, array $args = [], array $env = []): bool;

    /**
     * Install an HTTP MCP server configuration in this IDE.
     */
    public function installHttpMcp(string $key, string $url): bool;
}
