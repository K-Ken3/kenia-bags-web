<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminMfaService;
use Illuminate\Support\Facades\DB;

class AdminMfaTest extends StoreTestCase
{
    public function test_password_login_does_not_grant_admin_access_before_mfa_setup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/mfa/setup');

        $this->assertAuthenticatedAs($admin);
        $response->assertSessionMissing('admin_mfa_verified_at');
        $this->get('/admin')->assertRedirect('/admin/mfa/setup');
    }

    public function test_admin_can_enroll_and_confirm_totp(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/mfa/setup');

        $setup = $this->get('/admin/mfa/setup')->assertOk();
        $this->assertStringContainsString('AdminMfaSetup', $setup->getContent());
        $this->assertStringContainsString(
            'data:image/svg+xml;base64',
            str_replace('\\/', '/', $setup->getContent()),
        );

        $secret = $admin->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);

        $code = app(AdminMfaService::class)->currentCode($secret);
        $this->post('/admin/mfa/setup', ['code' => $code])
            ->assertRedirect('/admin/security')
            ->assertSessionHas('recovery_codes');

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.mfa_enabled', 'user_id' => $admin->id]);

        $security = $this->get('/admin/security')->assertOk();
        $this->assertStringContainsString('"recoveryCodeCount":8', $security->getContent());
        $this->assertStringContainsString('"recoveryCodes":["', $security->getContent());
        $this->get('/admin')->assertOk();
    }

    public function test_invalid_enrollment_code_does_not_activate_mfa(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password']);
        $this->get('/admin/mfa/setup')->assertOk();

        $secret = $admin->fresh()->two_factor_secret;
        $this->post('/admin/mfa/setup', ['code' => $this->invalidCode($secret)])
            ->assertSessionHasErrors('code');

        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
        $this->get('/admin')->assertRedirect('/admin/mfa/setup');
    }

    public function test_confirmed_admin_must_pass_the_mfa_challenge_after_login(): void
    {
        $admin = $this->createVerifiedAdmin();
        $code = app(AdminMfaService::class)->currentCode($admin->two_factor_secret);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/mfa/challenge');
        $this->get('/admin')->assertRedirect('/admin/mfa/challenge');

        $this->post('/admin/mfa/verify', ['code' => $code])
            ->assertRedirect('/admin');

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.mfa_verified', 'user_id' => $admin->id]);

        $this->withSession([
            'admin_mfa_verified_at' => now()->getTimestamp(),
            'admin_login_at' => now()->getTimestamp(),
            'admin_last_activity' => now()->getTimestamp(),
        ])->get('/admin')->assertOk();
    }

    public function test_invalid_mfa_codes_are_rate_limited(): void
    {
        $admin = $this->createVerifiedAdmin();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/mfa/challenge');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/mfa/verify', ['code' => 'invalid'])
                ->assertSessionHasErrors('code');
        }

        $this->post('/admin/mfa/verify', ['code' => 'invalid'])->assertStatus(429);
        $this->get('/admin')->assertRedirect('/admin/mfa/challenge');
    }

    public function test_recovery_code_is_single_use(): void
    {
        [$admin, $codes] = $this->adminWithRecoveryCodes();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/mfa/challenge');
        $this->post('/admin/mfa/verify', ['recovery_code' => $codes[0]])
            ->assertRedirect('/admin');
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/mfa/challenge');
        $this->post('/admin/mfa/verify', ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect('/admin/mfa/challenge');
    }

    public function test_recovery_code_regeneration_requires_password_and_invalidates_old_codes(): void
    {
        [$admin, $codes] = $this->adminWithRecoveryCodes();

        $this->actingAsVerifiedAdmin($admin)
            ->post('/admin/mfa/recovery-codes', ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');

        $this->post('/admin/mfa/recovery-codes', ['current_password' => 'password'])
            ->assertRedirect('/admin/security')
            ->assertSessionHas('recovery_codes');

        $service = app(AdminMfaService::class);
        $this->assertFalse($service->consumeRecoveryCode($admin->fresh(), $codes[0]));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.recovery_codes_regenerated', 'user_id' => $admin->id]);
    }

    public function test_security_changes_revoke_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        [$admin] = $this->adminWithRecoveryCodes();

        DB::table('sessions')->insert([
            'id' => 'other-session-id',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAsVerifiedAdmin($admin)
            ->post('/admin/mfa/recovery-codes', ['current_password' => 'password'])
            ->assertRedirect('/admin/security');

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
    }

    public function test_mfa_secrets_and_recovery_codes_are_encrypted_at_rest_and_hidden(): void
    {
        [$admin, $codes] = $this->adminWithRecoveryCodes();
        $raw = DB::table('users')->where('id', $admin->id)->first();

        $this->assertNotSame($admin->two_factor_secret, $raw->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], (string) $raw->two_factor_recovery_codes);
        $this->assertArrayNotHasKey('two_factor_secret', $admin->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $admin->toArray());
    }

    public function test_totp_code_replay_is_rejected(): void
    {
        $admin = $this->createVerifiedAdmin();
        $code = app(AdminMfaService::class)->currentCode($admin->two_factor_secret);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/admin/mfa/verify', ['code' => $code])->assertRedirect('/admin');
        $this->post('/admin/logout');

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/mfa/challenge');
        $this->post('/admin/mfa/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect('/admin/mfa/challenge');
    }

    public function test_non_admins_cannot_access_mfa_endpoints(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin/mfa/setup')->assertForbidden();
        $this->actingAs($customer)->get('/admin/mfa/challenge')->assertForbidden();
        $this->actingAs($customer)->post('/admin/mfa/verify', ['code' => '000000'])->assertForbidden();
    }

    public function test_alternative_login_routes_are_not_exposed_and_customers_cannot_admin_login(): void
    {
        $this->get('/login')->assertNotFound();
        $this->get('/two-factor-challenge')->assertNotFound();
        $this->get('/user/two-factor-authentication')->assertNotFound();

        $customer = User::factory()->create(['role' => 'customer']);
        $this->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function adminWithRecoveryCodes(): array
    {
        $admin = $this->createVerifiedAdmin();
        $service = app(AdminMfaService::class);
        $codes = $service->generateRecoveryCodes();
        $admin->forceFill([
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($codes),
        ])->save();

        return [$admin, $codes];
    }

    private function invalidCode(string $secret): string
    {
        $valid = (int) app(AdminMfaService::class)->currentCode($secret);

        return str_pad((string) (($valid + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
