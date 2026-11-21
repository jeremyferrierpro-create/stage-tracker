<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Collaboration\Models\Conversation;
use App\Domain\Collaboration\Models\ConversationParticipant;
use App\Domain\Collaboration\Models\Message;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Messagerie interne (REQ-F-003) : conversations directes, de groupe et
 * fils rattachés à un projet ou un stage. Les fils projet/stage sont
 * visibles par tous les membres actifs de l'espace et chacun y est
 * rattaché automatiquement à la première ouverture.
 */
class ConversationController extends Controller
{
    private const ATTACHMENT_MIMES = 'jpg,jpeg,png,gif,webp,pdf,txt,doc,docx,xls,xlsx,zip';

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, Workspace $workspace): View
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where(function ($q) use ($user): void {
                $q->whereIn('type', ['project', 'internship'])
                    ->orWhereHas('participants', fn ($p) => $p->where('users.id', $user->id));
            })
            ->with(['project', 'internship', 'participants'])
            ->get()
            ->sortByDesc(fn (Conversation $c) => $c->messages()->max('created_at'))
            ->values();

        $unread = [];
        foreach ($conversations as $conversation) {
            $unread[$conversation->id] = $this->unreadCount($conversation, $user);
        }

        $members = User::query()
            ->whereIn('id', $workspace->members()->where('status', 'active')->pluck('user_id'))
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('messages.index', [
            'workspace' => $workspace,
            'conversations' => $conversations,
            'unread' => $unread,
            'members' => $members,
        ]);
    }

    /**
     * Crée une conversation directe (user_id) ou de groupe (user_ids[] + title).
     */
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['direct', 'group'])],
            'user_id' => ['required_if:type,direct', 'nullable', 'integer'],
            'user_ids' => ['required_if:type,group', 'nullable', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $memberIds = $workspace->members()->where('status', 'active')->pluck('user_id')->all();
        $targets = $validated['type'] === 'direct'
            ? [$validated['user_id']]
            : $validated['user_ids'];

        if (count(array_diff($targets, $memberIds)) > 0 || count($targets) === 0) {
            return back()->withErrors(['user_id' => 'Destinataire invalide : membre actif requis.']);
        }

        if ($validated['type'] === 'direct') {
            $ids = array_merge([$request->user()->id], $targets);
            sort($ids);
            $directKey = implode(':', $ids);

            $conversation = Conversation::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'direct_key' => $directKey],
                [
                    'type' => 'direct',
                    'created_by' => $request->user()->id,
                ],
            );
        } else {
            $conversation = Conversation::query()->create([
                'workspace_id' => $workspace->id,
                'type' => 'group',
                'title' => $validated['title'] ?? 'Groupe',
                'created_by' => $request->user()->id,
            ]);
        }

        $participantIds = $validated['type'] === 'direct'
            ? array_merge([$request->user()->id], $targets)
            : array_merge([$request->user()->id], $validated['user_ids']);

        foreach (array_unique($participantIds) as $userId) {
            ConversationParticipant::query()->firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ]);
        }

        return redirect()->route('workspace.messages.show', [$workspace, $conversation]);
    }

    public function threadForInternship(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $model = $workspace->internships()->findOrFail($internship);

        $conversation = $this->getOrCreateThread($workspace, $request->user(), 'internship', [
            'internship_id' => $model->id,
        ]);

        return redirect()->route('workspace.messages.show', [$workspace, $conversation]);
    }

    public function threadForProject(Request $request, Workspace $workspace, string $internship, string $project): RedirectResponse
    {
        $internshipModel = $workspace->internships()->findOrFail($internship);
        $projectModel = $internshipModel->projects()->findOrFail($project);

        $conversation = $this->getOrCreateThread($workspace, $request->user(), 'project', [
            'project_id' => $projectModel->id,
        ]);

        return redirect()->route('workspace.messages.show', [$workspace, $conversation]);
    }

    public function show(Request $request, Workspace $workspace, string $conversation): View
    {
        $conversation = $this->findConversation($workspace, $conversation);
        $user = $request->user();

        $this->ensureAccess($conversation, $user, $workspace);
        $this->ensureParticipant($conversation, $user);
        $this->touchLastRead($conversation, $user);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('user')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return view('messages.show', [
            'workspace' => $workspace,
            'conversation' => $conversation->load(['project', 'internship', 'participants']),
            'messages' => $messages,
        ]);
    }

    public function storeMessage(Request $request, Workspace $workspace, string $conversation): RedirectResponse
    {
        $conversation = $this->findConversation($workspace, $conversation);
        $user = $request->user();

        $this->ensureAccess($conversation, $user, $workspace);
        $this->ensureParticipant($conversation, $user);

        $validated = $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:'.self::ATTACHMENT_MIMES],
        ]);

        $attributes = [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $validated['body'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->storeAs(
                'messages/'.$conversation->id,
                $file->hashName(),
                'local',
            );
            $attributes += [
                'attachment_path' => $path,
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime' => $file->getMimeType(),
            ];
        }

        $message = Message::query()->create($attributes);
        $this->touchLastRead($conversation, $user);
        $this->notifyRecipients($conversation, $user, $workspace);

        $this->audit->record(
            'message.sent',
            $workspace,
            $message,
            ['status' => $conversation->type, 'count' => 1],
            $user,
        );

        return redirect()->route('workspace.messages.show', [$workspace, $conversation]);
    }

    public function markRead(Request $request, Workspace $workspace, string $conversation): RedirectResponse
    {
        $conversation = $this->findConversation($workspace, $conversation);
        $this->touchLastRead($conversation, $request->user());

        return back();
    }

    public function downloadAttachment(
        Request $request,
        Workspace $workspace,
        string $conversation,
        string $message,
    ): StreamedResponse {
        $conversation = $this->findConversation($workspace, $conversation);
        $this->ensureAccess($conversation, $request->user(), $workspace);

        $message = $conversation->messages()->findOrFail($message);
        abort_unless($message->hasAttachment(), 404);

        return Storage::disk('local')->download(
            $message->attachment_path,
            $message->attachment_name,
        );
    }

    // --- Helpers ---------------------------------------------------------

    private function findConversation(Workspace $workspace, string $id): Conversation
    {
        return Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($id);
    }

    /**
     * Accès : les fils projet/stage sont ouverts à tout membre actif ;
     * les conversations direct/groupe exigent la participation.
     */
    private function ensureAccess(Conversation $conversation, User $user, Workspace $workspace): void
    {
        if (in_array($conversation->type, ['project', 'internship'], true)) {
            abort_if($workspace->roleFor($user) === null, 403);

            return;
        }

        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($isParticipant, 403);
    }

    private function ensureParticipant(Conversation $conversation, User $user): void
    {
        ConversationParticipant::query()->firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
    }

    private function touchLastRead(Conversation $conversation, User $user): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    private function unreadCount(Conversation $conversation, User $user): int
    {
        $lastRead = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->value('last_read_at');

        return $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->when($lastRead !== null, fn ($q) => $q->where('created_at', '>', $lastRead))
            ->count();
    }

    private function getOrCreateThread(
        Workspace $workspace,
        User $user,
        string $type,
        array $attributes,
    ): Conversation {
        $conversation = Conversation::query()->firstOrCreate(
            ['workspace_id' => $workspace->id] + $attributes,
            ['type' => $type, 'created_by' => $user->id],
        );

        $this->ensureParticipant($conversation, $user);

        return $conversation;
    }

    private function notifyRecipients(Conversation $conversation, User $sender, Workspace $workspace): void
    {
        if (in_array($conversation->type, ['project', 'internship'], true)) {
            $recipients = User::query()
                ->whereIn('id', $workspace->members()->where('status', 'active')->pluck('user_id'))
                ->where('id', '!=', $sender->id)
                ->get();
        } else {
            $recipients = $conversation->participants()->where('users.id', '!=', $sender->id)->get();
        }

        $url = route('workspace.messages.show', [$workspace, $conversation]);

        foreach ($recipients as $recipient) {
            $this->notifications->send(
                $workspace,
                $recipient,
                'message',
                'Nouveau message de '.$sender->name,
                $conversation->displayTitle(),
                ['url' => $url, 'conversation_id' => $conversation->id],
            );
        }
    }
}
