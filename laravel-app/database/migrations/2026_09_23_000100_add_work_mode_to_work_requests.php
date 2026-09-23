<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->string('work_mode', 20)->default('home_office')->after('work_date');
            $table->dropUnique('work_requests_user_id_work_date_unique');
            $table->unique(['user_id', 'work_date', 'work_mode'], 'work_requests_user_date_mode_unique');
            $table->index(['work_mode', 'work_date'], 'work_requests_mode_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->dropIndex('work_requests_mode_date_idx');
            $table->dropUnique('work_requests_user_date_mode_unique');
            $table->unique(['user_id', 'work_date']);
            $table->dropColumn('work_mode');
        });
    }
};
