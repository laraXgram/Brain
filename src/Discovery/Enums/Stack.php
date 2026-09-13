<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Enums;

enum Stack: string
{
    case Bot = 'bot';
    case Mtproto = 'mtproto';
    case Tma = 'tma';
    case LunaReact = 'luna-react';
    case LunaVue = 'luna-vue';
    case LunaSvelte = 'luna-svelte';
    case Api = 'api';
    case Blade = 'blade';
    case Surge = 'surge';
    case Mcp = 'mcp';
}
