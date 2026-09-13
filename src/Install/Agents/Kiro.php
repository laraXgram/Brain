<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\Platform;

class Kiro extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'kiro';
    }

    public function displayName(): string
    {
        return 'Kiro';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin => [
                'paths' => ['/Applications/Kiro.app'],
            ],
            Platform::Linux => [
                'paths' => [
                    '/opt/kiro',
                    '/usr/local/bin/kiro',
                    '~/.local/bin/kiro',
                ],
            ],
            Platform::Windows => [
                'paths' => [
                    '%ProgramFiles%\\Kiro',
                    '%LOCALAPPDATA%\\Programs\\Kiro',
                ],
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.kiro'],
        ];
    }

    public function httpMcpServerConfig(string $url): array
    {
        return [
            'url' => $url,
        ];
    }

    public function mcpConfigPath(): string
    {
        return config('brain.agents.kiro.mcp_config_path', '.kiro/settings/mcp.json');
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.kiro.guidelines_path', 'AGENTS.md');
    }

    public function skillsPath(): string
    {
        return config('brain.agents.kiro.skills_path', '.kiro/skills');
    }
}
