<?php

declare(strict_types=1);

namespace LaraGram\Brain\Install\Enums;

enum McpInstallationStrategy: string
{
    case SHELL = 'shell';
    case FILE = 'file';
    case NONE = 'none';
}
