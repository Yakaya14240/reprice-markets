@extends('layouts.app')

@section('title', 'Flux')

@section('content')
    <h1>Flux produit</h1>
    <div class="card">
        <table>
            <thead><tr><th>Nom</th><th>Chemin SFTP</th><th>Colonnes prix</th><th></th></tr></thead>
            <tbody>
            @forelse ($feeds as $feed)
                <tr>
                    <td>{{ $feed->name }}</td>
                    <td><code>{{ $feed->remote_path }}</code></td>
                    <td>{{ $feed->price_columns_count }}</td>
                    <td><a href="{{ route('feeds.show', $feed) }}">Détail</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Aucun flux. Lancez les seeders.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
