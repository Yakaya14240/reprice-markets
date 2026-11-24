@extends('layouts.admin')

@section('title', 'Nouvelle zone fiche')

@section('content')
    <h1>Nouvelle zone fiche</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.product-sheet-groups.store') }}">
            @csrf
            @include('admin.product-sheet-groups._form')
            <button class="btn" type="submit">Créer</button>
        </form>
    </div>
@endsection
