<?php

namespace Tests\Feature\Reporting;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Models\ReportTemplate;
use App\Domain\Reporting\Models\ReportTemplateVersion;
use App\Jobs\GenerateReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-064 — exports PDF/DOCX : rendu compatible PHP 8.5 (dompdf 3.1,
 * PhpWord 1.1 — vérifié préalablement), stockage privé, téléchargement
 * contrôlé et audité.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Internship $internship;

    private Report $report;

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
        $project = $this->internship->projects()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Projet démo export',
            'description' => null,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now(),
        ]);
        $project->activities()->create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Activité exportée',
            'description' => null,
            'type' => 'feature',
            'status' => 'in_progress',
            'priority' => 'medium',
            'objectives' => 'Objectif public.',
        ]);

        $template = ReportTemplate::query()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Trame export',
            'type' => 'memoir',
            'description' => null,
            'status' => 'published',
        ]);
        $version = ReportTemplateVersion::query()->create([
            'template_id' => $template->id,
            'version' => 'v1.0.0',
            'schema_json' => ['title' => 'Trame export', 'sections' => ['identity', 'activities']],
            'published_at' => now(),
        ]);

        $this->report = Report::query()->create([
            'internship_id' => $this->internship->id,
            'workspace_id' => $this->workspace->id,
            'template_version_id' => $version->id,
            'generated_by' => $this->owner->id,
            'generated_at' => now(),
            'status' => 'queued',
        ]);

        (new GenerateReport($this->report))->handle(app(AuditLogger::class));
        $this->report->refresh();
    }

    public function test_export_pdf_stores_and_downloads_a_valid_document(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('workspace.internships.reports.export', [$this->workspace, $this->internship, $this->report, 'pdf']))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $exportPath = 'reports/exports/'.$this->report->id.'.pdf';
        Storage::disk('local')->assertExists($exportPath);
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get($exportPath));

        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_export_docx_stores_and_downloads_a_valid_document(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('workspace.internships.reports.export', [$this->workspace, $this->internship, $this->report, 'docx']))
            ->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('Content-Type'),
        );

        $exportPath = 'reports/exports/'.$this->report->id.'.docx';
        Storage::disk('local')->assertExists($exportPath);

        // Signature ZIP (OOXML) du DOCX.
        $this->assertStringStartsWith('PK', (string) Storage::disk('local')->get($exportPath));
    }

    public function test_unknown_format_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->get(route('workspace.internships.reports.export', [$this->workspace, $this->internship, $this->report, 'exe']))
            ->assertNotFound();
    }

    public function test_outsider_cannot_export(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('workspace.internships.reports.export', [$this->workspace, $this->internship, $this->report, 'pdf']))
            ->assertForbidden();

        Storage::disk('local')->assertMissing('reports/exports/'.$this->report->id.'.pdf');
    }

    public function test_export_requires_a_generated_report(): void
    {
        $this->report->forceFill(['status' => 'queued'])->save();

        $this->actingAs($this->owner)
            ->get(route('workspace.internships.reports.export', [$this->workspace, $this->internship, $this->report, 'pdf']))
            ->assertNotFound();
    }
}
