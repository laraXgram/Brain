<?php

declare(strict_types=1);

namespace LaraGram\Brain\Docs;

/**
 * Lowercases, splits and lightly stems English text for the documentation index.
 */
class Tokenizer
{
    /**
     * Bump when the normalization changes, to invalidate cached indexes.
     */
    public const VERSION = '2';

    /** @var array<string, true> */
    protected const STOP_WORDS = [
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true, 'at' => true, 'be' => true,
        'by' => true, 'for' => true, 'from' => true, 'how' => true, 'in' => true, 'is' => true, 'it' => true,
        'of' => true, 'on' => true, 'or' => true, 'that' => true, 'the' => true, 'this' => true, 'to' => true,
        'with' => true, 'you' => true, 'your' => true, 'can' => true, 'will' => true, 'use' => true,
    ];

    /**
     * Normalize text for phrase matching: lowercase words separated by single spaces.
     */
    public static function normalize(string $text): string
    {
        $text = strtolower($text);

        return trim((string) preg_replace('/[^a-z0-9_]+/', ' ', $text));
    }

    /**
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        // Split camelCase identifiers (sendMessage => send message) before lowercasing, but keep the whole word too.
        $words = preg_split('/[^A-Za-z0-9_]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];

        foreach ($words as $word) {
            $parts = preg_split('/(?<=[a-z0-9])(?=[A-Z])|_/', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [$word];

            if (count($parts) > 1) {
                $terms[] = static::stem(strtolower($word));
            }

            foreach ($parts as $part) {
                $part = strtolower($part);

                if (strlen($part) < 2 || isset(self::STOP_WORDS[$part])) {
                    continue;
                }

                $terms[] = static::stem($part);
            }
        }

        return $terms;
    }

    public static function stem(string $word): string
    {
        $length = strlen($word);

        if ($length <= 3 || ctype_digit($word)) {
            return $word;
        }

        foreach ([
            ['ations', 'ate', 7],
            ['ation', 'ate', 6],
            ['ating', 'ate', 6],
            ['ated', 'ate', 5],
            ['ates', 'ate', 5],
            ['ies', 'y', 4],
            ['sses', 'ss', 5],
            ['ing', '', 6],
            ['ed', '', 5],
            ['ly', '', 5],
            ['es', '', 5],
            ['s', '', 4],
        ] as [$suffix, $replacement, $minimum]) {
            if ($length >= $minimum && str_ends_with($word, $suffix)) {
                if ($suffix === 's' && (str_ends_with($word, 'ss') || str_ends_with($word, 'us'))) {
                    return $word;
                }

                if ($suffix === 'es' && ! preg_match('/(sh|ch|x|z|ss)es$/', $word)) {
                    continue;
                }

                return substr($word, 0, -strlen($suffix)).$replacement;
            }
        }

        return $word;
    }
}
