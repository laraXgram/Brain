<?php

declare(strict_types=1);

namespace LaraGram\Brain;

use LaraGram\Foundation\Http\Middleware\VerifyCsrfToken;
use LaraGram\Http\Request;
use LaraGram\Log\Logger;
use LaraGram\Routing\Router;
use LaraGram\Support\Facades\Log;
use LaraGram\Support\Facades\Route;
use LaraGram\Support\ServiceProvider;
use LaraGram\View\Compilers\BladeCompiler;
use LaraGram\Brain\Install\GuidelineAssist;
use LaraGram\Brain\Install\GuidelineConfig;
use LaraGram\Brain\Mcp\Brain;
use LaraGram\Brain\Middleware\InjectBrain;
use LaraGram\Brain\Rules\RuleRepository;
use LaraGram\Brain\Services\BrowserLogger;
use LaraGram\Brain\Support\RenderFailures;
use LaraGram\Brain\Support\SkillParseFailures;
use LaraGram\Mcp\Facades\Mcp;
use LaraGram\Brain\Discovery\ProjectManager;

class BrainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/brain.php',
            'brain'
        );

        $this->app->singleton(RenderFailures::class, fn (): RenderFailures => new RenderFailures);
        $this->app->singleton(SkillParseFailures::class, fn (): SkillParseFailures => new SkillParseFailures);

        if (! $this->shouldRun()) {
            return;
        }

        $this->app->singleton(BrainManager::class, fn (): BrainManager => new BrainManager);

        $this->app->singleton(ProjectManager::class, fn (): ProjectManager => new ProjectManager);

        $this->app->singleton(GuidelineConfig::class, fn (): GuidelineConfig => new GuidelineConfig);

        $this->app->singleton(RuleRepository::class, fn (): RuleRepository => new RuleRepository(base_path('.ai/rules')));

        $this->app->singleton(GuidelineAssist::class, fn ($app): GuidelineAssist => new GuidelineAssist(
            $app->make(ProjectManager::class),
            $app->make(GuidelineConfig::class)
        ));
    }

    public function boot(Router $router): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        Mcp::local('laragram-brain', Brain::class);

        $this->registerPublishing();
        $this->registerCommands();

        if (config('brain.browser_logs_watcher', true)) {
            $this->registerRoutes();
            $this->registerBrowserLogger();
            $this->callAfterResolving('blade.compiler', $this->registerBladeDirectives(...));
            $this->hookIntoResponses($router);
        }
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/brain.php' => config_path('brain.php'),
            ], 'brain-config');
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\StartCommand::class,
                Console\InstallCommand::class,
                Console\UpdateCommand::class,
                Console\ExecuteToolCommand::class,
                Console\AddSkillCommand::class,
                Console\ListSkillCommand::class,
                Console\ScanCommand::class,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        Route::post('/_brain/browser-logs', function (Request $request) {
            $logs = $request->input('logs', []);

            // Handle sendBeacon's text/plain content type.
            if (empty($logs) && ! $request->isJson()) {
                $decoded = json_decode($request->getContent(), true);
                $logs = $decoded['logs'] ?? [];
            }

            /** @var Logger $logger */
            $logger = Log::channel('browser');

            /**
             *  @var array{
             *      type: 'error'|'warn'|'info'|'log'|'table'|'window_error'|'uncaught_error'|'unhandled_rejection',
             *      timestamp: string,
             *      data: array,
             *      url:string,
             *      userAgent:string
             *  } $log */
            foreach ($logs as $log) {
                $logger->write(
                    level: match ($log['type']) {
                        'warn' => 'warning',
                        'log', 'table' => 'debug',
                        'window_error', 'uncaught_error', 'unhandled_rejection' => 'error',
                        default => $log['type']
                    },
                    message: self::buildLogMessageFromData($log['data']),
                    context: [
                        'url' => $log['url'],
                        'user_agent' => $log['userAgent'] ?: null,
                        'timestamp' => $log['timestamp'] ?: now()->toIso8601String(),
                    ]
                );
            }

            return response()->json(['status' => 'logged']);
        })
            ->name('brain.browser-logs')
            ->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * Build a string message for the log based on various input types. Single-dimensional, and multi:
     * "data": {"message":"Unhandled Promise Rejection","reason":{"name":"TypeError","message":"NetworkError when attempting to fetch resource.","stack":""}}]
     */
    private static function buildLogMessageFromData(array $data): string
    {
        $messages = [];

        foreach ($data as $value) {
            $messages[] = match (true) {
                is_array($value) => self::buildLogMessageFromData($value),
                is_string($value), is_numeric($value) => (string) $value,
                is_bool($value) => $value ? 'true' : 'false',
                is_null($value) => 'null',
                is_object($value) => json_encode($value),
                default => $value,
            };
        }

        return implode(' ', $messages);
    }

    protected function registerBrowserLogger(): void
    {
        if (config('logging.channels.browser') !== null) {
            return;
        }

        config([
            'logging.channels.browser' => [
                'driver' => 'single',
                'path' => storage_path('logs'.DIRECTORY_SEPARATOR.'browser.log'),
                'level' => env('LOG_LEVEL', 'debug'),
                'days' => 14,
            ],
        ]);
    }

    protected function registerBladeDirectives(BladeCompiler $bladeCompiler): void
    {
        $bladeCompiler->directive('brainJs', fn (): string => '<?php echo '.BrowserLogger::class.'::getScript(); ?>');
    }

    protected function hookIntoResponses(Router $router): void
    {
        $this->app->booted(function () use ($router): void {
            $router->pushMiddlewareToGroup('web', InjectBrain::class);
        });
    }

    protected function shouldRun(): bool
    {
        if (! config('brain.enabled', true)) {
            return false;
        }

        // Only enable Brain on local environments or when debug is true
        if (! app()->environment('local') && config('app.debug', false) !== true) {
            return false;
        }

        return true;
    }
}
