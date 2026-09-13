<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install;

use LaraGram\Container\Container;
use LaraGram\Support\Collection;
use LaraGram\Brain\BrainManager;
use LaraGram\Brain\Install\Agents\Agent;
use LaraGram\Brain\Install\Enums\Platform;

class AgentsDetector
{
    public function __construct(
        private readonly Container $container,
        private readonly BrainManager $brainManager
    ) {}

    /**
     * Detect installed agents on the current platform.
     *
     * @return array<string>
     */
    public function discoverSystemInstalledAgents(): array
    {
        $platform = Platform::current();

        return $this->getAgents()
            ->filter(fn (Agent $program): bool => $program->detectOnSystem($platform))
            ->map(fn (Agent $program): string => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * Detect agents used in the current project.
     *
     * @return array<string>
     */
    public function discoverProjectInstalledAgents(string $basePath): array
    {
        return $this->getAgents()
            ->filter(fn (Agent $program): bool => $program->detectInProject($basePath))
            ->map(fn (Agent $program): string => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * Get all registered agents.
     *
     * @return Collection<string, Agent>
     */
    public function getAgents(): Collection
    {
        return collect($this->brainManager->getAgents())
            ->map(fn (string $className) => $this->container->make($className));
    }
}
