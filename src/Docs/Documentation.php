<?php

declare(strict_types=1);

namespace LaraGram\Brain\Docs;

use LaraGram\Brain\Support\PackageRegistry;

/**
 * The LaraGram documentation shipped with Brain (or refreshed with brain:docs --update).
 */
class Documentation
{
    public const BASE_URL = 'https://laraxgram.github.io';

    /**
     * Documentation pages owned by a first-party package. Every other page belongs to LaraGram itself.
     *
     * @var array<string, list<string>>
     */
    public const PACKAGE_PAGES = [
        PackageRegistry::MTPROTO => ['mtproto', 'mtproto-*'],
        PackageRegistry::LUNA_LARAGRAM => ['luna', 'luna-*', 'vite', 'frontend', 'starter-kits', 'precognition'],
        PackageRegistry::SURGE => ['surge'],
        PackageRegistry::WATCHDOG => ['watchdog'],
        PackageRegistry::TEMPORA => ['tempora'],
    ];

    /**
     * npm packages whose documentation lives with a composer package.
     *
     * @var array<string, string>
     */
    public const PACKAGE_ALIASES = [
        PackageRegistry::LUNA_JS => PackageRegistry::LUNA_LARAGRAM,
        PackageRegistry::LUNA_REACT => PackageRegistry::LUNA_LARAGRAM,
        PackageRegistry::LUNA_VUE => PackageRegistry::LUNA_LARAGRAM,
        PackageRegistry::LUNA_SVELTE => PackageRegistry::LUNA_LARAGRAM,
        PackageRegistry::LUNA_VITE => PackageRegistry::LUNA_LARAGRAM,
    ];

    public function __construct(protected int $version = 4)
    {
        //
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * The directory holding the Markdown pages, preferring a copy refreshed by brain:docs --update.
     */
    public function path(): ?string
    {
        foreach ([static::storagePath($this->version), static::bundledPath($this->version)] as $path) {
            if (is_dir($path) && glob($path.DIRECTORY_SEPARATOR.'*.md') !== []) {
                return $path;
            }
        }

        return null;
    }

    public static function bundledPath(int $version): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'v'.$version;
    }

    public static function storagePath(int $version): string
    {
        return storage_path('framework'.DIRECTORY_SEPARATOR.'brain'.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'v'.$version);
    }

    /**
     * @return array<string, string> page slug => absolute path
     */
    public function pages(): array
    {
        $path = $this->path();

        if ($path === null) {
            return [];
        }

        $pages = [];

        foreach (glob($path.DIRECTORY_SEPARATOR.'*.md') ?: [] as $file) {
            $pages[basename($file, '.md')] = $file;
        }

        ksort($pages);

        return $pages;
    }

    /**
     * The package a page belongs to.
     */
    public function packageFor(string $page): string
    {
        foreach (self::PACKAGE_PAGES as $package => $patterns) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $page)) {
                    return $package;
                }
            }
        }

        return PackageRegistry::LARAGRAM;
    }

    public static function canonicalPackage(string $package): string
    {
        return self::PACKAGE_ALIASES[$package] ?? $package;
    }

    public function url(string $page, ?string $anchor = null): string
    {
        return self::BASE_URL.'/v'.$this->version.'/'.$page.($anchor ? '#'.$anchor : '');
    }
}
