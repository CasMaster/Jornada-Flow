<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeployApprovalTest extends TestCase
{
    use RefreshDatabase;

    private string $pendingDir;

    private string $approvedDir;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir().'/mixhome-deploy-'.bin2hex(random_bytes(8));
        mkdir($base);
        $this->pendingDir = $base.'/pending';
        $this->approvedDir = $base.'/approved';
        mkdir($this->pendingDir);
        mkdir($this->approvedDir);
        config()->set('deploy_approval.environment', 'homologacao');
        config()->set('deploy_approval.key', str_repeat('a', 32));
        config()->set('deploy_approval.pending_dir', $this->pendingDir);
        config()->set('deploy_approval.approved_dir', $this->approvedDir);
    }

    protected function tearDown(): void
    {
        foreach ([$this->pendingDir, $this->approvedDir] as $directory) {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
        rmdir(dirname($this->pendingDir));
        parent::tearDown();
    }

    public function test_only_super_admin_can_see_or_approve_package(): void
    {
        $digest = str_repeat('b', 64);
        file_put_contents($this->pendingDir.'/homologacao-'.$digest, (string) (time() + 900));
        $manager = User::factory()->create(['role' => 'manager']);

        $this->get(route('admin.deploy.index'))->assertRedirect(route('login'));
        $this->actingAs($manager)->get(route('admin.deploy.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.deploy.approve', $digest), ['password' => 'password'])->assertForbidden();
        $inactive = User::factory()->create(['role' => 'super_admin', 'active' => false, 'password' => 'secret-password']);
        $this->actingAs($inactive)->post(route('admin.deploy.approve', $digest), ['password' => 'secret-password'])->assertSessionHasErrors('password');
        $this->assertFileDoesNotExist($this->approvedDir.'/homologacao-'.$digest);
    }

    public function test_approval_requires_current_password_and_exact_pending_digest(): void
    {
        $digest = str_repeat('b', 64);
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true, 'password' => Hash::make('secret-password')]);
        file_put_contents($this->pendingDir.'/homologacao-'.$digest, (string) (time() + 900));

        $this->actingAs($admin)->get(route('admin.deploy.index'))->assertOk()->assertSee($digest)->assertSee('aria-current="page"', false)->assertSee('>Deploy</a>', false);
        $this->post(route('admin.deploy.approve', $digest), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertFileDoesNotExist($this->approvedDir.'/homologacao-'.$digest);
        $this->post(route('admin.deploy.approve', str_repeat('c', 64)), ['password' => 'secret-password'])->assertStatus(409);
        $this->post(route('admin.deploy.approve', $digest), ['password' => 'secret-password'])->assertRedirect(route('admin.deploy.index'));

        $approval = json_decode(file_get_contents($this->approvedDir.'/homologacao-'.$digest), true);
        $expected = hash_hmac('sha256', 'homologacao:'.$digest.':'.$approval['expires'].':'.$admin->id, str_repeat('a', 32));
        $this->assertSame($expected, $approval['signature']);
        $this->assertSame($admin->id, $approval['actor']);
        $this->assertLessThanOrEqual(time() + 300, $approval['expires']);
        $this->assertSame(1, AuditLog::where('event', 'deployment.approved')->count());
    }

    public function test_expired_package_and_unconfigured_environment_cannot_be_approved(): void
    {
        $digest = str_repeat('d', 64);
        $admin = User::factory()->create(['role' => 'super_admin', 'active' => true, 'password' => Hash::make('secret-password')]);
        file_put_contents($this->pendingDir.'/homologacao-'.$digest, (string) (time() - 1));
        $this->actingAs($admin)->post(route('admin.deploy.approve', $digest), ['password' => 'secret-password'])->assertStatus(409);

        config()->set('deploy_approval.key', '');
        $this->post(route('admin.deploy.approve', $digest), ['password' => 'secret-password'])->assertNotFound();
        $this->assertFileDoesNotExist($this->approvedDir.'/homologacao-'.$digest);
    }
}
