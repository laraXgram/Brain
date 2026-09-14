<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Scanners;

use LaraGram\Brain\Support\Semver\VersionParser;
use LaraGram\Brain\Discovery\Enums\PackageSource;
use LaraGram\Brain\Discovery\PackageCollection;
use UnexpectedValueException;

class ComposerLock extends PackageScanner
{
    public function scan(): PackageCollection
    {
        $packages = new PackageCollection;

        $json = $this->readJsonOrWarn('composer.lock');

        if ($json === null) {
            return $packages;
        }

        if (! is_array($json['packages'] ?? null)) {
            $this->warn('Malformed composer.lock (missing "packages" key): '.$this->path.'composer.lock');
        }

        $aliases = $this->aliases($json['aliases'] ?? null);

        $this->processDependencies($this->versions($json['packages'] ?? null, $aliases), $packages, false, authoritative: true);
        $this->processDependencies($this->versions($json['packages-dev'] ?? null, $aliases), $packages, true, authoritative: true);

        return $packages;
    }

    public function minimumPhpVersion(): ?string
    {
        $require = $this->manifest()['require'] ?? null;

        if (! is_array($require) || ! is_string($require['php'] ?? null)) {
            return null;
        }

        try {
            $lowerBound = (new VersionParser)
                ->parseConstraints($require['php'])
                ->getLowerBound();
        } catch (UnexpectedValueException) {
            return null;
        }

        if ($lowerBound->isZero()) {
            return null;
        }

        if (preg_match('/^(\d+)\.(\d+)/', $lowerBound->getVersion(), $matches) !== 1) {
            return null;
        }

        return $matches[1].'.'.$matches[2];
    }

    protected function source(): PackageSource
    {
        return PackageSource::Composer;
    }

    protected function manifestFile(): string
    {
        return 'composer.json';
    }

    /**
     * @return array<string, bool>
     */
    protected function manifestSections(): array
    {
        return [
            'require-dev' => true,
            'require' => false,
        ];
    }

    protected function computePath(string $packageName): string
    {
        $vendorPath = str_replace('/', DIRECTORY_SEPARATOR, $this->vendorDir());
        $packageSegment = str_replace('/', DIRECTORY_SEPARATOR, $packageName);

        if ($this->isAbsolutePath($vendorPath)) {
            return $vendorPath.DIRECTORY_SEPARATOR.$packageSegment;
        }

        return $this->resolvedBase().DIRECTORY_SEPARATOR.$vendorPath.DIRECTORY_SEPARATOR.$packageSegment;
    }

    /**
     * @param  array<string, string>  $aliases
     * @return array<string, string>
     */
    private function versions(mixed $rawPackages, array $aliases = []): array
    {
        if (! is_array($rawPackages)) {
            return [];
        }

        $versions = [];

        foreach ($rawPackages as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $name = $raw['name'] ?? null;

            if (! is_string($name)) {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $version = $raw['version'] ?? null;
            $version = is_string($version) ? $version : '';

            $versions[$name] = str_starts_with($version, 'dev-')
                ? $this->branchVersion($name, $version, $raw, $aliases)
                : $version;
        }

        return $versions;
    }

    /**
     * Resolve the version of a package installed from a branch (e.g. a path repository on "dev-master").
     *
     * An inline alias ("dev-master as 4.0.0") wins, then the package's branch alias ("4.x-dev").
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, string>  $aliases
     */
    private function branchVersion(string $name, string $version, array $raw, array $aliases): string
    {
        // Composer records the default branch of an inline alias as "9999999-dev".
        $alias = $aliases[$name.'@'.$version]
            ?? (($raw['default-branch'] ?? false) === true ? ($aliases[$name.'@9999999-dev'] ?? null) : null);

        if ($alias !== null) {
            return $alias;
        }

        $branchAlias = $raw['extra']['branch-alias'][$version] ?? null;

        return is_string($branchAlias) ? $branchAlias : $version;
    }

    /**
     * @return array<string, string> "package@version" => alias
     */
    private function aliases(mixed $rawAliases): array
    {
        if (! is_array($rawAliases)) {
            return [];
        }

        $aliases = [];

        foreach ($rawAliases as $alias) {
            if (is_array($alias) && is_string($alias['package'] ?? null) && is_string($alias['version'] ?? null) && is_string($alias['alias'] ?? null)) {
                $aliases[$alias['package'].'@'.$alias['version']] = $alias['alias'];
            }
        }

        return $aliases;
    }

    private function vendorDir(): string
    {
        $config = $this->manifest()['config'] ?? null;

        if (is_array($config) && isset($config['vendor-dir']) && is_string($config['vendor-dir'])) {
            return $config['vendor-dir'];
        }

        return 'vendor';
    }

    private function isAbsolutePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return str_starts_with($path, '/');
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
