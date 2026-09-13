<?php

declare(strict_types=1);

namespace LaraGram\Brain\Console;

use LaraGram\Console\Command;
use LaraGram\Support\Facades\Commander;
use LaraGram\Console\Attribute\AsCommand;

#[AsCommand('brain:mcp', 'Starts LaraGram Brain (usually from mcp.json)')]
class StartCommand extends Command
{
    public function handle(): int
    {
        return Commander::call('mcp:start laragram-brain');
    }
}
