<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\McpInstallationStrategy;
use LaraGram\Brain\Install\Enums\Platform;

class GrokBuild extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'grok_build';
    }

    public function displayName(): string
    {
        return 'Grok Build';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v grok',
                'paths' => ['~/.grok'],
            ],
            Platform::Windows => [
                'command' => 'cmd /c where grok 2>nul',
                'paths' => ['%USERPROFILE%\\.grok'],
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.grok'],
            'files' => ['.grok/config.toml'],
        ];
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.grok_build.guidelines_path', 'AGENTS.md');
    }

    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    public function mcpConfigPath(): string
    {
        return config('brain.agents.grok_build.mcp_config_path', '.grok/config.toml');
    }

    public function mcpConfigKey(): string
    {
        return 'mcp_servers';
    }

    /** {@inheritDoc} */
    public function httpMcpServerConfig(string $url): array
    {
        return [
            'url' => $url,
        ];
    }

    /** {@inheritDoc} */
    public function mcpServerConfig(string $command, array $args = [], array $env = []): array
    {
        return collect([
            'command' => $command,
            'args' => $args,
            'env' => $env,
        ])->filter(fn ($value): bool => ! in_array($value, [[], null, ''], true))
            ->toArray();
    }

    public function skillsPath(): string
    {
        return config('brain.agents.grok_build.skills_path', '.grok/skills');
    }
}
