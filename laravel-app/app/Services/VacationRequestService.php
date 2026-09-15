<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacationRequest;
use App\Models\WorkRequest;
use App\Notifications\VacationStatusChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacationRequestService
{
    public function __construct(private AuditService $audit) {}

    public function create(User $user, string $startsOn, string $endsOn): VacationRequest
    {
        $this->ensureAvailable($user, $startsOn, $endsOn);

        return DB::transaction(function () use ($user, $startsOn, $endsOn) {
            $vacation = $user->vacationRequests()->create(['starts_on' => $startsOn, 'ends_on' => $endsOn, 'status' => 'pending']);
            $this->audit->record('vacation_request.created', $vacation, [], $vacation->only(['starts_on', 'ends_on', 'status']));
            return $vacation;
        });
    }

    public function review(User $manager, VacationRequest $vacation, string $decision, ?string $note): void
    {
        if ($vacation->status !== 'pending') throw ValidationException::withMessages(['decision' => 'Esta solicitação já foi analisada.']);
        if ($decision === 'approved') {
            $this->ensureAvailable($vacation->user, $vacation->starts_on->toDateString(), $vacation->ends_on->toDateString(), $vacation);
        }
        $old = $vacation->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']);
        $vacation->update(['status' => $decision, 'reviewed_by' => $manager->id, 'reviewed_at' => now(), 'review_note' => $note ?: null]);
        $this->audit->record('vacation_request.'.$decision, $vacation, $old, $vacation->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']));
        $vacation->user->notify(new VacationStatusChanged($vacation));
    }

    public function correct(User $admin, VacationRequest $vacation, string $startsOn, string $endsOn, string $note): void
    {
        if ($vacation->status === 'cancelled') throw ValidationException::withMessages(['note' => 'Uma solicitação cancelada não pode ser corrigida.']);
        $this->ensureAvailable($vacation->user, $startsOn, $endsOn, $vacation);
        $old = $vacation->only(['starts_on', 'ends_on', 'corrected_by', 'corrected_at', 'review_note']);
        $vacation->update(['starts_on' => $startsOn, 'ends_on' => $endsOn, 'corrected_by' => $admin->id, 'corrected_at' => now(), 'review_note' => trim($note)]);
        $this->audit->record('vacation_request.corrected', $vacation, $old, $vacation->only(['starts_on', 'ends_on', 'corrected_by', 'corrected_at', 'review_note']));
    }

    public function cancel(User $admin, VacationRequest $vacation, string $note): void
    {
        if ($vacation->status === 'cancelled') throw ValidationException::withMessages(['cancel_note' => 'Esta solicitação já está cancelada.']);
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
        if ($vacationConflict) throw ValidationException::withMessages(['starts_on' => 'O período conflita com outra solicitação de férias.']);

        $workConflict = WorkRequest::where('user_id', $user->id)->whereNotIn('status', ['rejected'])
            ->whereBetween('work_date', [$startsOn, $endsOn])->exists();
        if ($workConflict) throw ValidationException::withMessages(['starts_on' => 'O período conflita com dias de home office registrados.']);
    }

    public static function days(mixed $startsOn, mixed $endsOn): int
    {
        return CarbonImmutable::parse($startsOn)->diffInDays(CarbonImmutable::parse($endsOn)) + 1;
    }
}
