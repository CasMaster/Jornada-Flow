<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacation_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('acquisition_starts_on');
            $table->date('acquisition_ends_on');
            $table->date('expires_on')->nullable();
            $table->unsignedSmallInteger('granted_days')->default(30);
            $table->smallInteger('adjustment_days')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'acquisition_starts_on', 'acquisition_ends_on'], 'vacation_entitlement_period_unique');
            $table->index(['user_id', 'expires_on']);
        });

        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->foreignId('vacation_entitlement_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacation_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vacation_entitlement_id');
        });
        Schema::dropIfExists('vacation_entitlements');
    }
};
