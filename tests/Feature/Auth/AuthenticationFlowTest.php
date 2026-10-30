<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_authentication_pages(): void
    {
        $this->withoutVite();

        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
    }

    public function test_register_creates_an_unverified_account(): void
    {
        Event::fake([Registered::class]);

        $this->post('/register', [
            'name' => 'Demo Buyer',
            'email' => 'buyer@example.test',
            'password' => 'Excellent#Pass2026',
            'password_confirmation' => 'Excellent#Pass2026',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'buyer@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Excellent#Pass2026', $user->password));
        Event::assertDispatched(Registered::class);
    }

    public function test_unverified_account_cannot_open_customer_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/account')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_account_can_open_customer_dashboard(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')->assertOk();
    }

    public function test_login_and_logout_rotate_session(): void
    {
        $user = User::factory()->create(['password' => 'Excellent#Pass2026']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Excellent#Pass2026',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_failed_login_does_not_authenticate(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_insecure_registration_password_is_rejected(): void
    {
        $this->post('/register', [
            'name' => 'Demo Buyer',
            'email' => 'buyer@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }
}
