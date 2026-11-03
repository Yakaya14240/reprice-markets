<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            --warn: #8a6d3b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(180deg, #e8eef3 0%, var(--bg) 220px);
            color: var(--text);
        }
        a { color: var(--accent); text-decoration: none; }
        .shell { max-width: 1200px; margin: 0 auto; padding: 1.25rem; }
        header.appbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap;
        }
        .brand { font-size: 1.25rem; font-weight: 700; }
        nav { display: flex; gap: .85rem; flex-wrap: wrap; align-items: center; }
        nav a { color: var(--muted); font-weight: 600; }
        nav a:hover, nav a.active { color: var(--accent); }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; padding: 1rem; margin-bottom: 1rem;
        }
        .muted { color: var(--muted); }
        .btn {
            display: inline-block; background: var(--accent); color: #fff; border: 0;
            border-radius: 8px; padding: .55rem .9rem; font-weight: 700; cursor: pointer;
        }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .btn.secondary { background: #334e68; }
        .btn.ghost { background: transparent; color: var(--accent); border: 1px solid var(--border); }
        .flash {
            background: var(--accent-soft); border: 1px solid #9ed9c4;
            color: #03543f; padding: .8rem 1rem; border-radius: 8px; margin-bottom: 1rem;
        }
        .progress {
            height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden;
        }
        .progress > span {
            display: block; height: 100%; width: 0; background: var(--accent); transition: width .25s ease;
        }
        #ui-lock {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 32, .45);
            z-index: 1000; align-items: center; justify-content: center;
        }
        #ui-lock.active { display: flex; }
        #ui-lock .panel {
            background: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; width: min(420px, 92vw);
            box-shadow: 0 20px 50px rgba(0,0,0,.2);
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="shell">
    @auth
        <header class="appbar">
            <div class="brand">reprice-markets</div>
            <nav>
                <a href="{{ route('prices.index') }}" class="{{ request()->routeIs('prices.*') ? 'active' : '' }}">Prix</a>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Suivi</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}">Admin</a>
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

<div id="ui-lock">
    <div class="panel">
        <strong id="ui-lock-title">Publication en cours…</strong>
        <p class="muted" id="ui-lock-label" style="margin:.5rem 0 1rem;">Préparation</p>
        <div class="progress"><span id="ui-lock-bar"></span></div>
    </div>
</div>
@stack('scripts')
</body>
</html>
