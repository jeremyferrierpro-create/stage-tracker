<?php

namespace Tests\Feature\Auth;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_password_change_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.password.edit'));
        $response->assertOk();
        $response->assertSee('Changer mon mot de passe');
    }

    public function test_user_can_change_password_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'ancien-mot-de-passe-123',
        ]);

        $response = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'ancien-mot-de-passe-123',
            'password' => 'nouveau-mot-de-passe-securise-2026',
            'password_confirmation' => 'nouveau-mot-de-passe-securise-2026',
        ]);

        $response->assertRedirect(route('account.password.edit'));
        $response->assertSessionHas('status', 'Mot de passe modifié.');

        $user->refresh();
        $this->assertTrue(Hash::check('nouveau-mot-de-passe-securise-2026', $user->getAuthPassword()));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_changed',
            'actor_id' => $user->id,
        ]);
    }

    public function test_password_change_fails_if_current_password_is_incorrect(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'vrai-mot-de-passe-123',
        ]);

        $response = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'faux-mot-de-passe-123',
            'password' => 'nouveau-mot-de-passe-securise-2026',
            'password_confirmation' => 'nouveau-mot-de-passe-securise-2026',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue(Hash::check('vrai-mot-de-passe-123', $user->getAuthPassword()));
    }

    public function test_password_change_fails_if_new_password_is_too_short(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'vrai-mot-de-passe-123',
        ]);

        $response = $this->actingAs($user)->post(route('account.password.update'), [
            'current_password' => 'vrai-mot-de-passe-123',
            'password' => 'court',
            'password_confirmation' => 'court',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_admin_console_command_resets_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'utilisateur@example.test',
            'password_hash' => 'mot-de-passe-avant-reset',
        ]);

        $this->artisan('app:reset-password', [
            'email' => 'utilisateur@example.test',
            '--password' => 'mot-de-passe-reinitialise-123',
        ])
            ->expectsOutputToContain('Mot de passe réinitialisé pour utilisateur@example.test.')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('mot-de-passe-reinitialise-123', $user->getAuthPassword()));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_reset',
        ]);

        $log = AuditLog::query()->where('action', 'user.password_reset')->firstOrFail();
        $this->assertSame(hash('sha256', 'utilisateur@example.test'), $log->metadata['email_hash'] ?? null);
    }

    public function test_admin_console_command_fails_for_unknown_user(): void
    {
        $this->artisan('app:reset-password', [
            'email' => 'inconnu@example.test',
        ])
            ->expectsOutputToContain('Aucun compte pour inconnu@example.test.')
            ->assertFailed();
    }
}
