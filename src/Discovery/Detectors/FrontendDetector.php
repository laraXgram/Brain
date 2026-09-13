<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Detectors;

use LaraGram\Brain\Discovery\Ecosystems\JsEcosystem;
use LaraGram\Brain\Discovery\Enums\Frontend;

class FrontendDetector
{
    /** @var array<string, list<string>> Marker packages per frontend (any direct match counts) */
    private const MARKERS = [
        'vue' => ['vue', '@vitejs/plugin-vue', '@laraxgram/vue3'],
        'react' => ['react', 'react-dom', '@vitejs/plugin-react', '@laraxgram/react'],
        'svelte' => ['svelte', '@sveltejs/kit', '@sveltejs/vite-plugin-svelte', '@laraxgram/svelte'],
    ];

    /**
     * @return list<Frontend>
     */
    public static function detect(JsEcosystem $js): array
    {
        $found = [];

        foreach (self::MARKERS as $value => $markers) {
            if ($js->usesDirect($markers)) {
                $found[] = Frontend::from($value);
            }
        }

        return $found;
    }
}
