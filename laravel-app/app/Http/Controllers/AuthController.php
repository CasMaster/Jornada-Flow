<?php
namespace App\Http\Controllers;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
class AuthController extends Controller
{
    public function show(Request $request): View { return view('auth.login', ['teams' => Team::where('active', true)->orderBy('name')->get(), 'mode' => $request->string('modo', 'login')->toString(), 'profile' => $request->string('perfil', 'employee')->toString()]); }
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required','email'], 'password' => ['required','string'], 'profile'=>['required','in:employee,manager']]);
        if (!Auth::attempt(['email' => strtolower($credentials['email']), 'password' => $credentials['password'], 'active' => true], true)) return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        $validProfile = $credentials['profile'] === 'manager' ? $request->user()->isManager() : $request->user()->role === 'employee';
        if (!$validProfile) { Auth::logout(); return back()->withErrors(['email' => $credentials['profile'] === 'manager' ? 'Esta conta não possui acesso de gestor.' : 'Use a opção Sou gestor para esta conta.'])->onlyInput('email'); }
        $request->session()->regenerate();
        return redirect()->intended($request->user()->isManager() ? route('manager.dashboard') : route('employee.dashboard'));
    }
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate(['name'=>['required','string','max:150'],'email'=>['required','email','max:190','unique:users'],'team'=>['required','exists:teams,name'],'password'=>['required','confirmed',Password::min(8)]]);
        abort_unless(Team::where('name', $data['team'])->where('active', true)->exists(), 422);
        $user = User::create(['name'=>$data['name'],'email'=>strtolower($data['email']),'team'=>$data['team'],'password'=>$data['password'],'role'=>'employee','active'=>true]);
        Auth::login($user); $request->session()->regenerate(); return redirect()->route('employee.dashboard');
    }
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login');
    }
}
