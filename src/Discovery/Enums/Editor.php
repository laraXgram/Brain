<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Enums;

enum Editor: string
{
    case PhpStorm = 'phpstorm';
    case VsCode = 'vscode';
    case Zed = 'zed';
    case SublimeText = 'sublime-text';
}
