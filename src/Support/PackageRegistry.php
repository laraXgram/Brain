<?php

declare(strict_types=1);

namespace LaraGram\Brain\Support;

use LaraGram\Brain\Discovery\Enums\PackageSource;
use LaraGram\Brain\Discovery\Package;

class PackageRegistry
{
    public const BRAIN = 'laraxgram/brain';

    public const LARAGRAM = 'laraxgram/core';

    public const LARAQUEST = 'laraxgram/laraquest';

    public const MTPROTO = 'laraxgram/mtproto';

    public const SURGE = 'laraxgram/surge';

    public const WATCHDOG = 'laraxgram/watchdog';

    public const TEMPORA = 'laraxgram/tempora';

    public const MCP = 'laraxgram/mcp';

    public const LUNA_LARAGRAM = 'laraxgram/luna';

    public const LUNA_JS = '@laraxgram/luna';

    public const LUNA_REACT = '@laraxgram/react';

    public const LUNA_SVELTE = '@laraxgram/svelte';

    public const LUNA_VUE = '@laraxgram/vue3';

    public const LUNA_VITE = '@laraxgram/vite';

    /** @var array<string, string> */
    private const GUIDELINE_NAMES = [
        '@laraxgram/react' => 'luna-react',
        '@laraxgram/svelte' => 'luna-svelte',
        '@laraxgram/vue3' => 'luna-vue',
        'laraxgram/luna' => 'luna-laragram',
        'laraxgram/brain' => 'brain',
        'laraxgram/core' => 'laragram',
        'laraxgram/laraquest' => 'laraquest',
        'laraxgram/mtproto' => 'mtproto',
        'laraxgram/surge' => 'surge',
        'laraxgram/watchdog' => 'watchdog',
        'laraxgram/tempora' => 'tempora',
        'laraxgram/mcp' => 'mcp',
        'tailwindcss' => 'tailwindcss',
    ];

    public static function guidelineName(string $package): string
    {
        return self::GUIDELINE_NAMES[$package]
            ?? str_replace(['@', '/', '_'], ['', '-', '-'], strtolower($package));
    }

    public static function rosterName(string $package): string
    {
        return strtoupper(str_replace('-', '_', self::guidelineName($package)));
    }

    public static function isFirstParty(Package $package): bool
    {
        return match ($package->source()) {
            PackageSource::Composer => Composer::isFirstPartyPackage($package->name()),
            PackageSource::Npm => Npm::isFirstPartyPackage($package->name()),
        };
    }

    public static function brainPath(Package $package, string $subpath): ?string
    {
        if ($package->path() === null) {
            return null;
        }

        $path = implode(DIRECTORY_SEPARATOR, [$package->path(), 'resources', 'brain', $subpath]);

        return is_dir($path) ? $path : null;
    }
}
