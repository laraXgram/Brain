<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors\Approaches;

use LaraGram\Brain\Discovery\ApproachResult;
use LaraGram\Brain\Discovery\Enums\Approach;
use LaraGram\Brain\Discovery\Support\SourceFiles;

class BotUpdateAccess extends Convention
{
    protected function result(SourceFiles $files): ?ApproachResult
    {
        return $this->electByFiles($files, $files->botCode(), fn (string $contents): array => [
            Approach::UpdateAccessHelpers->value => (int) preg_match_all('/(?<![\w>$:])(?:chat|user|message|callback_query)\(\)\s*(?:\?->|->)/', $contents),
            Approach::UpdateAccessRequest->value => (int) preg_match_all('/\$(?:request|update)(?:\?->|->)(?:message|callback_query|edited_message|inline_query|channel_post|my_chat_member|chat_member)(?:\?->|->)/', $contents),
        ]);
    }
}
