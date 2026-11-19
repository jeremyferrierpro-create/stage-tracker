<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Accueil') — Stage Tracker</title>
    @php
        $vite = app(Illuminate\Foundation\Vite::class);
        $workspace = app(App\Domain\Identity\WorkspaceContext::class)->get();
        $membership = request()->attributes->get('workspace_membership');
        $userWorkspaces = auth()->user()
            ? auth()->user()->workspaces()->wherePivot('status', 'active')->orderBy('name')->get()
            : collect();
        $currentRoute = request()->route() ? request()->route()->getName() : null;
    @endphp
    @if ($vite->isRunningHot() || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- Fallback utilisé quand npm n'est pas encore installé ou le build absent. --}}
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @endif
</head>
<body>
    <a class="skip-link" href="#contenu">Aller au contenu</a>

    <header class="topbar">
        <a href="{{ route('home') }}" class="brand">Stage Tracker <span class="badge-tag">DWWM</span></a>
        <nav aria-label="Navigation principale">
            <a href="{{ route('help') }}" class="nav-help-link">Guide DWWM</a>
            @auth
                <a href="{{ route('workspaces.index') }}">Mes espaces</a>
                <a href="{{ route('account.password.edit') }}">Mot de passe</a>
                <span class="user">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="link">Se déconnecter</button>
                </form>
            @else
                <a href="{{ route('login') }}">Connexion</a>
                <a href="{{ route('register') }}" class="button small">Inscription</a>
            @endauth
        </nav>
    </header>

    @auth
        @if ($workspace && $membership)
            <div class="shell">
                <aside class="sidebar" aria-label="Navigation de l'espace">
                    <input type="checkbox" id="nav-toggle" class="nav-toggle-input"
                           aria-controls="sidebar-content" aria-label="Afficher ou masquer le menu de l'espace">
                    <label for="nav-toggle" class="nav-toggle">☰ Menu</label>

                    <div id="sidebar-content" class="sidebar-inner">
                        <div class="workspace-box">
                            <p class="workspace-label">Espace actif</p>

                            <details class="workspace-switcher">
                                <summary>{{ $workspace->name }}</summary>
                                <ul>
                                    @forelse ($userWorkspaces as $userWorkspace)
                                        <li>
                                            <a href="{{ route('workspace.dashboard', $userWorkspace) }}"
                                               @if ($userWorkspace->id === $workspace->id) aria-current="page" @endif>
                                                {{ $userWorkspace->name }}
                                                <span class="role">
                                                    {{ \App\Domain\Identity\Models\Workspace::roleLabel($userWorkspace->pivot->role) }}
                                                </span>
                                            </a>
                                        </li>
                                    @empty
                                        <li><span class="muted">Aucun autre espace.</span></li>
                                    @endforelse
                                    <li>
                                        <a href="{{ route('workspaces.index') }}">Voir tous les espaces</a>
                                    </li>
                                </ul>
                            </details>

                            <p>
                                <span class="badge">
                                    {{ \App\Domain\Identity\Models\Workspace::roleLabel($membership->role) }}
                                </span>
                            </p>
                        </div>

                        <nav class="workspace-nav" aria-label="Sections de l'espace">
                            <a href="{{ route('workspace.dashboard', $workspace) }}"
                               @if ($currentRoute === 'workspace.dashboard') aria-current="page" @endif>
                                Tableau de bord
                            </a>

                            <a href="{{ route('workspace.internships.index', $workspace) }}"
                               @if (str_starts_with((string) $currentRoute, 'workspace.internships')) aria-current="page" @endif>
                                Stages
                            </a>

                            <a href="{{ route('workspace.evidences.index', $workspace) }}"
                               @if (str_starts_with((string) $currentRoute, 'workspace.evidences')) aria-current="page" @endif>
                                Preuves
                            </a>

                            @if (in_array($membership->role, ['supervisor', 'tutor'], true))
                                <a href="{{ route('workspace.supervisor.dashboard', $workspace) }}"
                                   @if ($currentRoute === 'workspace.supervisor.dashboard') aria-current="page" @endif>
                                    Mes suivis
                                </a>
                            @endif

                            <a href="{{ route('workspace.reports.index', $workspace) }}"
                               @if ($currentRoute === 'workspace.reports.index') aria-current="page" @endif>
                                Rapports
                            </a>

                            @can('manageMembers', $workspace)
                                <a href="{{ route('workspace.members.index', $workspace) }}"
                                   @if ($currentRoute === 'workspace.members.index') aria-current="page" @endif>
                                    Membres et invitations
                                </a>
                                <a href="{{ route('workspace.competencies.index', $workspace) }}"
                                   @if (str_starts_with((string) $currentRoute, 'workspace.competencies')) aria-current="page" @endif>
                                    Compétences
                                </a>
                                <a href="{{ route('workspace.report-templates.index', $workspace) }}"
                                   @if (str_starts_with((string) $currentRoute, 'workspace.report-templates')) aria-current="page" @endif>
                                    Modèles de rapports
                                </a>
                            @endcan

                            @can('viewAudit', $workspace)
                                <a href="{{ route('workspace.audit.index', $workspace) }}"
                                   @if ($currentRoute === 'workspace.audit.index') aria-current="page" @endif>
                                    Journal d'audit
                                </a>
                            @endcan

                            <hr style="border: 0; border-top: 1px solid var(--border-subtle); margin: 0.8rem 0;">

                            <a href="{{ route('help') }}"
                               @if ($currentRoute === 'help') aria-current="page" @endif>
                                Guide & Méthode DWWM
                            </a>
                        </nav>
                    </div>
                </aside>

                <main id="contenu" class="content">
                    @if (session('status'))
                        <p class="flash" role="status">
                            <span>{{ session('status') }}</span>
                            <button type="button" class="clear" data-flash-dismiss aria-label="Masquer le message">×</button>
                        </p>
                    @endif

                    @yield('breadcrumb')

                    @yield('content')
                </main>
            </div>
        @else
            <main id="contenu" class="container">
                @if (session('status'))
                    <p class="flash" role="status">
                        <span>{{ session('status') }}</span>
                        <button type="button" class="clear" data-flash-dismiss aria-label="Masquer le message">×</button>
                    </p>
                @endif

                @yield('content')
            </main>
        @endif
    @else
        <main id="contenu" class="container">
            @yield('content')
        </main>
    @endauth

    <footer class="footer">
        Stage Tracker — suivi de stage et traçabilité des activités professionnelles.
    </footer>
</body>
</html>
