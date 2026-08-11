<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\Team;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function audits(Request $request): View
    {
        return view('admin.audits', ['logs' => AuditLog::with('actor')->latest()->paginate(50)]);
    }
}
