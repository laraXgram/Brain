<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class ValidationStyle extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        $tally = [
            Approach::ValidationInline->value => 0,
            Approach::ValidationFormRequest->value => 0,
        ];

        $paths = [];
        $formRequests = [];

        foreach ($files->php('Http/Requests') as $path) {
            if (str_contains($files->contents($path), 'function rules')) {
                $formRequests[$path] = true;
                $tally[Approach::ValidationFormRequest->value]++;
                $paths[] = $path;
            }
        }

        foreach ($files->php() as $path) {
            if (isset($formRequests[$path])) {
                continue;
            }

            $contents = $files->contents($path);

            $inline = preg_match('/(?:\$request|\$this)->validate(?:WithBag)?\s*\(/', $contents) === 1
                || preg_match('/Validator::make\s*\(/', $contents) === 1;

            if (! $inline) {
                continue;
            }

            $tally[Approach::ValidationInline->value]++;
            $paths[] = $path;
        }

        return $this->dominant($tally, $paths);
    }
}
