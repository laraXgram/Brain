<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install;

use LaraGram\Support\Collection;
use LaraGram\Support\Str;
use LaraGram\Brain\Install\Assists\Luna;
use LaraGram\Brain\Discovery\Enums\JsPackageManager;
use LaraGram\Brain\Discovery\ProjectManager;
use LaraGram\Support\Finder\Finder;

class GuidelineAssist
{
    /** @var array<string, string> */
    protected array $enumPaths = [];

    public function __construct(public ProjectManager $project, public GuidelineConfig $config, public ?Collection $skills = null)
    {
        $this->skills ??= collect();
        $this->enumPaths = $this->discover();
    }

    /**
     * @return array<string, string> - className, absolutePath
     */
    public function enums(): array
    {
        return $this->enumPaths;
    }

    /**
     * Discover all enum files in the application directory.
     *
     * @return array<string, string>
     */
    protected function discover(): array
    {
        $appPath = app_path();

        if (! is_dir($appPath)) {
            return [];
        }

        $enums = [];

        $finder = Finder::create()
            ->in($appPath)
            ->files()
            ->name('/[A-Z].*\.php$/');

        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $code = file_get_contents($path);

            if ($code === false) {
                continue;
            }

            if (stripos($code, 'enum') === false) {
                continue;
            }

            $tokens = token_get_all($code);

            foreach ($tokens as $token) {
                if (is_array($token) && $token[0] === T_ENUM) {
                    $className = app()->getNamespace().str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        $file->getRelativePathname()
                    );
                    $enums[$className] = $path;

                    break;
                }
            }
        }

        return $enums;
    }

    public function enumContents(): string
    {
        return collect($this->enumPaths)
            ->sortKeys()
            ->map(fn (string $path): string => is_file($path) ? (file_get_contents($path) ?: '') : '')
            ->filter()
            ->join(PHP_EOL);
    }

    public function luna(): Luna
    {
        return new Luna($this->project);
    }

    public function hasPackage(string $package, ?string $constraint = null): bool
    {
        if ($this->project->php()->uses($package, $constraint)) {
            return true;
        }

        return $this->project->js()->uses($package, $constraint);
    }

    public function nodePackageManager(): string
    {
        return ($this->project->js()->packageManager() ?? JsPackageManager::Npm)->value;
    }

    protected function detectedNodePackageManager(): string
    {
        return $this->nodePackageManager();
    }

    public function nodePackageManagerCommand(string $command): string
    {
        $npmExecutable = config('brain.executable_paths.npm');

        if ($npmExecutable) {
            return "{$npmExecutable} {$command}";
        }

        return "{$this->detectedNodePackageManager()} {$command}";
    }

    public function commanderCommand(string $command): string
    {
        return "{$this->commander()} {$command}";
    }

    public function composerCommand(string $command): string
    {
        $composerExecutable = config('brain.executable_paths.composer');

        if ($composerExecutable) {
            return "{$composerExecutable} {$command}";
        }

        return "composer {$command}";
    }

    public function binCommand(string $command): string
    {
        $vendorBinPrefix = config('brain.executable_paths.vendor_bin');

        if ($vendorBinPrefix) {
            return "{$vendorBinPrefix}{$command}";
        }

        return "vendor/bin/{$command}";
    }

    /**
     * The command that runs LaraGram's Commander (the "laragram" console script).
     */
    public function commander(): string
    {
        $phpExecutable = config('brain.executable_paths.php') ?: 'php';

        return "{$phpExecutable} laragram";
    }

    public function appPath(string $path = ''): string
    {
        $relativePath = ltrim(Str::after(app_path($path), base_path()), DIRECTORY_SEPARATOR);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }

    public function hasSkillsEnabled(): bool
    {
        return $this->config->hasSkills;
    }

    public function hasMcpEnabled(): bool
    {
        return $this->config->hasMcp;
    }
}
