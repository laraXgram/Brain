<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Tools;

use LaraGram\Log\Events\MessageLogged;
use LaraGram\Support\Facades\Cache;
use LaraGram\Support\Facades\Log;
use LaraGram\Support\Str;
use LaraGram\Brain\Concerns\ReadsLogs;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LastError extends Tool
{
    use ReadsLogs;

    /**
     * Indicates whether the Log listener has been registered for this process.
     */
    private static bool $listenerRegistered = false;

    public function __construct()
    {
        // Register the listener only once per PHP process.
        if (! self::$listenerRegistered) {
            Log::listen(function (MessageLogged $event): void {
                if ($event->level === 'error') {
                    rescue(fn () => Cache::forever('brain:last_error', [
                        'timestamp' => now()->toDateTimeString(),
                        'level' => $event->level,
                        'message' => $event->message,
                        'context' => [], // $event->context,
                    ]), report: false);
                }
            });

            self::$listenerRegistered = true;
        }
    }

    /**
     * The tool's description.
     */
    protected string $description = 'Get details of the last error/exception created in this application on the backend. Use browser-logs tool for browser errors.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        // First, attempt to retrieve the cached last error captured during runtime.
        // This works even if the log driver isn't a file driver, so is the preferred approach
        $cached = rescue(fn () => Cache::get('brain:last_error'), report: false);

        if ($cached) {
            $entry = "[{$cached['timestamp']}] {$cached['level']}: {$cached['message']}";

            if (! empty($cached['context'])) {
                $entry .= ' '.json_encode($cached['context']);
            }

            return Response::text($entry);
        }

        // Locate the correct log file using the shared helper.
        $logFile = $this->resolveLogFilePath();

        if (! file_exists($logFile)) {
            return Response::error("Log file not found at {$logFile}");
        }

        $entry = $this->readLastErrorEntry($logFile);

        if ($entry !== null) {
            return Response::text(Str::limit($entry, 500, '... more logs', true));
        }

        return Response::error('Unable to find an ERROR entry in the inspected portion of the log file.');
    }
}
