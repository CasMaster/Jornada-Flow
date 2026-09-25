<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('cash_allowance_days')->default(0)->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->dropColumn('cash_allowance_days');
        });
    }
};
