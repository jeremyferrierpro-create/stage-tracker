<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\ActivityCompetencyController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CompetencyController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportTemplateController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('workspaces.index')
        : redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Invitations (T-007)
|--------------------------------------------------------------------------
| Le token en clair circule uniquement dans le lien envoyÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© par e-mail.
| Seul son hash SHA-256 est stockÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© en base.
*/
Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->name('invitations.show');

Route::post('/invitations/{token}/register', [InvitationController::class, 'register'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('guest')
    ->name('invitations.register');

Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('auth')
    ->name('invitations.accept');

Route::post('/invitations/{token}/decline', [InvitationController::class, 'decline'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('auth')
    ->name('invitations.decline');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/compte/mot-de-passe', [AccountPasswordController::class, 'edit'])
        ->name('account.password.edit');
    Route::post('/compte/mot-de-passe', [AccountPasswordController::class, 'update'])
        ->name('account.password.update');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])
        ->name('workspaces.index');

    Route::get('/aide', [HelpController::class, 'index'])
        ->name('help');

    Route::prefix('/w/{workspace}')
        ->whereUuid('workspace')
        ->middleware('workspace.member')
        ->group(function (): void {
            Route::get('/dashboard', [WorkspaceController::class, 'dashboard'])
                ->name('workspace.dashboard');

            Route::get('/reports', [ReportController::class, 'workspaceIndex'])
                ->name('workspace.reports.index');

            // Membres et invitations (T-007).
            Route::get('/members', [MemberController::class, 'index'])
                ->name('workspace.members.index');

            Route::post('/invitations', [MemberController::class, 'store'])
                ->name('workspace.invitations.store');

            Route::delete('/invitations/{invitation}', [MemberController::class, 'destroy'])
                ->whereUuid('invitation')
                ->name('workspace.invitations.destroy');

            // Journal d'audit (T-009).
            Route::get('/audit', [AuditLogController::class, 'index'])
                ->name('workspace.audit.index');

            // Compétences du workspace (simplification 1.6.0).
            Route::get('/competencies', [CompetencyController::class, 'index'])
                ->name('workspace.competencies.index');
            Route::get('/competencies/create', [CompetencyController::class, 'create'])
                ->name('workspace.competencies.create');
            Route::post('/competencies', [CompetencyController::class, 'store'])
                ->name('workspace.competencies.store');
            Route::delete('/competencies/{competency}', [CompetencyController::class, 'destroy'])
                ->whereUuid('competency')
                ->name('workspace.competencies.destroy');

            // Stages (T-023).
            Route::get('/internships', [InternshipController::class, 'index'])
                ->name('workspace.internships.index');
            Route::get('/internships/create', [InternshipController::class, 'create'])
                ->name('workspace.internships.create');
            Route::post('/internships', [InternshipController::class, 'store'])
                ->name('workspace.internships.store');
            Route::get('/internships/{internship}', [InternshipController::class, 'show'])
                ->whereUuid('internship')
                ->name('workspace.internships.show');
            Route::get('/internships/{internship}/edit', [InternshipController::class, 'edit'])
                ->whereUuid('internship')
                ->name('workspace.internships.edit');
            Route::put('/internships/{internship}', [InternshipController::class, 'update'])
                ->whereUuid('internship')
                ->name('workspace.internships.update');
            Route::post('/internships/{internship}/participants', [InternshipController::class, 'storeParticipant'])
                ->whereUuid('internship')
                ->name('workspace.internships.participants.store');
            Route::delete('/internships/{internship}/participants/{participant}', [InternshipController::class, 'destroyParticipant'])
                ->whereUuid('internship')
                ->whereNumber('participant')
                ->name('workspace.internships.participants.destroy');
            Route::post('/internships/{internship}/participants/{participant}/reactivate', [InternshipController::class, 'reactivateParticipant'])
                ->whereUuid('internship')
                ->whereNumber('participant')
                ->name('workspace.internships.participants.reactivate');

            // Projets (T-024).
            Route::get('/internships/{internship}/projects', [ProjectController::class, 'index'])
                ->whereUuid('internship')
                ->name('workspace.internships.projects.index');
            Route::get('/internships/{internship}/projects/create', [ProjectController::class, 'create'])
                ->whereUuid('internship')
                ->name('workspace.internships.projects.create');
            Route::post('/internships/{internship}/projects', [ProjectController::class, 'store'])
                ->whereUuid('internship')
                ->name('workspace.internships.projects.store');
            Route::get('/internships/{internship}/projects/{project}', [ProjectController::class, 'show'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->name('workspace.internships.projects.show');
            Route::get('/internships/{internship}/projects/{project}/edit', [ProjectController::class, 'edit'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->name('workspace.internships.projects.edit');
            Route::put('/internships/{internship}/projects/{project}', [ProjectController::class, 'update'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->name('workspace.internships.projects.update');

            // Dépôts Git et connecteur GitHub (T-043 → T-045).
            Route::get('/internships/{internship}/projects/{project}/repositories/create', [RepositoryController::class, 'create'])
                ->whereUuid('internship')->whereUuid('project')
                ->name('workspace.internships.projects.repositories.create');
            Route::post('/internships/{internship}/projects/{project}/repositories', [RepositoryController::class, 'store'])
                ->whereUuid('internship')->whereUuid('project')
                ->name('workspace.internships.projects.repositories.store');
            Route::post('/internships/{internship}/projects/{project}/repositories/test-connection', [RepositoryController::class, 'testConnection'])
                ->whereUuid('internship')->whereUuid('project')
                ->name('workspace.internships.projects.repositories.test_connection');
            Route::get('/internships/{internship}/projects/{project}/repositories/{repository}', [RepositoryController::class, 'show'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('repository')
                ->name('workspace.internships.projects.repositories.show');
            Route::post('/internships/{internship}/projects/{project}/repositories/{repository}/sync', [RepositoryController::class, 'sync'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('repository')
                ->name('workspace.internships.projects.repositories.sync');
            Route::post('/internships/{internship}/projects/{project}/repositories/{repository}/commits/{commit}/link-activity', [RepositoryController::class, 'linkActivity'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('repository')->whereUuid('commit')
                ->name('workspace.internships.projects.repositories.link_activity');
            Route::delete('/internships/{internship}/projects/{project}/repositories/{repository}', [RepositoryController::class, 'destroy'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('repository')
                ->name('workspace.internships.projects.repositories.destroy');

            // Commentaires d'activité (T-050).
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/comments', [CommentController::class, 'store'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')
                ->name('workspace.internships.projects.activities.comments.store');
            Route::delete('/internships/{internship}/projects/{project}/activities/{activity}/comments/{comment}', [CommentController::class, 'destroy'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('comment')
                ->name('workspace.internships.projects.activities.comments.destroy');

            // Revues d'activité (T-051).
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/reviews', [ReviewController::class, 'store'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')
                ->name('workspace.internships.projects.activities.reviews.store');
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/reviews/{review}/feedback', [ReviewController::class, 'feedback'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('review')
                ->name('workspace.internships.projects.activities.reviews.feedback');

            // Vue dédiée maître de stage / tuteur (T-054).
            Route::get('/supervisor', [SupervisorController::class, 'dashboard'])
                ->name('workspace.supervisor.dashboard');

            // Compétences d'activité et évaluations (T-060, T-061).
            Route::get('/internships/{internship}/competencies', [ActivityCompetencyController::class, 'matrix'])
                ->whereUuid('internship')
                ->name('workspace.internships.competencies');
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/competencies', [ActivityCompetencyController::class, 'store'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')
                ->name('workspace.internships.projects.activities.competencies.store');
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/competencies/{competency}/document', [ActivityCompetencyController::class, 'document'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('competency')
                ->name('workspace.internships.projects.activities.competencies.document');
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/competencies/{competency}/assessments', [ActivityCompetencyController::class, 'assess'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('competency')
                ->name('workspace.internships.projects.activities.competencies.assess');
            Route::delete('/internships/{internship}/projects/{project}/activities/{activity}/competencies/{competency}', [ActivityCompetencyController::class, 'destroy'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('competency')
                ->name('workspace.internships.projects.activities.competencies.destroy');

            // Modèles de rapports (T-062, gestion admin).
            Route::get('/report-templates', [ReportTemplateController::class, 'index'])
                ->name('workspace.report-templates.index');
            Route::get('/report-templates/create', [ReportTemplateController::class, 'create'])
                ->name('workspace.report-templates.create');
            Route::post('/report-templates', [ReportTemplateController::class, 'store'])
                ->name('workspace.report-templates.store');
            Route::get('/report-templates/{template}', [ReportTemplateController::class, 'show'])
                ->whereUuid('template')
                ->name('workspace.report-templates.show');
            Route::get('/report-templates/{template}/edit', [ReportTemplateController::class, 'edit'])
                ->whereUuid('template')
                ->name('workspace.report-templates.edit');
            Route::put('/report-templates/{template}', [ReportTemplateController::class, 'update'])
                ->whereUuid('template')
                ->name('workspace.report-templates.update');
            Route::delete('/report-templates/{template}', [ReportTemplateController::class, 'destroy'])
                ->whereUuid('template')
                ->name('workspace.report-templates.destroy');
            Route::post('/report-templates/{template}/versions', [ReportTemplateController::class, 'storeVersion'])
                ->whereUuid('template')
                ->name('workspace.report-templates.versions.store');
            Route::post('/report-templates/{template}/versions/{version}/publish', [ReportTemplateController::class, 'publishVersion'])
                ->whereUuid('template')->whereUuid('version')
                ->name('workspace.report-templates.versions.publish');

            // Rapports de stage (T-063).
            Route::get('/internships/{internship}/reports', [ReportController::class, 'index'])
                ->whereUuid('internship')
                ->name('workspace.internships.reports.index');
            Route::post('/internships/{internship}/reports', [ReportController::class, 'store'])
                ->whereUuid('internship')
                ->name('workspace.internships.reports.store');
            Route::delete('/internships/{internship}/reports/{report}', [ReportController::class, 'destroy'])
                ->whereUuid('internship')->whereUuid('report')
                ->name('workspace.internships.reports.destroy');
            Route::post('/internships/{internship}/reports/{report}/retry', [ReportController::class, 'retry'])
                ->whereUuid('internship')->whereUuid('report')
                ->name('workspace.internships.reports.retry');
            Route::get('/internships/{internship}/reports/{report}/download', [ReportController::class, 'download'])
                ->whereUuid('internship')->whereUuid('report')
                ->name('workspace.internships.reports.download');
            Route::get('/internships/{internship}/reports/{report}/export/{format}', [ReportController::class, 'export'])
                ->whereUuid('internship')->whereUuid('report')->whereIn('format', ['pdf', 'docx'])
                ->name('workspace.internships.reports.export');

            // ActivitÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©s (T-025).
            Route::get('/internships/{internship}/projects/{project}/activities', [ActivityController::class, 'index'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->name('workspace.internships.projects.activities.index');
            Route::get('/internships/{internship}/projects/{project}/activities/create', [ActivityController::class, 'create'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->name('workspace.internships.projects.activities.create');
            Route::post('/internships/{internship}/projects/{project}/activities', [ActivityController::class, 'store'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->name('workspace.internships.projects.activities.store');
            Route::get('/internships/{internship}/projects/{project}/activities/{activity}', [ActivityController::class, 'show'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->whereUuid('activity')
                ->name('workspace.internships.projects.activities.show');
            Route::get('/internships/{internship}/projects/{project}/activities/{activity}/edit', [ActivityController::class, 'edit'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->whereUuid('activity')
                ->name('workspace.internships.projects.activities.edit');
            Route::put('/internships/{internship}/projects/{project}/activities/{activity}', [ActivityController::class, 'update'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->whereUuid('activity')
                ->name('workspace.internships.projects.activities.update');
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/archive', [ActivityController::class, 'archive'])
                ->whereUuid('internship')
                ->whereUuid('project')
                ->whereUuid('activity')
                ->name('workspace.internships.projects.activities.archive');
            // TÃƒÆ’Ã‚Â¢ches Kanban (T-026).
            Route::post('/internships/{internship}/projects/{project}/activities/{activity}/tasks', [TaskController::class, 'store'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')
                ->name('workspace.tasks.store');
            Route::put('/internships/{internship}/projects/{project}/activities/{activity}/tasks/{task}', [TaskController::class, 'update'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('task')
                ->name('workspace.tasks.update');
            Route::delete('/internships/{internship}/projects/{project}/activities/{activity}/tasks/{task}', [TaskController::class, 'destroy'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('task')
                ->name('workspace.tasks.destroy');

            // Planning prévisionnel vs journal réel.
            Route::get('/internships/{internship}/planning', [PlanningController::class, 'show'])
                ->whereUuid('internship')
                ->name('workspace.internships.planning.show');
            Route::post('/internships/{internship}/planning', [PlanningController::class, 'store'])
                ->whereUuid('internship')
                ->name('workspace.internships.planning.store');
            Route::put('/internships/{internship}/planning/{entry}', [PlanningController::class, 'update'])
                ->whereUuid('internship')->whereUuid('entry')
                ->name('workspace.internships.planning.update');
            Route::delete('/internships/{internship}/planning/{entry}', [PlanningController::class, 'destroy'])
                ->whereUuid('internship')->whereUuid('entry')
                ->name('workspace.internships.planning.destroy');
            Route::post('/internships/{internship}/planning/{entry}/activity', [PlanningController::class, 'createActivity'])
                ->whereUuid('internship')->whereUuid('entry')
                ->name('workspace.internships.planning.activity.store');

            // Journal quotidien (T-027/T-028).
            Route::get('/internships/{internship}/daily-logs', [DailyLogController::class, 'index'])
                ->whereUuid('internship')
                ->name('workspace.internships.daily_logs.index');
            Route::get('/internships/{internship}/daily-logs/create', [DailyLogController::class, 'create'])
                ->whereUuid('internship')
                ->name('workspace.internships.daily_logs.create');
            Route::post('/internships/{internship}/daily-logs', [DailyLogController::class, 'store'])
                ->whereUuid('internship')
                ->name('workspace.internships.daily_logs.store');
            Route::get('/internships/{internship}/daily-logs/{log}', [DailyLogController::class, 'show'])
                ->whereUuid('internship')->whereUuid('log')
                ->name('workspace.internships.daily_logs.show');
            Route::get('/internships/{internship}/daily-logs/{log}/edit', [DailyLogController::class, 'edit'])
                ->whereUuid('internship')->whereUuid('log')
                ->name('workspace.internships.daily_logs.edit');
            Route::put('/internships/{internship}/daily-logs/{log}', [DailyLogController::class, 'update'])
                ->whereUuid('internship')->whereUuid('log')
                ->name('workspace.internships.daily_logs.update');
            Route::post('/internships/{internship}/daily-logs/{log}/close', [DailyLogController::class, 'close'])
                ->whereUuid('internship')->whereUuid('log')
                ->name('workspace.internships.daily_logs.close');
            Route::post('/internships/{internship}/daily-logs/{log}/activities', [DailyLogController::class, 'linkActivity'])
                ->whereUuid('internship')->whereUuid('log')
                ->name('workspace.internships.daily_logs.link_activity');
            // Preuves et visibilitÃ© (T-041/T-042).
            Route::get('/evidences', [EvidenceController::class, 'index'])
                ->name('workspace.evidences.index');
            Route::get('/evidences/create', [EvidenceController::class, 'create'])
                ->name('workspace.evidences.create');
            Route::post('/evidences', [EvidenceController::class, 'store'])
                ->name('workspace.evidences.store');
            Route::get('/evidences/{evidence}', [EvidenceController::class, 'show'])
                ->whereUuid('evidence')
                ->name('workspace.evidences.show');
            Route::get('/evidences/{evidence}/edit', [EvidenceController::class, 'edit'])
                ->whereUuid('evidence')
                ->name('workspace.evidences.edit');
            Route::put('/evidences/{evidence}', [EvidenceController::class, 'update'])
                ->whereUuid('evidence')
                ->name('workspace.evidences.update');
            Route::post('/evidences/{evidence}/archive', [EvidenceController::class, 'archive'])
                ->whereUuid('evidence')
                ->name('workspace.evidences.archive');
            Route::post('/evidences/{evidence}/link-activity', [EvidenceController::class, 'linkActivity'])
                ->whereUuid('evidence')
                ->name('workspace.evidences.link_activity');
            Route::get('/internships/{internship}/projects/{project}/activities/{activity}/evidences/{evidence}/download', [EvidenceController::class, 'download'])
                ->whereUuid('internship')->whereUuid('project')->whereUuid('activity')->whereUuid('evidence')
                ->name('workspace.evidences.download_activity');
            Route::get('/evidences/{evidence}/download', [EvidenceController::class, 'download'])
                ->whereUuid('evidence')
                ->name('workspace.evidences.download');

            // Messagerie interne (REQ-F-003).
            Route::get('/messages', [ConversationController::class, 'index'])
                ->name('workspace.messages.index');
            Route::post('/messages', [ConversationController::class, 'store'])
                ->name('workspace.messages.store');
            Route::post('/internships/{internship}/messages', [ConversationController::class, 'threadForInternship'])
                ->whereUuid('internship')
                ->name('workspace.internships.messages.thread');
            Route::post('/internships/{internship}/projects/{project}/messages', [ConversationController::class, 'threadForProject'])
                ->whereUuid('internship')->whereUuid('project')
                ->name('workspace.internships.projects.messages.thread');
            Route::get('/messages/{conversation}', [ConversationController::class, 'show'])
                ->whereUuid('conversation')
                ->name('workspace.messages.show');
            Route::post('/messages/{conversation}/messages', [ConversationController::class, 'storeMessage'])
                ->whereUuid('conversation')
                ->name('workspace.messages.messages.store');
            Route::post('/messages/{conversation}/read', [ConversationController::class, 'markRead'])
                ->whereUuid('conversation')
                ->name('workspace.messages.read');
            Route::get('/messages/{conversation}/attachments/{message}', [ConversationController::class, 'downloadAttachment'])
                ->whereUuid('conversation')->whereUuid('message')
                ->name('workspace.messages.attachments.download');

            // Notifications in-app (REQ-F-002).
            Route::get('/notifications', [NotificationController::class, 'index'])
                ->name('workspace.notifications.index');
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
                ->whereUuid('notification')
                ->name('workspace.notifications.read');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
                ->name('workspace.notifications.read_all');
            Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])
                ->whereUuid('notification')
                ->name('workspace.notifications.open');

            // Pense-bête / notes et rappels (REQ-F-004).
            Route::get('/notes', [NoteController::class, 'index'])
                ->name('workspace.notes.index');
            Route::post('/notes', [NoteController::class, 'store'])
                ->name('workspace.notes.store');
            Route::put('/notes/{note}', [NoteController::class, 'update'])
                ->whereUuid('note')
                ->name('workspace.notes.update');
            Route::post('/notes/{note}/toggle', [NoteController::class, 'toggle'])
                ->whereUuid('note')
                ->name('workspace.notes.toggle');
            Route::delete('/notes/{note}', [NoteController::class, 'destroy'])
                ->whereUuid('note')
                ->name('workspace.notes.destroy');
        });
});
