<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\VacationEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserDirectoryController extends Controller
{
    public function __construct(private AuditService $audit, private VacationEntitlementService $vacationEntitlements) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->with('managedTeams:id,name')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.strtolower(trim($request->string('q')->toString())).'%';
                $query->where(function ($query) use ($term): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('team'), fn ($query) => $query->where('team', $request->string('team')))
            ->when($request->filled('status'), fn ($query) => $query->where('active', $request->string('status')->toString() === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'teams' => Team::orderBy('name')->get(),
            'totalUsers' => User::count(),
            'activeUsers' => User::where('active', true)->count(),
            'managerUsers' => User::whereIn('role', ['manager', 'super_admin'])->count(),
            'keycloakLinkedUsers' => User::whereNotNull('keycloak_subject')->count(),
            'keycloakPendingUsers' => User::whereNull('keycloak_subject')->count(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $generatedPassword = empty($data['password']);

        $user = DB::transaction(function () use ($data, $generatedPassword): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'team' => $data['team'] ?? '',
                'hired_on' => $data['hired_on'] ?? null,
                'active' => true,
                'password' => $generatedPassword ? Str::password(32) : $data['password'],
            ]);
            $this->syncManagedTeams($user, $data);

            return $user;
        });
        $this->audit->record('user.created', $user, [], $user->only(['name', 'email', 'role', 'team', 'hired_on', 'active']));
        $this->vacationEntitlements->sync($user, $request->user());

        if (! $generatedPassword) {
            return redirect()->route('admin.users.index')->with('success', 'Usuário criado com senha provisória.');
        }

        return $this->sendPasswordLink($user, 'Usuário criado.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $newHiredOn = $data['hired_on'] ?? null;
        if ($user->hired_on && $newHiredOn !== $user->hired_on->toDateString() && $user->vacationEntitlements()->exists()) {
            return back()->withErrors(['hired_on' => 'A data de contratação não pode ser alterada depois da geração dos períodos. Ajuste os saldos na área de férias.'])->withInput();
        }
        $old = $user->only(['name', 'email', 'role', 'team', 'hired_on', 'active']);
        DB::transaction(function () use ($user, $data): void {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'team' => $data['team'] ?? '',
                'hired_on' => $data['hired_on'] ?? null,
            ]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $this->syncManagedTeams($user, $data);
        });
        $this->audit->record('user.updated', $user, $old, $user->only(['name', 'email', 'role', 'team', 'hired_on', 'active']));
        $this->vacationEntitlements->sync($user, $request->user());

        return back()->with('success', 'Usuário atualizado.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'Você não pode desativar a própria conta.');
        $old = $user->only(['active']);
        DB::transaction(function () use ($user): void {
            $activating = ! $user->active;
            $user->forceFill([
                'active' => $activating,
                'remember_token' => $activating ? $user->remember_token : Str::random(60),
                'auth_version' => $activating ? $user->auth_version : $user->auth_version + 1,
            ])->save();
        });
        $this->audit->record('user.status_changed', $user, $old, $user->only(['active']));

        return back()->with('success', 'Status do usuário atualizado.');
    }

    public function passwordLink(User $user): RedirectResponse
    {
        abort_unless(config('auth.password_recovery_enabled'), 404);
        abort_unless($user->active, 422, 'Ative o usuário antes de enviar o acesso.');

        return $this->sendPasswordLink($user, 'Solicitação registrada.');
    }

    private function syncManagedTeams(User $user, array $data): void
    {
        $user->managedTeams()->sync($data['role'] === 'manager' ? ($data['manager_teams'] ?? []) : []);
    }

    private function sendPasswordLink(User $user, string $prefix): RedirectResponse
    {
        abort_unless(config('auth.password_recovery_enabled'), 422, 'Configure HTTPS e SMTP antes de enviar links de acesso.');
        $status = PasswordBroker::sendResetLink(['email' => $user->email]);
        $message = $status === PasswordBroker::RESET_LINK_SENT
            ? $prefix.' Link para definição de senha enviado por e-mail.'
            : $prefix.' Não foi possível enviar o e-mail; verifique a configuração de SMTP.';

        return redirect()->route('admin.users.index')->with(
            $status === PasswordBroker::RESET_LINK_SENT ? 'success' : 'warning',
            $message
        );
    }
}
