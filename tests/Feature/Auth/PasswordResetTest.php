<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_notification_without_account_disclosure(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);

        $this->post('/forgot-password', ['email' => 'unknown@example.test'])
            ->assertSessionHas('status');
    }

    public function test_password_reset_uses_native_broker(): void
    {
        $user = User::factory()->create(['password' => 'Original#Pass2026']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Replacement#Pass2026',
            'password_confirmation' => 'Replacement#Pass2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Replacement#Pass2026', $user->fresh()->password));
    }
}
