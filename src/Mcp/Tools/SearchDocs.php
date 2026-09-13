<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Tools;

use LaraGram\Brain\Discovery\Package;
use LaraGram\Brain\Discovery\ProjectManager;
use LaraGram\Brain\Docs\DocsIndex;
use LaraGram\Brain\Docs\DocsSearcher;
use LaraGram\Brain\Docs\Documentation;
use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
class SearchDocs extends Tool
{
    public function __construct(protected ProjectManager $project)
    {
        //
    }

    /**
     * The tool's description.
     */
    protected string $description = 'Search the LaraGram documentation for the installed major version, offline. Covers LaraGram itself (listening, requests, keyboards, conversations, steps, Temple8 templates, Eloquent, queues, web routing, Blade, ...), MTProto, Luna (Telegram Mini Apps), Surge, Watchdog, Tempora, and the Telegram Bot API methods and types. You must use this tool to search for LaraGram-ecosystem docs before using other approaches.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'queries' => $schema->array()
                ->items($schema->string()->description('Search query'))
                ->description('List of queries to perform, pass multiple if you aren\'t sure if it is "toggle" or "switch", for example')
                ->required(),
            'packages' => $schema->array()
                ->items($schema->string()->description("The package name (e.g., 'laraxgram/mtproto')"))
                ->description('Package names to limit searching to from application-info, e.g. laraxgram/core, laraxgram/laraquest (Telegram Bot API methods and types), laraxgram/mtproto, laraxgram/luna, @laraxgram/react, laraxgram/surge'),
            'token_limit' => $schema->integer()
                ->description('Maximum number of tokens to return in the response. Defaults to 3,000 tokens, maximum 1,000,000 tokens. If results are truncated, or you need more complete documentation, increase this value (e.g.5000, 10000)'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $packagesFilter = $this->resolveArrayParam($request->get('packages'));

        if ($packagesFilter instanceof Response) {
            return $packagesFilter;
        }

        $rawQueries = $this->resolveArrayParam($request->get('queries'));

        if ($rawQueries instanceof Response) {
            return $rawQueries;
        }

        $queries = array_values(array_filter(
            array_map(fn (mixed $query): string => trim((string) $query), $rawQueries ?? []),
            fn (string $query): bool => $query !== '' && $query !== '*'
        ));

        if ($queries === []) {
            return Response::error('Provide at least one search query.');
        }

        $tokenLimit = min(max(1, (int) ($request->get('token_limit') ?? 3000)), 1000000);

        try {
            $documentation = new Documentation($this->documentationVersion());

            if ($documentation->path() === null) {
                return Response::error('The LaraGram documentation is not available. Run [php laragram brain:docs --update] to download it.');
            }

            $results = (new DocsSearcher(DocsIndex::load($documentation)))
                ->search($queries, $this->packages($packagesFilter));
        } catch (Throwable $throwable) {
            return Response::error('Failed to search documentation: '.$throwable->getMessage());
        }

        if ($results === []) {
            return Response::text('No documentation matched the queries. Try broader or different words, or more queries.');
        }

        return Response::text(DocsSearcher::render($results, $tokenLimit));
    }

    protected function documentationVersion(): int
    {
        $major = rescue(fn () => $this->project->php()->packages()
            ->first(fn (Package $package): bool => $package->name() === PackageRegistry::LARAGRAM)
            ?->major(), null, report: false);

        return is_int($major) && is_dir(Documentation::bundledPath($major)) ? $major : 4;
    }

    /**
     * The packages whose documentation may be searched.
     *
     * @param  array<int, mixed>|null  $filter
     * @return list<string>
     */
    protected function packages(?array $filter): array
    {
        if ($filter !== null && $filter !== []) {
            return array_values(array_unique(array_map(
                fn (mixed $package): string => Documentation::canonicalPackage((string) $package),
                $filter,
            )));
        }

        $installed = rescue(fn () => $this->project->php()->packages()
            ->concat($this->project->js()->packages())
            ->map(fn (Package $package): string => Documentation::canonicalPackage($package->name()))
            ->all(), [], report: false);

        return array_values(array_unique([PackageRegistry::LARAGRAM, PackageRegistry::LARAQUEST, ...$installed]));
    }

    /**
     * @return array<int, mixed>|null|Response
     */
    private function resolveArrayParam(mixed $value): array|null|Response
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return Response::error('Invalid parameter: '.json_last_error_msg());
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return Response::error('Invalid parameter: expected a JSON array.');
        }

        return $decoded;
    }
}
