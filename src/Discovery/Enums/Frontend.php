<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Enums;

enum Frontend: string
{
    case Vue = 'vue';
    case React = 'react';
    case Svelte = 'svelte';
}
