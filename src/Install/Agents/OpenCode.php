<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\McpInstallationStrategy;
use LaraGram\Brain\Install\Enums\Platform;
use stdClass;

class OpenCode extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'opencode';
    }

    public function displayName(): string
    {
        return 'OpenCode';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v opencode',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where opencode 2>nul',
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'files' => ['opencode.json', 'opencode.jsonc'],
        ];
    }

    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    public function mcpConfigPath(): string
    {
        if ($configured = config('brain.agents.opencode.mcp_config_path')) {
            return $configured;
        }

        return file_exists(base_path('opencode.jsonc'))
            ? 'opencode.jsonc'
            : 'opencode.json';
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.opencode.guidelines_path', 'AGENTS.md');
    }

    public function mcpConfigKey(): string
    {
        return 'mcp';
    }

    /** {@inheritDoc} */
    public function defaultMcpConfig(): array
    {
        return [
            '$schema' => 'https://opencode.ai/config.json',
        ];
    }

    /** {@inheritDoc} */
    public function httpMcpServerConfig(string $url): array
    {
        return [
            'type' => 'remote',
            'enabled' => true,
            'url' => $url,
            'oauth' => new stdClass,
        ];
    }

    /** {@inheritDoc} */
    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return [
            'type' => 'local',
            'enabled' => true,
            'command' => [$command, ...$args],
            'environment' => $env,
        ];
    }

    public function skillsPath(): string
    {
        return config('brain.agents.opencode.skills_path', '.agents/skills');
    }
}
