<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class MultiStepFlow extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByOccurrence($files, $files->botCode(), fn (string $contents): array => [
            Approach::MultiStepConversation->value => (int) preg_match_all('/\bConversation::start\s*\(|extends\s+(?:\\\\?LaraGram\\\\Conversation\\\\)?Conversation\b/', $contents),
            Approach::MultiStepStepManager->value => (int) preg_match_all('/\bStep::set\s*\(|::onStep\s*\(/', $contents),
        ]);
    }
}
