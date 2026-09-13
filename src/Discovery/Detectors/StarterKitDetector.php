<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors;

use LaraGram\Support\Arr;
use LaraGram\Brain\Discovery\Scanners\Concerns\ParsesManifests;

class StarterKitDetector
{
    use ParsesManifests;

    public static function detect(string $basePath): ?string
    {
        $composer = self::readJsonFile($basePath.'composer.json') ?? [];
        $starterKit = Arr::get($composer, 'extra.laragram.starter-kit');

        return is_string($starterKit) && $starterKit !== '' ? $starterKit : null;
    }
}
