<?php

declare(strict_types=1);

namespace LaraGram\Brain\Mcp\Methods;

use LaraGram\Brain\Mcp\ToolExecutor;
use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Contracts\Errable;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;
use Throwable;

class CallToolWithExecutor implements Errable, Method
{
    use InteractsWithResponses;

    public function __construct(protected ToolExecutor $executor)
    {
        //
    }

    /**
     * Handle the JSON-RPC tool/call request with process isolation.
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context
            ->tools()
            ->first(
                fn ($tool): bool => $tool->name() === $request->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$request->params['name']}] not found.",
                    -32602,
                    $request->id,
                ));

        $arguments = [];

        if (isset($request->params['arguments']) && is_array($request->params['arguments'])) {
            $arguments = $request->params['arguments'];
        }

        try {
            $response = $this->executor->execute($tool::class, $arguments);
        } catch (Throwable $throwable) {
            $response = Response::error('Tool execution error: '.$throwable->getMessage());
        }

        return $this->toJsonRpcResponse($request, $response, fn ($responseFactory): array => $responseFactory->mergeStructuredContent(
            $responseFactory->mergeMeta([
                'content' => $responseFactory->responses()->map(fn ($response) => $response->content()->toTool($tool))->all(),
                'isError' => $responseFactory->responses()->contains(fn ($response) => $response->isError()),
            ])
        ));
    }
}
