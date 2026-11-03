@extends('layouts.app')

@section('title', $feed->name)

@section('content')
    <h1>{{ $feed->name }}</h1>
    <p class="muted"><code>{{ $feed->remote_path }}</code></p>

    <div class="card">
        <h2>Colonnes de prix</h2>
        <table>
            <thead><tr><th>Nom</th><th>Colonne CSV</th><th>Alias</th><th>Actif</th></tr></thead>
            <tbody>
            @forelse ($feed->priceColumns as $column)
                <tr>
                    <td>{{ $column->name }}</td>
                    <td>{{ $column->csv_column }}</td>
                    <td>{{ $column->alias }}</td>
                    <td>{{ $column->is_active ? 'oui' : 'non' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Aucune colonne de prix.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
