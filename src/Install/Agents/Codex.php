<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\McpInstallationStrategy;
use LaraGram\Brain\Install\Enums\Platform;

class Codex extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'codex';
    }

    public function displayName(): string
    {
        return 'Codex';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'which codex',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where codex 2>nul',
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.codex'],
            'files' => ['.codex/config.toml'],
        ];
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.codex.guidelines_path', 'AGENTS.md');
    }

    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    public function mcpConfigPath(): string
    {
        return config('brain.agents.codex.mcp_config_path', '.codex/config.toml');
    }

    public function mcpConfigKey(): string
    {
        return 'mcp_servers';
    }

    /** {@inheritDoc} */
    public function httpMcpServerConfig(string $url): array
    {
        return [
            'command' => 'npx',
            'args' => ['-y', 'mcp-remote', $url],
        ];
    }

    /** {@inheritDoc} */
    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return collect([
            'command' => $command,
            'args' => $args,
            'cwd' => config('brain.executable_paths.current_directory'),
            'env' => $env,
        ])->filter(fn ($value): bool => ! in_array($value, [[], null, ''], true))
            ->toArray();
    }

    public function skillsPath(): string
    {
        return config('brain.agents.codex.skills_path', '.agents/skills');
    }
}
