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
            <tr><th>Nom</th><th>Source</th><th>Lecture</th><th>Écriture</th><th>Colonnes</th><th>Actif</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($feeds as $feed)
                <tr>
                    <td>{{ $feed->name }}</td>
                    <td>{{ $feed->source_type->label() }}</td>
                    <td><code>{{ $feed->location }}</code></td>
                    <td>
                        @if($feed->hasWritableDestination())
                            <code>{{ $feed->writeDestinationLabel() }}</code>
                        @else
                            <span class="muted">non configurée</span>
                        @endif
                    </td>
                    <td>{{ $feed->price_columns_count }}</td>
                    <td>{{ $feed->is_active ? 'oui' : 'non' }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.feeds.edit', $feed) }}">Éditer</a>
                        <button
                            class="btn secondary"
                            type="button"
                            data-test-feed
                            data-url="{{ route('admin.feeds.test', $feed) }}"
                        >Test lecture</button>
                        <button
                            class="btn secondary"
                            type="button"
                            data-test-feed-write
                            data-url="{{ route('admin.feeds.test-write', $feed) }}"
                        >Test écriture</button>
                        <span class="muted" data-test-result></span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aucun flux.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $feeds->links() }}
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    async function runTest(btn) {
        const resultEl = btn.parentElement.querySelector('[data-test-result]');
        resultEl.textContent = 'test…';
        btn.disabled = true;
        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const data = await res.json();
            resultEl.textContent = data.message || (res.ok ? 'OK' : 'Échec');
            resultEl.style.color = data.ok ? '#86efac' : '#fca5a5';
        } catch (e) {
            resultEl.textContent = e.message;
            resultEl.style.color = '#fca5a5';
        } finally {
            btn.disabled = false;
        }
    }

    document.querySelectorAll('[data-test-feed], [data-test-feed-write]').forEach(btn => {
        btn.addEventListener('click', () => runTest(btn));
    });
})();
</script>
@endpush
