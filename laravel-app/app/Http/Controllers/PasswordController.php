<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function request(): View
    {
        abort_unless(config('auth.password_recovery_enabled'), 404);

        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        abort_unless(config('auth.password_recovery_enabled'), 404);
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', strtolower($data['email']))->where('active', true)->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('success', 'Se existir uma conta ativa para esse e-mail, enviaremos as instruções de recuperação.');
    }

    public function reset(Request $request, string $token): View
    {
        abort_unless(config('auth.password_recovery_enabled'), 404);

        return view('auth.reset-password', ['token' => $token, 'email' => $request->string('email')->toString()]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(config('auth.password_recovery_enabled'), 404);
        $credentials = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        abort_unless(User::where('email', strtolower($credentials['email']))->where('active', true)->exists(), 422);
        $credentials['email'] = strtolower($credentials['email']);

        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Senha redefinida. Entre com a nova senha.')
            : back()->withErrors(['email' => 'O link é inválido ou expirou. Solicite uma nova recuperação.'])->onlyInput('email');
    }
}
