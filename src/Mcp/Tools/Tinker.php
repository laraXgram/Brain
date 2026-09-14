<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Tools;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\Support\Facades\Commander;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Console\Command\Command as CommandAlias;
use LaraGram\Console\Output\BufferedOutput;
use Throwable;

class Tinker extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Execute PHP code in the LaraGram application context, like `php laragram tinker`. Use this for debugging issues, checking if functions exist, and testing code snippets. Do not create or change records without explicit user approval, and never send real Telegram API calls from here unless the user asks. Prefer existing Commander commands over custom tinker code.';

    /**
     * Determine whether the tool should be registered with the MCP server.
     */
    public function shouldRegister(): bool
    {
        if (! config('brain.tinker_tool_enabled', true)) {
            return false;
        }

        return rescue(fn (): bool => array_key_exists('tinker', Commander::all()), false, report: false);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()
                ->description('PHP code to execute (without opening <?php tags)')
                ->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $code = str_replace(['<?php', '?>'], '', (string) $request->get('code'));

        $output = new BufferedOutput;

        try {
            $exitCode = Commander::call('tinker', [
                '--execute' => $code,
                '--no-ansi' => true,
                '--no-interaction' => true,
            ], $output);
        } catch (Throwable $throwable) {
            return Response::text($throwable->getMessage());
        }

        if ($exitCode !== CommandAlias::SUCCESS) {
            return Response::text('Failed to execute tinker: '.$output->fetch());
        }

        return Response::text(trim($output->fetch()));
    }
}
