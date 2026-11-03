@extends('layouts.admin')

@section('title', 'Éditer flux')

@section('content')
    <h1>Éditer {{ $feed->name }}</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.feeds.update', $feed) }}">
            @csrf
            @method('PUT')
            @include('admin.feeds._form', ['feed' => $feed])
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>
@endsection
