<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\Platform;

class Junie extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'junie';
    }

    public function displayName(): string
    {
        return 'Junie';
    }

    public function useAbsolutePathForMcp(): bool
    {
        return true;
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin => [
                'paths' => ['/Applications/PhpStorm.app'],
            ],
            Platform::Linux => [
                'paths' => [
                    '/opt/phpstorm',
                    '/opt/PhpStorm*',
                    '/usr/local/bin/phpstorm',
                    '~/.local/share/JetBrains/Toolbox/apps/PhpStorm/ch-*',
                ],
            ],
            Platform::Windows => [
                'paths' => [
                    '%ProgramFiles%\\JetBrains\\PhpStorm*',
                    '%LOCALAPPDATA%\\JetBrains\\Toolbox\\apps\\PhpStorm\\ch-*',
                    '%LOCALAPPDATA%\\Programs\\PhpStorm',
                ],
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.idea', '.junie'],
        ];
    }

    public function mcpConfigPath(): string
    {
        return config('brain.agents.junie.mcp_config_path', '.junie/mcp/mcp.json');
    }

    /** {@inheritDoc} */
    public function httpMcpServerConfig(string $url): array
    {
        return [
            'command' => 'npx',
            'args' => ['-y', 'mcp-remote', $url],
        ];
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.junie.guidelines_path', 'AGENTS.md');
    }

    public function skillsPath(): string
    {
        return config('brain.agents.junie.skills_path', '.junie/skills');
    }
}
