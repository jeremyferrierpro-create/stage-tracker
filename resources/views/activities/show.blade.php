@extends('layouts.app')

@section('title', $activity->title)

@section('breadcrumb')
    <x-breadcrumb :items="[
        ['label' => 'Mes espaces', 'url' => route('workspaces.index')],
        ['label' => $workspace->name, 'url' => route('workspace.dashboard', $workspace)],
        ['label' => 'Stages', 'url' => route('workspace.internships.index', $workspace)],
        ['label' => $internship->title, 'url' => route('workspace.internships.show', [$workspace, $internship])],
        ['label' => 'Projets', 'url' => route('workspace.internships.projects.index', [$workspace, $internship])],
        ['label' => $project->name, 'url' => route('workspace.internships.projects.show', [$workspace, $internship, $project])],
        ['label' => 'Activités', 'url' => route('workspace.internships.projects.activities.index', [$workspace, $internship, $project])],
        ['label' => $activity->title],
    ]"/>
@endsection

@section('content')
    <h1>{{ $activity->title }}</h1>
    <p>
        <span class="badge">{{ $activity->typeLabel() }}</span>
        <span class="badge">{{ $activity->statusLabel() }}</span>
        <span class="badge">{{ $activity->priorityLabel() }}</span>
        @if ($activity->archived_at)
            <span class="muted">(archivée)</span>
        @endif
    </p>

    <div class="card">
        @if ($activity->description)
            <p><strong>Description :</strong> {{ $activity->description }}</p>
        @endif
        @if ($activity->start_date || $activity->end_date)
            <p><strong>Période :</strong> {{ $activity->start_date?->format('d/m/Y') ?? '—' }} → {{ $activity->end_date?->format('d/m/Y') ?? '—' }}</p>
        @endif
        @foreach (['objectives' => 'Objectifs', 'results' => 'Résultats', 'difficulties' => 'Difficultés', 'solutions' => 'Solutions'] as $field => $label)
            @if ($activity->{$field})
                <p><strong>{{ $label }} :</strong> {{ $activity->{$field} }}</p>
            @endif
        @endforeach
    </div>

    <div class="card">
        <h2>Tâches (Kanban)</h2>

        @if ($canManage)
        <form method="POST" action="{{ route('workspace.tasks.store', [$workspace, $internship, $project, $activity]) }}" class="actions" style="margin-bottom: 1rem;">
            @csrf
            <div>
                <label>Titre</label>
                <input type="text" name="title" required maxlength="200" placeholder="Nouvelle tâche">
            </div>
            <div>
                <label>Priorité</label>
                <select name="priority">
                    @foreach (\App\Domain\Project\Models\Task::PRIORITIES as $priority)
                        <option value="{{ $priority }}">{{ \App\Domain\Project\Models\Task::PRIORITY_LABELS[$priority] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Statut</label>
                <select name="status">
                    @foreach (\App\Domain\Project\Models\Task::STATUSES as $status)
                        <option value="{{ $status }}">{{ \App\Domain\Project\Models\Task::STATUS_LABELS[$status] }}</option>
                    @endforeach
                </select>
            </div>
            <div style="align-self: end;">
                <button type="submit" class="small">Ajouter</button>
            </div>
        </form>
        @endif

        @if ($tasks->isEmpty())
            <p class="muted">Aucune tâche.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Statut</th>
                        <th>Priorité</th>
                        <th>Échéance</th>
                        @if ($canManage) <th></th> @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $task)
                        <tr>
                            <td>{{ $task->title }}</td>
                            <td>
                                @if ($canManage)
                                <form method="POST" action="{{ route('workspace.tasks.update', [$workspace, $internship, $project, $activity, $task]) }}" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" onchange="this.form.submit()">
                                        @foreach (\App\Domain\Project\Models\Task::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($task->status === $status)>
                                                {{ \App\Domain\Project\Models\Task::STATUS_LABELS[$status] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                                @else
                                    {{ $task->statusLabel() }}
                                @endif
                            </td>
                            <td>{{ $task->priorityLabel() }}</td>
                            <td>{{ $task->due_date?->format('d/m/Y') ?? '—' }}</td>
                            @if ($canManage)
                            <td>
                                <form method="POST" action="{{ route('workspace.tasks.destroy', [$workspace, $internship, $project, $activity, $task]) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger small">Supprimer</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Preuves liées</h2>
        @if ($evidences->isEmpty())
            <p class="muted">Aucune preuve liée à cette activité.</p>
        @else
            <ul>
                @foreach ($evidences as $evidence)
                    <li>
                        <a href="{{ route('workspace.evidences.show', [$workspace, $evidence]) }}">{{ $evidence->title }}</a>
                        <span class="badge">{{ $evidence->typeLabel() }}</span>
                        @if ($evidence->pivot->note) — {{ $evidence->pivot->note }} @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <p>
            <a class="button secondary small" href="{{ route('workspace.evidences.index', $workspace) }}">Gérer les preuves</a>
        </p>
    </div>

    <div class="card">
        <h2>Commentaires</h2>

        @if ($canComment)
        <form method="POST" action="{{ route('workspace.internships.projects.activities.comments.store', [$workspace, $internship, $project, $activity]) }}" style="margin-bottom: 1rem;">
            @csrf
            <label for="comment-content">Votre commentaire</label>
            <textarea id="comment-content" name="content" rows="3" required maxlength="5000" placeholder="Votre commentaire (échappé automatiquement)"></textarea>
            <label for="comment-visibility">Visibilité</label>
            <select id="comment-visibility" name="visibility">
                @foreach ($commentVisibilities as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="actions">
                <button type="submit" class="small">Commenter</button>
            </div>
        </form>
        @endif

        @if ($comments->isEmpty())
            <p class="muted">Aucun commentaire.</p>
        @else
            <ul class="comment-list">
                @foreach ($comments as $comment)
                    <li>
                        <strong>{{ $comment->author->name }}</strong>
                        <span class="muted">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                        <span class="badge">{{ $comment->visibility }}</span>
                        <p>{{ $comment->content }}</p>
                        @if ($comment->author_id === auth()->id() || $canManage)
                            <form method="POST" action="{{ route('workspace.internships.projects.activities.comments.destroy', [$workspace, $internship, $project, $activity, $comment]) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger small">Supprimer</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="card">
        <h2>Revues</h2>

        @if ($canManage && $reviewers->isNotEmpty())
        <form method="POST" action="{{ route('workspace.internships.projects.activities.reviews.store', [$workspace, $internship, $project, $activity]) }}" style="margin-bottom: 1rem;">
            @csrf
            <label for="review-reviewer">Demander une revue à</label>
            <select id="review-reviewer" name="reviewer_id" required>
                <option value="">— réviseur —</option>
                @foreach ($reviewers as $participant)
                    <option value="{{ $participant->user_id }}">{{ $participant->user->name }} ({{ \App\Domain\Internship\Models\InternshipParticipant::ROLE_LABELS[$participant->role] }})</option>
                @endforeach
            </select>
            <div class="actions">
                <button type="submit" class="small">Demander la revue</button>
            </div>
        </form>
        @endif

        @if ($reviews->isEmpty())
            <p class="muted">Aucune revue demandée.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Réviseur</th>
                        <th>Statut</th>
                        <th>Feedback</th>
                        <th>Demandée le</th>
                        @if ($canReview) <th></th> @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reviews as $review)
                        <tr>
                            <td>{{ $review->reviewer->name }}</td>
                            <td><span class="badge">{{ $review->statusLabel() }}</span></td>
                            <td>{{ $review->feedback ?? '—' }}</td>
                            <td>{{ $review->created_at->format('d/m/Y H:i') }}</td>
                            @if ($canReview && $review->reviewer_id === auth()->id())
                            <td>
                                <form method="POST" action="{{ route('workspace.internships.projects.activities.reviews.feedback', [$workspace, $internship, $project, $activity, $review]) }}" class="inline">
                                    @csrf
                                    <select name="status">
                                        <option value="in_progress">En cours</option>
                                        <option value="approved">Approuvée</option>
                                        <option value="changes_requested">Modifications demandées</option>
                                    </select>
                                    <input type="text" name="feedback" maxlength="5000" placeholder="Feedback (optionnel)">
                                    <button type="submit" class="small">Enregistrer</button>
                                </form>
                            </td>
                            @else
                            <td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="actions">
        @if ($canManage)
            <a class="button secondary" href="{{ route('workspace.internships.projects.activities.edit', [$workspace, $internship, $project, $activity]) }}">Modifier</a>
        @endif
        @if ($canManage)
        <form method="POST" action="{{ route('workspace.internships.projects.activities.archive', [$workspace, $internship, $project, $activity]) }}" class="inline">
            @csrf
            <button type="submit" class="danger small">Archiver</button>
        </form>
        @endif
    </div>
@endsection