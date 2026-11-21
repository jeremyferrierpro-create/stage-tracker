<?php

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Internship\Models\InternshipParticipant;
use App\Domain\Authorization\EffectivePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * REQ-F-001 : un invité « lecture » (viewer) est rattaché aux stages
 * de l'espace en observateur — il peut lire mais pas créer.
 */
class ViewerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_is_attached_as_observer_and_can_read_but_not_write(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $internship = Internship::factory()->create([
            'workspace_id' => $workspace->id,
            'trainee_id' => User::factory()->create()->id,
        ]);

        $token = Str::random(48);
        Invitation::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'lecteur@example.test',
            'role' => 'viewer',
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(7),
            'created_by' => $owner->id,
        ]);

        // Parcours direct : invité sans compte → register via le lien.
        $this->post(route('invitations.register', $token), [
            'name' => 'Lecteur Test',
            'password' => 'motdepasse-tres-long',
            'password_confirmation' => 'motdepasse-tres-long',
        ])->assertRedirect(route('workspace.dashboard', $workspace));

        $viewer = User::query()->where('email', 'lecteur@example.test')->firstOrFail();

        // Rattaché en observateur sur le stage.
        $participant = InternshipParticipant::query()
            ->where('internship_id', $internship->id)
            ->where('user_id', $viewer->id)
            ->firstOrFail();
        $this->assertSame('observer', $participant->role);
        $this->assertSame('active', $participant->status);

        // Permissions effectives : lecture oui, écriture non.
        $permissions = EffectivePermissions::resolveForInternship($viewer, $workspace, $internship);
        $this->assertTrue($permissions['view_internship']);
        $this->assertTrue($permissions['view_activities']);
        $this->assertFalse($permissions['create_activity']);
        $this->assertFalse($permissions['deposit_evidence']);
        $this->assertFalse($permissions['manage_users']);

        // Le viewer voit le stage dans la liste.
        $this->actingAs($viewer)
            ->get(route('workspace.internships.index', $workspace))
            ->assertOk()
            ->assertSee($internship->title);
    }
}
