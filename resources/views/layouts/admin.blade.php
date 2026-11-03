<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin — reprice-markets')</title>
    <style>
        :root {
            --bg: #111827;
            --panel: #1f2937;
            --card: #273449;
            --text: #f3f4f6;
            --muted: #9ca3af;
            --accent: #38bdf8;
            --border: #374151;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(circle at top left, #1e3a5f, var(--bg) 45%);
            color: var(--text);
            min-height: 100vh;
        }
        a { color: var(--accent); text-decoration: none; }
        .shell { max-width: 1180px; margin: 0 auto; padding: 1.25rem; }
        header.appbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap;
            border-bottom: 1px solid var(--border); padding-bottom: .9rem;
        }
        .brand { font-weight: 800; letter-spacing: .02em; }
        .brand span { color: var(--accent); }
        nav { display: flex; gap: .8rem; flex-wrap: wrap; align-items: center; }
        nav a { color: var(--muted); font-weight: 600; }
        nav a:hover, nav a.active { color: #fff; }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; padding: 1rem; margin-bottom: 1rem;
        }
        .muted { color: var(--muted); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .4rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { color: var(--muted); font-size: .8rem; text-transform: uppercase; }
        label { display: block; font-weight: 600; margin-bottom: .35rem; }
        input, select, textarea {
            width: 100%; padding: .6rem .7rem; border: 1px solid var(--border);
            border-radius: 8px; margin-bottom: .9rem; font: inherit;
            background: #111827; color: var(--text);
        }
        .btn {
            display: inline-block; background: var(--accent); color: #0b1220; border: 0;
            border-radius: 8px; padding: .55rem .9rem; font-weight: 700; cursor: pointer;
        }
        .btn.secondary { background: #4b5563; color: #fff; }
        .flash {
            background: #0c4a6e; border: 1px solid #38bdf8; color: #e0f2fe;
            padding: .8rem 1rem; border-radius: 8px; margin-bottom: 1rem;
        }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .stat { font-size: 1.75rem; font-weight: 700; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
    </style>
</head>
<body>
<div class="shell">
    <header class="appbar">
        <div class="brand">reprice-markets <span>admin</span></div>
        <nav>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.feeds.index') }}" class="{{ request()->routeIs('admin.feeds.*') ? 'active' : '' }}">Flux</a>
            <a href="{{ route('admin.price-columns.index') }}" class="{{ request()->routeIs('admin.price-columns.*') ? 'active' : '' }}">Mapping prix</a>
            <a href="{{ route('admin.currencies.index') }}" class="{{ request()->routeIs('admin.currencies.*') ? 'active' : '' }}">Devises</a>
            <a href="{{ route('admin.marketplaces.index') }}" class="{{ request()->routeIs('admin.marketplaces.*') ? 'active' : '' }}">Marketplaces</a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Utilisateurs</a>
            <a href="{{ route('prices.index') }}">↩ Retour app</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button class="btn secondary" type="submit">Déconnexion</button>
            </form>
        </nav>
    </header>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @yield('content')
</div>
@stack('scripts')
</body>
</html>
