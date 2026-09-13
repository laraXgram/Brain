<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Concerns;

use LaraGram\Support\Collection;
use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Brain\Discovery\Package;
use LaraGram\Brain\Discovery\ProjectManager;

trait DiscoverPackagePaths
{
    /**
     * Only include guidelines for these package names if they're a direct requirement.
     * This fixes every Brain user getting the MCP guidelines due to indirect import.
     *
     * @var array<int, string>
     * */
    protected array $mustBeDirect = [
        PackageRegistry::MCP,
    ];

    /**
     * Packages excluded from Roster-based guideline discovery.
     * Brain is already loaded by getCoreGuidelines().
     *
     * @var array<int, string>
     */
    protected array $excludedPackages = [
        PackageRegistry::BRAIN,
    ];

    abstract protected function getProject(): ProjectManager;

    /**
     * Package priority system to handle conflicts between packages.
     * When a higher-priority package is present, lower-priority packages are excluded from guidelines.
     */
    protected function getPackagePriorities(): array
    {
        return [
            //
        ];
    }

    protected function shouldExcludePackage(Package $package): bool
    {
        if (in_array($package->name(), $this->excludedPackages, true)) {
            return true;
        }

        foreach ($this->getPackagePriorities() as $priorityPackage => $excludedPackages) {
            if (in_array($package->name(), $excludedPackages, true)
                && $this->usesPackage($priorityPackage)) {
                return true;
            }
        }

        return ! $package->isDirect() && in_array($package->name(), $this->mustBeDirect, true);
    }

    /**
     * @return Collection<int, array{path: string, name: string, version: string}>
     */
    protected function discoverPackagePaths(string $basePath): Collection
    {
        $packages = $this->packages()
            ->reject(fn (Package $package): bool => $this->shouldExcludePackage($package));

        /** @var Collection<int, array{path: string, name: string, version: string}> $result */
        $result = $packages
            ->map(function (Package $package) use ($basePath): array {
                $name = $this->normalizePackageName($package->name());

                return [
                    'path' => $basePath.DIRECTORY_SEPARATOR.$name,
                    'name' => $name,
                    'version' => (string) $package->major(),
                ];
            })
            ->collect();

        return $result->filter(fn (array $package): bool => is_dir($package['path']));
    }

    protected function normalizePackageName(string $name): string
    {
        return PackageRegistry::guidelineName($name);
    }

    /** @return Collection<int, Package> */
    protected function packages(): Collection
    {
        return $this->getProject()->php()->packages()->concat($this->getProject()->js()->packages());
    }

    protected function usesPackage(string $package, ?string $constraint = null): bool
    {
        if ($this->getProject()->php()->uses($package, $constraint)) {
            return true;
        }

        return $this->getProject()->js()->uses($package, $constraint);
    }

    protected function getBrainAiPath(): string
    {
        return __DIR__.'/../../../.ai';
    }

    protected function resolveFirstPartyBrainPath(Package $package, string $subpath): ?string
    {
        return PackageRegistry::isFirstParty($package)
            ? PackageRegistry::brainPath($package, $subpath)
            : null;
    }
}
