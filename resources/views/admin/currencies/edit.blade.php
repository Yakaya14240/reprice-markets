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
        <form method="POST" action="{{ route('admin.currencies.destroy', $currency) }}" style="margin-top:.8rem;" onsubmit="return confirm('Supprimer la devise {{ $currency->code }} ?');">
            @csrf
            @method('DELETE')
            <button class="btn secondary" type="submit">Supprimer la devise</button>
        </form>
    </div>
@endsection
