<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function passwordConfirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    /** Enrol and confirm 2FA for a user, returning the plaintext secret. */
    private function enrol(User $user): string
    {
        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));

        $secret = decrypt($user->fresh()->two_factor_secret);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), [
                'code' => app(Google2FA::class)->getCurrentOtp($secret),
            ]);

        // Fortify remembers used code timestamps to block replays; tests
        // run inside one 30s TOTP window, so clear that guard to mimic a
        // later login with a fresh code.
        Cache::flush();

        return $secret;
    }

    public function test_two_factor_can_be_enabled_and_confirmed(): void
    {
        $user = User::factory()->create();

        $this->enrol($user);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, $user->recoveryCodes());
    }

    public function test_enrolment_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('two-factor.enable'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_confirming_with_an_invalid_code_fails(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors();

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_login_with_confirmed_two_factor_diverts_to_challenge(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $this->enrol($user);
        auth()->logout();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_login_without_two_factor_proceeds_directly(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unconfirmed_enrolment_does_not_divert_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        // Secret generated but never confirmed
        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));
        auth()->logout();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_accepts_a_valid_totp_code(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $secret = $this->enrol($user);
        auth()->logout();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.login.store'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $this->enrol($user);
        auth()->logout();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_challenge_accepts_a_recovery_code_once(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $this->enrol($user);
        $recoveryCode = $user->fresh()->recoveryCodes()[0];
        auth()->logout();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // The used code is rotated out
        $this->assertNotContains($recoveryCode, $user->fresh()->recoveryCodes());

        // And cannot be replayed
        auth()->logout();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode]);
        $this->assertGuest();
    }

    public function test_a_consumed_totp_code_cannot_be_replayed(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $secret = $this->enrol($user);
        auth()->logout();

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        // First use succeeds
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['code' => $code]);
        $this->assertAuthenticatedAs($user);

        // Replaying the same code on a fresh challenge fails
        auth()->logout();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['code' => $code]);
        $this->assertGuest();
    }

    public function test_two_factor_can_be_disabled(): void
    {
        $user = User::factory()->create();
        $this->enrol($user);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->delete(route('two-factor.disable'));

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        $user = User::factory()->create();
        $this->enrol($user);
        $original = $user->fresh()->recoveryCodes();

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->post(route('two-factor.regenerate-recovery-codes'));

        $fresh = $user->fresh()->recoveryCodes();
        $this->assertCount(8, $fresh);
        $this->assertEmpty(array_intersect($original, $fresh));
    }

    public function test_secrets_are_never_exposed_through_inertia_props(): void
    {
        $user = User::factory()->create();
        $this->enrol($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->missing('auth.user.two_factor_secret')
                ->missing('auth.user.two_factor_recovery_codes'));
    }

    public function test_qr_code_and_secret_endpoints_serve_enrolment_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->post(route('two-factor.enable'));

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->getJson(route('two-factor.qr-code'))
            ->assertOk()
            ->assertJsonStructure(['svg', 'url']);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmed())
            ->getJson(route('two-factor.secret-key'))
            ->assertOk()
            ->assertJsonStructure(['secretKey']);
    }
}
