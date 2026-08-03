<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('manager_team', function (Blueprint $table) {
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->timestamps(); $table->primary(['manager_id', 'team_id']);
        });
        Schema::create('work_requests', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date'); $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable(); $table->timestamps();
            $table->unique(['user_id', 'work_date']); $table->index(['status', 'work_date']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('work_requests'); Schema::dropIfExists('manager_team'); Schema::dropIfExists('teams');
    }
};
