<?php

declare(strict_types=1);

namespace LaraGram\Brain\Console;

use LaraGram\Console\Command;
use LaraGram\Support\Collection;
use LaraGram\Brain\Concerns\ReportsSkillParseFailures;
use LaraGram\Brain\Install\ThirdPartyPackage;
use LaraGram\Brain\Support\Config;
use LaraGram\Brain\Support\SkillParseFailures;
use LaraGram\Brain\Discovery\ProjectManager;
use LaraGram\Console\Attribute\AsCommand;

use function LaraGram\Console\Prompts\multiselect;

#[AsCommand('brain:update', 'Update the LaraGram Brain guidelines & skills to the latest guidance')]
class UpdateCommand extends Command
{
    use ReportsSkillParseFailures;

    /** @var string */
    protected $signature = 'brain:update
        {--discover : Discover and prompt for newly available guidelines and skills (default)}
        {--no-discover : Skip discovering and prompting for newly available guidelines and skills}
        {--ignore-skills : Skip updating the skills directory}';

    public function handle(Config $config, ProjectManager $project): int
    {
        app(SkillParseFailures::class)->flush();

        if (! $config->isValid()) {
            $this->error('Please set up Brain with [php laragram brain:install] first.');

            return self::FAILURE;
        }

        $guidelines = $config->getGuidelines();
        $hasSkills = ! $this->option('ignore-skills') && ($config->hasSkills() || is_dir(base_path('.ai/skills')));

        if (! $guidelines && ! $hasSkills) {
            return self::SUCCESS;
        }

        if (empty($config->getAgents())) {
            $this->error('Please set up Brain with [php laragram brain:install] first.');

            return self::FAILURE;
        }

        if (! $this->option('no-discover')) {
            $this->discoverNewContent($config, $project);
        }

        $this->callSilently(InstallCommand::class, [
            '--no-interaction' => true,
            '--guidelines' => $guidelines,
            '--skills' => $hasSkills,
        ]);

        $this->reportSkillParseFailures();

        $this->info('Brain guidelines and skills updated successfully.');

        return self::SUCCESS;
    }

    protected function discoverNewContent(Config $config, ProjectManager $project): void
    {
        $newPackages = $this->resolveNewPackages($config, $project);

        if ($newPackages->isEmpty()) {
            return;
        }

        if (! $this->input->isInteractive() || $this->runningAsComposerScript()) {
            return;
        }

        /** @var array<int, string> $selectedPackages */
        $selectedPackages = multiselect(
            label: 'New packages with guidelines/skills discovered! Which would you like to add?',
            options: $newPackages
                ->mapWithKeys(fn (ThirdPartyPackage $pkg, string $name): array => [$name => $pkg->displayLabel()])
                ->toArray(),
            scroll: 10,
            required: false,
            hint: 'Select packages to include their guidelines and skills',
        );

        if ($selectedPackages !== []) {
            $config->setPackages(array_merge($config->getPackages(), $selectedPackages));
        }
    }

    /**
     * @return Collection<string, ThirdPartyPackage>
     */
    protected function resolveNewPackages(Config $config, ProjectManager $project): Collection
    {
        $configuredPackages = $config->getPackages();

        return ThirdPartyPackage::discover($project)
            ->filter(fn (ThirdPartyPackage $pkg, string $name): bool => ! in_array($name, $configuredPackages, true));
    }

    /**
     * Composer sets COMPOSER_DEV_MODE for the entire install/update run, including
     * post-update-cmd scripts, so prompting there would block an unattended `composer update`.
     */
    protected function runningAsComposerScript(): bool
    {
        return getenv('COMPOSER_DEV_MODE') !== false;
    }
}
