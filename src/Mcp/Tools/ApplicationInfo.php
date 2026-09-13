<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Tools;

use LaraGram\Support\Facades\DB;
use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaraGram\Brain\Discovery\Package;
use LaraGram\Brain\Discovery\ProjectManager;

#[IsReadOnly]
class ApplicationInfo extends Tool
{
    public function __construct(protected ProjectManager $project)
    {
        //
    }

    /**
     * The tool's description.
     */
    protected string $description = 'Get comprehensive application information including PHP version, LaraGram version, database engine, and all installed packages with their versions. You should use this tool on each new chat, and use the package & version data to write version specific code for the packages that exist.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        return Response::json([
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'laragram_version' => app()->version(),
            'database_engine' => DB::connection()->getDriverName(),
            'packages' => $this->project->php()->packages()
                ->concat($this->project->js()->packages())
                ->map(fn (Package $package): array => [
                    'roster_name' => PackageRegistry::rosterName($package->name()),
                    'version' => $package->version(),
                    'package_name' => $package->name(),
                ]),
        ]);
    }
}
