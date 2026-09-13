<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Tools;

use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class DatabaseConnections extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List the configured database connection names for this application.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $connections = array_keys(config('database.connections', []));

        return Response::json([
            'default_connection' => config('database.default'),
            'connections' => $connections,
        ]);
    }
}
