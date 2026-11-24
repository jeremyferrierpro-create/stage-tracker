@extends('layouts.app')

@php
    use Illuminate\Support\Str;
    $totalPlanned = collect($days)->sum('planned_minutes');
    $totalActual = collect($days)->sum('actual_minutes');
    $globalPercent = $totalPlanned > 0 ? min(100, (int) round($totalActual / $totalPlanned * 100)) : 0;
    $doneCount = collect($days)->where('status', 'done')->count();

    // Routes de base pour les formulaires du planning.
    $storeUrl = route('workspace.internships.planning.store', [$workspace, $internship]);
    $updateUrl = fn (string $id): string => route('workspace.internships.planning.update', [$workspace, $internship, $id]);
    $destroyUrl = fn (string $id): string => route('workspace.internships.planning.destroy', [$workspace, $internship, $id]);
    $activityUrl = fn (string $id): string => route('workspace.internships.planning.activity.store', [$workspace, $internship, $id]);
@endphp

@section('title', 'Planning — '.$internship->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Planning'],
    ]"/>
@endsection

@section('content')
    <h1>Planning vs réel — {{ $internship->title }}</h1>

    @if ($canManage)
        <div class="card">
            <h2>Ajouter une entrée</h2>
            <form method="POST" action="{{ $storeUrl }}">
                @csrf

                <label for="plan_date">Date</label>
                <input id="plan_date" type="date" name="plan_date" required value="{{ old('plan_date') }}">
                @error('plan_date') <p class="error">{{ $message }}</p> @enderror

                <label for="slot">Créneau</label>
                <select id="slot" name="slot" required>
                    @foreach (\App\Domain\Planning\Models\PlanEntry::SLOT_LABELS as $slot => $label)
                        <option value="{{ $slot }}" @selected(old('slot') === $slot)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('slot') <p class="error">{{ $message }}</p> @enderror

                <label for="title">Titre</label>
                <input id="title" type="text" name="title" required maxlength="200" value="{{ old('title') }}">
                @error('title') <p class="error">{{ $message }}</p> @enderror

                <label for="planned_minutes">Durée prévue (minutes)</label>
                <input id="planned_minutes" type="number" name="planned_minutes" required min="1" max="600" value="{{ old('planned_minutes', 210) }}">
                @error('planned_minutes') <p class="error">{{ $message }}</p> @enderror

                <label for="project_id">Projet (optionnel)</label>
                <select id="project_id" name="project_id">
                    <option value="">— Aucun —</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id') === $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
                @error('project_id') <p class="error">{{ $message }}</p> @enderror

                <label for="competency_ids">Compétences visées (optionnel)</label>
                <select id="competency_ids" name="competency_ids[]" multiple size="5">
                    @foreach ($competencies as $competency)
                        <option value="{{ $competency->id }}" @selected(in_array($competency->id, old('competency_ids', [])))>
                            {{ $competency->code }} — {{ $competency->title }}
                        </option>
                    @endforeach
                </select>
                <p class="muted">Maintenez Ctrl pour sélectionner plusieurs compétences.</p>
                @error('competency_ids') <p class="error">{{ $message }}</p> @enderror
                @error('competency_ids.*') <p class="error">{{ $message }}</p> @enderror

                <button type="submit">Ajouter</button>
            </form>
        </div>
    @endif

    @if (empty($days))
        <p class="muted">
            Aucun planning pour ce stage.
            @if ($canManage)
                Ajoutez une entrée ci-dessus ou utilisez <code>php artisan stage:import-planning</code> pour importer un fichier markdown.
            @else
                Le planning n'a pas encore été renseigné.
            @endif
        </p>
    @else
        <div class="card">
            <p>
                <strong>{{ count($days) }} jours</strong> —
                {{ $doneCount }} fait{{ $doneCount > 1 ? 's' : '' }} —
                prévu {{ $totalPlanned }} min, réel {{ $totalActual }} min,
                <strong>{{ $globalPercent }} %</strong> réalisé.
            </p>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Prévu</th>
                        <th>Réel</th>
                        <th>Écart</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr>
                            <td>
                                {{ \Illuminate\Support\Carbon::parse($day['date'])->format('D d/m/Y') }}
                            </td>
                            <td>
                                <span class="badge">{{ \App\Domain\Planning\PlanningComparisonService::statusLabel($day['status']) }}</span>
                            </td>
                            <td>
                                @foreach ($day['entries'] as $slot => $entries)
                                    <div>
                                        <strong>{{ \App\Domain\Planning\Models\PlanEntry::SLOT_LABELS[$slot] ?? $slot }}</strong>
                                        <ul>
                                            @foreach ($entries as $entry)
                                                @php
                                                    $linked = $activitiesByEntry[$entry->id] ?? collect();
                                                    $linkedCompetencies = $competenciesByEntry[$entry->id] ?? collect();
                                                    $linkedActivityIds = $linked->pluck('id')->all();
                                                    $linkedCompetencyIds = $linkedCompetencies->pluck('id')->all();
                                                @endphp
                                                <li>
                                                    {{ $entry->title }} <span class="muted">({{ $entry->planned_minutes }} min)</span>

                                                    @foreach ($linkedCompetencies as $competency)
                                                        <span class="badge">{{ $competency->code }}</span>
                                                    @endforeach

                                                    @if ($linked->isNotEmpty())
                                                        <br><span class="muted">Réalisé par :</span>
                                                        @foreach ($linked as $activity)
                                                            <a href="{{ route('workspace.internships.projects.activities.show', [$workspace, $internship, $activity->project, $activity]) }}">{{ $activity->title }}</a>@if (! $loop->last), @endif
                                                        @endforeach
                                                    @endif

                                                    @if ($canManage)
                                                        <details>
                                                            <summary class="muted">Actions</summary>

                                                            <h4>Modifier</h4>
                                                            <form method="POST" action="{{ $updateUrl($entry->id) }}">
                                                                @csrf
                                                                @method('PUT')
                                                                <label>Date</label>
                                                                <input type="date" name="plan_date" required value="{{ $entry->plan_date->toDateString() }}">
                                                                <label>Créneau</label>
                                                                <select name="slot" required>
                                                                    @foreach (\App\Domain\Planning\Models\PlanEntry::SLOT_LABELS as $slot => $label)
                                                                        <option value="{{ $slot }}" @selected($entry->slot === $slot)>{{ $label }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <label>Titre</label>
                                                                <input type="text" name="title" required maxlength="200" value="{{ $entry->title }}">
                                                                <label>Durée prévue (minutes)</label>
                                                                <input type="number" name="planned_minutes" required min="1" max="600" value="{{ $entry->planned_minutes }}">
                                                                <label>Projet</label>
                                                                <select name="project_id">
                                                                    <option value="">— Aucun —</option>
                                                                    @foreach ($projects as $project)
                                                                        <option value="{{ $project->id }}" @selected($entry->project_id === $project->id)>{{ $project->name }}</option>
                                                                    @endforeach
                                                                </select>

                                                                <label>Compétences visées</label>
                                                                <select name="competency_ids[]" multiple size="4">
                                                                    @foreach ($competencies as $competency)
                                                                        <option value="{{ $competency->id }}" @selected(in_array($competency->id, $linkedCompetencyIds))>
                                                                            {{ $competency->code }} — {{ $competency->title }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>

                                                                <label>Activités liées</label>
                                                                <select name="activity_ids[]" multiple size="4">
                                                                    @foreach ($activities as $activity)
                                                                        <option value="{{ $activity->id }}" @selected(in_array($activity->id, $linkedActivityIds))>
                                                                            {{ $activity->project->name }} — {{ $activity->title }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                <p class="muted">Laissez vide pour délier toutes les activités.</p>

                                                                <button type="submit">Enregistrer</button>
                                                            </form>

                                                            <h4>Créer une activité depuis cette entrée</h4>
                                                            <form method="POST" action="{{ $activityUrl($entry->id) }}">
                                                                @csrf
                                                                <label>Projet</label>
                                                                <select name="project_id" required>
                                                                    @foreach ($projects as $project)
                                                                        <option value="{{ $project->id }}" @selected($entry->project_id === $project->id)>{{ $project->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <label>Type</label>
                                                                <select name="type" required>
                                                                    @foreach (\App\Domain\Project\Models\Activity::TYPES as $type)
                                                                        <option value="{{ $type }}">{{ \App\Domain\Project\Models\Activity::TYPE_LABELS[$type] }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <label>Statut</label>
                                                                <select name="status" required>
                                                                    @foreach (\App\Domain\Project\Models\Activity::STATUSES as $status)
                                                                        <option value="{{ $status }}" @selected($status === 'todo')>{{ \App\Domain\Project\Models\Activity::STATUS_LABELS[$status] }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <label>Priorité</label>
                                                                <select name="priority" required>
                                                                    @foreach (\App\Domain\Project\Models\Activity::PRIORITIES as $priority)
                                                                        <option value="{{ $priority }}" @selected($priority === 'medium')>{{ \App\Domain\Project\Models\Activity::PRIORITY_LABELS[$priority] }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <button type="submit">Créer et lier</button>
                                                            </form>

                                                            <h4>Supprimer</h4>
                                                            <form method="POST" action="{{ $destroyUrl($entry->id) }}"
                                                                onsubmit="return confirm('Supprimer cette entrée de planning ?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit">Supprimer l'entrée</button>
                                                            </form>
                                                        </details>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @forelse ($day['logs'] as $log)
                                    <div>
                                        <strong>{{ $log->author->name }}</strong>
                                        — {{ $log->duration_minutes !== null ? $log->duration_minutes.' min' : 'durée non renseignée' }}
                                        @if ($log->work_done)
                                            <br><span class="muted">{{ Str::limit($log->work_done, 160) }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted">Aucun journal</span>
                                @endforelse
                            </td>
                            <td>
                                {{ $day['planned_minutes'] }} → {{ $day['actual_minutes'] }} min
                                <br><strong>{{ $day['percent'] }} %</strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
