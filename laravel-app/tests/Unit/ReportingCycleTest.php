<?php

namespace Tests\Unit;

use App\Support\ReportingCycle;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReportingCycleTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_current_cycle_starts_on_previous_month_before_day_twenty(): void
    {
        CarbonImmutable::setTestNow('2026-08-19 12:00:00');

        [$start, $end] = ReportingCycle::bounds();

        $this->assertSame('2026-07-20', $start->toDateString());
        $this->assertSame('2026-08-19', $end->toDateString());
    }

    public function test_current_cycle_starts_on_current_month_from_day_twenty(): void
    {
        CarbonImmutable::setTestNow('2026-08-20 12:00:00');

        [$start, $end] = ReportingCycle::bounds();

        $this->assertSame('2026-08-20', $start->toDateString());
        $this->assertSame('2026-09-19', $end->toDateString());
    }

    public function test_explicit_cycle_and_options_use_inclusive_twentieth_to_nineteenth_bounds(): void
    {
        [$start, $end] = ReportingCycle::bounds('2026-06-20');

        $this->assertSame('2026-06-20', $start->toDateString());
        $this->assertSame('2026-07-19', $end->toDateString());

        CarbonImmutable::setTestNow('2026-08-20 12:00:00');
        $this->assertSame([
            ['value' => '2026-08-20', 'label' => '20/08/2026 — 19/09/2026'],
            ['value' => '2026-07-20', 'label' => '20/07/2026 — 19/08/2026'],
        ], ReportingCycle::options(2));
    }
}
