<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
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
| Le token en clair circule uniquement dans le lien envoyé par e-mail.
| Seul son hash SHA-256 est stocké en base.
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
        });
});
