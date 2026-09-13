<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors;

use LaraGram\Brain\Discovery\Ecosystems\Ecosystem;
use LaraGram\Brain\Discovery\Ecosystems\JsEcosystem;
use LaraGram\Brain\Discovery\Enums\Stack;

class StackDetector
{
    /** @var list<array{stack: Stack, packages: list<string>}> */
    private const LUNA_RULES = [
        ['stack' => Stack::LunaReact, 'packages' => ['@laraxgram/react']],
        ['stack' => Stack::LunaVue, 'packages' => ['@laraxgram/vue3', '@laraxgram/vue']],
        ['stack' => Stack::LunaSvelte, 'packages' => ['@laraxgram/svelte']],
    ];

    /**
     * @return list<Stack>
     */
    public static function detect(Ecosystem $php, JsEcosystem $js, ?string $basePath = null): array
    {
        $stacks = [];
        $basePath = $basePath === null ? null : rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($php->usesDirect('laraxgram/core') && ($basePath === null || is_dir($basePath.'listens'))) {
            $stacks[] = Stack::Bot;
        }

        if ($php->usesDirect('laraxgram/mtproto')) {
            $stacks[] = Stack::Mtproto;
        }

        if ($php->usesDirect('laraxgram/luna')) {
            $stacks[] = Stack::Tma;
        }

        foreach (self::LUNA_RULES as $rule) {
            foreach ($rule['packages'] as $package) {
                if ($js->usesDirect($package)) {
                    $stacks[] = $rule['stack'];

                    continue 2;
                }
            }
        }

        if ($php->usesDirect('laraxgram/citadel')) {
            $stacks[] = Stack::Api;
        }

        if ($php->usesDirect('laraxgram/core') && ! $php->usesDirect('laraxgram/luna')
            && ($basePath === null || is_file($basePath.'routes'.DIRECTORY_SEPARATOR.'web.php'))) {
            $stacks[] = Stack::Blade;
        }

        if ($php->usesDirect('laraxgram/surge')) {
            $stacks[] = Stack::Surge;
        }

        if ($php->usesDirect('laraxgram/mcp')) {
            $stacks[] = Stack::Mcp;
        }

        return $stacks;
    }
}
