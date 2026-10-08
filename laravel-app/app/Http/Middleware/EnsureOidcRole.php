<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOidcRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($request->session()->get('auth_provider') !== 'oidc') {
            return $next($request);
        }

        $granted = $request->session()->get('oidc_roles', []);
        abort_unless(is_array($granted) && array_intersect($roles, $granted), 403, 'Sua conta não possui permissão para acessar esta área.');

        return $next($request);
    }
}
