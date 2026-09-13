<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp;

use LaraGram\Brain\Mcp\Methods\CallToolWithExecutor;
use LaraGram\Brain\Mcp\Prompts\LaraGramCodeSimplifier\LaraGramCodeSimplifier;
use LaraGram\Brain\Mcp\Prompts\UpgradeLaraGramV4\UpgradeLaraGramV4;
use LaraGram\Brain\Mcp\Tools\ApplicationInfo;
use LaraGram\Brain\Mcp\Tools\BrowserLogs;
use LaraGram\Brain\Mcp\Tools\DatabaseConnections;
use LaraGram\Brain\Mcp\Tools\DatabaseQuery;
use LaraGram\Brain\Mcp\Tools\DatabaseSchema;
use LaraGram\Brain\Mcp\Tools\GetAbsoluteUrl;
use LaraGram\Brain\Mcp\Tools\LastError;
use LaraGram\Brain\Mcp\Tools\ReadLogEntries;
use LaraGram\Brain\Mcp\Tools\RecordRule;
use LaraGram\Brain\Mcp\Tools\SearchDocs;
use LaraGram\Brain\Mcp\Tools\Tinker;
use LaraGram\Mcp\Schema\Icon;
use LaraGram\Mcp\Server;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\Tool;

class Brain extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'LaraGram Brain';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = 'LaraGram ecosystem MCP server for Telegram bot, MTProto, Mini App (Luna) and web applications, offering database access, error and browser logs, local LaraGram documentation search, and more. Brain helps with code generation.';

    /**
     * The icons exposed to MCP clients.
     *
     * @return list<Icon>
     */
    protected function icons(): array
    {
        $svg = (string) file_get_contents(__DIR__.'/../../resources/icons/brain.svg');

        return [
            Icon::from('data:image/svg+xml;base64,'.base64_encode($svg), 'image/svg+xml', ['40x40']),
        ];
    }

    /**
     * The default pagination length for resources that support pagination.
     */
    public int $defaultPaginationLength = 50;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<Resource>>
     */
    protected array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];

    protected function boot(): void
    {
        $this->tools = $this->discoverTools();
        $this->resources = $this->discoverResources();
        $this->prompts = $this->discoverPrompts();

        // Override the tools/call method to use our ToolExecutor
        $this->methods['tools/call'] = CallToolWithExecutor::class;
    }

    /**
     * @return array<int, class-string<Tool>>
     */
    protected function discoverTools(): array
    {
        return $this->filterPrimitives([
            ApplicationInfo::class,
            BrowserLogs::class,
            DatabaseConnections::class,
            DatabaseQuery::class,
            DatabaseSchema::class,
            GetAbsoluteUrl::class,
            LastError::class,
            ReadLogEntries::class,
            RecordRule::class,
            SearchDocs::class,
            Tinker::class,
        ], 'tools');
    }

    /**
     * @return array<int, class-string<Resource>>
     */
    protected function discoverResources(): array
    {
        return $this->filterPrimitives([
            Resources\ApplicationInfo::class,
        ], 'resources');
    }

    /**
     * @return array<int, class-string<Prompt>>
     */
    protected function discoverPrompts(): array
    {
        return $this->filterPrimitives([
            LaraGramCodeSimplifier::class,
            UpgradeLaraGramV4::class,
        ], 'prompts');
    }

    /**
     * @param  array<int, Tool|Resource|Prompt|class-string>  $availablePrimitives
     * @return array<int, Tool|Resource|Prompt|class-string>
     */
    private function filterPrimitives(array $availablePrimitives, string $type): array
    {
        $excludeList = config("brain.mcp.{$type}.exclude", []);
        $includeList = config("brain.mcp.{$type}.include", []);

        $filtered = collect($availablePrimitives)->reject(function (string|object $item) use ($excludeList): bool {
            $className = is_string($item) ? $item : $item::class;

            return in_array($className, $excludeList, true);
        });

        $explicitlyIncluded = collect($includeList)
            ->filter(fn (string $class): bool => class_exists($class));

        return $filtered
            ->merge($explicitlyIncluded)
            ->values()
            ->all();
    }
}
