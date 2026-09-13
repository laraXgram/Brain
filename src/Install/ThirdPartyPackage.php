<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install;

use LaraGram\Support\Collection;
use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Brain\Discovery\Package;
use LaraGram\Brain\Discovery\ProjectManager;

class ThirdPartyPackage
{
    public function __construct(
        public readonly string $name,
        public readonly bool $hasGuidelines,
        public readonly bool $hasSkills,
    ) {
        //
    }

    /**
     * Discover all third-party packages with brain features.
     *
     * @return Collection<string, ThirdPartyPackage>
     */
    public static function discover(ProjectManager $project): Collection
    {
        $withGuidelines = self::guidelineDirectories($project);
        $withSkills = self::skillDirectories($project);

        $allPackageNames = array_unique(array_merge(
            array_keys($withGuidelines),
            array_keys($withSkills)
        ));

        return collect($allPackageNames)
            ->mapWithKeys(fn (string $name): array => [
                $name => new self(
                    name: $name,
                    hasGuidelines: isset($withGuidelines[$name]),
                    hasSkills: isset($withSkills[$name]),
                ),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function guidelineDirectories(ProjectManager $project): array
    {
        return self::brainDirectories($project, 'guidelines');
    }

    /**
     * @return array<string, string>
     */
    public static function skillDirectories(ProjectManager $project): array
    {
        return self::brainDirectories($project, 'skills');
    }

    /**
     * Transitive dependencies are excluded so an indirect package cannot inject guidelines.
     *
     * @return array<string, string>
     */
    private static function brainDirectories(ProjectManager $project, string $subpath): array
    {
        /** @var array<string, string> */
        return $project->php()->packages()
            ->concat($project->js()->packages())
            ->filter(fn (Package $package): bool => $package->isDirect() && ! PackageRegistry::isFirstParty($package))
            ->mapWithKeys(fn (Package $package): array => [
                $package->name() => PackageRegistry::brainPath($package, $subpath),
            ])
            ->filter()
            ->all();
    }

    public function featureLabel(): string
    {
        return match (true) {
            $this->hasGuidelines && $this->hasSkills => 'guidelines, skills',
            $this->hasGuidelines => 'guideline',
            $this->hasSkills => 'skills',
            default => '',
        };
    }

    public function displayLabel(): string
    {
        return "{$this->name} ({$this->featureLabel()})";
    }
}
