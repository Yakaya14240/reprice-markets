@extends('layouts.admin')

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
        @unless(auth()->id() === $user->id)
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="margin-top:.8rem;" onsubmit="return confirm('Archiver le compte de {{ $user->name }} ? Il ne pourra plus se connecter.');">
                @csrf
                @method('DELETE')
                <button class="btn secondary" type="submit">Archiver le compte</button>
            </form>
        @endunless
    </div>
@endsection
