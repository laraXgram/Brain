<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install;

use LaraGram\Brain\Contracts\SupportsMcp;
use RuntimeException;

class McpWriter
{
    public const SUCCESS = 0;

    public function __construct(protected SupportsMcp $agent)
    {
        //
    }

    public function write(): int
    {
        $this->installBrainMcp();

        return self::SUCCESS;
    }

    protected function installBrainMcp(): void
    {
        $mcp = $this->buildBrainMcpCommand();

        if (! $this->agent->installMcp($mcp['key'], $mcp['command'], $mcp['args'])) {
            throw new RuntimeException('Failed to install Brain MCP: could not write configuration');
        }
    }

    /**
     * @return array{key: string, command: string, args: array<int, string>}
     */
    protected function buildBrainMcpCommand(): array
    {
        if ($this->isRunningInsideWsl()) {
            return [
                'key' => 'laragram-brain',
                'command' => 'wsl.exe',
                'args' => [$this->agent->getPhpPath(true), $this->agent->getCommanderPath(true), 'brain:mcp'],
            ];
        }

        return [
            'key' => 'laragram-brain',
            'command' => $this->agent->getPhpPath(),
            'args' => [$this->agent->getCommanderPath(), 'brain:mcp'],
        ];
    }

    private function isRunningInsideWsl(): bool
    {
        return ! empty(getenv('WSL_DISTRO_NAME')) || ! empty(getenv('IS_WSL'));
    }
}
