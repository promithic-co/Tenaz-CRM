<?php

namespace App\Http\Middleware;

use App\Services\LangfuseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlushLangfuse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Perform post-response actions.
     * No-op for now — Langfuse HTTP wrapper fires requests synchronously with a short timeout.
     * Reserved for future batching/async SDK integration.
     */
    public function terminate(Request $request, Response $response): void
    {
        // No-op — Http wrapper sends immediately, no batch buffer to flush
        app(LangfuseService::class);
    }
}
