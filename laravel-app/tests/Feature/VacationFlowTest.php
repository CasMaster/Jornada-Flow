<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationEntitlement;
use App\Models\VacationRequest;
use App\Models\WorkRequest;
use App\Services\VacationEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VacationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_requests_vacation_and_cannot_overlap_home_office(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        $entitlement = $this->entitlement($employee, 30);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => now()->addDays(10)->toDateString(), 'status' => 'pending']);

        $this->actingAs($employee)->post(route('vacations.store'), [
            'vacation_entitlement_id' => $entitlement->id,
            'starts_on' => now()->addDays(9)->toDateString(),
            'ends_on' => now()->addDays(12)->toDateString(),
        ])->assertSessionHasErrors('starts_on');

        $this->actingAs($employee)->post(route('vacations.store'), [
            'vacation_entitlement_id' => $entitlement->id,
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
        $allowedEntitlement = $this->entitlement($allowed, 30);
        $allowedVacation = VacationRequest::create(['user_id' => $allowed->id, 'vacation_entitlement_id' => $allowedEntitlement->id, 'starts_on' => now()->addMonth(), 'ends_on' => now()->addMonth()->addDays(4)]);
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
        $entitlement = $this->entitlement($employee, 30);
        $date = now()->addMonth()->startOfMonth()->addDays(10);
        $vacation = VacationRequest::create(['user_id' => $employee->id, 'vacation_entitlement_id' => $entitlement->id, 'starts_on' => $date->copy()->subDay(), 'ends_on' => $date->copy()->addDay()]);
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
        $entitlement = $this->entitlement($employee, 30);
        $vacation = VacationRequest::create(['user_id' => $employee->id, 'vacation_entitlement_id' => $entitlement->id, 'starts_on' => now()->addMonth(), 'ends_on' => now()->addMonth()->addDays(4), 'status' => 'approved']);

        $this->actingAs($admin)->put(route('admin.vacations.correct', $vacation), ['vacation_entitlement_id' => $entitlement->id, 'starts_on' => now()->addMonth()->addDay()->toDateString(), 'ends_on' => now()->addMonth()->addDays(5)->toDateString(), 'note' => 'Ajuste solicitado pelo RH'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.vacations.cancel', $vacation), ['note' => 'Cancelamento solicitado pelo RH'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vacation_requests', ['id' => $vacation->id, 'status' => 'cancelled', 'cancel_note' => 'Cancelamento solicitado pelo RH']);
        $this->assertSame(1, AuditLog::where('event', 'vacation_request.corrected')->count());
        $this->assertSame(1, AuditLog::where('event', 'vacation_request.cancelled')->count());
        $this->actingAs($admin)->get(route('manager.vacations.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_pending_requests_reserve_balance_and_rejection_releases_it(): void
    {
        Notification::fake();
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        $entitlement = $this->entitlement($employee, 10);
        $start = now()->addMonth()->startOfMonth();

        $this->actingAs($employee)->post(route('vacations.store'), [
            'vacation_entitlement_id' => $entitlement->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addDays(5)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, $entitlement->availableDays());
        $this->actingAs($employee)->get(route('vacations.index'))
            ->assertSee('4 dias restantes')
            ->assertSee('6 dias já estão reservados em solicitação pendente.');

        $this->actingAs($employee)->post(route('vacations.store'), [
            'vacation_entitlement_id' => $entitlement->id,
            'starts_on' => $start->copy()->addDays(10)->toDateString(),
            'ends_on' => $start->copy()->addDays(15)->toDateString(),
        ])->assertSessionHasErrors('ends_on');

        $vacation = $employee->vacationRequests()->firstOrFail();
        $this->actingAs($manager)->post(route('manager.vacations.review', $vacation), ['decision' => 'rejected'])->assertSessionHasNoErrors();
        $this->assertSame(10, $entitlement->availableDays());
    }

    public function test_super_admin_registers_and_adjusts_acquisition_period(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($admin)->post(route('admin.vacation-entitlements.store'), [
            'user_id' => $employee->id,
            'acquisition_starts_on' => '2025-01-01',
            'acquisition_ends_on' => '2025-12-31',
            'expires_on' => '2026-12-31',
            'granted_days' => 30,
            'adjustment_days' => -2,
            'notes' => 'Ajuste informado pelo RH',
        ])->assertSessionHasNoErrors();

        $entitlement = VacationEntitlement::firstOrFail();
        $this->assertSame(28, $entitlement->availableDays());
        $this->assertDatabaseHas('audit_logs', ['event' => 'vacation_entitlement.created']);
    }

    public function test_hiring_date_generates_completed_and_current_acquisition_periods_idempotently(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $employee = User::factory()->create(['role' => 'employee', 'hired_on' => today()->subYears(2)]);
        $service = app(VacationEntitlementService::class);

        $this->assertSame(3, $service->sync($employee, $admin));
        $this->assertSame(0, $service->sync($employee, $admin));
        $this->assertSame(3, $employee->vacationEntitlements()->count());
        $this->assertDatabaseHas('vacation_entitlements', [
            'user_id' => $employee->id,
            'granted_days' => 30,
            'adjustment_days' => 0,
        ]);
        $this->assertSame(3, AuditLog::where('event', 'vacation_entitlement.generated')->count());
    }

    public function test_employee_with_one_balance_does_not_need_to_choose_acquisition_period(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $entitlement = $this->entitlement($employee, 30);

        $this->actingAs($employee)->get(route('vacations.index'))
            ->assertOk()
            ->assertSee('dias restantes')
            ->assertSee('Este saldo será usado automaticamente.')
            ->assertSee('name="vacation_entitlement_id" value="'.$entitlement->id.'"', false)
            ->assertSee('data-available-from="'.$entitlement->acquisition_ends_on->copy()->addDay()->format('Y-m-d').'"', false)
            ->assertSee('data-expires-on="'.$entitlement->expires_on->format('Y-m-d').'"', false)
            ->assertSee('id="vacation-calendar"', false)
            ->assertSee('id="vacation-start-trigger"', false)
            ->assertDontSee('id="vacation-starts-on" type="date"', false)
            ->assertDontSee('Início aquisitivo');
    }

    public function test_employee_sees_current_accrual_period_before_first_balance_is_released(): void
    {
        $this->travelTo('2026-09-15');
        $employee = User::factory()->create([
            'role' => 'employee',
            'hired_on' => '2025-11-11',
        ]);

        $this->actingAs($employee)->get(route('vacations.index'))
            ->assertOk()
            ->assertSee('Em formação')
            ->assertSee('11/11/2025')
            ->assertSee('10/11/2026')
            ->assertSee('Disponível a partir de 11/11/2026')
            ->assertDontSee('Confirme sua data de admissão');

        $this->travelBack();
    }

    public function test_employee_can_plan_vacation_for_after_current_period_release(): void
    {
        $this->travelTo('2026-09-15');
        $employee = User::factory()->create([
            'role' => 'employee',
            'hired_on' => '2025-11-11',
        ]);
        app(VacationEntitlementService::class)->sync($employee);
        $entitlement = $employee->vacationEntitlements()->firstOrFail();

        $this->actingAs($employee)->get(route('vacations.index'))
            ->assertOk()
            ->assertSee('data-available-from="2026-11-11"', false)
            ->assertDontSee('id="vacation-starts-on" type="date" name="starts_on" data-today="2026-09-15" min="2026-09-15" value="" required disabled', false);

        $this->actingAs($employee)->post(route('vacations.store'), [
            'vacation_entitlement_id' => $entitlement->id,
            'starts_on' => '2026-11-11',
            'ends_on' => '2026-11-15',
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)->post(route('vacations.store'), [
            'vacation_entitlement_id' => $entitlement->id,
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-10',
        ])->assertSessionHasErrors('starts_on');

        $this->travelBack();
    }

    private function entitlement(User $user, int $days): VacationEntitlement
    {
        return VacationEntitlement::create([
            'user_id' => $user->id,
            'acquisition_starts_on' => now()->subYear()->startOfDay(),
            'acquisition_ends_on' => now()->subDay()->startOfDay(),
            'expires_on' => now()->addYears(2),
            'granted_days' => $days,
            'notes' => 'Saldo de teste',
        ]);
    }
}
