<?php

namespace Tests\Feature\Console;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user_command_creates_an_active_account(): void
    {
        $this->artisan('app:create-user', [
            'email' => 'Admin@Example.TEST',
            '--name' => 'Admin Test',
            '--password' => 'mot-de-passe-solide',
        ])->assertExitCode(0);

        $user = User::where('email', 'admin@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('mot-de-passe-solide', $user->password_hash));
    }

    public function test_create_workspace_command_creates_owner_membership(): void
    {
        $owner = User::factory()->create();

        $this->artisan('app:create-workspace', [
            'name' => 'Espace test',
            '--owner' => $owner->email,
            '--type' => 'pedagogical',
        ])->assertExitCode(0);

        $workspace = Workspace::where('name', 'Espace test')->first();

        $this->assertNotNull($workspace);
        $this->assertSame($owner->id, $workspace->owner_id);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_create_user_command_rejects_short_password(): void
    {
        $this->artisan('app:create-user', [
            'email' => 'court@example.test',
            '--name' => 'Mot de passe court',
            '--password' => 'trop-court',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'court@example.test']);
    }
}
