<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->string('request_type', 30)->default('vacation')->after('vacation_entitlement_id');
            $table->index(['request_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->dropIndex(['request_type', 'status']);
            $table->dropColumn('request_type');
        });
    }
};
