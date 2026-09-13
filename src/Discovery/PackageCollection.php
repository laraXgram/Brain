<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery;

use LaraGram\Support\Collection;

/**
 * @extends Collection<int, Package>
 */
class PackageCollection extends Collection
{
    public function dev(): static
    {
        return $this->filter(fn (Package $package): bool => $package->isDev())->values();
    }

    public function production(): static
    {
        return $this->filter(fn (Package $package): bool => ! $package->isDev())->values();
    }

    public function direct(): static
    {
        return $this->filter(fn (Package $package): bool => $package->isDirect())->values();
    }
}
