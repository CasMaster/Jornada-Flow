<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('source', 40)->default('manual')->after('blocks_requests');
            $table->string('scope', 20)->nullable()->after('source');
            $table->string('external_id', 100)->nullable()->after('scope');
            $table->timestamp('last_synced_at')->nullable()->after('external_id');
            $table->index(['source', 'last_synced_at']);
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropIndex(['source', 'last_synced_at']);
            $table->dropColumn(['source', 'scope', 'external_id', 'last_synced_at']);
        });
    }
};
