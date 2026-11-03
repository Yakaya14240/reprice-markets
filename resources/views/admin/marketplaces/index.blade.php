@extends('layouts.app')

@section('title', 'Marketplaces')

@section('content')
    <div class="actions" style="justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1 style="margin:0;">Marketplaces</h1>
        <a class="btn" href="{{ route('admin.marketplaces.create') }}">Ajouter</a>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Nom</th><th>Driver</th><th>URL</th><th>Shops</th><th>Actif</th><th></th></tr></thead>
            <tbody>
            @foreach ($marketplaces as $marketplace)
                <tr>
                    <td>{{ $marketplace->name }}</td>
                    <td>{{ $marketplace->driver->value }}</td>
                    <td>{{ $marketplace->api_url }}</td>
                    <td>{{ implode(', ', $marketplace->shop_ids ?? []) }}</td>
                    <td>{{ $marketplace->is_active ? 'oui' : 'non' }}</td>
                    <td><a href="{{ route('admin.marketplaces.edit', $marketplace) }}">Éditer</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
