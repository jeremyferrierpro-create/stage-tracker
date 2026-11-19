<?php

namespace App\Jobs;

use App\Domain\Audit\AuditLogger;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Review;
use App\Domain\Evaluation\Models\ActivityCompetency;
use App\Domain\Evidence\Models\Evidence;
use App\Domain\Git\Models\Commit;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Task;
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
            $version = $this->report->templateVersion()->with('template')->firstOrFail();
            $sections = $version->schema_json['sections'] ?? [];
            $type = $version->template?->type ?? 'memoir';

            [$html, $exclusions] = $this->render($internship, $sections, $type);

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
    private function render(Internship $internship, array $sections, string $type = 'memoir'): array
    {
        $exclusions = ['evidences' => 0, 'private_notes' => 0, 'private_comments' => 0];

        $ordered = $this->orderedSections($sections, $type);

        $parts = [
            '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">',
            '<title>Rapport — '.e($internship->title).'</title>',
            '<style>body{font-family:sans-serif;margin:2rem;line-height:1.45}h1{font-size:1.6rem}h2{margin-top:1.6rem;border-bottom:1px solid #bbb;padding-bottom:.2rem}table{border-collapse:collapse;width:100%;margin:.6rem 0}th,td{border:1px solid #999;padding:.35rem;text-align:left;vertical-align:top}li{margin:.2rem 0}.muted{color:#555}.cover{text-align:center;padding-top:5rem;page-break-after:always}.cover .type{text-transform:uppercase;letter-spacing:.15em;color:#555}.toc{page-break-after:always}.badge{display:inline-block;border:1px solid #777;border-radius:3px;padding:0 .3rem;font-size:.85em}</style>',
            '</head><body>',
        ];

        $parts[] = $this->coverSection($internship, $type);
        $parts[] = $this->tocSection($ordered);

        foreach ($ordered as $section) {
            $html = match ($section) {
                'identity' => $this->identitySection($internship),
                'activities' => $this->activitiesSection($internship),
                'daily_logs' => $this->dailyLogsSection($internship, $exclusions),
                'evidences' => $this->evidencesSection($internship, $exclusions),
                'competencies' => $this->competenciesSection($internship),
                'tasks' => $this->tasksSection($internship),
                'commits' => $this->commitsSection($internship),
                'collaboration' => $this->collaborationSection($internship, $exclusions),
                'synthesis' => $this->synthesisSection($internship, $exclusions),
                default => '',
            };

            if ($html !== '') {
                $parts[] = '<section id="section-'.$section.'">'.$html.'</section>';
            }
        }

        $parts[] = '<p class="muted">Exclusions appliquées (INV-14) : '
            .$exclusions['evidences'].' preuve(s) privée(s), '
            .$exclusions['private_notes'].' note(s) personnelle(s) de journal, '
            .$exclusions['private_comments'].' commentaire(s) privé(s).</p>';
        $parts[] = '</body></html>';

        return [implode("\n", $parts), $exclusions];
    }

    /**
     * Ordonne les sections selon le type de document, puis ajoute la synthèse.
     *
     * @param  list<string>  $sections
     * @return list<string>
     */
    private function orderedSections(array $sections, string $type): array
    {
        $order = match ($type) {
            'professional_file' => ['identity', 'evidences', 'competencies', 'activities', 'tasks', 'commits', 'collaboration', 'daily_logs'],
            'oral_presentation' => ['identity', 'activities', 'competencies', 'evidences', 'tasks', 'commits', 'collaboration', 'daily_logs'],
            default => ['identity', 'activities', 'daily_logs', 'evidences', 'competencies', 'tasks', 'commits', 'collaboration'],
        };

        $ordered = [];

        foreach ($order as $section) {
            if (in_array($section, $sections, true)) {
                $ordered[] = $section;
            }
        }

        foreach ($sections as $section) {
            if (! in_array($section, $ordered, true)) {
                $ordered[] = $section;
            }
        }

        if (! in_array('synthesis', $ordered, true)) {
            $ordered[] = 'synthesis';
        } else {
            // La synthèse reste toujours en fin de document.
            $ordered = array_values(array_filter($ordered, fn (string $section): bool => $section !== 'synthesis'));
            $ordered[] = 'synthesis';
        }

        return $ordered;
    }

    private function sectionTitle(string $section): string
    {
        return match ($section) {
            'identity' => 'Identité du stage',
            'activities' => 'Activités',
            'daily_logs' => 'Journal quotidien',
            'evidences' => 'Preuves',
            'competencies' => 'Compétences',
            'tasks' => 'Tâches',
            'commits' => 'Commits',
            'collaboration' => 'Revues et commentaires',
            'synthesis' => 'Synthèse',
            default => $section,
        };
    }

    private function coverSection(Internship $internship, string $type): string
    {
        $label = match ($type) {
            'professional_file' => 'Dossier professionnel',
            'oral_presentation' => 'Dossier de soutenance',
            default => 'Mémoire',
        };

        return '<section class="cover">'
            .'<p class="type">'.e($label).'</p>'
            .'<h1>'.e($internship->title).'</h1>'
            .'<p><strong>'.e($internship->trainee->name).'</strong></p>'
            .'<p>'.e($internship->company_name).'</p>'
            .'<p>'.optional($internship->start_date)->format('d/m/Y').' → '
            .optional($internship->end_date)->format('d/m/Y').'</p>'
            .'<p class="muted">Document généré le '.now()->format('d/m/Y à H:i').'</p>'
            .'</section>';
    }

    /**
     * @param  list<string>  $sections
     */
    private function tocSection(array $sections): string
    {
        $items = '';

        foreach ($sections as $section) {
            $items .= '<li><a href="#section-'.$section.'">'
                .e($this->sectionTitle($section)).'</a></li>';
        }

        return '<nav class="toc"><h2>Sommaire</h2><ol>'.$items.'</ol></nav>';
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
            ->map(function (Evidence $evidence): string {
                $link = '';

                if (is_string($evidence->external_url) && preg_match('#^https?://#i', $evidence->external_url) === 1) {
                    $link = '<br><a href="'.e($evidence->external_url).'">'.e($evidence->external_url).'</a>';
                }

                return '<li><strong>'.e($evidence->title).'</strong>'
                    .' — '.e($evidence->typeLabel())
                    .' ('.e($evidence->visibilityLabel()).')'
                    .($evidence->description ? '<br>'.nl2br(e($evidence->description)) : '')
                    .$link
                    .'</li>';
            })
            ->implode('');

        return '<h2>Preuves</h2>'
            .'<p class="muted">Preuves privées exclues de ce rapport.</p>'
            .'<ul>'.$rows.'</ul>';
    }

    private function tasksSection(Internship $internship): string
    {
        $activities = $internship->projects()->with('activities.tasks')->get()->flatMap->activities;
        $tasks = $activities->flatMap->tasks;

        if ($tasks->isEmpty()) {
            return '<h2>Tâches</h2><p>Aucune tâche.</p>';
        }

        $rows = $tasks
            ->sortBy(fn (Task $task): string => $task->activity->title.' '.$task->position)
            ->map(fn (Task $task): string => '<tr>'
                .'<td>'.e($task->activity->title).'</td>'
                .'<td>'.e($task->title).'</td>'
                .'<td>'.e($task->statusLabel()).'</td>'
                .'<td>'.e($task->priorityLabel()).'</td>'
                .'<td>'.optional($task->due_date)->format('d/m/Y').'</td>'
                .'<td>'.e((string) $task->acceptance_criteria).'</td>'
                .'</tr>')
            ->implode('');

        return '<h2>Tâches</h2>'
            .'<table><thead><tr><th>Activité</th><th>Tâche</th><th>Statut</th><th>Priorité</th><th>Échéance</th><th>Critères d\'acceptation</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>';
    }

    private function commitsSection(Internship $internship): string
    {
        $repositoryIds = $internship->projects()->with('repositories')->get()
            ->flatMap->repositories->pluck('id');

        $commits = Commit::query()
            ->whereIn('repository_id', $repositoryIds)
            ->with('files')
            ->orderByDesc('committed_at')
            ->get();

        if ($commits->isEmpty()) {
            return '<h2>Commits</h2><p>Aucun commit synchronisé.</p>';
        }

        $rows = $commits
            ->map(function (Commit $commit): string {
                $files = $commit->files
                    ->map(fn ($file): string => e($file->path).' ('.$file->change_type.', +'.$file->additions.'/−'.$file->deletions.')')
                    ->implode('<br>');

                $sha = $commit->url !== null && $commit->url !== ''
                    ? '<a href="'.e($commit->url).'">'.e($commit->shortSha()).'</a>'
                    : e($commit->shortSha());

                return '<tr>'
                    .'<td>'.optional($commit->committed_at)->format('d/m/Y H:i').'</td>'
                    .'<td>'.$sha.'</td>'
                    .'<td>'.e($commit->message).'</td>'
                    .'<td>'.e((string) $commit->author_name).'</td>'
                    .'<td>'.$files.'</td>'
                    .'</tr>';
            })
            ->implode('');

        return '<h2>Commits</h2>'
            .'<table><thead><tr><th>Date</th><th>SHA</th><th>Message</th><th>Auteur</th><th>Fichiers</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>';
    }

    private function collaborationSection(Internship $internship, array &$exclusions): string
    {
        $activityIds = $internship->projects()->with('activities')->get()->flatMap->activities->pluck('id');

        $comments = Comment::query()
            ->whereIn('activity_id', $activityIds)
            ->whereIn('visibility', ['stage', 'shared'])
            ->with(['author', 'activity'])
            ->orderBy('created_at')
            ->get();

        $exclusions['private_comments'] = Comment::query()
            ->whereIn('activity_id', $activityIds)
            ->where('visibility', 'private')
            ->count();

        $reviews = Review::query()
            ->whereIn('activity_id', $activityIds)
            ->with(['reviewer', 'activity'])
            ->orderByDesc('created_at')
            ->get();

        if ($comments->isEmpty() && $reviews->isEmpty()) {
            return '<h2>Revues et commentaires</h2><p>Aucun échange visible.</p>';
        }

        $commentItems = $comments
            ->map(fn (Comment $comment): string => '<li><strong>'.e($comment->activity->title).'</strong> — '
                .e($comment->author->name).' ('.e($comment->visibilityLabel()).') : '
                .nl2br(e($comment->content)).'</li>')
            ->implode('');

        $reviewRows = $reviews
            ->map(fn (Review $review): string => '<tr>'
                .'<td>'.e($review->activity->title).'</td>'
                .'<td>'.e($review->reviewer->name).'</td>'
                .'<td>'.e($review->statusLabel()).'</td>'
                .'<td>'.nl2br(e((string) $review->feedback)).'</td>'
                .'</tr>')
            ->implode('');

        $html = '<h2>Revues et commentaires</h2>';

        if ($commentItems !== '') {
            $html .= '<h3>Commentaires (visibles)</h3><ul>'.$commentItems.'</ul>';
        }

        if ($reviewRows !== '') {
            $html .= '<h3>Revues</h3>'
                .'<table><thead><tr><th>Activité</th><th>Relecteur</th><th>Statut</th><th>Retour</th></tr></thead>'
                .'<tbody>'.$reviewRows.'</tbody></table>';
        }

        return $html;
    }

    private function synthesisSection(Internship $internship, array $exclusions): string
    {
        $activityIds = $internship->projects()->with('activities')->get()->flatMap->activities->pluck('id');
        $tasks = Task::query()->whereIn('activity_id', $activityIds)->get();
        $visibleEvidences = Evidence::query()
            ->where('workspace_id', $internship->workspace_id)
            ->whereHas('activities', fn ($query) => $query->whereIn('activities.id', $activityIds))
            ->whereIn('visibility', ['stage', 'shared'])
            ->count();

        $associations = ActivityCompetency::query()
            ->where('workspace_id', $internship->workspace_id)
            ->whereIn('activity_id', $activityIds)
            ->with('assessments')
            ->get();

        $assessed = $associations->filter(fn (ActivityCompetency $association): bool => $association->assessments->isNotEmpty())->count();

        return '<h2>Synthèse</h2>'
            .'<p class="muted">Indicateurs calculés à partir des données autorisées du stage.</p>'
            .'<table><tbody>'
            .'<tr><th>Activités</th><td>'.$activityIds->count().'</td></tr>'
            .'<tr><th>Tâches terminées</th><td>'.$tasks->where('status', 'done')->count().' / '.$tasks->count().'</td></tr>'
            .'<tr><th>Entrées de journal</th><td>'.$internship->dailyLogs()->count().'</td></tr>'
            .'<tr><th>Preuves visibles</th><td>'.$visibleEvidences.'</td></tr>'
            .'<tr><th>Compétences évaluées</th><td>'.$assessed.' / '.$associations->count().'</td></tr>'
            .'<tr><th>Preuves privées exclues</th><td>'.$exclusions['evidences'].'</td></tr>'
            .'<tr><th>Commentaires privés exclus</th><td>'.$exclusions['private_comments'].'</td></tr>'
            .'<tr><th>Notes personnelles exclues</th><td>'.$exclusions['private_notes'].'</td></tr>'
            .'</tbody></table>';
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
                        : e($latest->statusLabel()).' par '.e($latest->assessor->name).' — « '.e($latest->appreciation).' »'
                            .'<br><span class="muted">'.$association->assessments->count().' évaluation(s) au total</span>')
                    .'</td>'
                    .'</tr>';
            })
            ->implode('');

        return '<h2>Compétences</h2>'
            .'<table><thead><tr><th>Compétence</th><th>Statut</th><th>Justification</th><th>Dernière appréciation</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table>';
    }
}
