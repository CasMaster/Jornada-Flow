<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\WorkRequest;
use App\Notifications\WorkRequestStatusChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkRequestService
{
    public function __construct(private AuditService $audit) {}

    public function createMany(User $user, array $dates, string $workMode = 'home_office'): int
    {
        $uniqueDates = array_unique($dates);
        $blocked = Holiday::where(function ($query) use ($uniqueDates) {
            foreach ($uniqueDates as $date) {
                $query->orWhereDate('date', $date);
            }
        })->where('blocks_requests', true)->get();
        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages(['dates' => 'Uma ou mais datas selecionadas estão bloqueadas no calendário corporativo.']);
        }
        $vacations = VacationRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('request_type', 'vacation')
            ->get(['starts_on', 'ends_on']);
        $vacationConflict = $vacations->contains(fn (VacationRequest $vacation) => collect($uniqueDates)
            ->contains(function (string $date) use ($vacation) {
                $requestedDate = CarbonImmutable::parse($date)->startOfDay();

                return $vacation->starts_on->startOfDay()->lte($requestedDate)
                    && $vacation->ends_on->startOfDay()->gte($requestedDate);
            }));
        if ($vacationConflict) {
            throw ValidationException::withMessages(['dates' => 'Não é possível registrar trabalho durante férias aprovadas.']);
        }
        $activeConflict = WorkRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('work_mode', '<>', $workMode)
            ->where(function ($query) use ($uniqueDates) {
                foreach ($uniqueDates as $date) {
                    $query->orWhereDate('work_date', $date);
                }
            })
            ->exists();
        if ($activeConflict) {
            throw ValidationException::withMessages(['dates' => 'Uma ou mais datas já possuem outro tipo de jornada registrado.']);
        }
        $added = 0;
        DB::transaction(function () use ($user, $dates, $workMode, &$added) {
            foreach (array_unique($dates) as $date) {
                $record = WorkRequest::firstOrCreate(
                    ['user_id' => $user->id, 'work_date' => $date, 'work_mode' => $workMode],
                    ['status' => 'pending']
                );
                if ($record->wasRecentlyCreated) {
                    $added++;
                    $this->audit->record('work_request.created', $record, [], ['work_date' => $date, 'work_mode' => $workMode, 'status' => 'pending']);
                }
            }
        });

        return $added;
    }

    public function review(User $manager, WorkRequest $record, string $decision, ?string $note = null): void
    {
        $old = $record->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']);
        $record->update(['status' => $decision, 'reviewed_by' => $manager->id, 'reviewed_at' => now(), 'review_note' => $note ?: null]);
        $this->audit->record('work_request.'.$decision, $record, $old, $record->only(['status', 'reviewed_by', 'reviewed_at', 'review_note']));
        $record->user->notify(new WorkRequestStatusChanged($record));
    }
}
