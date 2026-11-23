@extends('layouts.app')

@section('title', 'Connexion — Agrigator')

@push('styles')
<style>
    .login-shell {
        min-height: calc(100vh - 2.5rem);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 0;
    }
    .login-card {
        width: 100%;
        max-width: 420px;
        margin: 0;
        padding: 1.5rem 1.5rem 1.35rem;
    }
    .login-card .brand { margin: 0 0 .35rem; }
    .login-card h1 {
        margin: 1.1rem 0 .25rem;
        font-size: 1.5rem;
    }
    .login-card .muted { margin: 0 0 1.15rem; }
    .login-field { margin-bottom: .15rem; }
    .login-remember {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-weight: 500;
        margin: .15rem 0 1.15rem;
        cursor: pointer;
    }
    .login-remember input { margin: 0; }
    .login-card .btn {
        width: 100%;
        padding: .7rem 1rem;
        font-size: 1rem;
    }
</style>
@endpush

@section('content')
    <div class="login-shell">
        <div class="card login-card">
            <x-brand size="lg" />
            <h1>Connexion</h1>
            <p class="muted">Espace opérateurs multi-marketplace</p>

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
