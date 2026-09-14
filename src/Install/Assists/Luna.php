<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Assists;

use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Brain\Discovery\ProjectManager;

class Luna
{
    public function __construct(private ProjectManager $project)
    {
        //
    }

    public function gte(string $version): bool
    {
        if ($this->project->php()->uses(PackageRegistry::LUNA_LARAGRAM, ">={$version}")) {
            return true;
        }

        if ($this->project->js()->uses(PackageRegistry::LUNA_REACT, ">={$version}")) {
            return true;
        }

        if ($this->project->js()->uses(PackageRegistry::LUNA_SVELTE, ">={$version}")) {
            return true;
        }

        return $this->project->js()->uses(PackageRegistry::LUNA_VUE, ">={$version}");
    }

    public function hasFormComponent(): bool
    {
        return $this->gte('0.1.0');
    }

    public function hasFormComponentResets(): bool
    {
        return $this->gte('0.1.0');
    }

    /**
     * Determine if the application serves Telegram Mini Apps (a "telegram" auth guard or the telegram middleware).
     */
    public function usesTma(): bool
    {
        foreach ((array) config('auth.guards', []) as $guard) {
            if (is_array($guard) && ($guard['driver'] ?? null) === 'telegram') {
                return true;
            }
        }

        foreach (glob(base_path('routes').DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            if (preg_match('/[\'"]telegram(?::[^\'"]*)?[\'"]/', (string) file_get_contents($file)) === 1) {
                return true;
            }
        }

        return false;
    }

    public function pagesDirectory(): string
    {
        $jsPath = base_path('resources/js');

        if (is_dir($jsPath)) {
            $entries = @scandir($jsPath);

            if ($entries !== false && in_array('pages', $entries, true)) {
                return 'resources/js/pages';
            }
        }

        return 'resources/js/Pages';
    }
}
