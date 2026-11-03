<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'reprice-markets')</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --text: #1f2933;
            --muted: #61707d;
            --accent: #0f6e56;
            --accent-soft: #d8f3ea;
            --border: #d9e2ec;
            --danger: #9b1c1c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(180deg, #e8eef3 0%, var(--bg) 220px);
            color: var(--text);
        }
        a { color: var(--accent); text-decoration: none; }
        .shell { max-width: 1100px; margin: 0 auto; padding: 1.25rem; }
        header.appbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
        }
        .brand { font-size: 1.25rem; font-weight: 700; color: var(--text); }
        nav { display: flex; gap: .85rem; flex-wrap: wrap; align-items: center; }
        nav a { color: var(--muted); font-weight: 600; }
        nav a:hover, nav a.active { color: var(--accent); }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem;
        }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .stat { font-size: 1.75rem; font-weight: 700; }
        .muted { color: var(--muted); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .4rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { font-size: .85rem; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
        label { display: block; font-weight: 600; margin-bottom: .35rem; }
        input, select, textarea {
            width: 100%; padding: .6rem .7rem; border: 1px solid var(--border);
            border-radius: 8px; margin-bottom: .9rem; font: inherit;
        }
        .btn {
            display: inline-block; background: var(--accent); color: #fff; border: 0;
            border-radius: 8px; padding: .65rem 1rem; font-weight: 700; cursor: pointer;
        }
        .btn.secondary { background: #334e68; }
        .flash {
            background: var(--accent-soft); border: 1px solid #9ed9c4;
            color: #03543f; padding: .8rem 1rem; border-radius: 8px; margin-bottom: 1rem;
        }
        .error { color: var(--danger); margin-bottom: .8rem; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="shell">
    @auth
        <header class="appbar">
            <div class="brand">reprice-markets</div>
            <nav>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('feeds.index') }}" class="{{ request()->routeIs('feeds.*') ? 'active' : '' }}">Flux</a>
                <a href="{{ route('prices.index') }}" class="{{ request()->routeIs('prices.*') ? 'active' : '' }}">Prix</a>
                <a href="{{ route('pushes.index') }}" class="{{ request()->routeIs('pushes.*') ? 'active' : '' }}">Push</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.marketplaces.index') }}" class="{{ request()->routeIs('admin.marketplaces.*') ? 'active' : '' }}">Marketplaces</a>
                    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Utilisateurs</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button class="btn secondary" type="submit">Déconnexion</button>
                </form>
            </nav>
        </header>
    @endauth

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @yield('content')
</div>
</body>
</html>
