@extends('layouts.admin')

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
            @forelse ($marketplaces as $marketplace)
                <tr>
                    <td>{{ $marketplace->name }}</td>
                    <td>{{ $marketplace->driver->label() }}</td>
                    <td>{{ $marketplace->api_url }}</td>
                    <td>{{ implode(', ', $marketplace->shop_ids ?? []) }}</td>
                    <td>{{ $marketplace->is_active ? 'oui' : 'non' }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.marketplaces.edit', $marketplace) }}">Éditer</a>
                        <button
                            class="btn secondary"
                            type="button"
                            data-test-marketplace
                            data-url="{{ route('admin.marketplaces.test', $marketplace) }}"
                        >Test API</button>
                        <form method="POST" action="{{ route('admin.marketplaces.destroy', $marketplace) }}" onsubmit="return confirm('Supprimer {{ $marketplace->name }} ? Les mappings et historiques de push liés seront aussi supprimés.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn secondary" type="submit">Supprimer</button>
                        </form>
                        <span class="muted" data-test-result></span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucune marketplace.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $marketplaces->links() }}
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    document.querySelectorAll('[data-test-marketplace]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const resultEl = btn.parentElement.querySelector('[data-test-result]');
            resultEl.textContent = 'test…';
            resultEl.style.color = '';
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
        });
    });
})();
</script>
@endpush
