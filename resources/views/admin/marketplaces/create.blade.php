@extends('layouts.app')

@section('title', 'Nouvelle marketplace')

@section('content')
    <h1>Nouvelle marketplace</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.marketplaces.store') }}">
            @csrf
            @include('admin.marketplaces._form')
            <button class="btn" type="submit">Créer</button>
        </form>
    </div>
@endsection
