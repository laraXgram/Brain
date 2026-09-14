<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Brain Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Brain functionality which will
    | prevent Brain's routes from being registered and will also disable
    | Brain's browser logging functionality from reading or operating.
    |
    */

    'enabled' => env('BRAIN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Brain Project Rules
    |--------------------------------------------------------------------------
    |
    | Project rules let agents write decisions, traps and standing constraints
    | as tracked Markdown in /.ai/rules/. Enabling "scoped_guidelines" also
    | moves path-scoped guidelines to .ai/rules/brain/ - it stays opt-in.
    |
    */

    'rules' => [
        'enabled' => env('BRAIN_RULES_ENABLED', true),
        'scoped_guidelines' => env('BRAIN_RULES_SCOPED_GUIDELINES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Guidelines
    |--------------------------------------------------------------------------
    |
    | Any guidelines listed here will be excluded whenever Brain composes your
    | AI guidelines during brain:install or brain:update. Entries match the
    | names shown within the brain:install summary, e.g. "luna-laragram/core".
    |
    */

    'guidelines' => [
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Skills
    |--------------------------------------------------------------------------
    |
    | Any skills listed here will not be installed or synced to your agents
    | by brain:install and brain:update, e.g. "luna-react-development". Your
    | own skills within the ".ai/skills" directory are never excluded.
    |
    */

    'skills' => [
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Brain Executables Paths
    |--------------------------------------------------------------------------
    |
    | These options allow you to specify custom paths for the executables that
    | Brain uses. While configured, they take precedence over the automatic
    | discovery mechanism. When undefined, your system defaults are used.
    |
    */

    'executable_paths' => [
        'php' => env('BRAIN_PHP_EXECUTABLE_PATH'),
        'composer' => env('BRAIN_COMPOSER_EXECUTABLE_PATH'),
        'npm' => env('BRAIN_NPM_EXECUTABLE_PATH'),
        'vendor_bin' => env('BRAIN_VENDOR_BIN_EXECUTABLE_PATH'),
        'current_directory' => env('BRAIN_CURRENT_DIRECTORY_EXECUTABLE_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tinker Tool
    |--------------------------------------------------------------------------
    |
    | The "tinker" MCP tool lets agents run PHP code in the application's
    | context through "php laragram tinker". It is only registered when
    | this option is enabled and the tinker command is available.
    |
    */

    'tinker_tool_enabled' => env('BRAIN_TINKER_TOOL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Bot Runtime Tools
    |--------------------------------------------------------------------------
    |
    | When enabled, Brain exposes the bot runtime tools of laraxgram/mcp to
    | agents: listing listens, simulating Telegram updates, rendering the
    | templates and inspecting conversations. Nothing is sent to Telegram
    | and the tools are only available within the "local" environment.
    |
    */

    'bot_runtime_tools' => env('BRAIN_BOT_RUNTIME_TOOLS', true),

    /*
    |--------------------------------------------------------------------------
    | Brain Browser Logs Watcher
    |--------------------------------------------------------------------------
    |
    | The following option may be used to enable or disable the browser logs
    | watcher feature within LaraGram Brain. The log watcher will read any
    | errors within the browser's console (including Telegram Mini Apps
    | served by Luna) to give Brain better context.
    |
    */

    'browser_logs_watcher' => env('BRAIN_BROWSER_LOGS_WATCHER', true),

    /*
    |--------------------------------------------------------------------------
    | Browser Log Levels
    |--------------------------------------------------------------------------
    |
    | This option defines which browser console log levels will be captured by
    | Brain's browser logger. You may trim this list down to ['error'] when
    | warnings, info, and debug messages become too noisy to be relevant.
    |
    */

    'browser_log_levels' => explode(',', env('BRAIN_BROWSER_LOG_LEVELS', 'error,warning,info,debug')),

];
