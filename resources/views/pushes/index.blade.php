@extends('layouts.app')

@section('title', 'Push')

@section('content')
    <h1>Push marketplace</h1>

    <div class="card">
        <h2>Mettre un push en file</h2>
        <form method="POST" action="{{ route('pushes.store') }}">
            @csrf
            <label>Marketplace</label>
            <select name="marketplace_id" required>
                @foreach ($marketplaces as $marketplace)
                    <option value="{{ $marketplace->id }}">{{ $marketplace->name }} ({{ $marketplace->driver->value }})</option>
                @endforeach
            </select>
            <button class="btn" type="submit">Queue push</button>
        </form>
    </div>

    <div class="card">
        <h2>Batches</h2>
        <table>
            <thead><tr><th>#</th><th>Marketplace</th><th>Statut</th><th>Message</th><th>Par</th><th>Quand</th></tr></thead>
            <tbody>
            @forelse ($batches as $batch)
                <tr>
                    <td>{{ $batch->id }}</td>
                    <td>{{ $batch->marketplace?->name }}</td>
                    <td>{{ $batch->status->value }}</td>
                    <td>{{ $batch->result_message }}</td>
                    <td>{{ $batch->user?->name }}</td>
                    <td>{{ $batch->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucun batch.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $batches->links() }}
    </div>
@endsection
