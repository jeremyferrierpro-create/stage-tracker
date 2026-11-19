<?php

namespace Tests\Feature\Auth;

use App\Domain\Evaluation\Models\Competency;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Reporting\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_registration_form(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Créer mon espace Stagiaire');
        $response->assertSee('compétences officielles');
    }

    public function test_trainee_can_register_and_automatically_gets_dwwm_workspace_and_competencies(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Camille Dupont',
            'email' => 'camille.dupont@example.test',
            'password' => 'mot-de-passe-ultra-securise-2026',
            'password_confirmation' => 'mot-de-passe-ultra-securise-2026',
        ]);

        $user = User::query()->where('email', 'camille.dupont@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('Camille Dupont', $user->name);
        $this->assertSame('active', $user->status);

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());

        $workspace = Workspace::query()->where('owner_id', $user->id)->first();
        $this->assertNotNull($workspace);
        $this->assertStringContainsString('Camille Dupont', $workspace->name);
        $this->assertSame('pedagogical', $workspace->type);

        $response->assertRedirect(route('workspace.dashboard', $workspace));

        // Vérification des 6 compétences officielles DWWM (C1 à C6)
        $competencies = Competency::query()->where('workspace_id', $workspace->id)->orderBy('code')->get();
        $this->assertCount(6, $competencies);
        $this->assertSame(['C1', 'C2', 'C3', 'C4', 'C5', 'C6'], $competencies->pluck('code')->all());

        // Vérification du stage initialisé
        $internship = Internship::query()->where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($internship);
        $this->assertSame($user->id, $internship->trainee_id);
        $this->assertSame('active', $internship->status);

        // Vérification de la trame de rapport publiée
        $template = ReportTemplate::query()->where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($template);
        $this->assertSame('published', $template->status);
        $this->assertTrue($template->versions()->where('version', 'v1.0.0')->exists());

        // Vérification de l'événement d'audit
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.register',
            'actor_id' => $user->id,
        ]);
    }

    public function test_registration_validation_fails_for_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existant@example.test']);

        $response = $this->post(route('register.store'), [
            'name' => 'Autre Nom',
            'email' => 'existant@example.test',
            'password' => 'mot-de-passe-ultra-securise-2026',
            'password_confirmation' => 'mot-de-passe-ultra-securise-2026',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Auth::check());
    }

    public function test_registration_validation_fails_for_short_password(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Nouveau',
            'email' => 'nouveau@example.test',
            'password' => 'court',
            'password_confirmation' => 'court',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertFalse(Auth::check());
    }
}
