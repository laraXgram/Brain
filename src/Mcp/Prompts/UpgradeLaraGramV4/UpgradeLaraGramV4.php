<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Prompts\UpgradeLaraGramV4;

use LaraGram\Brain\Concerns\RendersBladeGuidelines;
use LaraGram\Brain\Support\PackageRegistry;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Brain\Discovery\ProjectManager;

class UpgradeLaraGramV4 extends Prompt
{
    use RendersBladeGuidelines;

    protected string $name = 'upgrade-laragram-v4';

    protected string $title = 'upgrade_laragram_v4';

    protected string $description = 'Provides step-by-step guidance for upgrading from LaraGram 3.x to 4.0.';

    public function shouldRegister(ProjectManager $project): bool
    {
        return $project->php()->uses(PackageRegistry::LARAGRAM, '>=3.0.0 <4.0.0');
    }

    public function handle(): Response
    {
        $content = $this->renderBladeFile(__DIR__.'/upgrade-laragram-v4.blade.php');

        return Response::text($content);
    }
}
