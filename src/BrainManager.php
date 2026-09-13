<?php

declare(strict_types=1);

namespace LaraGram\Brain;

use InvalidArgumentException;
use LaraGram\Brain\Install\Agents\Agent;
use LaraGram\Brain\Install\Agents\Amp;
use LaraGram\Brain\Install\Agents\Antigravity;
use LaraGram\Brain\Install\Agents\ClaudeCode;
use LaraGram\Brain\Install\Agents\Codex;
use LaraGram\Brain\Install\Agents\Copilot;
use LaraGram\Brain\Install\Agents\Cursor;
use LaraGram\Brain\Install\Agents\Factory;
use LaraGram\Brain\Install\Agents\GrokBuild;
use LaraGram\Brain\Install\Agents\Junie;
use LaraGram\Brain\Install\Agents\Kiro;
use LaraGram\Brain\Install\Agents\OpenCode;
use LaraGram\Brain\Install\Agents\Pi;
use LaraGram\Brain\Install\Agents\Zed;

class BrainManager
{
    /** @var array<string, class-string<Agent>> */
    private array $agents = [
        'amp' => Amp::class,
        'antigravity' => Antigravity::class,
        'claude_code' => ClaudeCode::class,
        'codex' => Codex::class,
        'copilot' => Copilot::class,
        'cursor' => Cursor::class,
        'factory' => Factory::class,
        'grok_build' => GrokBuild::class,
        'junie' => Junie::class,
        'kiro' => Kiro::class,
        'opencode' => OpenCode::class,
        'pi' => Pi::class,
        'zed' => Zed::class,
    ];

    /**
     * @param  class-string<Agent>  $className
     */
    public function registerAgent(string $key, string $className): void
    {
        if (array_key_exists($key, $this->agents)) {
            throw new InvalidArgumentException("Agent '{$key}' is already registered");
        }

        $this->agents[$key] = $className;
    }

    /**
     * @return array<string, class-string<Agent>>
     */
    public function getAgents(): array
    {
        $agents = $this->agents;
        ksort($agents);

        return $agents;
    }
}
