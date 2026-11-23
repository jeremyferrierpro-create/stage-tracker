<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Planning\Models\PlanEntry;
use App\Domain\Planning\PlanningMarkdownParser;
use App\Domain\Project\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importe un planning journalier markdown (tableaux par semaine) dans
 * plan_entries. Cherche le workspace / stage / projet par nom et ne
 * touche jamais aux daily_logs.
 */
class ImportPlanning extends Command
{
    protected $signature = 'stage:import-planning
                            {file : Chemin du fichier markdown du planning}
                            {--email= : E-mail d\'un membre du workspace}
                            {--workspace= : Nom ou identifiant du workspace}
                            {--internship= : Titre ou identifiant du stage}
                            {--project= : Nom du projet (ex. COAB)}';

    protected $description = 'Importe un planning journalier markdown dans plan_entries';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("Fichier introuvable : {$file}");

            return self::FAILURE;
        }

        $workspace = $this->resolveWorkspace();
        if ($workspace === null) {
            return self::FAILURE;
        }

        $internship = $this->resolveInternship($workspace);
        if ($internship === null) {
            return self::FAILURE;
        }

        $project = $this->resolveProject($internship);
        if ($project === false) {
            return self::FAILURE;
        }

        $entries = PlanningMarkdownParser::parse((string) file_get_contents($file));

        if ($entries === []) {
            $this->error('Aucune journée reconnue dans le fichier (format tableau attendu).');

            return self::FAILURE;
        }

        $days = count(array_unique(array_column($entries, 'plan_date')));

        DB::transaction(function () use ($internship, $workspace, $project, $entries): void {
            // On ne remplace que les entrées de planning de CE stage
            // (données créées par cet import), jamais les daily_logs.
            PlanEntry::query()
                ->where('internship_id', $internship->getKey())
                ->delete();

            foreach ($entries as $entry) {
                PlanEntry::query()->create([
                    'internship_id' => $internship->getKey(),
                    'workspace_id' => $workspace->getKey(),
                    'project_id' => $project?->getKey(),
                    'plan_date' => $entry['plan_date'],
                    'slot' => $entry['slot'],
                    'title' => $entry['title'],
                    'planned_minutes' => $entry['planned_minutes'],
                    'position' => $entry['position'],
                ]);
            }
        });

        $this->info(sprintf(
            'Import terminé : %d entrées sur %d jours (%s → %s), stage « %s ».',
            count($entries),
            $days,
            min(array_column($entries, 'plan_date')),
            max(array_column($entries, 'plan_date')),
            $internship->title,
        ));

        return self::SUCCESS;
    }

    private function resolveWorkspace(): ?Workspace
    {
        $email = Str::lower(trim((string) $this->option('email')));
        $name = trim((string) $this->option('workspace'));

        if ($email !== '') {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $this->error("Aucun compte pour {$email}.");

                return null;
            }

            // Workspaces où l'utilisateur est membre ou propriétaire.
            $workspaces = Workspace::query()
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
                ->get();

            if ($workspaces->count() === 1) {
                return $workspaces->first();
            }

            if ($workspaces->isEmpty()) {
                $this->error("{$email} n'appartient à aucun workspace.");

                return null;
            }

            $this->error("{$email} appartient à plusieurs espaces : ".$workspaces->pluck('name')->implode(', '))
                ->line('Précisez avec --workspace= ou utilisez un stage via --internship.');

            return null;
        }

        if ($name !== '') {
            $workspaces = Workspace::query()
                ->where('id', $name)
                ->orWhere('name', 'ilike', '%'.$name.'%')
                ->get();

            if ($workspaces->count() === 1) {
                return $workspaces->first();
            }

            if ($workspaces->isEmpty()) {
                $this->error("Aucun workspace ne correspond à « {$name} ».");
            } else {
                $this->error('Plusieurs workspaces correspondent : '.$workspaces->pluck('name')->implode(', '));
            }

            return null;
        }

        $this->error('Précisez la cible avec --email= ou --workspace=.');

        return null;
    }

    private function resolveInternship(Workspace $workspace): ?Internship
    {
        $needle = trim((string) $this->option('internship'));

        $query = Internship::query()->where('workspace_id', $workspace->getKey());

        if ($needle !== '') {
            $query->where(fn ($q) => $q->where('id', $needle)->orWhere('title', 'ilike', '%'.$needle.'%'));
        }

        $internships = $query->orderBy('start_date')->get();

        if ($internships->count() === 1) {
            return $internships->first();
        }

        if ($internships->isEmpty()) {
            $this->error('Aucun stage trouvé dans ce workspace.');
        } else {
            $list = $internships->map(
                fn (Internship $i): string => "{$i->title} ({$i->start_date?->format('d/m/Y')} → {$i->end_date?->format('d/m/Y')}, id {$i->id})"
            )->implode("\n  ");
            $this->error("Plusieurs stages trouvés, précisez avec --internship= :\n  {$list}");
        }

        return null;
    }

    private function resolveProject(Internship $internship): Project|null|false
    {
        $name = trim((string) $this->option('project'));

        if ($name === '') {
            return null;
        }

        $project = Project::query()
            ->where('internship_id', $internship->getKey())
            ->where('name', 'ilike', '%'.$name.'%')
            ->first();

        if ($project === null) {
            $this->error("Aucun projet « {$name} » dans le stage « {$internship->title} ».");

            return false;
        }

        return $project;
    }
}
