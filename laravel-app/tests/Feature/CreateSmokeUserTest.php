<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateSmokeUserTest extends TestCase
{
    use RefreshDatabase;

    public static function environments(): array
    {
        return [['producao', ''], ['homologacao', 'homologacao']];
    }

    #[DataProvider('environments')]
    public function test_provisions_synthetic_employee_without_business_data_or_secrets_in_audit(string $environment, string $prefix): void
    {
        config(['app.route_prefix' => $prefix, 'auth.password_recovery_enabled' => false]);
        Notification::fake();
        $password = bin2hex(random_bytes(16)).'Aa!9';

        $this->artisan('hibrido:create-smoke-user', ['environment' => $environment])
            ->expectsConfirmation('Confirma o container e o banco deste ambiente?', 'yes')
            ->expectsQuestion('Senha exclusiva do smoke test', $password)
            ->expectsQuestion('Confirme a senha', $password)
            ->assertSuccessful();

        $user = User::sole();
        $this->assertSame("smoke-{$environment}@mixhome.invalid", $user->email);
        $this->assertSame("Smoke Test Sintético - {$environment}", $user->name);
        $this->assertSame('employee', $user->role);
        $this->assertSame('', $user->team);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check($password, $user->password));
        foreach (['teams', 'manager_team', 'work_requests', 'notifications', 'password_reset_tokens'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $audit = AuditLog::sole();
        $this->assertSame('smoke_user.created', $audit->event);
        $this->assertSame($user->id, $audit->auditable_id);
        $this->assertSame(['environment' => $environment, 'role' => 'employee'], $audit->new_values);
        $this->assertStringNotContainsString($password, $audit->toJson());
        $this->assertStringNotContainsString($user->password, $audit->toJson());
        Notification::assertNothingSent();

        $this->get('/esqueci-a-senha')->assertNotFound();
        $this->post('/esqueci-a-senha', ['email' => $user->email])->assertNotFound();
        $this->post('/login', ['email' => $user->email, 'password' => $password, 'profile' => 'employee'])
            ->assertRedirect('/painel');
        $this->assertAuthenticatedAs($user);
        $this->get('/painel')->assertOk();
        $this->get('/gestor')->assertForbidden();
        $this->get('/admin/usuarios')->assertForbidden();
    }

    public function test_existing_account_is_never_overwritten_or_reactivated(): void
    {
        $user = User::factory()->create([
            'name' => 'Conta Sintética Existente', 'email' => 'SMOKE-PRODUCAO@mixhome.invalid', 'role' => 'super_admin', 'active' => false,
        ]);
        $before = $user->fresh()->getAttributes();
        $this->artisan('hibrido:create-smoke-user', ['environment' => 'producao'])->assertFailed();
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_environment_mismatch_and_noninteractive_execution_are_rejected(): void
    {
        foreach ([['environment' => 'invalid'], ['environment' => 'homologacao'], ['environment' => 'producao', '--no-interaction' => true]] as $arguments) {
            $this->artisan('hibrido:create-smoke-user', $arguments)->assertFailed();
        }
        config(['app.route_prefix' => 'homologacao']);
        $this->artisan('hibrido:create-smoke-user', ['environment' => 'producao'])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_declining_confirmation_does_not_create_user(): void
    {
        $this->artisan('hibrido:create-smoke-user', ['environment' => 'producao'])
            ->expectsConfirmation('Confirma o container e o banco deste ambiente?', 'no')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_weak_or_mismatched_passwords_are_rejected(): void
    {
        $strong = bin2hex(random_bytes(16)).'Aa!9';
        foreach ([['', ''], ['short', 'short'], [str_repeat('a', 24), str_repeat('a', 24)], [$strong, $strong.'x'], [str_repeat('Aa1!', 19), str_repeat('Aa1!', 19)]] as [$password, $confirmation]) {
            $this->artisan('hibrido:create-smoke-user', ['environment' => 'producao'])
                ->expectsConfirmation('Confirma o container e o banco deste ambiente?', 'yes')
                ->expectsQuestion('Senha exclusiva do smoke test', $password)
                ->expectsQuestion('Confirme a senha', $confirmation)
                ->assertFailed();
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_account_creation(): void
    {
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andReturnUsing(function () {
            DB::table('nonexistent_smoke_test_table')->insert(['id' => 1]);
        });
        $password = bin2hex(random_bytes(16)).'Aa!9';
        $this->artisan('hibrido:create-smoke-user', ['environment' => 'producao'])
            ->expectsConfirmation('Confirma o container e o banco deste ambiente?', 'yes')
            ->expectsQuestion('Senha exclusiva do smoke test', $password)
            ->expectsQuestion('Confirme a senha', $password)
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
