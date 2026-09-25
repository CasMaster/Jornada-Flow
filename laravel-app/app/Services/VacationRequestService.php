<?php

namespace App\Services;

use App\Models\Holiday;
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
        $this->ensureCltStart($startsOn);
        $this->ensureAvailable($user, $startsOn, $endsOn);

        return DB::transaction(function () use ($user, $entitlement, $startsOn, $endsOn, $cashAllowanceDays) {
            $lockedEntitlement = VacationEntitlement::lockForUpdate()->findOrFail($entitlement->id);
            $this->ensureCashAllowance($lockedEntitlement, $cashAllowanceDays);
            $this->ensureBalance($lockedEntitlement, self::days($startsOn, $endsOn) + $cashAllowanceDays, null, $startsOn, $endsOn);
            $this->ensureCltPeriods($lockedEntitlement, self::days($startsOn, $endsOn), $cashAllowanceDays);
            $vacation = $user->vacationRequests()->create(['vacation_entitlement_id' => $lockedEntitlement->id, 'request_type' => 'vacation', 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'cash_allowance_days' => $cashAllowanceDays, 'status' => 'pending']);
            $this->audit->record('vacation_request.created', $vacation, [], $vacation->only(['request_type', 'starts_on', 'ends_on', 'cash_allowance_days', 'status']));

            return $vacation;
        });
    }

    public function createCashAllowance(User $user, VacationEntitlement $entitlement): VacationRequest
    {
        if ($entitlement->user_id !== $user->id) {
            throw ValidationException::withMessages(['vacation_entitlement_id' => 'O saldo selecionado não pertence a este colaborador.']);
        }

        return DB::transaction(function () use ($user, $entitlement) {
            $lockedEntitlement = VacationEntitlement::lockForUpdate()->findOrFail($entitlement->id);
            $days = intdiv($lockedEntitlement->totalDays(), 3);
            $this->ensureCashAllowance($lockedEntitlement, $days);
            $this->ensureBalance($lockedEntitlement, $days);
            $compatibilityDate = $lockedEntitlement->expires_on?->copy()->addDay()
                ?? $lockedEntitlement->acquisition_ends_on->copy()->addYear()->addDay();
            $request = $user->vacationRequests()->create(['vacation_entitlement_id' => $lockedEntitlement->id, 'request_type' => 'cash_allowance', 'starts_on' => $compatibilityDate, 'ends_on' => $compatibilityDate, 'cash_allowance_days' => $days, 'status' => 'pending']);
            $this->audit->record('vacation_allowance.created', $request, [], $request->only(['request_type', 'cash_allowance_days', 'status']));

            return $request;
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
                if ($lockedVacation->isAllowanceOnly()) {
                    $this->ensureCashAllowance($lockedEntitlement, $lockedVacation->cash_allowance_days, $lockedVacation->id);
                    $this->ensureBalance($lockedEntitlement, $lockedVacation->cash_allowance_days, $lockedVacation->id);
                } else {
                    $this->ensureCltStart($lockedVacation->starts_on->toDateString());
                    $this->ensureAvailable($lockedVacation->user, $lockedVacation->starts_on->toDateString(), $lockedVacation->ends_on->toDateString(), $lockedVacation);
                    $this->ensureBalance($lockedEntitlement, $lockedVacation->totalDebitedDays(), $lockedVacation->id, $lockedVacation->starts_on, $lockedVacation->ends_on);
                    $this->ensureCltPeriods($lockedEntitlement, $lockedVacation->days(), $lockedVacation->cash_allowance_days, $lockedVacation->id);
                }
            }
            $old = $lockedVacation->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']);
            $lockedVacation->update(['status' => $decision, 'reviewed_by' => $manager->id, 'reviewed_at' => now(), 'review_note' => $note ?: null]);
            $this->audit->record('vacation_request.'.$decision, $lockedVacation, $old, $lockedVacation->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']));
            $lockedVacation->user->notify(new VacationStatusChanged($lockedVacation));
        });
    }

    public function correct(User $admin, VacationRequest $vacation, VacationEntitlement $entitlement, string $startsOn, string $endsOn, string $note): void
    {
        if ($vacation->isAllowanceOnly()) {
            throw ValidationException::withMessages(['note' => 'Uma solicitação exclusiva de abono não possui período de descanso para corrigir.']);
        }
        if ($vacation->status === 'cancelled') {
            throw ValidationException::withMessages(['note' => 'Uma solicitação cancelada não pode ser corrigida.']);
        }
        if ($entitlement->user_id !== $vacation->user_id) {
            throw ValidationException::withMessages(['vacation_entitlement_id' => 'O saldo selecionado não pertence ao colaborador.']);
        }
        $this->ensureCltStart($startsOn);
        $this->ensureAvailable($vacation->user, $startsOn, $endsOn, $vacation);
        DB::transaction(function () use ($admin, $vacation, $entitlement, $startsOn, $endsOn, $note) {
            $lockedEntitlement = VacationEntitlement::lockForUpdate()->findOrFail($entitlement->id);
            $this->ensureCorrectedCashAllowance($lockedEntitlement, self::days($startsOn, $endsOn), $vacation->cash_allowance_days);
            $this->ensureBalance($lockedEntitlement, self::days($startsOn, $endsOn) + $vacation->cash_allowance_days, $vacation->id, $startsOn, $endsOn);
            $this->ensureCltPeriods($lockedEntitlement, self::days($startsOn, $endsOn), $vacation->cash_allowance_days, $vacation->id);
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

    private function ensureCltStart(string $startsOn): void
    {
        $start = CarbonImmutable::parse($startsOn);
        if ($start->lt(today()->addDays(30))) {
            throw ValidationException::withMessages(['starts_on' => 'A solicitação deve respeitar antecedência mínima de 30 dias.']);
        }
        if ($start->isWeekend()) {
            throw ValidationException::withMessages(['starts_on' => 'O primeiro dia das férias deve ser um dia útil.']);
        }

        $holiday = Holiday::where('blocks_requests', true)->whereDate('date', $start)->first();
        if ($holiday) {
            throw ValidationException::withMessages(['starts_on' => "As férias não podem começar no feriado {$holiday->name}."]);
        }

        $upcomingHolidays = Holiday::where('blocks_requests', true)
            ->whereDate('date', '>=', $start->addDay()->toDateString())
            ->whereDate('date', '<=', $start->addDays(2)->toDateString())
            ->exists();
        if ($upcomingHolidays || $start->addDay()->isSunday() || $start->addDays(2)->isSunday()) {
            throw ValidationException::withMessages(['starts_on' => 'As férias não podem começar nos dois dias anteriores a feriado ou repouso semanal.']);
        }
    }

    private function ensureCltPeriods(VacationEntitlement $entitlement, int $restDays, int $cashAllowanceDays, ?int $ignoreRequestId = null): void
    {
        $requests = $entitlement->requests()->where('request_type', 'vacation')->whereNotIn('status', ['rejected', 'cancelled'])
            ->when($ignoreRequestId, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->get(['starts_on', 'ends_on']);
        if ($requests->count() >= 3) {
            throw ValidationException::withMessages(['starts_on' => 'Este saldo já atingiu o limite de três períodos de férias.']);
        }
        if ($restDays < 5) {
            throw ValidationException::withMessages(['ends_on' => 'Cada período de férias deve ter pelo menos 5 dias corridos.']);
        }

        $remaining = $entitlement->availableDays($ignoreRequestId) - $restDays - $cashAllowanceDays;
        if ($remaining > 0 && $remaining < 5) {
            throw ValidationException::withMessages(['ends_on' => 'A divisão deixaria um saldo menor que 5 dias, que não pode formar outro período.']);
        }
        if ($requests->count() === 2 && $remaining > 0) {
            throw ValidationException::withMessages(['ends_on' => 'O terceiro período deve utilizar todo o saldo restante.']);
        }

        $hasFourteenDayPeriod = $restDays >= 14 || $requests->contains(fn (VacationRequest $request) => $request->days() >= 14);
        if (! $hasFourteenDayPeriod && $remaining < 14) {
            throw ValidationException::withMessages(['ends_on' => 'A divisão deve preservar pelo menos um período de 14 dias corridos.']);
        }
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

    private function ensureCashAllowance(VacationEntitlement $entitlement, int $cashAllowanceDays, ?int $ignoreRequestId = null): void
    {
        if ($cashAllowanceDays === 0) {
            return;
        }
        $maximum = intdiv($entitlement->totalDays(), 3);
        if ($cashAllowanceDays !== $maximum) {
            throw ValidationException::withMessages(['cash_allowance_days' => "O abono deve corresponder a 1/3 do saldo ({$maximum} dia(s))."]);
        }
        if ($entitlement->approvedDays($ignoreRequestId) > 0 || $entitlement->reservedDays($ignoreRequestId) > 0) {
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
        if ($cashAllowanceDays !== $maximum) {
            throw ValidationException::withMessages(['ends_on' => 'A correção deve preservar os dias de abono originalmente solicitados.']);
        }
    }
}
