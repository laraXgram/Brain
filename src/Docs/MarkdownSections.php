<?php

declare(strict_types=1);

namespace LaraGram\Brain\Docs;

/**
 * Splits a documentation page into sections at its "##" and "###" headings.
 */
class MarkdownSections
{
    /**
     * @return list<array{title: string, heading: string, anchor: ?string, content: string}>
     */
    public static function split(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $title = '';
        $sections = [];
        $current = ['heading' => '', 'anchor' => null, 'lines' => []];
        $pendingAnchor = null;
        $fence = null;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(`{3,}|~{3,})/', $line, $matches)) {
                $marker = $matches[1][0];

                $fence = $fence === null ? $marker : ($fence === $marker ? null : $fence);
                $current['lines'][] = $line;

                continue;
            }

            if ($fence !== null) {
                $current['lines'][] = $line;

                continue;
            }

            if (preg_match('/^<a name="([^"]+)"><\/a>\s*$/', trim($line), $matches)) {
                $pendingAnchor = $matches[1];

                continue;
            }

            if ($title === '' && preg_match('/^#\s+(.+)$/', $line, $matches)) {
                $title = static::clean($matches[1]);

                continue;
            }

            if (preg_match('/^(#{2,3})\s+(.+)$/', $line, $matches)) {
                $sections[] = $current;
                $heading = static::clean($matches[2]);
                $current = [
                    'heading' => $heading,
                    'anchor' => $pendingAnchor ?? static::slug($heading),
                    'lines' => [],
                ];
                $pendingAnchor = null;

                continue;
            }

            if (preg_match('/^#{4,}\s+(.+)$/', $line, $matches)) {
                $current['lines'][] = '**'.static::clean($matches[1]).'**';
                $pendingAnchor = null;

                continue;
            }

            $current['lines'][] = static::stripHtml($line);
        }

        $sections[] = $current;

        $result = [];

        foreach ($sections as $section) {
            $content = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $section['lines'])));

            if ($content === '') {
                continue;
            }

            $result[] = [
                'title' => $title,
                'heading' => $section['heading'] === '' ? $title : $section['heading'],
                'anchor' => $section['anchor'],
                'content' => $content,
            ];
        }

        return $result;
    }

    protected static function clean(string $heading): string
    {
        return trim((string) preg_replace('/\s*\{#[^}]+\}\s*$/', '', strip_tags($heading)));
    }

    protected static function stripHtml(string $line): string
    {
        if (preg_match('/^\s*<\/?(div|style|head|input|x-[a-z-]+)\b[^>]*>\s*$/i', $line)) {
            return '';
        }

        return $line;
    }

    public static function slug(string $heading): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($heading)), '-');
    }
}
