@extends('layouts.admin')

@section('title', 'Nouvel utilisateur')

@section('content')
    <h1>Nouvel utilisateur</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.users._form')
            <button class="btn" type="submit">Créer</button>
        </form>
    </div>
@endsection
