<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const INVALID_LINK = 'This password reset link is invalid or has expired.';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // The reset email is sent via defer(); run deferred callbacks immediately
        // so assertions can see the notification (documented testing helper).
        $this->withoutDefer();
    }

    private function resetWith(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/reset-password', array_merge([
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Forgot password
    // ---------------------------------------------------------------

    public function test_registered_email_receives_reset_link_to_the_web_app(): void
    {
        $user = User::factory()->create(['email' => 'jane+eggs@example.com']);

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email]);

        $response->assertOk()
            ->assertExactJson(['message' => 'Check your mailbox for a password reset link.']);

        Notification::assertSentTo($user, PasswordResetNotification::class, function ($notification, $channels) use ($user) {
            $this->assertSame(['mail'], $channels);

            $url = $notification->toMail($user)->actionUrl;
            $expectedPrefix = rtrim(config('app.site_url'), '/').'/reset-password?';
            $this->assertStringStartsWith($expectedPrefix, $url);

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($notification->token, $query['token']);
            $this->assertSame($user->email, $query['email']);
            $this->assertTrue(Password::broker()->tokenExists($user, $query['token']));

            return true;
        });
    }

    public function test_reset_email_matches_egg9_email_style(): void
    {
        $user = User::factory()->create(['name' => 'Jane']);

        $mail = (new PasswordResetNotification('abc'))->toMail($user);

        $this->assertSame('🔑 Reset Your Egg9 Password', $mail->subject);
        $this->assertSame('Hi Jane!', $mail->greeting);
        $this->assertSame('Reset Password', $mail->actionText);
        $this->assertContains('Thanks for being part of the Egg9 family! 🐣', $mail->outroLines);
    }

    public function test_reset_email_is_sent_even_when_email_notifications_are_disabled(): void
    {
        $user = User::factory()->create(['email_notifications_enabled' => false]);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_unknown_email_gets_identical_response_and_no_email(): void
    {
        $user = User::factory()->create();

        $known = $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);
        // Broker throttle (60 s per email) must not be distinguishable either.
        $throttled = $this->postJson('/api/forgot-password', ['email' => $user->email]);

        foreach ([$unknown, $throttled] as $response) {
            $this->assertSame($known->getStatusCode(), $response->getStatusCode());
            $this->assertSame($known->getContent(), $response->getContent());
        }

        Notification::assertSentTimes(PasswordResetNotification::class, 1);
    }

    public function test_send_failure_still_returns_generic_response_and_logs_without_details(): void
    {
        $user = User::factory()->create();
        Log::spy();
        Password::shouldReceive('sendResetLink')->once()->andThrow(new RuntimeException('smtp down for '.$user->email, 42));

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['message' => 'Check your mailbox for a password reset link.']);

        // Cause is logged by class + code only, never the message (may contain the recipient).
        Log::shouldHaveReceived('error')->once()->with('Password reset email failed', [
            'exception_class' => RuntimeException::class,
            'code' => 42,
        ]);
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/forgot-password', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/forgot-password', ['email' => "user{$i}@example.com"])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'user6@example.com'])->assertStatus(429);
    }

    // ---------------------------------------------------------------
    // Reset password
    // ---------------------------------------------------------------

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-secret')]);
        $user->createToken('api-token');
        $token = Password::createToken($user);

        $this->resetWith(['token' => $token, 'email' => $user->email])
            ->assertOk()
            ->assertExactJson(['message' => 'Password has been reset successfully.']);

        $this->assertSame(0, $user->tokens()->count(), 'All Sanctum tokens are revoked');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'old-secret'])
            ->assertStatus(422);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'new-secret'])
            ->assertOk();
    }

    public function test_invalid_expired_reused_and_unknown_resets_get_identical_error(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $wrongToken = $this->resetWith(['token' => 'wrong-token', 'email' => $user->email]);
        $unknownEmail = $this->resetWith(['token' => Password::createToken($other), 'email' => 'nobody@example.com']);

        $token = Password::createToken($user);
        $this->resetWith(['token' => $token, 'email' => $user->email])->assertOk();
        $reused = $this->resetWith(['token' => $token, 'email' => $user->email]);

        $expiredToken = Password::createToken($other);
        $this->travel(61)->minutes();
        $expired = $this->resetWith(['token' => $expiredToken, 'email' => $other->email]);

        $wrongToken->assertStatus(422)
            ->assertJsonValidationErrors(['token' => self::INVALID_LINK])
            ->assertJsonMissingValidationErrors(['email', 'password']);

        foreach ([$unknownEmail, $reused, $expired] as $response) {
            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame($wrongToken->getContent(), $response->getContent());
        }
    }

    public function test_reset_validates_the_new_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->resetWith([
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secret',
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->resetWith([
            'token' => $token,
            'email' => $user->email,
            'password' => '12345',
            'password_confirmation' => '12345',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // The token was not consumed by the failed attempts.
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_reset_password_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->resetWith(['token' => 'wrong', 'email' => 'nobody@example.com'])->assertStatus(422);
        }

        $this->resetWith(['token' => 'wrong', 'email' => 'nobody@example.com'])->assertStatus(429);
    }

    // ---------------------------------------------------------------
    // JSON errors without an Accept header (bootstrap/app.php)
    // ---------------------------------------------------------------

    public function test_api_validation_errors_are_json_without_accept_header(): void
    {
        $this->post('/api/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->post('/api/reset-password', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function test_protected_route_without_accept_header_returns_401_json(): void
    {
        $this->get('/api/user')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_confirm_pickup_link_still_renders_html_pages(): void
    {
        // Tampered / unsigned link -> custom branded HTML 403, not JSON.
        $this->get('/api/orders/confirm-pickup/1')
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Link expired');

        // Valid signature for a missing order -> controller's HTML 404 page.
        $url = URL::temporarySignedRoute('orders.confirm-pickup-email', now()->addHour(), ['order' => 999999]);
        $this->get($url)
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
