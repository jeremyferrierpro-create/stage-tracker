<?php

namespace Tests\Feature\Reporting;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use App\Domain\Reporting\Models\Report;
use App\Domain\Reporting\Models\ReportTemplate;
use App\Jobs\GenerateReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $internship = Internship::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $workspace->id,
        ]);
        $activity = Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $workspace->id,
        ]);

        return [$owner, $workspace, $internship, $project, $activity];
    }

    public function test_report_generation_with_all_sections_produces_html_with_synthesis_and_toc(): void
    {
        [$owner, $workspace, $internship, $project, $activity] = $this->setupContext();

        Task::create([
            'activity_id' => $activity->id,
            'workspace_id' => $workspace->id,
            'title' => 'Tâche terminée',
            'status' => 'done',
            'priority' => 'high',
        ]);

        $template = ReportTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Trame Complète',
            'type' => 'memoir',
            'status' => 'published',
        ]);

        $version = $template->versions()->create([
            'version' => 'v1.0.0',
            'schema_json' => [
                'title' => 'Trame Complète',
                'sections' => ['identity', 'activities', 'tasks', 'commits', 'collaboration', 'synthesis'],
            ],
            'published_at' => now(),
        ]);

        $report = Report::create([
            'internship_id' => $internship->id,
            'template_version_id' => $version->id,
            'workspace_id' => $workspace->id,
            'status' => 'queued',
            'generated_by' => $owner->id,
            'generated_at' => now(),
        ]);

        $job = new GenerateReport($report);
        app()->call([$job, 'handle']);

        $report->refresh();
        $this->assertSame('generated', $report->status);
        $this->assertNotNull($report->file_path);

        $html = Storage::disk('local')->get($report->file_path);
        $this->assertStringContainsString('Sommaire', $html);
        $this->assertStringContainsString('Tâche terminée', $html);
        $this->assertStringContainsString('Synthèse', $html);
        $this->assertStringContainsString('Indicateurs calculés', $html);
    }

    public function test_workspace_reports_index_displays_workspace_reports(): void
    {
        [$owner, $workspace, $internship] = $this->setupContext();

        $template = ReportTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Trame Démo',
            'type' => 'memoir',
            'status' => 'published',
        ]);
        $version = $template->versions()->create([
            'version' => 'v1.0.0',
            'schema_json' => ['title' => 'T', 'sections' => ['identity']],
            'published_at' => now(),
        ]);

        Report::create([
            'internship_id' => $internship->id,
            'template_version_id' => $version->id,
            'workspace_id' => $workspace->id,
            'status' => 'generated',
            'generated_by' => $owner->id,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('workspace.reports.index', $workspace));
        $response->assertOk();
        $response->assertSee('Rapports — '.$workspace->name);
        $response->assertSee('Trame Démo');
    }

    public function test_failed_report_can_be_retried(): void
    {
        Queue::fake();
        [$owner, $workspace, $internship] = $this->setupContext();

        $template = ReportTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Trame Test',
            'type' => 'memoir',
            'status' => 'published',
        ]);
        $version = $template->versions()->create([
            'version' => 'v1.0.0',
            'schema_json' => ['title' => 'T', 'sections' => ['identity']],
            'published_at' => now(),
        ]);

        $report = Report::create([
            'internship_id' => $internship->id,
            'template_version_id' => $version->id,
            'workspace_id' => $workspace->id,
            'status' => 'failed',
            'generated_by' => $owner->id,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->post(
            route('workspace.internships.reports.retry', [$workspace, $internship, $report])
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Génération relancée.');

        $report->refresh();
        $this->assertSame('queued', $report->status);

        Queue::assertPushed(GenerateReport::class);
    }

    public function test_report_can_be_deleted(): void
    {
        Storage::fake('local');
        [$owner, $workspace, $internship] = $this->setupContext();

        $template = ReportTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => 'Trame Test',
            'type' => 'memoir',
            'status' => 'published',
        ]);
        $version = $template->versions()->create([
            'version' => 'v1.0.0',
            'schema_json' => ['title' => 'T', 'sections' => ['identity']],
            'published_at' => now(),
        ]);

        $filePath = 'reports/test-to-delete.html';
        Storage::disk('local')->put($filePath, 'content');

        $report = Report::create([
            'internship_id' => $internship->id,
            'template_version_id' => $version->id,
            'workspace_id' => $workspace->id,
            'status' => 'generated',
            'file_path' => $filePath,
            'generated_by' => $owner->id,
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->delete(
            route('workspace.internships.reports.destroy', [$workspace, $internship, $report])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }
}
