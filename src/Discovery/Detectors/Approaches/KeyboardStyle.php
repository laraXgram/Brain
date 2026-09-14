<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class KeyboardStyle extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFiles($files, $files->botCode(), fn (string $contents): array => [
            Approach::KeyboardBuilder->value => (int) preg_match_all('/\b(?:Keyboard|Make)::\w+\s*\(|@keyboard\b/', $contents),
            Approach::KeyboardArray->value => (int) preg_match_all('/[\'"](?:inline_keyboard|keyboard)[\'"]\s*=>\s*\[/', $contents),
        ]);
    }
}
