<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Accueil') — Stage Tracker</title>
    <style>
        :root {
            --ink: #1f2933;
            --muted: #616e7c;
            --line: #d9e2ec;
            --accent: #1d4ed8;
            --bg: #f5f7fa;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.6;
        }
        a { color: var(--accent); }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1.5rem;
            background: #fff;
            border-bottom: 1px solid var(--line);
        }
        .brand { font-weight: 700; text-decoration: none; color: var(--ink); }
        .topbar nav { display: flex; align-items: center; gap: 1rem; font-size: .95rem; }
        .topbar nav form { margin: 0; }
        .user { color: var(--muted); }
        .container { max-width: 880px; margin: 2rem auto; padding: 0 1.25rem; }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
        }
        h1 { font-size: 1.55rem; margin-top: 0; }
        h2 { font-size: 1.15rem; margin-top: 0; }
        label { display: block; font-weight: 600; margin: 1rem 0 .35rem; }
        input[type="email"], input[type="password"], input[type="text"], select, textarea {
            width: 100%;
            padding: .6rem .7rem;
            border: 1px solid var(--line);
            border-radius: 6px;
            font: inherit;
            background: #fff;
        }
        textarea { min-height: 110px; resize: vertical; }
        table { width: 100%; border-collapse: collapse; font-size: .95rem; }
        th, td { text-align: left; padding: .55rem .45rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
        pre {
            background: #0f172a;
            color: #e2e8f0;
            padding: .8rem;
            border-radius: 8px;
            overflow-x: auto;
            font-size: .78rem;
            white-space: pre-wrap;
        }
        .button.secondary { background: #fff; color: var(--accent); border: 1px solid var(--accent); }
        button.danger { background: #b91c1c; }
        button.small, .button.small { margin-top: 0; padding: .35rem .7rem; font-size: .85rem; }
        .inline { display: inline; }
        .inline form { display: inline; }
        .pagination { margin-top: 1rem; }
        .pagination svg { width: 1rem; height: 1rem; }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; }
        button, .button {
            display: inline-block;
            margin-top: 1.1rem;
            padding: .6rem 1.1rem;
            border: 0;
            border-radius: 6px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        button.link {
            background: none;
            color: var(--accent);
            padding: 0;
            margin: 0;
            font-weight: 500;
        }
        .error { color: #b91c1c; font-size: .9rem; margin: .4rem 0 0; }
        .flash {
            background: #ecfdf3;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: .7rem 1rem;
            border-radius: 8px;
        }
        .muted { color: var(--muted); }
        .grid { display: grid; gap: 1rem; }
        @media (min-width: 640px) { .grid { grid-template-columns: 1fr 1fr; } }
        .badge {
            display: inline-block;
            padding: .15rem .55rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: .8rem;
            font-weight: 600;
        }
        .footer {
            max-width: 880px;
            margin: 2rem auto 3rem;
            padding: 0 1.25rem;
            color: var(--muted);
            font-size: .85rem;
        }
        .remember { display: flex; align-items: center; gap: .5rem; margin-top: 1rem; font-weight: 400; }
        .remember input { margin: 0; }
    </style>
</head>
<body>
<header class="topbar">
    <a href="{{ route('home') }}" class="brand">Stage Tracker</a>
    <nav>
        @auth
            <a href="{{ route('workspaces.index') }}">Mes espaces</a>
            <span class="user">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link">Se déconnecter</button>
            </form>
        @endauth
    </nav>
</header>

<main class="container">
    @if (session('status'))
        <p class="flash" role="status">{{ session('status') }}</p>
    @endif

    @yield('content')
</main>

<footer class="footer">
    Stage Tracker — suivi de stage et traçabilité des activités professionnelles.
</footer>
</body>
</html>
