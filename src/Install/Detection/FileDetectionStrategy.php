<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Detection;

use LaraGram\Brain\Install\Contracts\DetectionStrategy;
use LaraGram\Brain\Install\Enums\Platform;

class FileDetectionStrategy implements DetectionStrategy
{
    public function detect(array $config, ?Platform $platform = null): bool
    {
        $basePath = $config['basePath'] ?? getcwd();

        if (isset($config['files'])) {
            foreach ($config['files'] as $file) {
                if (file_exists($basePath.DIRECTORY_SEPARATOR.$file)) {
                    return true;
                }
            }
        }

        return false;
    }
}
