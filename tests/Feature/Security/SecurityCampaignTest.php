<?php

namespace Tests\Feature\Security;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Campagne de sécurité (T-070) : couverture HTTP des tests
 * obligatoires de SECURITY §6 dont la preuve n'existe pas encore
 * sous forme de test automatisé.
 *
 * S-01, S-03, S-04, S-05, S-07 et S-08 sont couverts par :
 * MultiTenantCampaignTest / WorkspaceIsolationTest (S-01),
 * EvidenceTest (S-03), RepositoryTest (S-04),
 * ReportTest::test_mt11 + ReportExportTest (S-05),
 * LoginTest (S-07), CommentTest (S-08).
 */
class SecurityCampaignTest extends TestCase
{
    use RefreshDatabase;

    /**
     * S-02 : un workspace_id forgé dans l'entrée est ignoré — la ressource
     * créée appartient toujours au workspace du contexte (route binding),
     * jamais à celui fourni par le client.
     */
    public function test_s02_workspace_id_in_input_is_ignored(): void
    {
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $admin->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => 'owner',
        ]);

        // Espace étranger que le client tente d'injecter dans la requête.
        $otherWorkspace = Workspace::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('workspace.internships.create', $workspace))
            ->post(route('workspace.internships.store', $workspace), [
                'title' => 'Injection cross-workspace',
                'trainee_id' => $admin->id,
                'company_name' => 'Entreprise Test',
                'start_date' => '2026-11-01',
                'end_date' => '2027-03-31',
                'status' => 'active',
                // Tentative d'injection :
                'workspace_id' => $otherWorkspace->id,
            ]);

        $response->assertRedirect();

        $internship = Internship::query()->where('title', 'Injection cross-workspace')->sole();

        $this->assertSame($workspace->id, $internship->workspace_id);
        $this->assertNotSame($otherWorkspace->id, $internship->workspace_id);
    }
}
