<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkRequest;
use App\Notifications\WorkRequestStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkRequestService
{
    public function __construct(private AuditService $audit) {}

    public function createMany(User $user, array $dates): int
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
        $added = 0;
        DB::transaction(function () use ($user, $dates, &$added) {
            foreach (array_unique($dates) as $date) {
                $record = WorkRequest::firstOrCreate(['user_id' => $user->id, 'work_date' => $date], ['status' => 'pending']);
                if ($record->wasRecentlyCreated) {
                    $added++;
                    $this->audit->record('work_request.created', $record, [], ['work_date' => $date, 'status' => 'pending']);
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
