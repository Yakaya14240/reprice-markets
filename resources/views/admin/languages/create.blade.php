@extends('layouts.admin')

@section('title', 'Nouvelle langue')

@section('content')
    <h1>Nouvelle langue</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.languages.store') }}">
            @csrf
            @include('admin.languages._form')
            <button class="btn" type="submit">Créer</button>
        </form>
    </div>
@endsection
