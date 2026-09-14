<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp;

use DirectoryIterator;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Telegram\BotRuntime;
use Throwable;

class ToolRegistry
{
    /** @var array<int, class-string>|null */
    private static ?array $cachedTools = null;

    /** @var array<class-string, Tool>|null */
    private static ?array $cachedToolsetTools = null;

    /**
     * Get all available tools based on the discovery logic from Brain server.
     *
     * @return array<int, class-string>
     */
    public static function getAvailableTools(): array
    {
        if (self::$cachedTools !== null) {
            return self::$cachedTools;
        }

        $tools = [];

        // Discover tools from the Tools directory
        $excludedTools = config('brain.mcp.tools.exclude', []);
        $toolDir = new DirectoryIterator(__DIR__.DIRECTORY_SEPARATOR.'Tools');

        foreach ($toolDir as $toolFile) {
            if ($toolFile->isFile() && $toolFile->getExtension() === 'php') {
                $fqdn = 'LaraGram\\Brain\\Mcp\\Tools\\'.$toolFile->getBasename('.php');

                if (class_exists($fqdn) && ! in_array($fqdn, $excludedTools, true)) {
                    $tools[] = $fqdn;
                }
            }
        }

        // Add the tools built by package toolsets
        foreach (array_keys(self::toolsetTools()) as $toolClass) {
            if (! in_array($toolClass, $excludedTools, true)) {
                $tools[] = $toolClass;
            }
        }

        // Add extra tools from configuration
        $extraTools = config('brain.mcp.tools.include', []);

        foreach ($extraTools as $toolClass) {
            if (class_exists($toolClass) && ! in_array($toolClass, $tools, true)) {
                $tools[] = $toolClass;
            }
        }

        self::$cachedTools = $tools;

        return $tools;
    }

    /**
     * Get the tool instances built by package toolsets, keyed by their class.
     *
     * Toolset tools receive their toolset through the constructor, so they are
     * rebuilt from the same toolset in the tool subprocess instead of the container.
     *
     * @return array<class-string, Tool>
     */
    public static function toolsetTools(): array
    {
        if (self::$cachedToolsetTools !== null) {
            return self::$cachedToolsetTools;
        }

        $tools = [];

        if (config('brain.bot_runtime_tools', true) && class_exists(BotRuntime::class)) {
            try {
                foreach (BotRuntime::tools()->all() as $tool) {
                    $tools[$tool::class] = $tool;
                }
            } catch (Throwable) {
                //
            }
        }

        return self::$cachedToolsetTools = $tools;
    }

    /**
     * Resolve the tool instance for the given tool class.
     */
    public static function resolve(string $toolClass): Tool
    {
        return self::toolsetTools()[$toolClass] ?? app($toolClass);
    }

    /**
     * Check if a tool class is allowed to be executed.
     */
    public static function isToolAllowed(string $toolClass): bool
    {
        return in_array($toolClass, self::getAvailableTools(), true);
    }

    /**
     * Clear the cached tools (useful for testing or when configuration changes).
     */
    public static function clearCache(): void
    {
        self::$cachedTools = null;
        self::$cachedToolsetTools = null;
    }

    /**
     * Get tool names (class basenames) mapped to their full class names.
     *
     * @return array<string, class-string>
     */
    public static function getToolNames(): array
    {
        $tools = self::getAvailableTools();
        $names = [];

        foreach ($tools as $toolClass) {
            $name = class_basename($toolClass);
            $names[$name] = $toolClass;
        }

        return $names;
    }
}
