<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\ManagerDelegation;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function team(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:teams']]);
        $team = Team::create(['name' => $data['name'], 'active' => true]);
        $this->audit->record('team.created', $team, [], $team->only(['name', 'active']));

        return back()->with('success', 'Equipe criada.');
    }

    public function toggleTeam(Team $team): RedirectResponse
    {
        $old = $team->only(['active']);
        $team->update(['active' => ! $team->active]);
        $this->audit->record('team.updated', $team, $old, $team->only(['active']));

        return back()->with('success', 'Equipe atualizada.');
    }

    public function holiday(Request $request): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date', 'unique:holidays,date'], 'name' => ['required', 'string', 'max:120'], 'blocks_requests' => ['nullable', 'boolean']]);
        $holiday = Holiday::create(['date' => $data['date'], 'name' => $data['name'], 'blocks_requests' => $request->boolean('blocks_requests'), 'created_by' => $request->user()->id]);
        $this->audit->record('holiday.created', $holiday, [], $holiday->only(['date', 'name', 'blocks_requests']));

        return back()->with('success', 'Data corporativa adicionada.');
    }

    public function deleteHoliday(Holiday $holiday): RedirectResponse
    {
        $this->audit->record('holiday.deleted', $holiday, $holiday->toArray(), []);
        $holiday->delete();

        return back()->with('success', 'Data corporativa removida.');
    }

    public function delegation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'manager_id' => ['required', 'integer', 'different:delegate_id', 'exists:users,id'],
            'delegate_id' => ['required', 'integer', 'exists:users,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $manager = User::findOrFail($data['manager_id']);
        $delegate = User::findOrFail($data['delegate_id']);
        abort_unless($manager->isManager() && $delegate->isManager() && $manager->active && $delegate->active, 422, 'Selecione gestores ativos.');

        $delegation = ManagerDelegation::create([...$data, 'created_by' => $request->user()->id]);
        $this->audit->record('manager_delegation.created', $delegation, [], $delegation->only(['manager_id', 'delegate_id', 'starts_on', 'ends_on']));

        return back()->with('success', 'Delegação programada.');
    }

    public function deleteDelegation(ManagerDelegation $delegation): RedirectResponse
    {
        $this->audit->record('manager_delegation.deleted', $delegation, $delegation->toArray(), []);
        $delegation->delete();

        return back()->with('success', 'Delegação encerrada.');
    }

    public function audits(Request $request): View
    {
        return view('admin.audits', ['logs' => AuditLog::with('actor')->latest()->paginate(50)]);
    }

    public function operations(): View
    {
        $databaseSize = DB::getDriverName() === 'pgsql'
            ? DB::selectOne('select pg_size_pretty(pg_database_size(current_database())) as size')?->size
            : null;

        return view('admin.operations', [
            'queuedJobs' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'oldestJob' => DB::table('jobs')->min('created_at'),
            'pendingRequests' => \App\Models\WorkRequest::where('status', 'pending')->count(),
            'staleRequests' => \App\Models\WorkRequest::where('status', 'pending')->where('created_at', '<=', now()->subDays(2))->count(),
            'lastHolidaySync' => Holiday::max('last_synced_at'),
            'lastAudit' => AuditLog::max('created_at'),
            'databaseSize' => $databaseSize,
        ]);
    }
}
