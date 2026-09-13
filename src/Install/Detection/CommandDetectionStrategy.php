<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Detection;

use LaraGram\Support\Facades\Process;
use LaraGram\Brain\Install\Contracts\DetectionStrategy;
use LaraGram\Brain\Install\Enums\Platform;
use LaraGram\Console\Process\Exception\ProcessSignaledException;

class CommandDetectionStrategy implements DetectionStrategy
{
    public function detect(array $config, ?Platform $platform = null): bool
    {
        if (! isset($config['command'])) {
            return false;
        }

        try {
            return Process::run($config['command'])->successful();
        } catch (ProcessSignaledException) {
            return false;
        }
    }
}
