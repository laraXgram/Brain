<?php

declare(strict_types=1);

namespace LaraGram\Brain;

use LaraGram\Support\Facades\Facade;

/**
 * @method static void registerAgent(string $key, string $className)
 * @method static array getAgents()
 *
 * @see BrainManager
 */
class Brain extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BrainManager::class;
    }
}
