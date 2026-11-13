<?php

namespace Tests\Feature\Journal;

use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLogTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithMember(User $user, string $role = 'owner'): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $workspace;
    }

    public function test_owner_creates_a_daily_log(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $version = FrameworkVersion::factory()->published()->create();
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'host_organization_id' => $organization->id,
            'framework_version_id' => $version->id,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.internships.daily_logs.store', [$workspace, $internship]), [
                'work_date' => '2026-10-01',
                'work_done' => 'Travaux du jour',
                'duration_minutes' => 480,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('daily_logs', [
            'internship_id' => $internship->id,
            'work_date' => '2026-10-01',
            'status' => 'draft',
        ]);
    }

    public function test_daily_log_is_unique_per_internship_author_date(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $version = FrameworkVersion::factory()->published()->create();
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'host_organization_id' => $organization->id,
            'framework_version_id' => $version->id,
        ]);

        DailyLog::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $owner->id,
            'work_date' => '2026-10-01',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.internships.daily_logs.store', [$workspace, $internship]), [
                'work_date' => '2026-10-01',
            ])
            ->assertSessionHasErrors('work_date');
    }

    public function test_closed_daily_log_is_read_only(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $version = FrameworkVersion::factory()->published()->create();
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'host_organization_id' => $organization->id,
            'framework_version_id' => $version->id,
        ]);

        $log = DailyLog::factory()->closed()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $owner->id,
            'work_date' => '2026-10-01',
        ]);

        $this->actingAs($owner)
            ->get(route('workspace.internships.daily_logs.edit', [$workspace, $internship, $log]))
            ->assertForbidden();
    }

    public function test_closing_a_daily_log_sets_closed_at(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);
        $organization = Organization::factory()->create(['workspace_id' => $workspace->id]);
        $version = FrameworkVersion::factory()->published()->create();
        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => $trainee->id,
            'host_organization_id' => $organization->id,
            'framework_version_id' => $version->id,
        ]);

        $log = DailyLog::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
            'author_id' => $owner->id,
            'work_date' => '2026-10-01',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.internships.daily_logs.close', [$workspace, $internship, $log]))
            ->assertRedirect();

        $log->refresh();
        $this->assertTrue($log->isClosed());
        $this->assertNotNull($log->closed_at);
    }
}
