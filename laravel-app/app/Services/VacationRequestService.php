<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacationEntitlement;
use App\Models\VacationRequest;
use App\Models\WorkRequest;
use App\Notifications\VacationStatusChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacationRequestService
{
    public function __construct(private AuditService $audit) {}

    public function create(User $user, VacationEntitlement $entitlement, string $startsOn, string $endsOn, int $cashAllowanceDays = 0): VacationRequest
    {
        if ($entitlement->user_id !== $user->id) {
            throw ValidationException::withMessages(['vacation_entitlement_id' => 'O saldo selecionado não pertence a este colaborador.']);
        }
        $this->ensureAvailable($user, $startsOn, $endsOn);

        return DB::transaction(function () use ($user, $entitlement, $startsOn, $endsOn, $cashAllowanceDays) {
            $lockedEntitlement = VacationEntitlement::lockForUpdate()->findOrFail($entitlement->id);
            $this->ensureCashAllowance($lockedEntitlement, self::days($startsOn, $endsOn), $cashAllowanceDays);
            $this->ensureBalance($lockedEntitlement, self::days($startsOn, $endsOn) + $cashAllowanceDays, null, $startsOn, $endsOn);
            $vacation = $user->vacationRequests()->create(['vacation_entitlement_id' => $lockedEntitlement->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'cash_allowance_days' => $cashAllowanceDays, 'status' => 'pending']);
            $this->audit->record('vacation_request.created', $vacation, [], $vacation->only(['starts_on', 'ends_on', 'cash_allowance_days', 'status']));

            return $vacation;
        });
    }

    public function review(User $manager, VacationRequest $vacation, string $decision, ?string $note): void
    {
        DB::transaction(function () use ($manager, $vacation, $decision, $note) {
            $lockedVacation = VacationRequest::with(['user', 'entitlement'])->lockForUpdate()->findOrFail($vacation->id);
            if ($lockedVacation->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Esta solicitação já foi analisada.']);
            }
            if ($decision === 'approved') {
                if (! $lockedVacation->entitlement) {
                    throw ValidationException::withMessages(['decision' => 'Vincule a solicitação a um período aquisitivo antes de aprovar.']);
                }
                $lockedEntitlement = VacationEntitlement::lockForUpdate()->findOrFail($lockedVacation->vacation_entitlement_id);
                $this->ensureAvailable($lockedVacation->user, $lockedVacation->starts_on->toDateString(), $lockedVacation->ends_on->toDateString(), $lockedVacation);
                $this->ensureBalance($lockedEntitlement, $lockedVacation->totalDebitedDays(), $lockedVacation->id, $lockedVacation->starts_on, $lockedVacation->ends_on);
            }
            $old = $lockedVacation->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']);
            $lockedVacation->update(['status' => $decision, 'reviewed_by' => $manager->id, 'reviewed_at' => now(), 'review_note' => $note ?: null]);
            $this->audit->record('vacation_request.'.$decision, $lockedVacation, $old, $lockedVacation->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']));
            $lockedVacation->user->notify(new VacationStatusChanged($lockedVacation));
        });
    }

    public function correct(User $admin, VacationRequest $vacation, VacationEntitlement $entitlement, string $startsOn, string $endsOn, string $note): void
    {
        if ($vacation->status === 'cancelled') {
            throw ValidationException::withMessages(['note' => 'Uma solicitação cancelada não pode ser corrigida.']);
        }
        if ($entitlement->user_id !== $vacation->user_id) {
            throw ValidationException::withMessages(['vacation_entitlement_id' => 'O saldo selecionado não pertence ao colaborador.']);
        }
        $this->ensureAvailable($vacation->user, $startsOn, $endsOn, $vacation);
        DB::transaction(function () use ($admin, $vacation, $entitlement, $startsOn, $endsOn, $note) {
            $lockedEntitlement = VacationEntitlement::lockForUpdate()->findOrFail($entitlement->id);
            $this->ensureCorrectedCashAllowance($lockedEntitlement, self::days($startsOn, $endsOn), $vacation->cash_allowance_days);
            $this->ensureBalance($lockedEntitlement, self::days($startsOn, $endsOn) + $vacation->cash_allowance_days, $vacation->id, $startsOn, $endsOn);
            $old = $vacation->only(['vacation_entitlement_id', 'starts_on', 'ends_on', 'cash_allowance_days', 'corrected_by', 'corrected_at', 'review_note']);
            $vacation->update(['vacation_entitlement_id' => $lockedEntitlement->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'corrected_by' => $admin->id, 'corrected_at' => now(), 'review_note' => trim($note)]);
            $this->audit->record('vacation_request.corrected', $vacation, $old, $vacation->only(['vacation_entitlement_id', 'starts_on', 'ends_on', 'corrected_by', 'corrected_at', 'review_note']));
        });
    }

    public function cancel(User $admin, VacationRequest $vacation, string $note): void
    {
        if ($vacation->status === 'cancelled') {
            throw ValidationException::withMessages(['cancel_note' => 'Esta solicitação já está cancelada.']);
        }
        $old = $vacation->only(['status', 'cancelled_by', 'cancelled_at', 'cancel_note']);
        $vacation->update(['status' => 'cancelled', 'cancelled_by' => $admin->id, 'cancelled_at' => now(), 'cancel_note' => trim($note)]);
        $this->audit->record('vacation_request.cancelled', $vacation, $old, $vacation->only(['status', 'cancelled_by', 'cancelled_at', 'cancel_note']));
        $vacation->user->notify(new VacationStatusChanged($vacation));
    }

    private function ensureAvailable(User $user, string $startsOn, string $endsOn, ?VacationRequest $ignore = null): void
    {
        $vacationConflict = $user->vacationRequests()->whereNotIn('status', ['rejected', 'cancelled'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->whereDate('starts_on', '<=', $endsOn)->whereDate('ends_on', '>=', $startsOn)->exists();
        if ($vacationConflict) {
            throw ValidationException::withMessages(['starts_on' => 'O período conflita com outra solicitação de férias.']);
        }

        $workConflict = WorkRequest::where('user_id', $user->id)->whereNotIn('status', ['rejected'])
            ->whereBetween('work_date', [$startsOn, $endsOn])->exists();
        if ($workConflict) {
            throw ValidationException::withMessages(['starts_on' => 'O período conflita com dias de home office registrados.']);
        }
    }

    public static function days(mixed $startsOn, mixed $endsOn): int
    {
        return CarbonImmutable::parse($startsOn)->diffInDays(CarbonImmutable::parse($endsOn)) + 1;
    }

    private function ensureBalance(VacationEntitlement $entitlement, int $requestedDays, ?int $ignoreRequestId = null, mixed $startsOn = null, mixed $endsOn = null): void
    {
        if ($entitlement->expires_on?->lt(today())) {
            throw ValidationException::withMessages(['vacation_entitlement_id' => 'O período aquisitivo selecionado está vencido.']);
        }
        if ($startsOn && CarbonImmutable::parse($startsOn)->lte($entitlement->acquisition_ends_on)) {
            throw ValidationException::withMessages(['starts_on' => 'As férias devem começar após o término do período aquisitivo.']);
        }
        if ($endsOn && $entitlement->expires_on && CarbonImmutable::parse($endsOn)->gt($entitlement->expires_on)) {
            throw ValidationException::withMessages(['ends_on' => 'As férias devem terminar dentro do prazo de utilização deste saldo.']);
        }
        if ($requestedDays > $entitlement->availableDays($ignoreRequestId)) {
            $availableDays = $entitlement->availableDays($ignoreRequestId);
            throw ValidationException::withMessages(['ends_on' => "A solicitação excede o saldo disponível de {$availableDays} dia(s)."]);
        }
    }

    private function ensureCashAllowance(VacationEntitlement $entitlement, int $restDays, int $cashAllowanceDays): void
    {
        if ($cashAllowanceDays === 0) {
            return;
        }
        $maximum = intdiv($entitlement->totalDays(), 3);
        if ($cashAllowanceDays !== $maximum || $restDays + $cashAllowanceDays !== $entitlement->totalDays()) {
            throw ValidationException::withMessages(['cash_allowance_days' => "O abono deve corresponder a 1/3 do saldo ({$maximum} dia(s))."]);
        }
        if ($entitlement->approvedDays() > 0 || $entitlement->reservedDays() > 0) {
            throw ValidationException::withMessages(['cash_allowance_days' => 'O abono deve ser solicitado antes de utilizar ou reservar este saldo.']);
        }
        if (today()->gt($entitlement->acquisition_ends_on->copy()->subDays(15))) {
            throw ValidationException::withMessages(['cash_allowance_days' => 'O prazo para solicitar o abono deste período aquisitivo terminou.']);
        }
    }

    private function ensureCorrectedCashAllowance(VacationEntitlement $entitlement, int $restDays, int $cashAllowanceDays): void
    {
        if ($cashAllowanceDays === 0) {
            return;
        }

        $maximum = intdiv($entitlement->totalDays(), 3);
        if ($cashAllowanceDays !== $maximum || $restDays + $cashAllowanceDays !== $entitlement->totalDays()) {
            throw ValidationException::withMessages(['ends_on' => 'A correção deve preservar os dias de descanso e de abono originalmente solicitados.']);
        }
    }
}
