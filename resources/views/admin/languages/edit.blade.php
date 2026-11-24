@extends('layouts.admin')

@section('title', 'Éditer langue')

@section('content')
    <h1>Éditer {{ $language->code }}</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.languages.update', $language) }}">
            @csrf
            @method('PUT')
            @include('admin.languages._form', ['language' => $language])
            <button class="btn" type="submit">Enregistrer</button>
        </form>
        <form method="POST" action="{{ route('admin.languages.destroy', $language) }}" style="margin-top:.8rem;" onsubmit="return confirm('Supprimer la langue {{ $language->code }} ?');">
            @csrf
            @method('DELETE')
            <button class="btn secondary" type="submit">Supprimer la langue</button>
        </form>
    </div>
@endsection
