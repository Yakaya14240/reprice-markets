@extends('layouts.app')

@section('title', 'Suivi')

@section('content')
    <h1>Suivi</h1>
    <p class="muted">Historique récent des modifications et publications.</p>

    <div class="grid" style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
        <div class="card"><div class="muted">Marketplaces actives</div><div style="font-size:1.75rem;font-weight:700;">{{ $marketplacesCount }}</div></div>
        <div class="card"><div class="muted">Flux actifs</div><div style="font-size:1.75rem;font-weight:700;">{{ $feedsCount }}</div></div>
    </div>

    <div class="card">
        <h2>Dernières modifications publiées</h2>
        <table style="width:100%;border-collapse:collapse;">
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
                <tr><td colspan="5" class="muted">Aucune modification publiée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Derniers push</h2>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Quand</th><th>Marketplace</th><th>Statut</th><th>Opérateur</th><th>Message</th></tr></thead>
            <tbody>
            @forelse ($recentPushes as $batch)
                <tr>
                    <td>{{ $batch->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $batch->marketplace?->name }}</td>
                    <td>{{ $batch->status->value }}</td>
                    <td>{{ $batch->user?->name }}</td>
                    <td>{{ $batch->result_message }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Aucun push.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
