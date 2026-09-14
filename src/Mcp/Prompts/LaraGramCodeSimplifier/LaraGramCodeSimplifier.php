<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Prompts\LaraGramCodeSimplifier;

use LaraGram\Brain\Concerns\RendersBladeGuidelines;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Prompt;

class LaraGramCodeSimplifier extends Prompt
{
    use RendersBladeGuidelines;

    protected string $name = 'laragram-code-simplifier';

    protected string $title = 'laragram_code_simplifier';

    protected string $description = 'Simplifies and refines PHP/LaraGram code for clarity, consistency, and maintainability while preserving all functionality. Focuses on recently modified code unless instructed otherwise.';

    public function handle(): Response
    {
        $content = $this->renderBladeFile(__DIR__.'/laragram-code-simplifier.blade.php');

        return Response::text($content);
    }
}
