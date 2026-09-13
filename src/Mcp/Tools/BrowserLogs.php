<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Tools;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\Brain\Concerns\ReadsLogs;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class BrowserLogs extends Tool
{
    use ReadsLogs;

    /**
     * The tool's description.
     */
    protected string $description = 'Read the last N log entries from the BROWSER log. Very helpful for debugging the frontend and JS/Javascript';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entries' => $schema->integer()
                ->description('Number of log entries to return.')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $maxEntries = $request->integer('entries');

        if ($maxEntries <= 0) {
            return Response::error('The "entries" argument must be greater than 0.');
        }

        $logFile = $this->resolveLogFilePath('browser', storage_path('logs'.DIRECTORY_SEPARATOR.'browser.log'));

        if (! file_exists($logFile)) {
            return Response::error('No log file found at '.$logFile.'. This probably means no logs yet, or the `browser` log channel does not write to a file.');
        }

        $entries = $this->readLastLogEntries($logFile, $maxEntries);

        if ($entries === []) {
            return Response::text('Unable to retrieve log entries, or no logs');
        }

        $logs = implode("\n\n", $entries);

        if (empty(trim($logs))) {
            return Response::text('No log entries yet.');
        }

        return Response::text($logs);
    }
}
