<?php

declare(strict_types=1);

namespace LaraGram\Brain\Docs;

use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Laraquest\Schema\ApiSchema;
use Throwable;

/**
 * An inverted index over the documentation sections and the Telegram Bot API schema.
 *
 * @phpstan-type Section array{id: int, package: string, page: string, title: string, heading: string, url: string, content: string, text: string, length: int}
 */
class DocsIndex
{
    /** @var list<Section> */
    protected array $sections = [];

    /** @var array<string, array<int, int>> term => [section id => weighted frequency] */
    protected array $postings = [];

    protected float $averageLength = 0.0;

    public function __construct(protected Documentation $documentation)
    {
        //
    }

    /**
     * Load the index from the cache or build it.
     */
    public static function load(Documentation $documentation): static
    {
        $index = new static($documentation);
        $cacheFile = $index->cacheFile();

        if ($cacheFile !== null && is_file($cacheFile)) {
            try {
                $data = require $cacheFile;

                if (is_array($data) && isset($data['sections'], $data['postings'], $data['average'])) {
                    $index->sections = $data['sections'];
                    $index->postings = $data['postings'];
                    $index->averageLength = (float) $data['average'];

                    return $index;
                }
            } catch (Throwable) {
                //
            }
        }

        $index->build();

        if ($cacheFile !== null) {
            $index->store($cacheFile);
        }

        return $index;
    }

    public function build(): void
    {
        $this->sections = [];
        $this->postings = [];

        foreach ($this->documentation->pages() as $page => $file) {
            foreach (MarkdownSections::split((string) file_get_contents($file)) as $section) {
                $this->add(
                    package: $this->documentation->packageFor($page),
                    page: $page,
                    title: $section['title'],
                    heading: $section['heading'],
                    url: $this->documentation->url($page, $section['anchor']),
                    content: $section['content'],
                );
            }
        }

        $this->addBotApiSchema();

        $total = array_sum(array_column($this->sections, 'length'));
        $this->averageLength = $this->sections === [] ? 0.0 : $total / count($this->sections);
    }

    protected function addBotApiSchema(): void
    {
        try {
            if (! class_exists(ApiSchema::class) || ! ApiSchema::exists()) {
                return;
            }

            $methods = ApiSchema::methods();
            $types = ApiSchema::types();
        } catch (Throwable) {
            return;
        }

        foreach ($methods as $name => $method) {
            $lines = [trim((string) ($method['description'] ?? ''))];

            if (! empty($method['returns'])) {
                $lines[] = "Returns: {$method['returns']}.";
            }

            if (! empty($method['parameters'])) {
                $lines[] = '';
                $lines[] = '| Parameter | Type | Required | Description |';
                $lines[] = '|---|---|---|---|';

                foreach ($method['parameters'] as $parameter) {
                    $lines[] = sprintf('| `%s` | %s | %s | %s |', $parameter['name'], $parameter['type'], $parameter['required'] ? 'Yes' : 'Optional', trim((string) preg_replace('/\s+/', ' ', $parameter['description'])));
                }
            }

            $lines[] = '';
            $lines[] = "Call it on the bot request: `\$request->{$name}(...)` (named arguments match the parameters) or `\$request->call('{$name}', [...])`.";

            $this->add(PackageRegistry::LARAQUEST, 'bot-api', 'Telegram Bot API', "Method: {$name}", 'https://core.telegram.org/bots/api#'.strtolower($name), implode("\n", $lines));
        }

        foreach ($types as $name => $type) {
            $lines = [trim((string) ($type['description'] ?? ''))];

            if (! empty($type['fields'])) {
                $lines[] = '';
                $lines[] = '| Field | Type | Required | Description |';
                $lines[] = '|---|---|---|---|';

                foreach ($type['fields'] as $field) {
                    $lines[] = sprintf('| `%s` | %s | %s | %s |', $field['name'], $field['type'], $field['required'] ? 'Yes' : 'Optional', trim((string) preg_replace('/\s+/', ' ', $field['description'])));
                }
            }

            $this->add(PackageRegistry::LARAQUEST, 'bot-api', 'Telegram Bot API', "Type: {$name}", 'https://core.telegram.org/bots/api#'.strtolower($name), implode("\n", $lines));
        }
    }

    protected function add(string $package, string $page, string $title, string $heading, string $url, string $content): void
    {
        $id = count($this->sections);
        $text = Tokenizer::normalize($title.' '.$heading.' '.$content);
        $length = 0;

        $headingTerms = Tokenizer::terms(str_replace('-', ' ', $page).' '.$title.' '.$heading);
        $contentTerms = Tokenizer::terms($content);

        foreach ([[$headingTerms, 3], [$contentTerms, 1]] as [$terms, $weight]) {
            foreach ($terms as $term) {
                $this->postings[$term][$id] = ($this->postings[$term][$id] ?? 0) + $weight;
                $length += $weight;
            }
        }

        $this->sections[] = [
            'id' => $id,
            'package' => $package,
            'page' => $page,
            'title' => $title,
            'heading' => $heading,
            'url' => $url,
            'content' => $content,
            'text' => ' '.$text.' ',
            'length' => $length,
        ];
    }

    /**
     * @return list<Section>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * @return array<int, int> section id => weighted term frequency
     */
    public function postings(string $term): array
    {
        return $this->postings[$term] ?? [];
    }

    public function averageLength(): float
    {
        return $this->averageLength;
    }

    protected function cacheFile(): ?string
    {
        $pages = $this->documentation->pages();

        if ($pages === []) {
            return null;
        }

        $hash = hash_init('xxh128');

        foreach ($pages as $page => $file) {
            hash_update($hash, $page.':'.filesize($file).':'.filemtime($file).'|');
        }

        try {
            hash_update($hash, class_exists(ApiSchema::class) && ApiSchema::exists() ? (string) (ApiSchema::version() ?? filemtime(ApiSchema::path())) : 'no-schema');
        } catch (Throwable) {
            //
        }

        hash_update($hash, Tokenizer::VERSION);

        return storage_path('framework'.DIRECTORY_SEPARATOR.'brain'.DIRECTORY_SEPARATOR.'docs-index-v'.$this->documentation->version().'-'.hash_final($hash).'.php');
    }

    protected function store(string $cacheFile): void
    {
        try {
            $directory = dirname($cacheFile);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            foreach (glob($directory.DIRECTORY_SEPARATOR.'docs-index-v'.$this->documentation->version().'-*.php') ?: [] as $stale) {
                @unlink($stale);
            }

            $temporary = $cacheFile.'.'.getmypid().'.tmp';

            file_put_contents($temporary, '<?php return '.var_export([
                'sections' => $this->sections,
                'postings' => $this->postings,
                'average' => $this->averageLength,
            ], true).';');

            rename($temporary, $cacheFile);
        } catch (Throwable) {
            //
        }
    }
}
