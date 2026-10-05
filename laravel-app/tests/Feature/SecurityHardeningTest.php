<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_is_logged_out_on_the_next_authenticated_request(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->actingAs($user)
            ->get(route('employee.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_session_issued_before_deactivation_stays_revoked_after_reactivation(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'auth_version' => 1,
        ]);

        $this->actingAs($user)
            ->withSession(['authenticated_version' => 0])
            ->get(route('employee.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_deactivation_rotates_remember_token_without_deleting_retained_session_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create(['remember_token' => Str::random(60)]);
        $this->assertTrue($user->active);
        $oldToken = $user->remember_token;
        DB::table('sessions')->insert([
            'id' => 'retained-security-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'security-test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->active);
        $this->assertNotSame($oldToken, $user->remember_token);
        $this->assertSame(1, $user->auth_version);
        $this->assertDatabaseHas('sessions', ['id' => 'retained-security-session', 'user_id' => $user->id]);
    }

    public function test_work_request_rejects_more_than_the_supported_number_of_dates(): void
    {
        $user = User::factory()->create();
        $dates = collect(range(1, WorkRequestService::MAX_DATES_PER_REQUEST + 1))
            ->map(fn (int $day): string => now()->addDays($day)->toDateString())
            ->all();

        $this->actingAs($user)
            ->post(route('employee.requests.store'), ['dates' => $dates])
            ->assertSessionHasErrors('dates');

        $this->assertDatabaseCount('work_requests', 0);
    }

    public function test_domain_service_also_rejects_an_oversized_date_batch(): void
    {
        $user = User::factory()->create();
        $dates = collect(range(1, WorkRequestService::MAX_DATES_PER_REQUEST + 1))
            ->map(fn (int $day): string => now()->addDays($day)->toDateString())
            ->all();

        $this->expectException(ValidationException::class);
        app(WorkRequestService::class)->createMany($user, $dates);
    }

    public function test_work_request_route_is_rate_limited_per_user(): void
    {
        $user = User::factory()->create();
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($user)
                ->post(route('employee.requests.store'), ['dates' => [now()->addMonths(2)->addDays($attempt)->toDateString()]])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post(route('employee.requests.store'), ['dates' => [now()->addMonths(3)->toDateString()]])
            ->assertTooManyRequests();
    }
}
