<?php

namespace Tests\Feature\Help;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_help_page(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('help'));

        $response->assertOk();
        $response->assertSee('Titre Professionnel Développeur Web et Web Mobile');
        $response->assertSee('Mode d\'emploi', false);
        $response->assertSee('CCP 1');
        $response->assertSee('CCP 2');
        $response->assertSee('C1');
        $response->assertSee('C6');
        $response->assertSee('Dossier Professionnel');
    }
}
