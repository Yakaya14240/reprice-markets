@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>
    <p class="muted">Bonjour {{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</p>

    <div class="grid">
        <div class="card"><div class="muted">Marketplaces actives</div><div class="stat">{{ $marketplacesCount }}</div></div>
        <div class="card"><div class="muted">Flux actifs</div><div class="stat">{{ $feedsCount }}</div></div>
    </div>

    <div class="card">
        <h2>Dernières modifications de prix</h2>
        <table>
            <thead><tr><th>Quand</th><th>Opérateur</th><th>SKU</th><th>Colonne</th><th>Nouveau</th></tr></thead>
            <tbody>
            @forelse ($recentChanges as $change)
                <tr>
                    <td>{{ $change->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $change->user?->name }}</td>
                    <td>{{ $change->sku }}</td>
                    <td>{{ $change->csv_column }}</td>
                    <td>{{ $change->new_price }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Aucune modification pour le moment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Derniers push</h2>
        <table>
            <thead><tr><th>Quand</th><th>Marketplace</th><th>Statut</th><th>Opérateur</th></tr></thead>
            <tbody>
            @forelse ($recentPushes as $batch)
                <tr>
                    <td>{{ $batch->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $batch->marketplace?->name }}</td>
                    <td>{{ $batch->status->value }}</td>
                    <td>{{ $batch->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Aucun push pour le moment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
