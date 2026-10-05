<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\ManagerDelegation;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkRequest;
use App\Notifications\PendingRequestsDigest;
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

    public function test_pending_digest_ignores_technical_placeholder_account(): void
    {
        $this->travelTo('2026-09-17 08:00:00');
        Notification::fake();
        $team = Team::create(['name' => 'Equipe Digest', 'active' => true]);
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $manager->managedTeams()->attach($team);
        $placeholder = User::factory()->create(['role' => 'super_admin', 'active' => true, 'email' => 'gestor@mixfiscal.com.br']);
        $employee = User::factory()->create(['team' => $team->name]);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-09-18', 'status' => 'pending']);

        $this->artisan('hibrido:notify-pending')->assertSuccessful();

        Notification::assertSentTo($manager, PendingRequestsDigest::class);
        Notification::assertNotSentTo($placeholder, PendingRequestsDigest::class);
        $this->travelBack();
    }

    public function test_login_uses_one_form_and_routes_each_role_to_its_dashboard(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Entre na sua conta')
            ->assertSee('Colaboradores, gestores e administradores utilizam o mesmo acesso.')
            ->assertDontSee('Sou colaborador')
            ->assertDontSee('Sou gestor')
            ->assertDontSee('Primeiro acesso');
        $manager = User::factory()->create(['role' => 'manager', 'password' => 'password']);
        $employee = User::factory()->create(['role' => 'employee', 'password' => 'password']);
        $this->post('/login', ['email' => $manager->email, 'password' => 'password'])->assertRedirect('/gestor');
        $this->post('/logout');
        $this->post('/login', ['email' => $employee->email, 'password' => 'password'])->assertRedirect('/painel');
    }

    public function test_login_error_is_rendered_inside_authentication_card(): void
    {
        $response = $this->from('/login')->post('/login', ['email' => 'invalido@example.com', 'password' => 'errada']);

        $response->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('auth-alert', false)->assertSee('Não foi possível entrar')->assertSee('E-mail ou senha inválidos.');
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

    public function test_public_registration_is_disabled_and_precreated_employee_can_submit_immutable_request(): void
    {
        Team::create(['name' => 'Fiscal', 'active' => true]);
        $this->post('/cadastro', ['name' => 'Ana', 'email' => 'ana@example.com', 'team' => 'Fiscal', 'password' => 'password1', 'password_confirmation' => 'password1'])->assertNotFound();
        $employee = User::factory()->create(['name' => '=HYPERLINK("https://example.invalid")', 'role' => 'employee', 'team' => 'Fiscal']);
        $this->actingAs($employee);
        $this->post('/solicitacoes', ['dates' => ['2026-08-05']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_requests', ['work_date' => '2026-08-05 00:00:00', 'status' => 'pending']);
        $this->get('/painel')->assertOk()->assertSee('Selecione os dias')->assertSee('Dias já registrados')->assertSee('05/08/2026');
        $this->delete('/solicitacoes/1')->assertNotFound();
    }

    public function test_employee_can_submit_request_using_json_dashboard_flow(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->postJson('/solicitacoes', ['dates' => ['2026-08-06']])
            ->assertCreated()
            ->assertJson(['saved' => 1]);

        $this->assertDatabaseHas('work_requests', [
            'user_id' => $employee->id,
            'work_date' => '2026-08-06 00:00:00',
            'status' => 'pending',
        ]);
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
        $this->post('/login', ['email' => $manager->email, 'password' => 'password'])->assertRedirect('/gestor');
        $this->get('/painel')->assertOk();
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

    public function test_manager_can_export_scoped_detailed_csv(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['name' => 'Pessoa CSV', 'role' => 'employee', 'team' => 'Fiscal']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-07-21', 'status' => 'pending']);

        $response = $this->actingAs($manager)->get('/gestor/exportar?cycle=2026-07-20&format=csv&export_status=all');

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Pessoa CSV', $response->streamedContent());
    }

    public function test_login_is_rate_limited_by_email_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'alvo@mixfiscal.com.br', 'password' => 'incorreta']);
        }

        $this->post('/login', ['email' => 'alvo@mixfiscal.com.br', 'password' => 'incorreta'])
            ->assertTooManyRequests();
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['name' => '=HYPERLINK("https://example.invalid")', 'role' => 'employee', 'team' => 'Fiscal']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-07-21', 'status' => 'pending']);

        $csv = $this->actingAs($manager)->get('/gestor/exportar?cycle=2026-07-20&format=csv&export_status=all')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
    }

    public function test_employee_calendar_exposes_request_status_accessibly(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => now()->startOfMonth()->addDay(), 'status' => 'approved']);

        $this->actingAs($employee)->get('/painel')->assertOk()
            ->assertSee('role="grid"', false)
            ->assertSee('window.HIBRIDO_REQUESTS=JSON.parse', false)
            ->assertSee('Aprovada');
    }

    public function test_only_super_admin_can_view_operations_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)->get('/admin/operacao')->assertOk()->assertSee('Saúde da')->assertSee('FALHAS NA FILA');
        $this->actingAs($manager)->get('/admin/operacao')->assertForbidden();
    }

    public function test_management_areas_are_split_into_dedicated_screens(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Localizar solicitações')
            ->assertDontSee('Delegação de gestores');

        $this->actingAs($admin)->get(route('admin.teams.index'))
            ->assertOk()
            ->assertSee('Equipes e')
            ->assertSee('Feriados e bloqueios')
            ->assertSee('Delegação de gestores');

        $this->actingAs($manager)->get(route('admin.teams.index'))->assertForbidden();
    }

    public function test_retention_is_dry_run_and_requires_feature_flag(): void
    {
        AuditLog::create(['event' => 'old.event', 'created_at' => now()->subYears(3), 'updated_at' => now()->subYears(3)]);

        $this->artisan('hibrido:apply-retention')->assertSuccessful();
        $this->assertDatabaseHas('audit_logs', ['event' => 'old.event']);
        $this->artisan('hibrido:apply-retention', ['--execute' => true])->assertFailed();
        $this->assertDatabaseHas('audit_logs', ['event' => 'old.event']);
    }

    public function test_authorized_retention_anonymizes_old_inactive_accounts(): void
    {
        config(['app.data_retention_enabled' => true]);
        $inactive = User::factory()->create(['active' => false, 'updated_at' => now()->subYears(3)]);
        $expiredAudit = AuditLog::create(['event' => 'expired.event']);
        $expiredAudit->timestamps = false;
        $expiredAudit->forceFill(['created_at' => now()->subYears(3), 'updated_at' => now()->subYears(3)])->save();

        $this->artisan('hibrido:apply-retention', ['--execute' => true])->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'expired.event']);
        $this->assertDatabaseHas('users', ['id' => $inactive->id, 'name' => 'Usuário anonimizado', 'email' => "anonimo-{$inactive->id}@mixhome.invalid"]);
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

        $this->from('/esqueci-a-senha')->post('/esqueci-a-senha', ['email' => $user->email])
            ->assertRedirect('/esqueci-a-senha')
            ->assertSessionHas('success');
        $this->get('/esqueci-a-senha')
            ->assertOk()
            ->assertSee('flash-message flash-success', false)
            ->assertSee('Se existir uma conta ativa para esse e-mail');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->post('/redefinir-senha', [
            'token' => $token, 'email' => $user->email, 'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect('/login');
        $this->get('/login')
            ->assertOk()
            ->assertSee('flash-message flash-success', false)
            ->assertSee('Senha redefinida');

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

    public function test_manager_can_filter_multiple_allowed_teams(): void
    {
        $fiscal = Team::create(['name' => 'Fiscal']);
        $support = Team::create(['name' => 'Suporte']);
        Team::create(['name' => 'TI']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach([$fiscal->id, $support->id]);
        $fiscalRequest = WorkRequest::create(['user_id' => User::factory()->create(['name' => 'Pessoa Fiscal', 'team' => 'Fiscal'])->id, 'work_date' => '2026-08-20']);
        $supportRequest = WorkRequest::create(['user_id' => User::factory()->create(['name' => 'Pessoa Suporte', 'team' => 'Suporte'])->id, 'work_date' => '2026-08-21']);
        WorkRequest::create(['user_id' => User::factory()->create(['name' => 'Pessoa TI', 'team' => 'TI'])->id, 'work_date' => '2026-08-22']);

        $response = $this->actingAs($manager)->get('/gestor?cycle=2026-08-20&teams[]=Fiscal&teams[]=Suporte');

        $response->assertOk()->assertSee('Pessoa Fiscal')->assertSee('Pessoa Suporte')->assertDontSee('Pessoa TI');
        $this->assertEqualsCanonicalizing([$fiscalRequest->id, $supportRequest->id], $response->viewData('records')->pluck('id')->all());
        $this->get('/gestor/exportar?cycle=2026-08-20&teams[]=Fiscal&teams[]=Suporte')->assertOk();
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
        $this->get('/health/ready')->assertOk()->assertJsonPath('database', 'ok')->assertHeader('Server-Timing');
    }

    public function test_super_admin_can_delegate_manager_teams_temporarily(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create(['role' => 'manager']);
        $delegate = User::factory()->create(['role' => 'manager']);
        $owner->managedTeams()->attach($team);
        $employee = User::factory()->create(['team' => 'Fiscal']);
        $record = WorkRequest::create(['user_id' => $employee->id, 'work_date' => today()]);

        $this->actingAs($admin)->post('/admin/delegacoes', [
            'manager_id' => $owner->id, 'delegate_id' => $delegate->id,
            'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDay()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($delegate)->get('/gestor')->assertOk()->assertSee($employee->name);
        $this->post("/gestor/solicitacoes/{$record->id}/analisar", ['decision' => 'approved', 'review_note' => 'Cobertura de férias.'])->assertRedirect();
        $this->assertDatabaseHas('work_requests', ['id' => $record->id, 'status' => 'approved', 'review_note' => 'Cobertura de férias.']);
        $this->assertDatabaseCount('manager_delegations', 1);
    }

    public function test_expired_delegation_does_not_grant_access(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $owner = User::factory()->create(['role' => 'manager']);
        $delegate = User::factory()->create(['role' => 'manager']);
        $owner->managedTeams()->attach($team);
        $record = WorkRequest::create(['user_id' => User::factory()->create(['team' => 'Fiscal'])->id, 'work_date' => today()]);
        ManagerDelegation::create([
            'manager_id' => $owner->id, 'delegate_id' => $delegate->id,
            'starts_on' => today()->subDays(3), 'ends_on' => today()->subDay(),
        ]);

        $this->actingAs($delegate)->post("/gestor/solicitacoes/{$record->id}/analisar", ['decision' => 'approved'])->assertForbidden();
    }

    public function test_manager_can_order_and_resize_filtered_results(): void
    {
        $team = Team::create(['name' => 'Fiscal']);
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->managedTeams()->attach($team);
        $employee = User::factory()->create(['team' => 'Fiscal']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-09-01']);
        WorkRequest::create(['user_id' => $employee->id, 'work_date' => '2026-09-02']);

        $records = $this->actingAs($manager)->get('/gestor?cycle=2026-08-20&sort=date_desc&per_page=50')->viewData('records');
        $this->assertSame(50, $records->perPage());
        $this->assertSame('2026-09-02', $records->first()->work_date->toDateString());
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
