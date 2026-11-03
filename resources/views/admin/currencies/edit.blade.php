@extends('layouts.admin')

@section('title', 'Éditer devise')

@section('content')
    <h1>Éditer {{ $currency->code }}</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.currencies.update', $currency) }}">
            @csrf
            @method('PUT')
            @include('admin.currencies._form', ['currency' => $currency])
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>
@endsection
