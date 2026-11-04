<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed_to_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Connexion');
    }

    public function test_active_user_can_login_and_last_login_is_recorded(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        $response = $this->post('/login', [
            'email' => strtoupper($user->email),
            'password' => 'correct-horse-battery',
        ]);

        $response->assertRedirect(route('workspaces.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_passwords_are_hashed_with_argon2id(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        $this->assertStringStartsWith('$argon2id$', $user->password_hash);
    }

    public function test_invalid_password_is_rejected_with_a_neutral_message(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Identifiants incorrects ou compte désactivé.',
            ]);

        $this->assertGuest();
    }

    public function test_disabled_account_cannot_login_and_history_is_kept(): void
    {
        $user = User::factory()->disabled()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'correct-horse-battery',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'disabled',
        ]);
    }

    public function test_unknown_email_returns_the_same_neutral_message(): void
    {
        $this->from('/login')
            ->post('/login', [
                'email' => 'inconnu@example.test',
                'password' => 'whatever-password',
            ])
            ->assertSessionHasErrors([
                'email' => 'Identifiants incorrects ou compte désactivé.',
            ]);

        $this->assertGuest();
    }

    public function test_throttling_returns_429_after_five_failures(): void
    {
        $user = User::factory()->create([
            'password_hash' => 'correct-horse-battery',
        ]);

        $this->from('/login');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertRedirect('/login');
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
