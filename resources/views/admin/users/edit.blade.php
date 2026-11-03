@extends('layouts.app')

@section('title', 'Éditer utilisateur')

@section('content')
    <h1>Éditer {{ $user->name }}</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('admin.users._form', ['user' => $user])
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>
@endsection
