<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\Platform;

class Zed extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'zed';
    }

    public function displayName(): string
    {
        return 'Zed';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin => [
                'paths' => ['/Applications/Zed.app'],
            ],
            Platform::Linux => [
                'command' => 'command -v zed || command -v zeditor',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where zed 2>nul',
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.zed'],
        ];
    }

    public function mcpConfigPath(): string
    {
        return config('brain.agents.zed.mcp_config_path', '.zed/settings.json');
    }

    public function mcpConfigKey(): string
    {
        return 'context_servers';
    }

    /** {@inheritDoc} */
    public function httpMcpServerConfig(string $url): array
    {
        return [
            'url' => $url,
        ];
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.zed.guidelines_path', 'AGENTS.md');
    }

    public function skillsPath(): string
    {
        return config('brain.agents.zed.skills_path', '.agents/skills');
    }
}
