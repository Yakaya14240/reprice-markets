@extends('layouts.admin')

@section('title', 'Éditer zone fiche')

@section('content')
    <h1>Éditer {{ $group->name }}</h1>
    <div class="card">
        <form method="POST" action="{{ route('admin.product-sheet-groups.update', $group) }}">
            @csrf
            @method('PUT')
            @include('admin.product-sheet-groups._form', ['group' => $group])
            <button class="btn" type="submit">Enregistrer</button>
        </form>
        <form method="POST" action="{{ route('admin.product-sheet-groups.destroy', $group) }}" style="margin-top:.8rem;" onsubmit="return confirm('Supprimer la zone {{ $group->name }} ?');">
            @csrf
            @method('DELETE')
            <button class="btn secondary" type="submit">Supprimer la zone</button>
        </form>
    </div>
@endsection
