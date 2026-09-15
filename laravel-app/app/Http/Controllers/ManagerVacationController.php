<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Models\VacationEntitlement;
use App\Models\VacationRequest;
use App\Services\VacationEntitlementService;
use App\Services\VacationRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerVacationController extends Controller
{
    public function __construct(
        private VacationRequestService $vacations,
        private VacationEntitlementService $vacationEntitlements,
    ) {}

    private function allowedTeams(User $user): array
    {
        if ($user->role === 'super_admin') {
            return Team::pluck('name')->all();
        }
        $delegated = $user->receivedDelegations()->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->pluck('manager_id');

        return Team::whereHas('managers', fn (Builder $query) => $query->whereKey([$user->id, ...$delegated]))->pluck('name')->all();
    }

    private function query(Request $request): Builder
    {
        $teams = $this->allowedTeams($request->user());

        return VacationRequest::with(['user.vacationEntitlements.requests', 'reviewer', 'entitlement.requests'])->whereHas('user', fn (Builder $q) => $q->whereIn('team', $teams))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('team'), fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('team', $request->string('team'))))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = '%'.strtolower(trim($request->string('q')->toString())).'%';
                $q->whereHas('user', fn (Builder $u) => $u->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]));
            });
    }

    public function index(Request $request): View
    {
        $query = $this->query($request);
        $employees = $request->user()->role === 'super_admin'
            ? User::where('active', true)->orderBy('name')->get(['id', 'name', 'email', 'hired_on'])
            : collect();

        return view('vacations.manage', [
            'vacations' => (clone $query)->orderBy('starts_on')->paginate(25)->withQueryString(),
            'approved' => (clone $query)->where('status', 'approved')->whereDate('ends_on', '>=', today())->orderBy('starts_on')->limit(20)->get(),
            'teams' => $this->allowedTeams($request->user()),
            'employees' => $employees,
            'entitlements' => $request->user()->role === 'super_admin' ? VacationEntitlement::with(['user', 'requests'])->latest('acquisition_ends_on')->limit(100)->get() : collect(),
            'accrualPeriods' => $employees->filter(fn (User $user) => $user->hired_on)->map(fn (User $user) => [
                'user' => $user,
                'period' => $this->vacationEntitlements->currentAccrualPeriod($user),
            ]),
        ]);
    }

    public function review(Request $request, VacationRequest $vacation): RedirectResponse
    {
        $this->authorize('review', $vacation);
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:1000']]);
        $this->vacations->review($request->user(), $vacation, $data['decision'], $data['review_note'] ?? null);

        return back()->with('success', 'Solicitação de férias analisada.');
    }

    public function correct(Request $request, VacationRequest $vacation): RedirectResponse
    {
        $data = $request->validate(['vacation_entitlement_id' => ['required', 'exists:vacation_entitlements,id'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'note' => ['required', 'string', 'max:1000']]);
        $this->vacations->correct($request->user(), $vacation, VacationEntitlement::findOrFail($data['vacation_entitlement_id']), $data['starts_on'], $data['ends_on'], $data['note']);

        return back()->with('success', 'Período de férias corrigido.');
    }

    public function cancel(Request $request, VacationRequest $vacation): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $this->vacations->cancel($request->user(), $vacation, $data['note']);

        return back()->with('success', 'Solicitação de férias cancelada e preservada no histórico.');
    }

    public function export(Request $request): StreamedResponse
    {
        $records = $this->query($request)->orderBy('starts_on')->get();

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Colaborador', 'E-mail', 'Equipe', 'Período aquisitivo', 'Início', 'Fim', 'Dias corridos', 'Status', 'Analisado por'], ';');
            foreach ($records as $item) {
                fputcsv($out, [$item->user->name, $item->user->email, $item->user->team, $item->entitlement ? $item->entitlement->acquisition_starts_on->format('d/m/Y').' a '.$item->entitlement->acquisition_ends_on->format('d/m/Y') : 'Legado', $item->starts_on->format('d/m/Y'), $item->ends_on->format('d/m/Y'), $item->days(), ['pending' => 'Pendente', 'approved' => 'Aprovada', 'rejected' => 'Recusada', 'cancelled' => 'Cancelada'][$item->status], $item->reviewer?->name ?? ''], ';');
            } fclose($out);
        }, 'ferias-mixhome-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
