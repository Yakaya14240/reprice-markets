@extends('layouts.app')

@section('title', 'Connexion — reprice-markets')

@section('content')
    <div class="card" style="max-width:420px;margin:4rem auto;">
        <h1>Connexion</h1>
        <p class="muted">Espace opérateurs multi-marketplace</p>

        @foreach ($errors->all() as $error)
            <div class="error">{{ $error }}</div>
        @endforeach

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Mot de passe</label>
            <input id="password" type="password" name="password" required>

            <label style="font-weight:500;">
                <input type="checkbox" name="remember" value="1" style="width:auto;margin-right:.4rem;">
                Se souvenir de moi
            </label>

            <button class="btn" type="submit">Se connecter</button>
        </form>
    </div>
@endsection
