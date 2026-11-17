<?php

namespace App\Jobs;

use App\Domain\Audit\AuditLogger;
use App\Domain\Evaluation\Models\ActivityCompetency;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Project\Models\Activity;
use App\Domain\Reporting\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Génération asynchrone d'un rapport de stage (T-063, INV-14) :
 * - le contenu est filtré par visibilité côté serveur ;
 * - les données privées sont exclues et les exclusions sont listées ;
 * - la génération est auditée (succès comme échec).
 */
class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Report $report) {}

    public function handle(AuditLogger $audit): void
    {
        try {
            $internship = $this->report->internship()->with('trainee')->firstOrFail();
            $version = $this->report->templateVersion()->firstOrFail();
            $sections = $version->schema_json['sections'] ?? [];

            [$html, $exclusions] = $this->render($internship, $sections);

            $path = 'reports/'.$this->report->id.'.html';
            Storage::disk('local')->put($path, $html);

            $this->report->forceFill([
                'file_path' => $path,
                'status' => 'generated',
            ])->save();

            $audit->record(
                'report.generated',
                $this->report->workspace,
                $this->report,
                [
                    'status' => 'generated',
                    'count' => count($sections),
                    'excluded_evidences' => $exclusions['evidences'],
                    'excluded_notes' => $exclusions['private_notes'],
                    'excluded_comments' => $exclusions['private_comments'],
                ],
                $this->report->generator,
            );
        } catch (\Throwable $exception) {
            $this->report->forceFill(['status' => 'failed'])->save();

            $audit->record(
                'report.failed',
                $this->report->workspace,
                $this->report,
                ['status' => 'failed', 'reason' => $exception->getMessage()],
                $this->report->generator,
            );
        }
    }

    /**
     * Construit le HTML du rapport : uniquement des données autorisées.
     *
     * @param  list<string>  $sections
     * @return array{0: string, 1: array{evidences: int, private_notes: int, private_comments: int}}
     */
    private function render(Internship $internship, array $sections): array
    {
        $exclusions = ['evidences' => 0, 'private_notes' => 0, 'private_comments' => 0];

        $parts = [
            '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">',
            '<title>Rapport — '.e($internship->title).'</title>',
            '<style>body{font-family:sans-serif;margin:2rem}h1{font-size:1.4rem}h2{margin-top:1.5rem}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:.35rem;text-align:left}li{margin:.2rem 0}</style>',
            '</head><body>',
            '<h1>'.e($internship->title).'</h1>',
            '<p class="muted">Contenu : faits (activités, journal, preuves), '
            .'déclarations (justifications du stagiaire), '
            .'appréciations (évaluations nominatives des évaluateurs habilités).</p>',
        ];

        foreach ($sections as $section) {
            $parts[] = match ($section) {
                'identity' => $this->identitySection($internship),
                'activities' => $this->activitiesSection($internship),
                'daily_logs' => $this->dailyLogsSection($internship, $exclusions),
                'evidences' => $this->evidencesSection($internship, $exclusions),
                'competencies' => $this->competenciesSection($internship),
                default => '',
            };
        }

        $parts[] = '<p class="muted">Exclusions appliquées (INV-14) : '
            .$exclusions['evidences'].' preuve(s) privée(s), '
            .$exclusions['private_notes'].' note(s) personnelle(s) de journal, '
            .$exclusions['private_comments'].' commentaire(s) privé(s).</p>';
        $parts[] = '</body></html>';

        return [implode("\n", $parts), $exclusions];
    }

    private function identitySection(Internship $internship): string
    {
        return '<h2>Identité du stage</h2>'
            .'<ul>'
            .'<li>Stagiaire : '.e($internship->trainee->name).'</li>'
            .'<li>Organisation d\'accueil : '.e($internship->company_name).'</li>'
            .'<li>Période : '.optional($internship->start_date)->format('d/m/Y').' → '
            .optional($internship->end_date)->format('d/m/Y').'</li>'
            .'<li>Objectifs : '.e((string) $internship->objectives).'</li>'
            .'<li>Statut : '.e($internship->statusLabel()).'</li>'
            .'</ul>';
    }

    private function activitiesSection(Internship $internship): string
    {
        $activities = $internship->projects()->with('activities')->get()->flatMap->activities;

        if ($activities->isEmpty()) {
            return '<h2>Activités</h2><p>Aucune activité.</p>';
        }

        $rows = $activities
            ->map(fn (Activity $activity): string => '<tr>'
                .'<td>'.e($activity->title).'</td>'
                .'<td>'.e($activity->typeLabel()).'</td>'
                .'<td>'.e($activity->statusLabel()).'</td>'
                .'<td>'.e((string) $activity->objectives).'</td>'
                .'<td>'.e((string) $activity->results).'</td>'
                .'</tr>')
            ->implode('');

        return '<h2>Activités</h2>'
            .'<table><thead><tr><th>Titre</th><th>Type</th><th>Statut</th><th>Objectifs</th><th>Résultats</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>';
    }

    private function dailyLogsSection(Internship $internship, array &$exclusions): string
    {
        $logs = $internship->dailyLogs()->orderBy('work_date')->get();

        // INV-14 : private_notes n'est jamais sérialisé dans un export.
        $exclusions['private_notes'] = $logs
            ->filter(fn (DailyLog $log): bool => trim((string) $log->private_notes) !== '')
            ->count();

        if ($logs->isEmpty()) {
            return '<h2>Journal quotidien</h2><p>Aucune entrée.</p>';
        }

        $rows = $logs
            ->map(fn (DailyLog $log): string => '<tr>'
                .'<td>'.optional($log->work_date)->format('d/m/Y').'</td>'
                .'<td>'.e((string) $log->objectives).'</td>'
                .'<td>'.e((string) $log->work_done).'</td>'
                .'<td>'.e((string) $log->results).'</td>'
                .'<td>'.e($log->status === 'closed' ? 'Clôturée' : 'Brouillon').'</td>'
                .'</tr>')
            ->implode('');

        return '<h2>Journal quotidien</h2>'
            .'<p class="muted">Notes personnelles exclues de ce rapport.</p>'
            .'<table><thead><tr><th>Date</th><th>Objectifs</th><th>Travail réalisé</th><th>Résultats</th><th>Statut</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>';
    }

    private function evidencesSection(Internship $internship, array &$exclusions): string
    {
        $activityIds = $internship->projects()->with('activities')->get()->flatMap->activities->pluck('id');

        $evidences = Evidence::query()
            ->where('workspace_id', $internship->workspace_id)
            ->whereHas('activities', fn ($query) => $query->whereIn('activities.id', $activityIds))
            ->get();

        $visible = $evidences->filter(
            fn (Evidence $evidence): bool => in_array($evidence->visibility, ['stage', 'shared'], true),
        );
        $exclusions['evidences'] = $evidences->count() - $visible->count();

        if ($visible->isEmpty()) {
            return '<h2>Preuves</h2><p>Aucune preuve visible.</p>';
        }

        $rows = $visible
            ->map(fn (Evidence $evidence): string => '<li>'.e($evidence->title)
                .' — '.e($evidence->typeLabel())
                .' ('.e($evidence->visibilityLabel()).')'
                .'</li>')
            ->implode('');

        return '<h2>Preuves</h2>'
            .'<p class="muted">Preuves privées exclues de ce rapport.</p>'
            .'<ul>'.$rows.'</ul>';
    }

    private function competenciesSection(Internship $internship): string
    {
        $activityIds = $internship->projects()->with('activities')->get()->flatMap->activities->pluck('id');

        $associations = ActivityCompetency::query()
            ->where('workspace_id', $internship->workspace_id)
            ->whereIn('activity_id', $activityIds)
            ->with(['competency', 'assessments.assessor'])
            ->get();

        if ($associations->isEmpty()) {
            return '<h2>Compétences</h2><p>Aucune compétence associée.</p>';
        }

        $rows = $associations
            ->sortBy(fn (ActivityCompetency $association): string => $association->competency->code)
            ->map(function (ActivityCompetency $association): string {
                $latest = $association->assessments->sortByDesc('assessed_at')->first();

                return '<tr>'
                    .'<td>'.e($association->competency->code).' '.e($association->competency->title).'</td>'
                    .'<td>'.e($association->statusLabel()).'</td>'
                    .'<td>'.e($association->justification).'</td>'
                    .'<td>'.($latest === null
                        ? 'Aucune'
                        : e($latest->statusLabel()).' par '.e($latest->assessor->name).' — « '.e($latest->appreciation).' »')
                    .'</td>'
                    .'</tr>';
            })
            ->implode('');

        return '<h2>Compétences</h2>'
            .'<table><thead><tr><th>Compétence</th><th>Statut</th><th>Justification</th><th>Dernière appréciation</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>';
    }
}
