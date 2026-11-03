@extends('layouts.admin')

@section('title', 'Flux')

@section('content')
    <div class="actions" style="justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">Flux</h1>
        <a class="btn" href="{{ route('admin.feeds.create') }}">Ajouter</a>
    </div>
    <div class="card">
        <table>
            <thead>
            <tr><th>Nom</th><th>Source</th><th>Emplacement</th><th>Écriture</th><th>Colonnes</th><th>Actif</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($feeds as $feed)
                <tr>
                    <td>{{ $feed->name }}</td>
                    <td>{{ $feed->source_type->label() }}</td>
                    <td><code>{{ $feed->location }}</code></td>
                    <td><code>{{ $feed->effectiveWriteLocation() }}</code></td>
                    <td>{{ $feed->price_columns_count }}</td>
                    <td>{{ $feed->is_active ? 'oui' : 'non' }}</td>
                    <td><a href="{{ route('admin.feeds.edit', $feed) }}">Éditer</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aucun flux.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
