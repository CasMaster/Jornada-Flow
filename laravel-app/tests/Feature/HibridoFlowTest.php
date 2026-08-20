<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkRequest;
use App\Notifications\WorkRequestStatusChanged;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class HibridoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exposes_employee_and_manager_areas(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sou colaborador')->assertSee('Sou gestor');
        $this->get('/login?perfil=manager')->assertOk()->assertSee('Painel do gestor')->assertSee('Entrar como gestor');
        $manager = User::factory()->create(['role' => 'manager', 'password' => 'password']);
        $this->post('/login', ['email' => $manager->email, 'password' => 'password', 'profile' => 'manager'])->assertRedirect('/gestor');
    }

    public function test_authenticated_users_do_not_loop_between_root_and_login(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($employee)->get('/login')->assertRedirect('/');
        $this->get('/')->assertRedirect('/painel');

        $this->actingAs($manager)->get('/login')->assertRedirect('/');
        $this->get('/')->assertRedirect('/gestor');
    }

    public function test_employee_can_register_and_submit_immutable_request(): void
    {
        Team::create(['name' => 'Fiscal', 'active' => true]);
        $this->post('/cadastro', ['name' => 'Ana', 'email' => 'ana@example.com', 'team' => 'Fiscal', 'password' => 'password1', 'password_confirmation' => 'password1'])->assertRedirect('/painel');
        $this->post('/solicitacoes', ['dates' => ['2026-08-05']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_requests', ['work_date' => '2026-08-05 00:00:00', 'status' => 'pending']);
        $this->get('/painel')->assertOk()->assertSee('Selecione os dias')->assertSee('Dias já registrados')->assertSee('05/08/2026');
        $this->delete('/solicitacoes/1')->assertNotFound();
    }

    public function test_manager_can_only_review_requests_from_assigned_teams(): void
    {
        $a = Team::create(['name' => 'Fiscal']);
        $b = Team::create(['name' => 'TI']);
        $manager = User::factory()->create(['role' => 'manager', 'team' => '']);
        $manager->managedTeams()->attach($a);
        $ana = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        $bob = User::factory()->create(['role' => 'employee', 'team' => 'TI']);
        $allowed = WorkRequest::create(['user_id' => $ana->id, 'work_date' => '2026-08-05']);
        $blocked = WorkRequest::create(['user_id' => $bob->id, 'work_date' => '2026-08-06']);
        $this->actingAs($manager)->post("/gestor/solicitacoes/{$allowed->id}/analisar", ['decision' => 'approved'])->assertRedirect();
        $this->actingAs($manager)->post("/gestor/solicitacoes/{$blocked->id}/analisar", ['decision' => 'rejected'])->assertForbidden();
        $this->assertDatabaseHas('work_requests', ['id' => $allowed->id, 'status' => 'approved']);
        $this->assertDatabaseHas('work_requests', ['id' => $blocked->id, 'status' => 'pending']);
    }

    public function test_manager_can_use_employee_panel_and_submit_own_request(): void
    {
        Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager', 'team' => 'Fiscal', 'password' => 'password']);
        $this->post('/login', ['email' => $manager->email, 'password' => 'password', 'profile' => 'employee'])->assertRedirect('/painel');
        $this->post('/solicitacoes', ['dates' => ['2026-08-12']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_requests', ['user_id' => $manager->id, 'work_date' => '2026-08-12 00:00:00', 'status' => 'pending']);
        $this->get('/painel')->assertOk()->assertSee('Meu home office')->assertSee('Gestão');
    }

    public function test_manager_cycle_filter_uses_twentieth_through_nineteenth(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-07-19']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-07-20']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-08-19']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-08-20']);
        $response = $this->actingAs($manager)->get('/gestor?cycle=2026-07-20');
        $response->assertOk()->assertSee('20/07/2026')->assertSee('19/08/2026');
        $this->assertSame(2, $response->viewData('records')->count());
    }

    public function test_super_admin_can_export_xlsx(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $employee = User::factory()->create(['role' => 'employee', 'team' => 'Fiscal']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-07-21', 'status' => 'approved']);
        $response = $this->actingAs($admin)->get('/gestor/exportar?cycle=2026-07-20');
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_super_admin_uses_paginated_user_directory_and_creates_invited_user(): void
    {
        config(['auth.password_recovery_enabled' => true]);
        Notification::fake();
        $team = Team::create(['name' => 'Fiscal']);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get('/admin/usuarios?q='.$admin->email)
            ->assertOk()->assertSee($admin->email);

        $this->post('/admin/usuarios', [
            'name' => 'Nova Pessoa', 'email' => 'nova.pessoa@example.com', 'role' => 'manager',
            'team' => 'Fiscal', 'manager_teams' => [$team->id],
        ])->assertRedirect('/admin/usuarios');

        $created = User::where('email', 'nova.pessoa@example.com')->firstOrFail();
        $this->assertTrue($created->managedTeams->contains($team));
        Notification::assertSentTo($created, ResetPassword::class);
    }

    public function test_active_user_can_request_and_complete_password_recovery(): void
    {
        config(['auth.password_recovery_enabled' => true]);
        Notification::fake();
        $user = User::factory()->create(['email' => 'recuperar@example.com', 'active' => true]);

        $this->post('/esqueci-a-senha', ['email' => $user->email])->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->post('/redefinir-senha', [
            'token' => $token, 'email' => $user->email, 'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect('/login');

        $this->assertTrue(auth()->validate(['email' => $user->email, 'password' => 'nova-senha-segura']));
    }

    public function test_corporate_calendar_blocks_request_and_allows_informational_date(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        Holiday::create(['date' => '2026-08-15', 'name' => 'Bloqueio', 'blocks_requests' => true]);
        Holiday::create(['date' => '2026-08-16', 'name' => 'Informativo', 'blocks_requests' => false]);
        $this->actingAs($employee)->post('/solicitacoes', ['dates' => ['2026-08-15']])->assertSessionHasErrors('dates');
        $this->post('/solicitacoes', ['dates' => ['2026-08-16']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_requests', ['user_id' => $employee->id, 'work_date' => '2026-08-16 00:00:00']);
    }

    public function test_manager_can_review_authorized_requests_in_batch_and_audit_is_recorded(): void
    {
        Notification::fake();
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['team' => 'Fiscal']);
        $records = collect(['2026-08-10', '2026-08-11'])->map(fn ($date) => WorkRequest::create(['user_id' => $employee->id, 'work_date' => $date]));
        $this->actingAs($manager)->post('/gestor/solicitacoes/analisar-em-lote', ['requests' => $records->pluck('id')->all(), 'decision' => 'approved'])->assertRedirect();
        $this->assertSame(2, WorkRequest::where('status', 'approved')->count());
        $this->assertSame(2, AuditLog::where('event', 'work_request.approved')->count());
        Notification::assertSentToTimes($employee, WorkRequestStatusChanged::class, 2);
    }

    public function test_batch_review_is_atomic_from_authorization_perspective(): void
    {
        $allowedTeam = Team::create(['name' => 'Fiscal']);
        Team::create(['name' => 'TI']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($allowedTeam);
        $allowed = WorkRequest::create(['user_id' => User::factory()->create(['team' => 'Fiscal'])->id, 'work_date' => '2026-08-10']);
        $blocked = WorkRequest::create(['user_id' => User::factory()->create(['team' => 'TI'])->id, 'work_date' => '2026-08-11']);
        $this->actingAs($manager)->post('/gestor/solicitacoes/analisar-em-lote', ['requests' => [$allowed->id, $blocked->id], 'decision' => 'approved'])->assertForbidden();
        $this->assertSame(0, WorkRequest::where('status', 'approved')->count());
    }

    public function test_manager_search_pagination_and_export_remain_team_scoped(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        Team::create(['name' => 'TI']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $ana = User::factory()->create(['name' => 'Ana Permitida', 'email' => 'ana@example.com', 'team' => 'Fiscal']);
        $blocked = User::factory()->create(['name' => 'Pessoa Bloqueada', 'team' => 'TI']);
        foreach (range(0, 29) as $offset) {
            WorkRequest::create(['user_id' => $ana->id, 'work_date' => Carbon::parse('2026-07-20')->addDays($offset), 'status' => 'approved']);
        }
        WorkRequest::create(['user_id' => $blocked->id, 'work_date' => '2026-07-21', 'status' => 'approved']);
        $response = $this->actingAs($manager)->get('/gestor?cycle=2026-07-20&q=ana');
        $response->assertOk()->assertSee('Ana Permitida')->assertDontSee('Pessoa Bloqueada');
        $this->assertSame(25, $response->viewData('records')->count());
        $this->get('/gestor/exportar?cycle=2026-07-20')->assertOk();
    }

    public function test_super_admin_can_manage_holiday_and_view_audit(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post('/admin/calendario', ['date' => '2026-09-07', 'name' => 'Independência', 'blocks_requests' => 1])->assertRedirect();
        $this->assertDatabaseHas('holidays', ['name' => 'Independência']);
        $this->get('/admin/auditoria')->assertOk()->assertSee('holiday.created');
    }

    public function test_readiness_endpoint_checks_database(): void
    {
        $this->get('/health/ready')->assertOk()->assertJsonPath('database', 'ok');
    }

    public function test_https_proxy_is_trusted_and_session_cookie_is_secure(): void
    {
        config(['session.secure' => true]);

        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'mixhome.app.br',
        ])->get('/login');

        $response->assertOk();
        $this->assertStringContainsString('secure', strtolower(implode(';', $response->headers->all('set-cookie'))));
    }
}
