<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Authorization\EffectivePermissions;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Journal\Models\DailyLogActivity;
use App\Domain\Project\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyLogController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Workspace $workspace, string $internship): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);

        return view('daily_logs.index', [
            'workspace' => $workspace,
            'internship' => $internship,
            'logs' => $internship->dailyLogs()->with('author')->orderByDesc('work_date')->get(),
        ]);
    }

    public function create(Workspace $workspace, string $internship): View
    {
        $internship = $workspace->internships()->findOrFail($internship);

        $this->authorizeManage($workspace, $internship);

        return view('daily_logs.create', [
            'workspace' => $workspace,
            'internship' => $internship,
        ]);
    }

    public function store(Request $request, Workspace $workspace, string $internship): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);

        $this->authorizeManage($workspace, $internship);

        $validated = $request->validate([
            'work_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($internship, $request): void {
                    $exists = DailyLog::query()
                        ->where('internship_id', $internship->getKey())
                        ->where('author_id', $request->user()->id)
                        ->where('work_date', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Une journée existe déjà pour cette date.');
                    }
                },
            ],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'work_done' => ['nullable', 'string', 'max:5000'],
            'difficulties' => ['nullable', 'string', 'max:5000'],
            'solutions' => ['nullable', 'string', 'max:5000'],
            'results' => ['nullable', 'string', 'max:5000'],
            'remaining' => ['nullable', 'string', 'max:5000'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $log = DailyLog::query()->create(array_merge($validated, [
            'internship_id' => $internship->getKey(),
            'workspace_id' => $workspace->getKey(),
            'author_id' => $request->user()->id,
            'status' => 'draft',
        ]));

        $this->audit->record(
            'daily_log.created',
            $workspace,
            $log,
            [
                'subject' => $internship->title,
                'status' => 'draft',
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.daily_logs.show', [$workspace, $internship, $log])
            ->with('status', 'Journée créée.');
    }

    public function show(Workspace $workspace, string $internship, string $log): View
    {
        $this->authorize('view', $workspace);

        $internship = $workspace->internships()->findOrFail($internship);

        // T-052 : lecture du journal soumise aux permissions effectives
        // (supervisor/tutor rattachés, sinon accès refusé).
        abort_unless(
            EffectivePermissions::resolveForInternship(auth()->user(), $workspace, $internship)['view_journal'],
            403,
        );

        $logModel = $internship->dailyLogs()->with(['author', 'activities.activity'])->findOrFail($log);

        return view('daily_logs.show', [
            'workspace' => $workspace,
            'internship' => $internship,
            'log' => $logModel,
            'activities' => $internship->projects()->with('activities')->get()->flatMap->activities,
        ]);
    }

    public function edit(Workspace $workspace, string $internship, string $log): View
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $logModel = $internship->dailyLogs()->findOrFail($log);

        $this->authorizeManage($workspace, $internship);

        if ($logModel->isClosed()) {
            abort(403, 'Une journée clôturée est en lecture seule.');
        }

        return view('daily_logs.edit', [
            'workspace' => $workspace,
            'internship' => $internship,
            'log' => $logModel,
        ]);
    }

    public function update(Request $request, Workspace $workspace, string $internship, string $log): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $logModel = $internship->dailyLogs()->findOrFail($log);

        $this->authorizeManage($workspace, $internship);

        if ($logModel->isClosed()) {
            abort(403);
        }

        $validated = $request->validate([
            'work_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($internship, $request, $logModel): void {
                    $exists = DailyLog::query()
                        ->where('internship_id', $internship->getKey())
                        ->where('author_id', $request->user()->id)
                        ->where('work_date', $value)
                        ->where('id', '!=', $logModel->getKey())
                        ->exists();

                    if ($exists) {
                        $fail('Une journée existe déjà pour cette date.');
                    }
                },
            ],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'work_done' => ['nullable', 'string', 'max:5000'],
            'difficulties' => ['nullable', 'string', 'max:5000'],
            'solutions' => ['nullable', 'string', 'max:5000'],
            'results' => ['nullable', 'string', 'max:5000'],
            'remaining' => ['nullable', 'string', 'max:5000'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $logModel->fill($validated);
        $changed = array_keys($logModel->getDirty());
        $logModel->save();

        $this->audit->record(
            'daily_log.updated',
            $workspace,
            $logModel,
            [
                'subject' => $internship->title,
                'status' => $logModel->status,
                'changed' => implode(',', $changed),
            ],
            $request->user(),
        );

        return redirect()
            ->route('workspace.internships.daily_logs.show', [$workspace, $internship, $logModel])
            ->with('status', 'Journée mise à jour.');
    }

    public function close(Workspace $workspace, string $internship, string $log): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $logModel = $internship->dailyLogs()->findOrFail($log);

        $this->authorizeManage($workspace, $internship);

        if (! $logModel->isClosed()) {
            $logModel->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
            ])->save();

            $this->audit->record(
                'daily_log.closed',
                $workspace,
                $logModel,
                [
                    'subject' => $internship->title,
                    'status' => 'closed',
                ],
                auth()->user(),
            );
        }

        return redirect()
            ->route('workspace.internships.daily_logs.show', [$workspace, $internship, $logModel])
            ->with('status', 'Journée clôturée.');
    }

    public function linkActivity(Request $request, Workspace $workspace, string $internship, string $log): RedirectResponse
    {
        $internship = $workspace->internships()->findOrFail($internship);
        $logModel = $internship->dailyLogs()->findOrFail($log);

        $this->authorizeManage($workspace, $internship);

        if ($logModel->isClosed()) {
            abort(403);
        }

        $validated = $request->validate([
            'activity_id' => ['required', 'uuid'],
            'contribution' => ['nullable', 'string', 'max:5000'],
        ]);

        $activity = Activity::query()->findOrFail($validated['activity_id']);

        DailyLogActivity::query()->firstOrCreate(
            [
                'daily_log_id' => $logModel->getKey(),
                'activity_id' => $activity->getKey(),
            ],
            [
                'workspace_id' => $workspace->getKey(),
                'contribution' => $validated['contribution'] ?? null,
            ],
        );

        return redirect()
            ->route('workspace.internships.daily_logs.show', [$workspace, $internship, $logModel])
            ->with('status', 'Activité liée à la journée.');
    }

    private function authorizeManage(Workspace $workspace, Internship $internship): void
    {
        $user = auth()->user();
        $isManager = in_array($workspace->roleFor($user), ['owner', 'admin'], true);
        $isTrainee = $internship->trainee_id === $user?->id;

        if (! $isManager && ! $isTrainee) {
            abort(403);
        }
    }
}
