@extends('layouts.admin')

@section('title', 'Nouveau flux')

@section('content')
    <h1>Nouveau flux</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.feeds.store') }}">
            @csrf
            @include('admin.feeds._form')
            <button class="btn" type="submit">Créer</button>
        </form>
    </div>
@endsection
