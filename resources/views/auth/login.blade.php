@extends('layouts.app')

@section('title', 'Connexion — Agrigator')

@push('styles')
<style>
    body:has(.login-shell) {
        background:
            radial-gradient(ellipse 80% 55% at 50% -10%, rgba(61, 174, 103, .18), transparent 55%),
            radial-gradient(ellipse 60% 40% at 100% 100%, rgba(15, 110, 86, .08), transparent 45%),
            linear-gradient(180deg, #e7eef4 0%, #f4f6f8 42%, #eef2f5 100%);
    }
    .shell:has(.login-shell) {
        max-width: none;
        padding: 0;
        min-height: 100vh;
    }
    .login-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 1rem;
    }
    .login-card {
        width: 100%;
        max-width: 400px;
        margin: 0;
        padding: 1.85rem 1.75rem 1.6rem;
        border-radius: 16px;
        border-color: #cfd8e3;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, .7) inset,
            0 18px 40px rgba(15, 23, 32, .08);
    }
    .login-card .brand {
        margin: 0 auto .15rem;
        display: flex;
        justify-content: center;
    }
    .login-card .brand-logo-lg {
        height: 78px;
        max-width: min(280px, 78vw);
    }
    .login-lede {
        margin: .85rem 0 1.35rem;
        text-align: center;
        color: var(--muted);
        font-size: .95rem;
        font-weight: 500;
    }
    .login-field {
        margin-bottom: .2rem;
    }
    .login-card label:not(.login-remember) {
        font-size: .82rem;
        letter-spacing: .01em;
        color: #334e68;
    }
    .login-card input[type="email"],
    .login-card input[type="password"] {
        margin-bottom: .95rem;
        border-color: #b8c4d1;
        background: #fbfcfd;
        padding: .72rem .8rem;
    }
    .login-card input[type="email"]:focus,
    .login-card input[type="password"]:focus {
        outline: 2px solid rgba(61, 174, 103, .35);
        outline-offset: 1px;
        border-color: #3DAE67;
        background: #fff;
    }
    .login-remember {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-weight: 500;
        font-size: .92rem;
        color: #486581;
        margin: .1rem 0 1.25rem;
        cursor: pointer;
    }
    .login-remember input {
        margin: 0;
        width: 1rem;
        height: 1rem;
        accent-color: #3DAE67;
    }
    .login-card .btn {
        width: 100%;
        padding: .78rem 1rem;
        font-size: 1rem;
        background: #2f9a58;
        box-shadow: 0 1px 0 rgba(255, 255, 255, .2) inset;
    }
    .login-card .btn:hover {
        background: #278a4c;
    }
    .login-card .error {
        margin-bottom: 1rem;
    }
    .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>
@endpush

@section('content')
    <div class="login-shell">
        <div class="card login-card">
            <h1 class="visually-hidden">Connexion</h1>
            <x-brand size="lg" />
            <p class="login-lede">Accès opérateurs</p>

            @foreach ($errors->all() as $error)
                <div class="error" role="alert">{{ $error }}</div>
            @endforeach

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="login-field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>

                <div class="login-field">
                    <label for="password">Mot de passe</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                </div>

                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1">
                    Se souvenir de moi
                </label>

                <button class="btn" type="submit">Se connecter</button>
            </form>
        </div>
    </div>
@endsection
