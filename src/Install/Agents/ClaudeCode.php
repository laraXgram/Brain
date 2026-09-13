<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Agents;

use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Enums\McpInstallationStrategy;
use LaraGram\Brain\Install\Enums\Platform;

class ClaudeCode extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function name(): string
    {
        return 'claude_code';
    }

    public function displayName(): string
    {
        return 'Claude Code';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v claude',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where claude 2>nul',
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.claude'],
            'files' => ['CLAUDE.md'],
        ];
    }

    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    public function mcpConfigPath(): string
    {
        return config('brain.agents.claude_code.mcp_config_path', '.mcp.json');
    }

    public function guidelinesPath(): string
    {
        return config('brain.agents.claude_code.guidelines_path', 'CLAUDE.md');
    }

    public function skillsPath(): string
    {
        return config('brain.agents.claude_code.skills_path', '.claude/skills');
    }
}
