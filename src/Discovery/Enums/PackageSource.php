<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Enums;

enum PackageSource: string
{
    case Composer = 'composer';
    case Npm = 'npm';
}
