<?php

use App\Models\User;
use App\Models\WorkRequest;
use App\Notifications\PendingRequestsDigest;
use App\Support\ReportingCycle;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

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

Artisan::command('hibrido:apply-retention {--execute : Confirma a aplicação das alterações}', function () {
    $cutoff = now()->subYears(2);
    $counts = [
        'audit_logs' => DB::table('audit_logs')->where('created_at', '<', $cutoff)->count(),
        'notifications' => DB::table('notifications')->where('created_at', '<', $cutoff)->count(),
        'sessions' => DB::table('sessions')->where('last_activity', '<', $cutoff->timestamp)->count(),
        'rejected_requests' => DB::table('work_requests')->where('status', 'rejected')->where('updated_at', '<', $cutoff)->count(),
        'inactive_users' => DB::table('users')->where('active', false)->where('updated_at', '<', $cutoff)->count(),
    ];
    $this->table(['Categoria', 'Registros elegíveis'], collect($counts)->map(fn ($count, $name) => [$name, $count]));
    if (! $this->option('execute')) {
        $this->warn('Simulação concluída. Use --execute somente após backup e autorização do Super Admin.');

        return;
    }
    if (! config('app.data_retention_enabled')) {
        $this->error('DATA_RETENTION_ENABLED não está habilitado.');

        return 1;
    }
    DB::transaction(function () use ($cutoff) {
        DB::table('audit_logs')->where('created_at', '<', $cutoff)->delete();
        DB::table('notifications')->where('created_at', '<', $cutoff)->delete();
        DB::table('sessions')->where('last_activity', '<', $cutoff->timestamp)->delete();
        DB::table('work_requests')->where('status', 'rejected')->where('updated_at', '<', $cutoff)->delete();
        DB::table('users')->where('active', false)->where('updated_at', '<', $cutoff)->orderBy('id')->eachById(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['name' => 'Usuário anonimizado', 'email' => "anonimo-{$user->id}@mixhome.invalid", 'team' => '', 'password' => Hash::make(Str::random(64)), 'remember_token' => null, 'updated_at' => now()]);
        });
    });
    $this->info('Política de retenção aplicada e contas elegíveis anonimizadas.');
})->purpose('Simula ou aplica a retenção de dados de dois anos');

Schedule::command('hibrido:apply-retention --execute')->monthlyOn(5, '02:30')->when(fn () => config('app.data_retention_enabled'))->withoutOverlapping()->onOneServer();
