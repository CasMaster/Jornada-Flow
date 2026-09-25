<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkModeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_receives_approved_work_modes_grouped_alphabetically_for_selected_month(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $manager->managedTeams()->attach($team);
        $bruno = User::factory()->create(['name' => 'Bruno', 'team' => 'Fiscal', 'active' => true]);
        $ana = User::factory()->create(['name' => 'Ana', 'team' => 'Fiscal', 'active' => true]);

        WorkRequest::create(['user_id' => $ana->id, 'work_date' => '2026-09-03', 'work_mode' => 'home_office', 'status' => 'approved']);
        WorkRequest::create(['user_id' => $ana->id, 'work_date' => '2026-09-04', 'work_mode' => 'onsite', 'status' => 'approved']);
        WorkRequest::create(['user_id' => $bruno->id, 'work_date' => '2026-09-05', 'work_mode' => 'onsite', 'status' => 'approved']);
        WorkRequest::create(['user_id' => $bruno->id, 'work_date' => '2026-09-06', 'work_mode' => 'home_office', 'status' => 'pending']);
        WorkRequest::create(['user_id' => $bruno->id, 'work_date' => '2026-10-05', 'work_mode' => 'home_office', 'status' => 'approved']);

        $this->actingAs($manager)
            ->getJson(route('manager.work-mode-distribution', ['month' => 9, 'year' => 2026]))
            ->assertOk()
            ->assertExactJson(['data' => [
                ['collaborator' => 'Ana', 'home_office' => 1, 'onsite' => 1],
                ['collaborator' => 'Bruno', 'home_office' => 0, 'onsite' => 1],
            ]]);
    }

    public function test_manager_cannot_see_collaborators_outside_allowed_teams(): void
    {
        $allowed = Team::create(['name' => 'Fiscal']);
        Team::create(['name' => 'Financeiro']);
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $manager->managedTeams()->attach($allowed);
        $visible = User::factory()->create(['name' => 'Visível', 'team' => 'Fiscal', 'active' => true]);
        $hidden = User::factory()->create(['name' => 'Oculto', 'team' => 'Financeiro', 'active' => true]);
        WorkRequest::create(['user_id' => $visible->id, 'work_date' => '2026-09-03', 'work_mode' => 'home_office', 'status' => 'approved']);
        WorkRequest::create(['user_id' => $hidden->id, 'work_date' => '2026-09-03', 'work_mode' => 'onsite', 'status' => 'approved']);

        $this->actingAs($manager)
            ->getJson(route('manager.work-mode-distribution', ['month' => 9, 'year' => 2026]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.collaborator', 'Visível');
    }

    public function test_dashboard_exposes_chart_and_period_validation(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $manager->managedTeams()->attach($team);

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Modalidade de Trabalho')
            ->assertSee('Distribuição de dias por modalidade de trabalho')
            ->assertSee('.work-mode-chart-scroll{overflow-y:hidden}.work-mode-tooltip{top:8px;bottom:auto}', false);

        $this->actingAs($manager)
            ->getJson(route('manager.work-mode-distribution', ['month' => 13, 'year' => 2026]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('month');
    }

    public function test_loaded_chart_hides_the_loading_state(): void
    {
        $stylesheet = file_get_contents(public_path('assets/manager.css'));

        $this->assertStringContainsString('.work-mode-status[hidden],.work-mode-chart-scroll[hidden]{display:none}', $stylesheet);
    }
}
