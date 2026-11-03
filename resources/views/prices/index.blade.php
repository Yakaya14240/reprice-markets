@extends('layouts.app')

@section('title', 'Prix')

@section('content')
    <h1>Prix & audit</h1>
    <p class="muted">L’édition live SFTP arrive ensuite. L’audit trail est déjà en place.</p>

    <div class="card">
        <h2>Enregistrer une modification (audit)</h2>
        <form method="POST" action="{{ route('prices.log') }}">
            @csrf
            <label>Flux</label>
            <select name="feed_file_id" required>
                <option value="">—</option>
                @foreach ($feeds as $feed)
                    <option value="{{ $feed->id }}" @selected($selectedFeedId === $feed->id)>{{ $feed->name }}</option>
                @endforeach
            </select>
            <label>SKU</label>
            <input name="sku" required>
            <label>Colonne CSV</label>
            <input name="csv_column" placeholder="Prix_TTC" required>
            <label>Ancien prix</label>
            <input name="old_price" type="number" step="0.01">
            <label>Nouveau prix</label>
            <input name="new_price" type="number" step="0.01" min="0.01" required>
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>

    <div class="card">
        <h2>Historique</h2>
        <table>
            <thead><tr><th>Quand</th><th>Flux</th><th>SKU</th><th>Colonne</th><th>Ancien</th><th>Nouveau</th><th>Par</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->feedFile?->name }}</td>
                    <td>{{ $log->sku }}</td>
                    <td>{{ $log->csv_column }}</td>
                    <td>{{ $log->old_price }}</td>
                    <td>{{ $log->new_price }}</td>
                    <td>{{ $log->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Vide.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $logs->links() }}
    </div>
@endsection
