<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class MassAssignment extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFile($files, 'Models', function (string $contents): array {
            $fillable = preg_match('/protected\s+(?:\S+\s+)*\$fillable\b/', $contents) === 1
                || preg_match('/#\[\s*Fillable\b/', $contents) === 1;
            $guarded = preg_match('/protected\s+(?:\S+\s+)*\$guarded\b/', $contents) === 1
                || preg_match('/#\[\s*Guarded\b/', $contents) === 1;

            return [
                Approach::MassAssignmentFillable->value => $fillable ? 1 : 0,
                Approach::MassAssignmentGuarded->value => $guarded ? 1 : 0,
            ];
        });
    }
}
