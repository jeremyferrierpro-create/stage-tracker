<?php

namespace Tests\Feature\Database;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Organization\Models\Organization;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_the_neutral_demo_dataset(): void
    {
        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertSame(4, User::count());
        $this->assertSame(1, Workspace::count());
        $this->assertSame(4, WorkspaceMember::count());
        $this->assertSame(2, Organization::count());

        $workspace = Workspace::query()->sole();
        $this->assertSame('Espace Démo', $workspace->name);
        $this->assertSame(0, $workspace->invitations()->count());
        $this->assertSame(0, AuditLog::count());

        foreach (['trainee', 'supervisor', 'tutor'] as $role) {
            $this->assertSame(1, $workspace->members()->where('role', $role)->count());
        }

        foreach ($workspace->organizations()->get() as $organization) {
            $this->assertStringEndsWith('.test', (string) $organization->contact_email);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->artisan('db:seed')->assertExitCode(0);

        $users = User::count();
        $organizations = Organization::count();

        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertSame($users, User::count());
        $this->assertSame($organizations, Organization::count());
    }

    public function test_demo_credentials_are_usable(): void
    {
        $this->artisan('db:seed')->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@example.test')->sole();

        $this->assertTrue($admin->isActive());
        $this->assertTrue(Hash::check('mot-de-passe-demo-2026', $admin->getAuthPassword()));
        $this->assertSame('owner', $admin->workspaces()->first()->pivot->role);
    }

    public function test_seeder_refuses_outside_local_and_test(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->expectException(RuntimeException::class);

        (new DatabaseSeeder)->setContainer($this->app)->run();
    }
}
