<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class AuthorizationStyle extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFile($files, 'Controllers', fn (string $contents): array => [
            Approach::AuthorizationGate->value => (int) preg_match_all('/\bGate::(?:authorize|allows|denies|any|none|check|inspect)\s*\(/', $contents),
            Approach::AuthorizationUserCan->value => (int) preg_match_all('/(?:->user\(\)|\$user)->(?:can|cannot)\s*\(/', $contents),
            Approach::AuthorizationTrait->value => (int) preg_match_all('/\$this->authorize\s*\(/', $contents),
        ]);
    }
}
