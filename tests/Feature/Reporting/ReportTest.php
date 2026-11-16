<?php

namespace Tests\Feature\Reporting;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Evidence\Models\ActivityEvidence;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Models\ReportTemplate;
use App\Domain\Reporting\Models\ReportTemplateVersion;
use App\Jobs\GenerateReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-062 (modèles versionnés, immuabilité) et T-063 (génération filtrée,
 * INV-14 / MT-11).
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Internship $internship;

    private Activity $activity;

    private ReportTemplateVersion $publishedVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        $this->internship = Internship::factory()->create(['workspace_id' => $this->workspace->id]);
        $project = Project::factory()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
        ]);
        $this->activity = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $this->workspace->id,
            'title' => 'Activité publique du stage',
        ]);

        $template = ReportTemplate::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Suivi maître de stage',
            'type' => 'supervisor_followup',
            'description' => null,
            'status' => 'published',
        ]);
        $this->publishedVersion = ReportTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version' => 'v1.0.0',
            'schema_json' => [
                'title' => 'Suivi maître de stage',
                'sections' => ['identity', 'activities', 'daily_logs', 'evidences', 'competencies'],
            ],
            'published_at' => now(),
        ]);
    }

    /**
     * MT-11 : un stage contenant des données privées.
     */
    private function privateData(): void
    {
        $privateEvidence = Evidence::query()->create([
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->internship->trainee_id,
            'title' => 'PREUVE PRIVEE TITRE SECRET',
            'type' => 'technical_doc',
            'demonstrated' => 'Démarche personnelle.',
            'external_url' => 'https://example.test/prive',
            'visibility' => 'private',
        ]);
        ActivityEvidence::query()->create([
            'activity_id' => $this->activity->id,
            'evidence_id' => $privateEvidence->id,
            'workspace_id' => $this->workspace->id,
            'note' => null,
        ]);

        $this->internship->dailyLogs()->create([
            'workspace_id' => $this->workspace->id,
            'author_id' => $this->internship->trainee_id,
            'work_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'duration_minutes' => 480,
            'objectives' => 'Journée publique.',
            'work_done' => 'Travail visible dans le rapport.',
            'private_notes' => 'NOTES PERSONNELLES SECRETES DU JOURNAL',
            'status' => 'closed',
        ]);
    }

    public function test_admin_manages_templates_and_versions(): void
    {
        $this->actingAs($this->owner)
            ->post(route('workspace.report-templates.store', $this->workspace), [
                'name' => 'Bilan hebdomadaire',
                'type' => 'weekly_followup',
                'description' => 'Trame hebdo.',
            ])
            ->assertRedirect();

        $template = ReportTemplate::query()->where('name', 'Bilan hebdomadaire')->sole();

        $this->actingAs($this->owner)
            ->post(route('workspace.report-templates.versions.store', [$this->workspace, $template]), [
                'version' => 'v1.0.0',
                'sections' => ['identity', 'activities'],
            ])
            ->assertRedirect();

        $version = $template->versions()->where('version', 'v1.0.0')->sole();
        $this->assertNull($version->published_at);

        // Publication → immuable.
        $this->actingAs($this->owner)
            ->post(route('workspace.report-templates.versions.publish', [$this->workspace, $template, $version]))
            ->assertRedirect();

        $this->assertNotNull($version->fresh()->published_at);
        $this->assertSame('published', $template->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report_template.version.published']);
    }

    public function test_invalid_schema_is_rejected(): void
    {
        $template = ReportTemplate::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Trame invalide',
            'type' => 'memoir',
            'description' => null,
            'status' => 'draft',
        ]);

        $this->actingAs($this->owner)
            ->post(route('workspace.report-templates.versions.store', [$this->workspace, $template]), [
                'version' => 'v1.0.0',
                'sections' => ['section_inconnue'],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, $template->versions()->count());
    }

    public function test_non_admin_cannot_manage_templates(): void
    {
        $trainee = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);

        $this->actingAs($trainee)
            ->post(route('workspace.report-templates.store', $this->workspace), [
                'name' => 'Trame pirate',
                'type' => 'memoir',
            ])
            ->assertForbidden();

        $this->assertSame(1, ReportTemplate::query()->count());
    }

    public function test_published_version_is_immutable(): void
    {
        // Ré-écrire une version publiée doit être impossible : aucune route
        // d'édition n'existe — on vérifie que la republication est refusée.
        $this->actingAs($this->owner)
            ->post(route('workspace.report-templates.versions.publish', [$this->workspace, $this->publishedVersion->template, $this->publishedVersion]))
            ->assertSessionHasErrors('version');

        $first = $this->publishedVersion->fresh()->published_at;

        $this->actingAs($this->owner)
            ->post(route('workspace.report-templates.versions.publish', [$this->workspace, $this->publishedVersion->template, $this->publishedVersion]))
            ->assertSessionHasErrors('version');

        $this->assertTrue($this->publishedVersion->fresh()->published_at->equalTo($first));
    }

    public function test_trainee_can_request_a_report(): void
    {
        Queue::fake();

        // Le stagiaire est membre workspace (rôle trainee).
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->internship->trainee_id,
            'role' => 'trainee',
        ]);

        $this->actingAs($this->internship->trainee)
            ->post(route('workspace.internships.reports.store', [$this->workspace, $this->internship]), [
                'template_version_id' => $this->publishedVersion->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(GenerateReport::class);
        $this->assertDatabaseHas('reports', ['status' => 'queued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.requested']);
    }

    public function test_supervisor_without_export_grant_cannot_generate(): void
    {
        $supervisor = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
        ]);

        $permissions = EffectivePermissions::resolveForInternship($supervisor, $this->workspace, $this->internship);
        $this->assertFalse($permissions['export']);
    }

    public function test_supervisor_with_export_grant_can_generate(): void
    {
        Queue::fake();

        $supervisor = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
        ]);
        $this->internship->participants()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $supervisor->id,
            'role' => 'supervisor',
            'permissions' => ['export' => true],
            'status' => 'active',
        ]);

        $this->actingAs($supervisor)
            ->post(route('workspace.internships.reports.store', [$this->workspace, $this->internship]), [
                'template_version_id' => $this->publishedVersion->id,
            ])
            ->assertRedirect();

        Queue::assertPushed(GenerateReport::class);
    }

    public function test_mt11_generated_report_excludes_private_data_and_lists_exclusions(): void
    {
        $this->privateData();

        $report = Report::query()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
            'template_version_id' => $this->publishedVersion->id,
            'generated_by' => $this->owner->id,
            'generated_at' => now(),
            'status' => 'queued',
        ]);

        (new GenerateReport($report))->handle(app(AuditLogger::class));

        $report->refresh();
        $this->assertSame('generated', $report->status);
        $this->assertNotNull($report->file_path);

        Storage::disk('local')->assertExists($report->file_path);
        $content = Storage::disk('local')->get($report->file_path);

        // INV-14 / MT-11 : aucune donnée privée.
        $this->assertStringNotContainsString('PREUVE PRIVEE TITRE SECRET', $content);
        $this->assertStringNotContainsString('NOTES PERSONNELLES SECRETES DU JOURNAL', $content);
        $this->assertStringNotContainsString('private_notes', $content);

        // Les données publiques sont présentes.
        $this->assertStringContainsString('Travail visible dans le rapport.', $content);
        $this->assertStringContainsString('Activité publique du stage', $content);

        // Les exclusions sont listées.
        $this->assertStringContainsString('preuve(s) privée(s)', $content);
        $this->assertStringContainsString('note(s) personnelle(s)', $content);

        // La génération est auditée avec les compteurs d'exclusion.
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.generated']);
    }

    public function test_report_failure_is_audited(): void
    {
        $report = Report::query()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
            'template_version_id' => $this->publishedVersion->id,
            'generated_by' => $this->owner->id,
            'generated_at' => now(),
            'status' => 'queued',
        ]);

        // Échec d'écriture (disque indisponible) : le rapport passe en
        // `failed` et l'échec est audité — pas de crash muet.
        $diskMock = \Mockery::mock(Storage::disk('local'))->makePartial();
        $diskMock->shouldReceive('put')->andThrow(new \RuntimeException('disque indisponible'));
        Storage::shouldReceive('disk')->with('local')->andReturn($diskMock);

        (new GenerateReport($report))->handle(app(AuditLogger::class));

        $report->refresh();
        $this->assertSame('failed', $report->status);
        $this->assertNull($report->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.failed']);
    }

    public function test_download_requires_membership(): void
    {
        $this->privateData();

        $report = Report::query()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
            'template_version_id' => $this->publishedVersion->id,
            'generated_by' => $this->owner->id,
            'generated_at' => now(),
            'status' => 'queued',
        ]);

        (new GenerateReport($report))->handle(app(AuditLogger::class));

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('workspace.internships.reports.download', [$this->workspace, $this->internship, $report]))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->get(route('workspace.internships.reports.download', [$this->workspace, $this->internship, $report]))
            ->assertOk();
    }

    public function test_unpublished_version_cannot_be_used_for_generation(): void
    {
        $draftTemplate = ReportTemplate::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Trame brouillon',
            'type' => 'memoir',
            'description' => null,
            'status' => 'draft',
        ]);
        $draftVersion = $draftTemplate->versions()->create([
            'version' => 'v0.1.0',
            'schema_json' => ['title' => 'Brouillon', 'sections' => ['identity']],
            'published_at' => null,
        ]);

        $this->actingAs($this->owner)
            ->post(route('workspace.internships.reports.store', [$this->workspace, $this->internship]), [
                'template_version_id' => $draftVersion->id,
            ])
            ->assertNotFound();

        $this->assertSame(0, Report::query()->count());
    }
}
