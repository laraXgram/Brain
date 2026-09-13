<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class CommandSignatureSyntax extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFile($files, 'Commands', fn (string $contents): array => [
            Approach::CommandAttributeSyntax->value => (int) preg_match_all('/#\[\s*AsCommand\b/', $contents),
            Approach::CommandPropertySyntax->value => (int) preg_match_all('/protected\s+(?:\S+\s+)*\$(?:signature|description)\b\s*=/', $contents),
        ]);
    }
}
