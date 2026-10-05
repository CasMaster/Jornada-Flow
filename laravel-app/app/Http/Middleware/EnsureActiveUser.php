<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $authenticatedVersion = $request->session()->get('authenticated_version');
        $sessionWasRevoked = $authenticatedVersion === null
            ? $user->auth_version > 0
            : (int) $authenticatedVersion !== $user->auth_version;

        if (! $user->active || $sessionWasRevoked) {
            Auth::logout();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sua sessão não é mais válida. Entre novamente.'], 403);
            }

            return redirect()->route('login')->withErrors(['email' => 'Sua sessão não é mais válida. Entre novamente.']);
        }

        if ($authenticatedVersion === null) {
            $request->session()->put('authenticated_version', $user->auth_version);
        }

        return $next($request);
    }
}
