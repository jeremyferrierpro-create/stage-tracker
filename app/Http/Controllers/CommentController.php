<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Commentaires d'activité (T-050). Auteur et date immuables (INV-12) :
 * création et suppression seulement, aucune édition. Le contenu est
 * échappé par Blade ({{ }}) — risque XSS couvert par un test.
 */
class CommentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Workspace $workspace, string $internship, string $project, string $activity): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);

        $permissions = EffectivePermissions::resolveForInternship($request->user(), $workspace, $internshipModel);
        abort_unless($permissions['comment'], 403);

        // Un viewer ne commente que les éléments partagés (matrice §3, 🔶).
        $workspaceRole = $workspace->roleFor($request->user());
        $allowedVisibilities = $workspaceRole === 'viewer'
            ? ['shared']
            : Comment::VISIBILITIES;

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', 'string', 'in:'.implode(',', $allowedVisibilities)],
        ]);

        $comment = Comment::query()->create([
            'activity_id' => $activityModel->getKey(),
            'workspace_id' => $workspace->getKey(),
            'author_id' => $request->user()->id,
            'content' => $validated['content'],
            'visibility' => $validated['visibility'],
        ]);

        $this->audit->record(
            'comment.created',
            $workspace,
            $comment,
            [
                'subject' => $activityModel->title,
                'status' => $comment->visibility,
            ],
            $request->user(),
        );

        return redirect()
            ->back()
            ->with('status', 'Commentaire ajouté.');
    }

    public function destroy(Request $request, Workspace $workspace, string $internship, string $project, string $activity, string $comment): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);
        $activityModel = $projectModel->activities()->findOrFail($activity);
        $commentModel = $activityModel->comments()->findOrFail($comment);

        $isAuthor = $commentModel->author_id === $request->user()->id;
        $isManager = in_array($workspace->roleFor($request->user()), ['owner', 'admin'], true);

        if (! $isAuthor && ! $isManager) {
            abort(403);
        }

        $commentModel->delete();

        $this->audit->record(
            'comment.deleted',
            $workspace,
            $activityModel,
            [
                'subject' => $activityModel->title,
                'reason' => $isAuthor ? 'auteur' : 'gestion',
            ],
            $request->user(),
        );

        return redirect()->back()->with('status', 'Commentaire supprimé.');
    }
}
