<?php

declare(strict_types=1);

namespace LaraGram\Brain\Docs;

/**
 * Searches the documentation index.
 *
 * Query syntax:
 *  - words are stemmed and combined with AND: `rate limit` matches "rate" AND "limit"
 *  - "quoted phrases" must appear as adjacent words in order
 *  - several queries are combined with OR
 */
class DocsSearcher
{
    protected const K1 = 1.2;

    protected const B = 0.75;

    public function __construct(protected DocsIndex $index)
    {
        //
    }

    /**
     * @param  list<string>  $queries
     * @param  list<string>|null  $packages  Limit results to these packages (null for all).
     * @return list<array{section: array<string, mixed>, score: float}>
     */
    public function search(array $queries, ?array $packages = null, int $limit = 50): array
    {
        $results = [];

        foreach ($queries as $position => $query) {
            foreach ($this->searchOne($query, $packages) as $id => $score) {
                // Earlier queries win ties, the best score of all queries counts.
                $score += (count($queries) - $position) * 0.001;
                $results[$id] = max($results[$id] ?? 0.0, $score);
            }
        }

        arsort($results);

        $sections = $this->index->sections();
        $ranked = [];

        foreach (array_slice($results, 0, $limit, true) as $id => $score) {
            $ranked[] = ['section' => $sections[$id], 'score' => $score];
        }

        return $ranked;
    }

    /**
     * @param  list<string>|null  $packages
     * @return array<int, float> section id => score
     */
    protected function searchOne(string $query, ?array $packages): array
    {
        preg_match_all('/"([^"]+)"/', $query, $matches);

        $phrases = array_values(array_filter(array_map(Tokenizer::normalize(...), $matches[1])));
        $words = Tokenizer::terms((string) preg_replace('/"[^"]*"/', ' ', $query));
        $terms = array_values(array_unique([...$words, ...array_merge(...array_map(Tokenizer::terms(...), $phrases ?: ['']))]));

        if ($terms === []) {
            return [];
        }

        $sections = $this->index->sections();
        $count = count($sections);
        $scores = [];
        $matchedTerms = [];

        foreach ($terms as $term) {
            $postings = $this->index->postings($term);

            if ($postings === []) {
                continue;
            }

            $idf = log(1 + ($count - count($postings) + 0.5) / (count($postings) + 0.5));

            foreach ($postings as $id => $frequency) {
                $section = $sections[$id];

                if ($packages !== null && ! in_array($section['package'], $packages, true)) {
                    continue;
                }

                $norm = self::K1 * (1 - self::B + self::B * ($section['length'] / max(1.0, $this->index->averageLength())));
                $scores[$id] = ($scores[$id] ?? 0.0) + $idf * ($frequency * (self::K1 + 1)) / ($frequency + $norm);
                $matchedTerms[$id][$term] = true;
            }
        }

        $required = count($terms);
        $strict = [];

        foreach ($scores as $id => $score) {
            if (count($matchedTerms[$id]) < $required) {
                continue;
            }

            foreach ($phrases as $phrase) {
                if (! str_contains($sections[$id]['text'], ' '.$phrase.' ')) {
                    continue 2;
                }
            }

            $strict[$id] = $score;
        }

        if ($strict !== [] || $phrases !== []) {
            return $strict;
        }

        // No section contains every word: fall back to the sections matching most of them.
        $fallback = [];

        foreach ($scores as $id => $score) {
            if (count($matchedTerms[$id]) >= (int) ceil($required / 2)) {
                $fallback[$id] = $score * count($matchedTerms[$id]) / $required * 0.5;
            }
        }

        return $fallback;
    }

    /**
     * Render the results as Markdown within a token budget (about four characters per token).
     *
     * @param  list<array{section: array<string, mixed>, score: float}>  $results
     */
    public static function render(array $results, int $tokenLimit): string
    {
        $budget = max(200, $tokenLimit) * 4;
        $output = [];
        $used = 0;

        foreach ($results as $result) {
            $section = $result['section'];
            $heading = $section['heading'] === $section['title'] ? $section['title'] : "{$section['title']} › {$section['heading']}";
            $block = "## {$heading}\nSource: {$section['url']}\n\n{$section['content']}\n";

            if ($used + strlen($block) > $budget) {
                $remaining = $budget - $used;

                if ($remaining > 400) {
                    $output[] = static::truncate($block, $remaining)."\n\n_(truncated, increase token_limit for more)_\n";
                }

                break;
            }

            $output[] = $block;
            $used += strlen($block);
        }

        return implode("\n---\n\n", $output);
    }

    protected static function truncate(string $block, int $length): string
    {
        $cut = substr($block, 0, $length);

        // Don't leave a code fence open.
        if (substr_count($cut, '```') % 2 === 1) {
            $cut .= "\n```";
        }

        return rtrim($cut);
    }
}
