<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
        $response->headers->set('Server-Timing', 'app;dur='.$durationMs);

        if ($durationMs >= config('observability.slow_request_ms')) {
            Log::warning('slow_request', [
                'duration_ms' => $durationMs,
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }
}
