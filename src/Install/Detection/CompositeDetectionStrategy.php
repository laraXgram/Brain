<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Detection;

use LaraGram\Brain\Install\Contracts\DetectionStrategy;
use LaraGram\Brain\Install\Enums\Platform;

class CompositeDetectionStrategy implements DetectionStrategy
{
    /**
     * @param  DetectionStrategy[]  $strategies
     */
    public function __construct(private readonly array $strategies)
    {
        //
    }

    public function detect(array $config, ?Platform $platform = null): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->detect($config, $platform)) {
                return true;
            }
        }

        return false;
    }
}
