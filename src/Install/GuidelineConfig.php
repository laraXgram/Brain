<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install;

class GuidelineConfig
{
    public bool $laragramStyle = false;

    public bool $caresAboutLocalization = false;

    public bool $hasAnApi = false;

    public bool $hasSkills = false;

    public bool $hasMcp = false;

    /**
     * @var array<int, string>
     */
    public array $aiGuidelines;
}
