<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\WorkRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VacationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_requests_vacation_and_cannot_overlap_home_office(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => now()->addDays(10)->toDateString(), 'status' => 'pending']);

        $this->actingAs($employee)->post(route('vacations.store'), [
            'starts_on' => now()->addDays(9)->toDateString(),
            'ends_on' => now()->addDays(12)->toDateString(),
        ])->assertSessionHasErrors('starts_on');

        $this->actingAs($employee)->post(route('vacations.store'), [
            'starts_on' => now()->addDays(20)->toDateString(),
            'ends_on' => now()->addDays(24)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vacation_requests', ['user_id' => $employee->id, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'vacation_request.created']);
    }

    public function test_manager_reviews_only_vacations_from_managed_team(): void
    {
        Notification::fake();
        $fiscal = Team::create(['name' => 'Fiscal']);
        Team::create(['name' => 'TI']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($fiscal);
        $allowed = User::factory()->create(['team' => 'Fiscal']);
        $denied = User::factory()->create(['team' => 'TI']);
        $allowedVacation = VacationRequest::create(['user_id' => $allowed->id, 'starts_on' => now()->addMonth(), 'ends_on' => now()->addMonth()->addDays(4)]);
        $deniedVacation = VacationRequest::create(['user_id' => $denied->id, 'starts_on' => now()->addMonth(), 'ends_on' => now()->addMonth()->addDays(4)]);

        $this->actingAs($manager)->post(route('manager.vacations.review', $deniedVacation), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($manager)->post(route('manager.vacations.review', $allowedVacation), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vacation_requests', ['id' => $allowedVacation->id, 'status' => 'approved', 'reviewed_by' => $manager->id]);
    }

    public function test_approved_vacation_blocks_home_office(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $date = now()->addMonth()->startOfMonth()->addDays(2);
        VacationRequest::create(['user_id' => $employee->id, 'starts_on' => $date->copy()->subDay(), 'ends_on' => $date->copy()->addDay(), 'status' => 'approved']);
        $this->actingAs($employee)->post(route('employee.requests.store'), ['dates' => [$date->toDateString()]])->assertSessionHasErrors('dates');
        $this->assertDatabaseMissing('work_requests', ['user_id' => $employee->id, 'work_date' => $date->toDateString()]);
    }

    public function test_manager_cannot_approve_after_a_home_office_conflict_was_created(): void
    {
        Notification::fake();
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        $date = now()->addMonth()->startOfMonth()->addDays(10);
        $vacation = VacationRequest::create(['user_id' => $employee->id, 'starts_on' => $date->copy()->subDay(), 'ends_on' => $date->copy()->addDay()]);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => $date, 'status' => 'pending']);

        $this->actingAs($manager)->post(route('manager.vacations.review', $vacation), ['decision' => 'approved'])->assertSessionHasErrors('starts_on');
        $this->assertDatabaseHas('vacation_requests', ['id' => $vacation->id, 'status' => 'pending']);
    }

    public function test_super_admin_corrects_cancels_and_exports_vacations(): void
    {
        Notification::fake();
        Team::create(['name' => 'Fiscal']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $employee = User::factory()->create(['team' => 'Fiscal']);
        $vacation = VacationRequest::create(['user_id' => $employee->id, 'starts_on' => now()->addMonth(), 'ends_on' => now()->addMonth()->addDays(4), 'status' => 'approved']);

        $this->actingAs($admin)->put(route('admin.vacations.correct', $vacation), ['starts_on' => now()->addMonth()->addDay()->toDateString(), 'ends_on' => now()->addMonth()->addDays(5)->toDateString(), 'note' => 'Ajuste solicitado pelo RH'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.vacations.cancel', $vacation), ['note' => 'Cancelamento solicitado pelo RH'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vacation_requests', ['id' => $vacation->id, 'status' => 'cancelled', 'cancel_note' => 'Cancelamento solicitado pelo RH']);
        $this->assertSame(1, AuditLog::where('event', 'vacation_request.corrected')->count());
        $this->assertSame(1, AuditLog::where('event', 'vacation_request.cancelled')->count());
        $this->actingAs($admin)->get(route('manager.vacations.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
