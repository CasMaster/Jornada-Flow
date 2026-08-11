<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json(['status' => 'ok', 'database' => 'ok', 'queued_jobs' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count(), 'checked_at' => now()->toIso8601String()]);
        } catch (\Throwable $error) {
            report($error);

            return response()->json(['status' => 'error', 'database' => 'unavailable'], 503);
        }
    }
}
