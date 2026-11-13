<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FrameworkBlockController;
use App\Http\Controllers\FrameworkCompetencyController;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\FrameworkCriterionController;
use App\Http\Controllers\FrameworkVersionController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
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
| Le token en clair circule uniquement dans le lien envoyÃ© par e-mail.
| Seul son hash SHA-256 est stockÃ© en base.
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
        });
    // RÃ©fÃ©rentiels (T-021/T-022) â€” ressource globale, administration par propriÃ©taires/admins actifs.
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
