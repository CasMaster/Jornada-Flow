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
        $oidcSessionExpired = $request->session()->get('auth_provider') === 'oidc'
            && (int) $request->session()->get('oidc_expires_at', 0) <= now()->timestamp;
        $oidcRoles = $request->session()->get('oidc_roles', []);
        $oidcAccessRevoked = $request->session()->get('auth_provider') === 'oidc'
            && (! is_array($oidcRoles) || ! array_intersect([
                config('oidc.user_role'),
                config('oidc.admin_role'),
            ], $oidcRoles));

        if (! $user->active || $sessionWasRevoked || $oidcSessionExpired || $oidcAccessRevoked) {
            Auth::logout();
            $request->session()->invalidate();
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
