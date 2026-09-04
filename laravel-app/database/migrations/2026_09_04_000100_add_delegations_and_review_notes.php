<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['delegate_id', 'starts_on', 'ends_on']);
        });

        Schema::table('work_requests', function (Blueprint $table) {
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_requests', fn (Blueprint $table) => $table->dropColumn('review_note'));
        Schema::dropIfExists('manager_delegations');
    }
};
