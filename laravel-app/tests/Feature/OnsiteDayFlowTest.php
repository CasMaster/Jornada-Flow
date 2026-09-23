<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\WorkRequest;
use App\Notifications\WorkRequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OnsiteDayFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_submits_onsite_day_for_manager_approval(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);

        $this->actingAs($employee)->postJson('/solicitacoes', [
            'dates' => ['2026-10-05'],
            'work_mode' => 'onsite',
        ])->assertCreated()->assertJson(['saved' => 1, 'work_mode' => 'onsite']);

        $this->assertDatabaseHas('work_requests', [
            'user_id' => $employee->id,
            'work_date' => '2026-10-05 00:00:00',
            'work_mode' => 'onsite',
            'status' => 'pending',
        ]);
    }

    public function test_manager_can_approve_onsite_day(): void
    {
        Notification::fake();
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        $record = WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-10-05', 'work_mode' => 'onsite']);

        $this->actingAs($manager)
            ->post(route('manager.review', $record), ['decision' => 'approved'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('work_requests', ['id' => $record->id, 'work_mode' => 'onsite', 'status' => 'approved', 'reviewed_by' => $manager->id]);
        Notification::assertSentTo($employee, WorkRequestStatusChanged::class, fn ($notification) => $notification->record->isOnsite());
    }

    public function test_active_home_office_and_onsite_requests_cannot_share_a_date(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-10-05', 'work_mode' => 'home_office']);

        $this->actingAs($employee)->postJson('/solicitacoes', [
            'dates' => ['2026-10-05'],
            'work_mode' => 'onsite',
        ])->assertUnprocessable()->assertJsonValidationErrors('dates');

        $this->assertDatabaseMissing('work_requests', ['user_id' => $employee->id, 'work_date' => '2026-10-05 00:00:00', 'work_mode' => 'onsite']);
    }

    public function test_manager_view_identifies_onsite_requests(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => today(), 'work_mode' => 'onsite']);

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Presencial')
            ->assertSee('name="work_mode"', false);
    }
}
