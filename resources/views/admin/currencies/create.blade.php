@extends('layouts.admin')

@section('title', 'Nouvelle devise')

@section('content')
    <h1>Nouvelle devise</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.currencies.store') }}">
            @csrf
            @include('admin.currencies._form')
            <button class="btn" type="submit">Créer</button>
        </form>
    </div>
@endsection
