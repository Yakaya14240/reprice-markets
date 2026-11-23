<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès refusé — Agrigator</title>
    @php($faviconVersion = @filemtime(public_path('favicon.svg')) ?: time())
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $faviconVersion }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32x32.png') }}?v={{ $faviconVersion }}" type="image/png" sizes="32x32">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v={{ $faviconVersion }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $faviconVersion }}">
    <style>
        :root {
            --bg: #f4f6f8;
            --text: #1f2933;
            --muted: #61707d;
            --accent: #0f6e56;
            --border: #d9e2ec;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(180deg, #e8eef3 0%, var(--bg) 220px);
            color: var(--text);
            padding: 1.25rem;
        }
        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            width: min(440px, 100%);
            box-shadow: 0 16px 40px rgba(15, 23, 32, .08);
        }
        h1 { margin: 0 0 .5rem; font-size: 1.35rem; }
        p { margin: 0 0 1rem; color: var(--muted); line-height: 1.45; }
        a {
            display: inline-block;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            padding: .55rem .9rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Accès refusé</h1>
        <p>Vous n’avez pas les droits nécessaires pour ouvrir cette page. Si vous pensez que c’est une erreur, contactez un administrateur.</p>
        <a href="{{ auth()->check() ? route('prices.index') : route('login') }}">
            {{ auth()->check() ? 'Retour aux prix' : 'Connexion' }}
        </a>
    </div>
</body>
</html>
