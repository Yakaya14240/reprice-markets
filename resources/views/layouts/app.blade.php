<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Agrigator')</title>
    @php($faviconVersion = @filemtime(public_path('favicon.svg')) ?: time())
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $faviconVersion }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32x32.png') }}?v={{ $faviconVersion }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon-16x16.png') }}?v={{ $faviconVersion }}" type="image/png" sizes="16x16">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ $faviconVersion }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $faviconVersion }}">
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
            --brand-agri: #111111;
            --brand-gator: #3DAE67;
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
        .brand { display: inline-flex; align-items: center; gap: .55rem; line-height: 1; }
        .brand-logo { display: block; height: 42px; width: auto; max-width: min(220px, 55vw); object-fit: contain; }
        .brand-logo-sm { height: 34px; }
        .brand-logo-lg { height: 72px; max-width: min(320px, 80vw); }
        .brand-mark { display: block; height: 28px; width: auto; object-fit: contain; }
        .brand-name { font-size: 1.25rem; font-weight: 800; color: var(--brand-agri); letter-spacing: .01em; }
        .brand-name .brand-gator { color: var(--brand-gator); }
        .brand-suffix { color: var(--muted); font-weight: 700; font-size: .9em; }
        nav { display: flex; gap: .85rem; flex-wrap: wrap; align-items: center; }
        nav a { color: var(--muted); font-weight: 600; }
        nav a:hover, nav a.active { color: var(--accent); }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; padding: 1rem; margin-bottom: 1rem;
        }
        .muted { color: var(--muted); }
        .error {
            background: #fde8e8; border: 1px solid #f5c2c2; color: var(--danger);
            padding: .75rem .9rem; border-radius: 8px; margin-bottom: .9rem; font-weight: 600;
        }
        label { display: block; font-weight: 600; margin-bottom: .35rem; }
        input:not([type="checkbox"]):not([type="radio"]), select, textarea {
            width: 100%; padding: .6rem .7rem; border: 1px solid var(--border);
            border-radius: 8px; margin-bottom: .9rem; font: inherit; background: #fff; color: var(--text);
        }
        input[type="checkbox"], input[type="radio"] { width: auto; margin: 0; }
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
        #toast-stack {
            position: fixed; right: 1rem; bottom: 1rem; z-index: 1100;
            display: flex; flex-direction: column; gap: .5rem; max-width: min(420px, calc(100vw - 2rem));
        }
        .toast {
            background: #fff; border: 1px solid var(--border); border-left: 4px solid var(--accent);
            border-radius: 10px; padding: .75rem .9rem; box-shadow: 0 12px 30px rgba(15,23,32,.12);
            color: var(--text); font-weight: 600;
        }
        .toast.error { border-left-color: var(--danger); }
        .toast.success { border-left-color: #0f6e56; }
        .toast.warn { border-left-color: var(--warn); }
        .pagination { display: flex; gap: .35rem; flex-wrap: wrap; margin-top: 1rem; align-items: center; }
        .pagination > * { color: var(--muted); }
        .pagination .page-link, .pagination a, .pagination span {
            display: inline-block; padding: .35rem .6rem; border: 1px solid var(--border);
            border-radius: 6px; color: var(--text); background: #fff;
        }
        .pagination .active span, .pagination [aria-current="page"] span {
            border-color: var(--accent); color: #fff; background: var(--accent);
        }
        .filters { display:flex; gap:.6rem; flex-wrap:wrap; align-items:end; margin-bottom:.8rem; }
        .filters label { display:block; font-size:.85rem; color:var(--muted); font-weight:600; margin:0 0 .25rem; }
        .filters input, .filters select {
            margin:0; padding:.5rem .65rem; border:1px solid var(--border); border-radius:8px; background:#fff;
        }
    </style>
    @stack('styles')
</head>
<body>
<div class="shell">
    @auth
        <header class="appbar">
            <x-brand />
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
<div id="toast-stack" aria-live="polite"></div>
<script>
window.showToast = function (message, type = 'error') {
    const stack = document.getElementById('toast-stack');
    if (!stack || !message) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (type || 'error');
    el.textContent = String(message);
    stack.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transition = 'opacity .25s ease';
        setTimeout(() => el.remove(), 250);
    }, 4200);
};
</script>
@stack('scripts')
</body>
</html>
