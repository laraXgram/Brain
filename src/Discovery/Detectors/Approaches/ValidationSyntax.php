<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class ValidationSyntax extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFile($files, 'Http/Requests', fn (string $contents): ?array => str_contains($contents, 'function rules') ? [
            Approach::ValidationPipeSyntax->value => (int) preg_match_all("/=>\s*'(?![^']*regex:)[^']*\|[^']*'/", $contents),
            Approach::ValidationArraySyntax->value => (int) preg_match_all("/=>\s*\[\s*'/", $contents),
        ] : null);
    }
}
