<?php

declare(strict_types=1);

namespace LaraGram\Brain\Console;

use Exception;
use LaraGram\Console\Command;
use LaraGram\Support\Collection;
use LaraGram\Support\Str;
use LaraGram\Brain\Concerns\DisplayHelper;
use LaraGram\Brain\Concerns\ReportsSkillParseFailures;
use LaraGram\Brain\Contracts\SupportsGuidelines;
use LaraGram\Brain\Contracts\SupportsMcp;
use LaraGram\Brain\Contracts\SupportsSkills;
use LaraGram\Brain\Install\Agents\Agent;
use LaraGram\Brain\Install\AgentsDetector;
use LaraGram\Brain\Install\GuidelineComposer;
use LaraGram\Brain\Install\GuidelineConfig;
use LaraGram\Brain\Install\GuidelineWriter;
use LaraGram\Brain\Install\McpWriter;
use LaraGram\Brain\Install\RuleComposer;
use LaraGram\Brain\Install\Skill;
use LaraGram\Brain\Install\SkillComposer;
use LaraGram\Brain\Install\SkillWriter;
use LaraGram\Brain\Install\ThirdPartyPackage;
use LaraGram\Brain\Rules\RuleRepository;
use LaraGram\Brain\Support\Config;
use LaraGram\Brain\Support\RenderFailures;
use LaraGram\Brain\Support\SkillParseFailures;
use LaraGram\Console\Prompts\Terminal;
use LaraGram\Brain\Discovery\ProjectManager;
use RuntimeException;
use Throwable;

use function LaraGram\Console\Prompts\grid;
use function LaraGram\Console\Prompts\multiselect;
use function LaraGram\Console\Prompts\note;

class InstallCommand extends Command
{
    use DisplayHelper;
    use ReportsSkillParseFailures;

    protected $signature = 'brain:install
        {--guidelines : Install AI guidelines}
        {--skills : Install agent skills}
        {--mcp : Install MCP server configuration}';

    /** @var Collection<int, Agent> */
    private Collection $selectedAgents;

    /** @var Collection<int, string> */
    private Collection $selectedBrainFeatures;

    /** @var Collection<int, string> */
    private Collection $selectedThirdPartyPackages;

    private string $projectName;

    /** @var array<non-empty-string> */
    private array $systemInstalledAgents = [];

    /** @var array<non-empty-string> */
    private array $projectInstalledAgents = [];

    /** @var array<int, string> */
    private array $installedSkillNames = [];

    public function __construct(
        private readonly AgentsDetector $agentsDetector,
        private readonly Config $config,
        private readonly ProjectManager $project,
        private readonly Terminal $terminal
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        app(SkillParseFailures::class)->flush();

        $this->terminal->initDimensions();
        $this->projectName = config('app.name');

        $this->displayBrainHeader('Install', $this->projectName);
        $this->discoverEnvironment();
        $this->collectInstallationPreferences();
        $this->performInstallation();

        $this->reportRenderFailures();
        $this->reportSkillParseFailures();

        $this->noteInferConventions();

        $this->outro();

        return self::SUCCESS;
    }

    protected function discoverEnvironment(): void
    {
        if ($this->config->getAgents() !== []) {
            return;
        }

        $this->systemInstalledAgents = $this->agentsDetector->discoverSystemInstalledAgents();
        $this->projectInstalledAgents = $this->agentsDetector->discoverProjectInstalledAgents(base_path());
    }

    protected function collectInstallationPreferences(): void
    {
        $this->selectedBrainFeatures = $this->selectBrainFeatures();

        $this->selectedThirdPartyPackages = $this->selectedBrainFeatures->contains('guidelines') || $this->selectedBrainFeatures->contains('skills')
            ? $this->selectThirdPartyPackages()
            : collect();

        $this->selectedAgents = $this->selectAgents();
    }

    protected function performInstallation(): void
    {
        app()->instance(GuidelineConfig::class, $this->buildGuidelineConfig());

        if ($this->selectedBrainFeatures->contains('guidelines')) {
            $this->installGuidelines();
        }

        if ($this->selectedBrainFeatures->contains('skills')) {
            $this->installSkills();
        }

        if ($this->selectedBrainFeatures->contains('mcp')) {
            $this->installMcpServerConfig();
        }

        $this->storeConfig();
    }

