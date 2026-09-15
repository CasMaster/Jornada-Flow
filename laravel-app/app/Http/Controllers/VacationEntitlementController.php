<?php

namespace App\Http\Controllers;

use App\Models\VacationEntitlement;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VacationEntitlementController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'acquisition_starts_on' => ['required', 'date'],
            'acquisition_ends_on' => ['required', 'date', 'after_or_equal:acquisition_starts_on'],
            'expires_on' => ['nullable', 'date', 'after:acquisition_ends_on'],
            'granted_days' => ['required', 'integer', 'min:0', 'max:90'],
            'adjustment_days' => ['nullable', 'integer', 'min:-90', 'max:90'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);
        if (($data['granted_days'] + ($data['adjustment_days'] ?? 0)) < 0) {
            return back()->withErrors(['adjustment_days' => 'O saldo total não pode ser negativo.'])->withInput();
        }
        if (VacationEntitlement::where('user_id', $data['user_id'])->whereDate('acquisition_starts_on', $data['acquisition_starts_on'])->whereDate('acquisition_ends_on', $data['acquisition_ends_on'])->exists()) {
            return back()->withErrors(['acquisition_starts_on' => 'Este período aquisitivo já está cadastrado para o colaborador.'])->withInput();
        }

        $entitlement = DB::transaction(function () use ($request, $data) {
            $entitlement = VacationEntitlement::create([
                ...$data,
                'adjustment_days' => $data['adjustment_days'] ?? 0,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->audit->record('vacation_entitlement.created', $entitlement, [], $entitlement->only([
                'user_id', 'acquisition_starts_on', 'acquisition_ends_on', 'expires_on', 'granted_days', 'adjustment_days', 'notes',
            ]));

            return $entitlement;
        });

        return back()->with('success', "Saldo de {$entitlement->totalDays()} dias cadastrado.");
    }

    public function update(Request $request, VacationEntitlement $entitlement): RedirectResponse
    {
        $data = $request->validate([
            'expires_on' => ['nullable', 'date', 'after:'.$entitlement->acquisition_ends_on->toDateString()],
            'granted_days' => ['required', 'integer', 'min:0', 'max:90'],
            'adjustment_days' => ['required', 'integer', 'min:-90', 'max:90'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);
        $minimum = $entitlement->approvedDays() + $entitlement->reservedDays();
        if (($data['granted_days'] + $data['adjustment_days']) < $minimum) {
            return back()->withErrors(['adjustment_days' => "O saldo total não pode ficar abaixo dos {$minimum} dias já consumidos ou reservados."]);
        }

        DB::transaction(function () use ($request, $entitlement, $data) {
            $old = $entitlement->only(['expires_on', 'granted_days', 'adjustment_days', 'notes']);
            $entitlement->update([...$data, 'updated_by' => $request->user()->id]);
            $this->audit->record('vacation_entitlement.updated', $entitlement, $old, $entitlement->only(['expires_on', 'granted_days', 'adjustment_days', 'notes']));
        });

        return back()->with('success', 'Saldo de férias atualizado e auditado.');
    }
}
