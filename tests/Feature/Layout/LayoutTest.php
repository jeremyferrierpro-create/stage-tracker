<?php

namespace Tests\Feature\Layout;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
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

    public function test_workspace_layout_provides_skip_link_sidebar_and_role_badge(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);

        $response = $this->actingAs($owner)
            ->get(route('workspace.dashboard', $workspace))
            ->assertOk();

        $this->assertStringContainsString('html lang="fr"', (string) $response->getContent());
        $response
            ->assertSee('Aller au contenu')
            ->assertSee("Sections de l'espace", false)
            ->assertSee('Espace actif')
            ->assertSee('Tableau de bord')
            ->assertSee('Stages')
            ->assertSee('Membres et invitations')
            ->assertSee("Journal d'audit", false)
            ->assertSee('Propriétaire')
            ->assertSee($workspace->name)
            ->assertSee("Fil d'Ariane", false);
    }

    public function test_trainee_sidebar_hides_administration_links(): void
    {
        $trainee = User::factory()->create();
        $workspace = $this->workspaceWithMember($trainee, 'trainee');

        $this->actingAs($trainee)
            ->get(route('workspace.dashboard', $workspace))
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Stagiaire')
            ->assertDontSee('Membres et invitations', false)
            ->assertDontSee("Journal d'audit", false);
    }

    public function test_workspace_switcher_lists_other_workspaces(): void
    {
        $member = User::factory()->create();
        $first = $this->workspaceWithMember($member, 'owner');
        $second = Workspace::factory()->create(['owner_id' => $member->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $second->id,
            'user_id' => $member->id,
            'role' => 'trainee',
        ]);

        $this->actingAs($member)
            ->get(route('workspace.dashboard', $first))
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee($second->name)
            ->assertSee('Stagiaire')
            ->assertSee('Voir tous les espaces');
    }

    public function test_modules_sections_are_not_links_until_delivered(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);

        $response = $this->actingAs($owner)
            ->get(route('workspace.dashboard', $workspace))
            ->assertOk();

        $content = strtolower((string) $response->getContent());

        $this->assertStringContainsString('à venir', $content);
        foreach (['preuves', 'rapports'] as $section) {
            $this->assertStringNotContainsString(">{$section}</a>", (string) $content);
        }
    }

    public function test_guest_pages_have_no_workspace_navigation(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Navigation principale')
            ->assertDontSee('Tableau de bord')
            ->assertDontSee("Sections de l'espace", false);
    }

    public function test_authenticated_pages_outside_workspace_have_no_sidebar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertDontSee("Sections de l'espace", false);
    }

    public function test_competency_pages_show_breadcrumb(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithMember($owner);

        $this->actingAs($owner)
            ->get(route('workspace.competencies.index', $workspace))
            ->assertOk()
            ->assertSee("Fil d'Ariane", false)
            ->assertSee('Compétences');
    }
}