    protected function reportRenderFailures(): void
    {
        $renderFailures = app(RenderFailures::class);

        if ($renderFailures->isEmpty()) {
            return;
        }

        $paths = $renderFailures->paths();
        $packages = $renderFailures->packages();

        $this->newLine();
        $this->warn(sprintf('Skipped %d %s that could not be rendered:', count($paths), Str::plural('file', $paths)));

        foreach ($paths as $path) {
            $this->line('  - '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
        }

        if ($packages !== []) {
            $this->warn('These ship Brain files built for an older Brain version, so Brain used its own where it had them. Update them with: composer update '.implode(' ', $packages));
        }
    }

    protected function noteInferConventions(): void
    {
        note('💡 Run the infer-conventions skill to record your app conventions and sharpen code generation.');
    }

    protected function outro(): void
    {
        $url = 'https://laraxgram.github.io/v4/';
        $link = $this->hyperlink($url, $url);
        $text = 'Enjoy the brain 🚀 Next steps: ';

        $this->displayOutro($text, $link, $this->terminal->cols());
    }

    /**
     * @return Collection<int, string>
     */
    protected function selectBrainFeatures(): Collection
    {
        $featureLabels = collect([
            'guidelines' => 'AI Guidelines',
            'skills' => 'Agent Skills',
            'mcp' => 'Brain MCP Server Configuration',
        ]);

        $explicit = $featureLabels->keys()->filter(fn ($feature) => $this->option($feature));

        if ($explicit->isNotEmpty()) {
            return $explicit->values();
        }

        $configValues = collect([
            'guidelines' => $this->config->getGuidelines(),
            'skills' => $this->config->hasSkills(),
            'mcp' => $this->config->getMcp(),
        ]);

        $defaults = $configValues->filter()->keys()->whenEmpty(fn () => $featureLabels->keys());

        if (! $this->input->isInteractive()) {
            return $defaults->values();
        }

        return collect(multiselect(
            label: 'Which Brain features would you like to configure?',
            options: $featureLabels->all(),
            default: $defaults->all(),
            required: true,
            hint: 'This will override the current guidelines, skills, and MCP configuration',
        ));
    }

    /**
     * @return Collection<int, string>
     */
    protected function selectThirdPartyPackages(): Collection
    {
        $packages = ThirdPartyPackage::discover($this->project);

        if ($packages->isEmpty()) {
            return collect();
        }

        $defaults = collect($this->config->getPackages())
            ->filter(fn (string $name) => $packages->has($name))
            ->values();

        if (! $this->input->isInteractive()) {
            return $defaults;
        }

        return collect(multiselect(
            label: 'Which third-party AI guidelines/skills would you like to install?',
            options: $packages->mapWithKeys(fn (ThirdPartyPackage $pkg, string $name): array => [
                $name => $pkg->displayLabel(),
            ])->toArray(),
            default: $defaults->all(),
            scroll: 10,
            hint: 'You can add or remove them later by running this command again',
        ));
    }

    /**
     * @return Collection<int, Agent>
     */
    protected function selectAgents(): Collection
    {
        $allAgents = $this->agentsDetector->getAgents();

        if ($allAgents->isEmpty()) {
            return collect();
        }

        $featureInterfaces = [
            'guidelines' => SupportsGuidelines::class,
            'skills' => SupportsSkills::class,
            'mcp' => SupportsMcp::class,
        ];

        $filteredAgents = $allAgents->filter(
            fn (Agent $agent): bool => $this->selectedBrainFeatures->contains(
                fn ($feature): bool => isset($featureInterfaces[$feature]) && $agent instanceof $featureInterfaces[$feature])
        )->keyBy(fn (Agent $agent): string => $agent->name());

        if ($filteredAgents->isEmpty()) {
            return collect();
        }

        $options = $filteredAgents
            ->mapWithKeys(fn (Agent $agent): array => [$agent->name() => $agent->displayName()])
            ->sort();

        $defaults = collect($this->config->getAgents())
            ->filter(fn (string $name) => $filteredAgents->has($name))
            ->whenEmpty(fn () => collect([...$this->projectInstalledAgents, ...$this->systemInstalledAgents])
                ->unique()
                ->filter(fn (string $name) => $filteredAgents->has($name))
            )
            ->values();

        if (! $this->input->isInteractive()) {
            return $defaults
                ->map(fn (string $name) => $filteredAgents->get($name))
                ->values();
        }

        $selected = multiselect(
            label: 'Which AI agents would you like to configure?',
            options: $options->all(),
            default: $defaults->all(),
            scroll: $options->count(),
            required: true,
        );

        return collect($selected)
            ->map(fn (string $name) => $filteredAgents->get($name))
            ->values();
    }

    /**
     * @return Collection<int, Agent&SupportsMcp>
     */
    protected function agentsWithMcp(): Collection
    {
        return $this->selectedAgents->filter(fn (Agent $a): bool => $a instanceof SupportsMcp);
    }

    /**
     * @return Collection<int, Agent&SupportsGuidelines>
     */
    protected function agentsWithGuidelines(): Collection
    {
        return $this->selectedAgents->filter(fn (Agent $a): bool => $a instanceof SupportsGuidelines);
    }

    /**
     * @return Collection<int, Agent&SupportsSkills>
     */
    protected function agentsWithSkills(): Collection
    {
        return $this->selectedAgents->filter(fn (Agent $a): bool => $a instanceof SupportsSkills);
    }

    protected function installGuidelines(): void
    {
        $guidelinesAgents = $this->agentsWithGuidelines();
        $guidelineConfig = $this->buildGuidelineConfig();
        $composer = app(GuidelineComposer::class)->config($guidelineConfig);

        $this->syncRuleFiles($composer);

        $guidelines = $composer->guidelines();
        $composedAiGuidelines = $composer->compose();

        $this->installFeature(
            agents: $guidelinesAgents,
            emptyMessage: 'No agents are selected for guideline installation.',
            headerMessage: sprintf('Adding %d guidelines to your selected agents', $guidelines->count()),
            nameResolver: fn (Agent $agent): string => $agent->displayName(),
            processor: fn (Agent&SupportsGuidelines $agent): int => (new GuidelineWriter($agent))->write($composedAiGuidelines),
            featureName: 'guidelines',
            beforeProcess: fn () => grid($guidelines->map(fn ($guideline, string $key): string => $key.($guideline['custom'] ? '*' : ''))->sort()->values()->toArray()),
            withDelay: true,
        );
    }

    protected function syncRuleFiles(GuidelineComposer $composer): void
    {
        $repository = app(RuleRepository::class);

        if (! config('brain.rules.enabled', true) || ! config('brain.rules.scoped_guidelines', false)) {
            rescue(fn () => $repository->clearManaged(), report: false);

            return;
        }

        try {
            $written = $repository->syncManaged((new RuleComposer($composer))->composeManaged());
        } catch (Throwable) {
            try {
                $repository->clearManaged();
            } catch (Throwable $cleanupError) {
                throw new RuntimeException(
                    'Failed to write path-scoped rules and could not clear .ai/rules/brain. '
                    .'Resolve the directory (it may be locked) and re-run brain:install.',
                    0,
                    $cleanupError,
                );
            }

            $composer->withoutRuleExtraction();

            $this->warn('Could not write path-scoped rules to .ai/rules/brain — keeping them inline in the guidelines instead.');

            return;
        }

        if ($written !== []) {
            $this->info(sprintf('Extracted %d path-scoped %s to .ai/rules/brain', count($written), Str::plural('rule file', count($written))));
        }
    }

    protected function installSkills(): void
    {
        $skillsAgents = $this->agentsWithSkills();
        $skillsComposer = app(SkillComposer::class)->config($this->buildGuidelineConfig());
        $skills = $skillsComposer->skills();
        $previouslyTrackedSkills = $this->config->getSkills();
        // Matched on directory name: brain.json tracks frontmatter names, which are unreadable here.
        $invalidSkillNames = app(SkillParseFailures::class)->skillNames();
        $preservedSkillNames = array_values(array_intersect($previouslyTrackedSkills, $invalidSkillNames));
        $trackedSkillsToSync = array_values(array_diff($previouslyTrackedSkills, $preservedSkillNames));

        $this->installedSkillNames = array_values(array_unique([
            ...$skills->keys()->toArray(),
            ...$preservedSkillNames,
        ]));

        /** @var Collection<int, SupportsSkills&Agent> $skillsAgents */
        $this->installFeature(
            agents: $skillsAgents,
            emptyMessage: 'No agents are selected for skill installation.',
            headerMessage: sprintf('Syncing %d skills for skills-capable agents', $skills->count()),
            nameResolver: fn (SupportsSkills&Agent $agent): string => $agent->displayName(),
            processor: fn (SupportsSkills&Agent $agent): array => (new SkillWriter($agent))->sync($skills, $trackedSkillsToSync),
            featureName: 'skills',
            beforeProcess: $skills->isNotEmpty()
                ? fn () => grid($skills->map(fn (Skill $skill): string => $skill->displayName())->sort()->values()->toArray())
                : null,
        );
    }

    protected function buildGuidelineConfig(): GuidelineConfig
    {
        $guidelineConfig = new GuidelineConfig;
        $guidelineConfig->hasAnApi = false;
        $guidelineConfig->aiGuidelines = $this->selectedThirdPartyPackages->values()->toArray();
        $guidelineConfig->hasSkills = $this->selectedBrainFeatures->contains('skills');
        $guidelineConfig->hasMcp = $this->selectedBrainFeatures->contains('mcp') || ($this->isExplicitFlagMode() && $this->config->getMcp());

        return $guidelineConfig;
    }

    protected function storeConfig(): void
    {
        $explicitMode = $this->isExplicitFlagMode();

        if (! $explicitMode) {
            $this->config->flush();
            $this->config->setAgents($this->selectedAgents->map(fn (Agent $agent): string => $agent->name())->values()->toArray());
            $this->config->setPackages($this->selectedThirdPartyPackages->values()->toArray());
        } elseif ($this->selectedBrainFeatures->contains('guidelines') || $this->selectedBrainFeatures->contains('skills')) {
            $this->config->setPackages($this->selectedThirdPartyPackages->values()->toArray());
        }

        // A non-interactive install (scripts, CI) configures the detected agents; remember them for brain:update.
        if ($explicitMode && $this->config->getAgents() === [] && $this->selectedAgents->isNotEmpty()) {
            $this->config->setAgents($this->selectedAgents->map(fn (Agent $agent): string => $agent->name())->values()->toArray());
        }

        if ($this->selectedBrainFeatures->contains('guidelines')) {
            $this->config->setGuidelines(true);
        }

        if ($this->selectedBrainFeatures->contains('skills')) {
            $this->config->setSkills($this->installedSkillNames);
        }

        if ($this->selectedBrainFeatures->contains('mcp')) {
            $this->config->setMcp(true);
        }
    }

    protected function isExplicitFlagMode(): bool
    {
        if ($this->option('guidelines')) {
            return true;
        }

        if ($this->option('skills')) {
            return true;
        }

        return (bool) $this->option('mcp');
    }

    protected function installMcpServerConfig(): void
    {
        $this->installFeature(
            agents: $this->agentsWithMcp(),
            emptyMessage: 'No agents are selected for MCP installation.',
            headerMessage: 'Installing MCP servers to your selected Agents',
            nameResolver: fn (Agent $agent): string => $agent->displayName(),
            processor: fn (Agent&SupportsMcp $agent): int => (new McpWriter($agent))->write(),
            featureName: 'MCP servers',
            withDelay: true,
        );
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $agents
     * @param  callable(T): string  $nameResolver
     * @param  callable(T): mixed  $processor
     * @param  ?callable(): void  $beforeProcess
     */
    protected function installFeature(
        Collection $agents,
        string $emptyMessage,
        string $headerMessage,
        callable $nameResolver,
        callable $processor,
        string $featureName,
        ?callable $beforeProcess = null,
        bool $withDelay = false,
    ): void {
        if ($agents->isEmpty()) {
            $this->info($emptyMessage);

            return;
        }

        $this->newLine();
        $this->info($headerMessage);

        if ($beforeProcess !== null) {
            $beforeProcess();
        }

        $this->newLine();

        if ($withDelay) {
            usleep(750000);
        }

        $failed = [];
        $nameMap = $agents->map(fn ($agent): string => $nameResolver($agent));
        $longestName = $nameMap->map(fn (string $name) => Str::length($name))->max() ?? 0;

        foreach ($agents as $index => $agent) {
            $name = $nameMap[$index];
            $this->output->write('  '.str_pad($name, $longestName).'... ');

            try {
                $processor($agent);
                $this->line($this->green('✓'));
            } catch (Exception $e) {
                $failed[$name] = $e->getMessage();
                $this->line($this->red('✗'));
            }
        }

        if ($failed !== []) {
            $this->newLine();
            $this->error(sprintf('✗ Failed to install %s to %d agent%s:',
                $featureName,
                count($failed),
                count($failed) === 1 ? '' : 's'
            ));

            foreach ($failed as $agentName => $error) {
                $this->line("  - {$agentName}: {$error}");
            }
        }

        $this->newLine();
    }
}
