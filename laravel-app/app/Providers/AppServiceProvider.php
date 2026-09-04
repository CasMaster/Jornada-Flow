<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if ($query->time >= config('observability.slow_query_ms')) {
                Log::warning('slow_query', ['duration_ms' => $query->time, 'connection' => $query->connectionName, 'sql' => $query->sql]);
            }
        });
    }
}
