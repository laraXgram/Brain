<?php

declare(strict_types=1);

namespace LaraGram\Brain\Middleware;

use Closure;
use LaraGram\Http\JsonResponse;
use LaraGram\Http\RedirectResponse;
use LaraGram\Http\Request;
use LaraGram\View\View;
use LaraGram\Brain\Services\BrowserLogger;
use LaraGram\Http\BinaryFileResponse;
use LaraGram\Http\BaseResponse as Response;
use LaraGram\Http\StreamedResponse;

class InjectBrain
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($this->shouldInject($request, $response)) {
            $originalView = $response->original ?? null;
            $injectedContent = $this->injectScript($response->getContent());
            $response->setContent($injectedContent);

            if ($originalView instanceof View && property_exists($response, 'original')) {
                $response->original = $originalView;
            }
        }

        return $response;
    }

    protected function shouldInject(Request $request, Response $response): bool
    {
        if ($request->headers->has('X-Luna')) {
            return false;
        }

        $responseTypes = [
            StreamedResponse::class,
            BinaryFileResponse::class,
            JsonResponse::class,
            RedirectResponse::class,
        ];

        foreach ($responseTypes as $type) {
            if ($response instanceof $type) {
                return false;
            }
        }

        if (! str_contains((string) $response->headers->get('content-type', ''), 'html')) {
            return false;
        }

        $content = $response->getContent();

        // Check for an <html> or <head> tag without matching e.g. <header>
        if (preg_match('/<(html|head)[\s>]/', $content) !== 1) {
            return false;
        }

        // Check if already injected
        return ! str_contains($content, 'browser-logger-active');
    }

    protected function injectScript(string $content): string
    {
        $script = BrowserLogger::getScript();

        // Try to inject before closing </head>
        if (str_contains($content, '</head>')) {
            return str_replace('</head>', $script."\n</head>", $content);
        }

        // Fallback: inject before closing </body>
        if (str_contains($content, '</body>')) {
            return str_replace('</body>', $script."\n</body>", $content);
        }

        return $content.$script;
    }
}
