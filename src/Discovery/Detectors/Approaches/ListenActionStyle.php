<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class ListenActionStyle extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByOccurrence($files, $files->listens(), fn (string $contents): array => [
            Approach::ListenActionClosure->value => (int) preg_match_all('/\b(?:Bot|Client)::\w+\s*\([^;]*?,\s*(?:static\s+)?(?:function|fn)\s*\(/s', $contents),
            Approach::ListenActionController->value => (int) preg_match_all('/\b(?:Bot|Client)::\w+\s*\([^;]*?,\s*(?:\[\s*[\w\\\\]+::class|[\w\\\\]+::class|\'[\w\\\\]+@\w+\')/s', $contents),
        ]);
    }
}
