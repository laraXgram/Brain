<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\Platform;

class Antigravity extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'antigravity';
    }

    public function displayName(): string
    {
        return 'Antigravity';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v antigravity',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where antigravity 2>nul',
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.gemini'],
            'files' => ['.agents/mcp_config.json'],
        ];
    }

    public function mcpConfigPath(): string
    {
        return config('brain.agents.antigravity.mcp_config_path', '.agents/mcp_config.json');
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.antigravity.guidelines_path', 'AGENTS.md');
    }

    public function skillsPath(): string
    {
        return config('brain.agents.antigravity.skills_path', '.agents/skills');
    }
}
