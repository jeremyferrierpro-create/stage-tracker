<?php

use App\Http\Controllers\ActivityCompetencyController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\FrameworkBlockController;
use App\Http\Controllers\FrameworkCompetencyController;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\FrameworkCriterionController;
use App\Http\Controllers\FrameworkVersionController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportTemplateController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TrainingEnrollmentController;
use App\Http\Controllers\TrainingProgramController;
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
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])
        ->name('workspaces.index');

    Route::prefix('/w/{workspace}')
        ->whereUuid('workspace')
        ->middleware('workspace.member')
        ->group(function (): void {
            Route::get('/dashboard', [WorkspaceController::class, 'dashboard'])
                ->name('workspace.dashboard');

            // Membres et invitations (T-007).
            Route::get('/members', [MemberController::class, 'index'])
                ->name('workspace.members.index');

            Route::post('/invitations', [MemberController::class, 'store'])
                ->name('workspace.invitations.store');

            Route::delete('/invitations/{invitation}', [MemberController::class, 'destroy'])
                ->whereUuid('invitation')
                ->name('workspace.invitations.destroy');

            // Organisations (T-008).
            Route::get('/organizations', [OrganizationController::class, 'index'])
                ->name('workspace.organizations.index');

            Route::get('/organizations/create', [OrganizationController::class, 'create'])
                ->name('workspace.organizations.create');

            Route::post('/organizations', [OrganizationController::class, 'store'])
                ->name('workspace.organizations.store');

            Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])
                ->whereUuid('organization')
                ->name('workspace.organizations.edit');

            Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])
                ->whereUuid('organization')
                ->name('workspace.organizations.update');

            Route::post('/organizations/{organization}/archive', [OrganizationController::class, 'archive'])
                ->whereUuid('organization')
                ->name('workspace.organizations.archive');

            // Journal d'audit (T-009).
            Route::get('/audit', [AuditLogController::class, 'index'])
                ->name('workspace.audit.index');

            // Formations (T-020).
            Route::get('/training', [TrainingProgramController::class, 'index'])
                ->name('workspace.training.index');
            Route::get('/training/create', [TrainingProgramController::class, 'create'])
                ->name('workspace.training.create');
            Route::post('/training', [TrainingProgramController::class, 'store'])
                ->name('workspace.training.store');
            Route::get('/training/{program}/edit', [TrainingProgramController::class, 'edit'])
                ->whereUuid('program')
                ->name('workspace.training.edit');
            Route::put('/training/{program}', [TrainingProgramController::class, 'update'])
                ->whereUuid('program')
                ->name('workspace.training.update');
            Route::post('/training/{program}/enrollments', [TrainingEnrollmentController::class, 'store'])
                ->whereUuid('program')
                ->name('workspace.training.enrollments.store');
            Route::post('/training/{program}/enrollments/{enrollment}/status', [TrainingEnrollmentController::class, 'updateStatus'])
                ->whereUuid('program')
                ->whereNumber('enrollment')
                ->name('workspace.training.enrollments.status');
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
        });
    // RÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©fÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©rentiels (T-021/T-022) ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ressource globale, administration par propriÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©taires/admins actifs.
    Route::middleware('auth')->group(function (): void {
        Route::get('/frameworks', [FrameworkController::class, 'index'])
            ->name('frameworks.index');
        Route::get('/frameworks/create', [FrameworkController::class, 'create'])
            ->name('frameworks.create');
        Route::post('/frameworks', [FrameworkController::class, 'store'])
            ->name('frameworks.store');
        Route::get('/frameworks/{framework}', [FrameworkController::class, 'show'])
            ->whereUuid('framework')
            ->name('frameworks.show');
        Route::get('/frameworks/{framework}/edit', [FrameworkController::class, 'edit'])
            ->whereUuid('framework')
            ->name('frameworks.edit');
        Route::put('/frameworks/{framework}', [FrameworkController::class, 'update'])
            ->whereUuid('framework')
            ->name('frameworks.update');
        Route::post('/frameworks/{framework}/versions', [FrameworkVersionController::class, 'store'])
            ->whereUuid('framework')
            ->name('frameworks.versions.store');
        Route::post('/framework-versions/{version}/publish', [FrameworkVersionController::class, 'publish'])
            ->whereUuid('version')
            ->name('frameworks.versions.publish');
        Route::post('/framework-versions/{version}/blocks', [FrameworkBlockController::class, 'store'])
            ->whereUuid('version')
            ->name('frameworks.blocks.store');
        Route::delete('/framework-versions/{version}/blocks/{block}', [FrameworkBlockController::class, 'destroy'])
            ->whereUuid('version')
            ->whereUuid('block')
            ->name('frameworks.blocks.destroy');
        Route::post('/framework-versions/{version}/competencies', [FrameworkCompetencyController::class, 'store'])
            ->whereUuid('version')
            ->name('frameworks.competencies.store');
        Route::delete('/framework-versions/{version}/competencies/{competency}', [FrameworkCompetencyController::class, 'destroy'])
            ->whereUuid('version')
            ->whereUuid('competency')
            ->name('frameworks.competencies.destroy');
        Route::post('/framework-competencies/{competency}/criteria', [FrameworkCriterionController::class, 'store'])
            ->whereUuid('competency')
            ->name('frameworks.criteria.store');
        Route::delete('/framework-competencies/{competency}/criteria/{criterion}', [FrameworkCriterionController::class, 'destroy'])
            ->whereUuid('competency')
            ->whereUuid('criterion')
            ->name('frameworks.criteria.destroy');
    });
});
