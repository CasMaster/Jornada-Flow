<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.login', ['profile' => $request->string('perfil', 'employee')->toString()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'profile' => ['required', 'in:employee,manager']]);
        if (! Auth::attempt(['email' => strtolower($credentials['email']), 'password' => $credentials['password'], 'active' => true], true)) {
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }
        $validProfile = $credentials['profile'] !== 'manager' || $request->user()->isManager();
        if (! $validProfile) {
            Auth::logout();

            return back()->withErrors(['email' => 'Esta conta não possui acesso de gestor.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('authenticated_version', $request->user()->auth_version);

        return redirect()->intended($credentials['profile'] === 'manager' ? route('manager.dashboard') : route('employee.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
