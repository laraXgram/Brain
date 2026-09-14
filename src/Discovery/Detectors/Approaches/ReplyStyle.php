<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class ReplyStyle extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFiles($files, $files->botCode(), fn (string $contents): array => [
            Approach::ReplyTemplate->value => (int) preg_match_all('/(?<![\w>$:])template\s*\(/', $contents),
            Approach::ReplyDirect->value => (int) preg_match_all('/->(?:sendMessage|editMessageText|sendPhoto)\s*\(/', $contents),
        ]);
    }
}
