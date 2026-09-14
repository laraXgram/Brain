<?php

declare(strict_types=1);

namespace LaraGram\Brain\Skills\Remote;

class AuditResult
{
    /**
     * @param  list<array{risk: Risk, file: string, reason: string}>  $findings
     */
    public function __construct(
        public string $partner,
        public Risk $risk,
        public ?int $alerts = null,
        public ?string $analyzedAt = null,
        public array $findings = [],
    ) {
        //
    }
}
