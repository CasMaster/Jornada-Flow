<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacationEntitlement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class VacationEntitlementService
{
    public function __construct(private AuditService $audit) {}

    public function sync(User $user, ?User $actor = null): int
    {
        if (! $user->hired_on) {
            return 0;
        }

        return DB::transaction(function () use ($user, $actor): int {
            $created = 0;
            $hiredOn = CarbonImmutable::parse($user->hired_on);

            for ($year = 0; $year < 100; $year++) {
                $startsOn = $hiredOn->addYearsNoOverflow($year);
                $nextStartsOn = $hiredOn->addYearsNoOverflow($year + 1);
                $endsOn = $nextStartsOn->subDay();
                if ($endsOn->gte(today())) {
                    break;
                }

                $entitlement = VacationEntitlement::where('user_id', $user->id)
                    ->whereDate('acquisition_starts_on', $startsOn)
                    ->whereDate('acquisition_ends_on', $endsOn)
                    ->first();
                if (! $entitlement) {
                    $entitlement = VacationEntitlement::create([
                        'user_id' => $user->id,
                        'acquisition_starts_on' => $startsOn->toDateString(),
                        'acquisition_ends_on' => $endsOn->toDateString(),
                        'expires_on' => $endsOn->addYearNoOverflow()->toDateString(),
                        'granted_days' => 30,
                        'adjustment_days' => 0,
                        'notes' => 'Gerado automaticamente pela data de contratação.',
                        'created_by' => $actor?->id,
                        'updated_by' => $actor?->id,
                    ]);
                    $created++;
                    $this->audit->record('vacation_entitlement.generated', $entitlement, [], $entitlement->only([
                        'user_id', 'acquisition_starts_on', 'acquisition_ends_on', 'expires_on', 'granted_days',
                    ]));
                }
            }

            return $created;
        });
    }

    public function syncAll(): int
    {
        $created = 0;
        User::whereNotNull('hired_on')->where('active', true)->orderBy('id')->eachById(function (User $user) use (&$created) {
            $created += $this->sync($user);
        });

        return $created;
    }

    /**
     * @return array{starts_on: CarbonImmutable, ends_on: CarbonImmutable, available_on: CarbonImmutable}|null
     */
    public function currentAccrualPeriod(User $user): ?array
    {
        if (! $user->hired_on) {
            return null;
        }

        $startsOn = CarbonImmutable::parse($user->hired_on);

        while ($startsOn->addYearNoOverflow()->lte(today())) {
            $startsOn = $startsOn->addYearNoOverflow();
        }

        $endsOn = $startsOn->addYearNoOverflow()->subDay();

        return [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'available_on' => $endsOn->addDay(),
        ];
    }
}
