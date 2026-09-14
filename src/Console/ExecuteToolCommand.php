<?php

declare(strict_types=1);

namespace LaraGram\Brain\Console;

use LaraGram\Brain\Mcp\ToolRegistry;
use LaraGram\Console\Command;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Support\ValidationMessages;
use LaraGram\Validation\ValidationException;
use Throwable;

class ExecuteToolCommand extends Command
{
    protected $signature = 'brain:execute-tool {tool} {arguments}';

    protected $description = 'Execute a Brain MCP tool in isolation (internal command)';

    protected $hidden = true;

    public function handle(): int
    {
        $toolClass = $this->argument('tool');
        $argumentsEncoded = $this->argument('arguments');

        // Validate the tool is registered
        if (! ToolRegistry::isToolAllowed($toolClass)) {
            $this->error("Tool not registered or not allowed: {$toolClass}");

            return 1;
        }

        $decoded = base64_decode($argumentsEncoded, true);

        if ($decoded === false) {
            $this->error('Invalid arguments encoding.');

            return 1;
        }

        // Decode arguments
        $arguments = json_decode($decoded, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid arguments format: '.json_last_error_msg());

            return static::FAILURE;
        }

        $tool = ToolRegistry::resolve($toolClass);

        $request = new Request($arguments ?? []);

        ob_start();

        try {
            /** @var Response|ResponseFactory $response */
            $response = $tool->handle($request); // @phpstan-ignore-line
        } catch (ValidationException $exception) {
            $response = Response::error(ValidationMessages::from($exception));
        } catch (Throwable $throwable) {
            ob_end_clean();

            $errorResult = Response::error("Tool execution failed (E_THROWABLE): {$throwable->getMessage()}");

            $this->error(json_encode([
                'isError' => true,
                'content' => [
                    $errorResult->content()->toTool($tool),
                ],
            ]));

            return static::FAILURE;
        }

        ob_end_clean();

        echo json_encode($this->serialize($tool, $response));

        return static::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Tool $tool, Response|ResponseFactory $response): array
    {
        $factory = $response instanceof ResponseFactory ? $response : new ResponseFactory($response);

        return $factory->mergeStructuredContent([
            'isError' => $factory->responses()->contains(fn (Response $response): bool => $response->isError()),
            'content' => $factory->responses()->map(fn (Response $response): array => $response->content()->toTool($tool))->all(),
        ]);
    }
}
