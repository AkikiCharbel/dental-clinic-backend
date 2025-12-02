<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestId
{
    /**
     * The header name for the request ID.
     */
    public const HEADER_NAME = 'X-Request-ID';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get existing request ID from header or generate a new one
        $requestId = $request->header(self::HEADER_NAME) ?? $this->generateRequestId();

        // Store the request ID in the request headers for later use
        $request->headers->set(self::HEADER_NAME, $requestId);

        // Process the request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set(self::HEADER_NAME, $requestId);

        return $response;
    }

    /**
     * Generate a unique request ID.
     */
    protected function generateRequestId(): string
    {
        return (string) Str::uuid();
    }
}
