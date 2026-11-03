@extends('layouts.admin')

@section('title', 'Éditer marketplace')

@section('content')
    <h1>Éditer {{ $marketplace->name }}</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.marketplaces.update', $marketplace) }}">
            @csrf
            @method('PUT')
            @include('admin.marketplaces._form', ['marketplace' => $marketplace])
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>
@endsection
