<?php

declare(strict_types=1);

namespace LaraGram\Brain\Console;

use LaraGram\Console\Command;
use LaraGram\Support\Collection;
use LaraGram\Brain\Concerns\DisplayHelper;
use LaraGram\Brain\Install\SkillComposer;

use function LaraGram\Console\Prompts\note;
use function LaraGram\Console\Prompts\table;

class ListSkillCommand extends Command
{
    use DisplayHelper;

    protected $signature = 'brain:list-skills';

    protected $description = 'List all available skills in the current project';

    public function handle(SkillComposer $skillComposer): int
    {
        $skills = $skillComposer->skills();

        if ($skills->isEmpty()) {
            $this->info('No skills available in this project.');

            return self::SUCCESS;
        }

        $this->displayBrainHeader('Skills', config('app.name'));

        $count = $skills->count();
        note("Found {$count} skill".($count === 1 ? '' : 's'));

        $this->displaySkillsTable($skills);

        return self::SUCCESS;
    }

    protected function displaySkillsTable(Collection $skills): void
    {
        $rows = $skills
            ->sortBy(fn ($skill) => $skill->name)
            ->map(fn ($skill): array => $skill->custom
                ? [$this->dim($skill->name.'*'), $this->yellow('local')]
                : [$skill->name, $this->dim($skill->package)]
            )
            ->values()
            ->toArray();

        table(
            headers: ['Skill', 'Source'],
            rows: $rows
        );
    }
}
