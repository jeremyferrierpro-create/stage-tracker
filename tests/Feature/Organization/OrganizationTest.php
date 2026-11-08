<?php

namespace Tests\Feature\Organization;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWith(User $user, string $role = 'owner'): Workspace
    {
        $owner = $role === 'owner' ? $user : User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);

        return $workspace;
    }

    public function test_owner_can_create_a_company_and_a_training_center(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        $this->actingAs($owner)
            ->post(route('workspace.organizations.store', $workspace), [
                'name' => 'Entreprise Accueil',
                'type' => 'company',
                'contact_email' => 'contact@entreprise.test',
                'contact_phone' => '0102030405',
                'address' => '1 rue de l\'Exemple',
            ])
            ->assertRedirect(route('workspace.organizations.index', $workspace));

        $this->actingAs($owner)
            ->post(route('workspace.organizations.store', $workspace), [
                'name' => 'Centre de Formation',
                'type' => 'training_center',
            ])
            ->assertRedirect(route('workspace.organizations.index', $workspace));

        $this->assertDatabaseHas('organizations', [
            'workspace_id' => $workspace->id,
            'name' => 'Entreprise Accueil',
            'type' => 'company',
        ]);
        $this->assertDatabaseHas('organizations', [
            'workspace_id' => $workspace->id,
            'name' => 'Centre de Formation',
            'type' => 'training_center',
        ]);

        $this->actingAs($owner)
            ->get(route('workspace.organizations.index', $workspace))
            ->assertOk()
            ->assertSee('Entreprise Accueil')
            ->assertSee('Entreprise d\'accueil')
            ->assertSee('Centre de Formation')
            ->assertSee('Organisme de formation');

        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.created']);
        $this->assertSame(2, Organization::where('workspace_id', $workspace->id)->count());
    }

    public function test_member_can_view_organizations_but_cannot_manage_them(): void
    {
        $trainee = User::factory()->create();
        $workspace = $this->workspaceWith($trainee, 'trainee');
        Organization::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Organisation visible',
        ]);

        $this->actingAs($trainee)
            ->get(route('workspace.organizations.index', $workspace))
            ->assertOk()
            ->assertSee('Organisation visible');

        $this->actingAs($trainee)
            ->get(route('workspace.organizations.create', $workspace))
            ->assertForbidden();

        $this->actingAs($trainee)
            ->post(route('workspace.organizations.store', $workspace), [
                'name' => 'Interdite',
                'type' => 'company',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('organizations', ['name' => 'Interdite']);
    }

    public function test_organization_validation_rejects_missing_name_and_invalid_type(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);

        $this->actingAs($owner)
            ->post(route('workspace.organizations.store', $workspace), [
                'name' => '',
                'type' => 'unknown',
            ])
            ->assertSessionHasErrors(['name', 'type']);

        $this->assertSame(0, Organization::where('workspace_id', $workspace->id)->count());
    }

    public function test_organization_can_be_updated(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $organization = Organization::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Ancien nom',
            'type' => 'company',
        ]);

        $this->actingAs($owner)
            ->put(route('workspace.organizations.update', [$workspace, $organization]), [
                'name' => 'Nouveau nom',
                'type' => 'association',
                'contact_email' => 'nouveau@example.test',
                'contact_phone' => '0600000000',
                'address' => '2 rue Neuve',
            ])
            ->assertRedirect(route('workspace.organizations.index', $workspace));

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Nouveau nom',
            'type' => 'association',
            'contact_email' => 'nouveau@example.test',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.updated']);
    }

    public function test_organization_is_archived_not_deleted(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWith($owner);
        $organization = Organization::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'À archiver',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.organizations.archive', [$workspace, $organization]))
            ->assertRedirect(route('workspace.organizations.index', $workspace));

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
        $this->assertNotNull($organization->fresh()->archived_at);
        $this->assertSame(0, $workspace->organizations()->active()->count());
        $this->assertSame(1, $workspace->organizations()->archived()->count());

        $this->actingAs($owner)
            ->get(route('workspace.organizations.index', $workspace))
            ->assertOk()
            ->assertSee('À archiver')
            ->assertSee('Organisations archivées');

        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.archived']);
    }

    public function test_organization_from_another_workspace_is_not_accessible(): void
    {
        $alice = User::factory()->create();
        $workspaceA = $this->workspaceWith($alice);

        $bob = User::factory()->create();
        $workspaceB = $this->workspaceWith($bob);

        $organizationB = Organization::factory()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Organisation secrète B',
        ]);

        $this->actingAs($alice)
            ->get(route('workspace.organizations.edit', [$workspaceA, $organizationB]))
            ->assertNotFound();

        $this->actingAs($alice)
            ->put(route('workspace.organizations.update', [$workspaceA, $organizationB]), [
                'name' => 'Tentative',
                'type' => 'company',
            ])
            ->assertNotFound();

        $this->actingAs($alice)
            ->post(route('workspace.organizations.archive', [$workspaceA, $organizationB]))
            ->assertNotFound();

        $this->actingAs($alice)
            ->get(route('workspace.organizations.index', $workspaceA))
            ->assertOk()
            ->assertDontSee('Organisation secrète B');

        $this->assertNull($organizationB->fresh()->archived_at);
        $this->assertSame('Organisation secrète B', $organizationB->fresh()->name);
    }
}
