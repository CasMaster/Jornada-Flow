<?php

use App\Models\User;
use App\Models\WorkRequest;
use App\Notifications\PendingRequestsDigest;
use App\Support\ReportingCycle;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hibrido:notify-pending', function () {
    [$start,$end] = ReportingCycle::bounds();
    if (now()->startOfDay()->diffInDays($end, false) > 3) {
        $this->info('Fora da janela de aviso.');

        return;
    }
    User::whereIn('role', ['manager', 'super_admin'])->where('active', true)->each(function (User $manager) use ($start, $end) {
        $teams = $manager->role === 'super_admin' ? null : $manager->managedTeams()->pluck('name');
        $count = WorkRequest::where('status', 'pending')->whereBetween('work_date', [$start, $end])->when($teams, fn ($q) => $q->whereHas('user', fn ($u) => $u->whereIn('team', $teams)))->count();
        if ($count) {
            $manager->notify(new PendingRequestsDigest($count));
        }
    });
    $this->info('Avisos enfileirados.');
})->purpose('Notifica gestores sobre pendências próximas ao fechamento');

Schedule::command('hibrido:notify-pending')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('hibrido:sync-holidays')->monthlyOn(1, '03:00')->withoutOverlapping()->onOneServer();
