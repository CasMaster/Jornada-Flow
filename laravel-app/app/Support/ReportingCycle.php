<?php
namespace App\Support;
use Carbon\CarbonImmutable;
class ReportingCycle
{
    public static function bounds(?string $value = null): array
    {
        $today = CarbonImmutable::today();
        $start = $value && preg_match('/^\d{4}-\d{2}-20$/', $value)
            ? CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay()
            : ($today->day >= 20 ? $today->day(20) : $today->subMonthNoOverflow()->day(20));
        return [$start, $start->addMonth()->subDay()];
    }
    public static function options(int $count = 13): array
    {
        [$current] = self::bounds(); $items = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $current->subMonths($i); $end = $start->addMonth()->subDay();
            $items[] = ['value' => $start->format('Y-m-d'), 'label' => $start->format('d/m/Y').' — '.$end->format('d/m/Y')];
        }
        return $items;
    }
}
