<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class AuthRetrievalStyle extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFile($files, null, fn (string $contents): array => [
            Approach::AuthFacade->value => (int) preg_match_all('/\bAuth::(?:user|id|check|guest)\s*\(/', $contents),
            Approach::AuthRequest->value => (int) preg_match_all('/\$request->user\(\)/', $contents),
            Approach::AuthHelper->value => (int) preg_match_all('/\bauth\(\)->(?:user|id|check|guest)\s*\(/', $contents),
        ]);
    }
}
