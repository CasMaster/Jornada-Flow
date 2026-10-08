<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OidcService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuthController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function redirectToProvider(Request $request, OidcService $oidc): RedirectResponse
    {
        try {
            $authorization = $oidc->authorizationRequest();
            $request->session()->put('oidc_transaction', [
                'state' => $authorization['state'],
                'nonce' => $authorization['nonce'],
                'code_verifier' => $authorization['code_verifier'],
                'created_at' => now()->timestamp,
            ]);

            return redirect()->away($authorization['url']);
        } catch (Throwable) {
            Log::warning('OIDC login could not be started.');

            return redirect()->route('login')->withErrors(['oidc' => 'O acesso corporativo está temporariamente indisponível. Tente novamente.']);
        }
    }

    public function callback(Request $request, OidcService $oidc): RedirectResponse
    {
        $transaction = $request->session()->pull('oidc_transaction');
        if (! is_array($transaction)
            || now()->timestamp - (int) ($transaction['created_at'] ?? 0) > 600
            || ! is_string($request->query('state'))
            || ! is_string($transaction['state'] ?? null)
            || ! hash_equals($transaction['state'], $request->query('state'))) {
            return redirect()->route('login')->withErrors(['oidc' => 'A tentativa de acesso expirou ou é inválida. Inicie novamente.']);
        }

        if ($request->query('error') || ! is_string($request->query('code'))) {
            return redirect()->route('login')->withErrors(['oidc' => 'O acesso corporativo não foi concluído.']);
        }

        try {
            $tokens = $oidc->exchangeCode($request->query('code'), (string) $transaction['code_verifier']);
            $claims = $oidc->validateIdToken($tokens['id_token'], (string) $transaction['nonce']);
            $roles = $oidc->applicationRoles($claims);
            $allowedRoles = [config('oidc.user_role'), config('oidc.admin_role')];
            abort_unless(array_intersect($allowedRoles, $roles), 403, 'Sua conta não possui acesso ao Jornada Flow.');

            $user = $this->resolveOidcUser($claims, $roles);
            abort_unless($user->active, 403, 'Sua conta está desativada. Procure o administrador.');

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put([
                'authenticated_version' => $user->auth_version,
                'auth_provider' => 'oidc',
                'oidc_roles' => $roles,
                'oidc_expires_at' => (int) $claims['exp'],
                'oidc_authenticated_at' => now()->timestamp,
                'oidc_id_token' => $tokens['id_token'],
            ]);

            return redirect()->intended($user->isManager() ? route('manager.dashboard') : route('employee.dashboard'));
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('OIDC callback validation failed.', array_filter([
                'exception_type' => $exception::class,
                'test_detail' => app()->environment('testing') ? $exception->getMessage() : null,
            ]));

            return redirect()->route('login')->withErrors(['oidc' => 'Não foi possível validar o acesso corporativo. Inicie novamente.']);
        }
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt(['email' => strtolower($credentials['email']), 'password' => $credentials['password'], 'active' => true], true)) {
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('authenticated_version', $request->user()->auth_version);
        $request->session()->put('auth_provider', 'local');

        $dashboard = $request->user()->isManager() ? route('manager.dashboard') : route('employee.dashboard');

        return redirect()->intended($dashboard);
    }

    public function logout(Request $request, OidcService $oidc): RedirectResponse
    {
        $isOidc = $request->session()->get('auth_provider') === 'oidc';
        $idToken = $request->session()->get('oidc_id_token');
        $logoutUrl = null;
        if ($isOidc) {
            try {
                $logoutUrl = $oidc->logoutUrl(is_string($idToken) ? $idToken : null);
            } catch (Throwable) {
                Log::warning('OIDC logout endpoint is unavailable.');
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $logoutUrl ? redirect()->away($logoutUrl) : redirect()->route('login');
    }

    /** @param array<string, mixed> $claims @param list<string> $roles */
    private function resolveOidcUser(array $claims, array $roles): User
    {
        $subject = (string) $claims['sub'];
        $user = User::where('keycloak_subject', $subject)->first();
        $email = strtolower(trim((string) ($claims['email'] ?? '')));

        if (! $user && $email !== '') {
            abort_unless(($claims['email_verified'] ?? false) === true, 403, 'O e-mail corporativo precisa estar validado.');
            $user = User::whereRaw('LOWER(email) = ?', [$email])->whereNull('keycloak_subject')->first();
        }

        if (! $user) {
            abort_unless($email !== '' && ($claims['email_verified'] ?? false) === true, 403, 'A conta corporativa não possui um e-mail validado.');
            $user = new User([
                'name' => trim((string) ($claims['name'] ?? '')) ?: $email,
                'email' => $email,
                'password' => Str::random(64),
                'role' => in_array(config('oidc.admin_role'), $roles, true) ? 'super_admin' : 'employee',
                'team' => '',
                'active' => true,
            ]);
        }

        $user->keycloak_subject = $subject;
        if (in_array(config('oidc.admin_role'), $roles, true)) {
            $user->role = 'super_admin';
        }
        $user->save();

        return $user;
    }
}
