@extends('layouts.admin')

@section('title', 'Mapping prix')

@section('content')
    <h1>Mapping des champs prix</h1>
    <p class="muted">Sélectionnez les colonnes CSV et la devise associée (ex. Pologne = PLN). Une colonne peut être « prix publique » (remise Mirakl) et une « graphe défaut » pour l’historique produit.</p>

    <div class="card">
        <form method="GET" class="actions" style="align-items:end;">
            <div style="flex:1;min-width:220px;">
                <label>Flux</label>
                <select name="feed_file_id" onchange="this.form.submit()">
                    @foreach ($feeds as $feed)
                        <option value="{{ $feed->id }}" @selected($selectedFeedId === $feed->id)>{{ $feed->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if($selectedFeed)
            <div class="actions" style="margin-top:.5rem;">
                <form method="POST" action="{{ route('admin.price-columns.refresh-headers', $selectedFeed) }}">
                    @csrf
                    <button class="btn secondary" type="submit">Recharger les colonnes CSV</button>
                </form>
                <span class="muted">
                    @if(count($availableHeaders))
                        {{ count($availableHeaders) }} colonnes disponibles
                        @if($selectedFeed->snapshot?->loaded_at)
                            — maj {{ $selectedFeed->snapshot->loaded_at->format('d/m/Y H:i') }}
                        @endif
                    @else
                        Aucune colonne en mémoire : rechargez le flux.
                    @endif
                </span>
            </div>
        @endif
    </div>

    @if($selectedFeedId)
        <div class="card">
            <h2>Ajouter une colonne prix</h2>
            @if($currencies->isEmpty())
                <p class="muted">Aucune devise active. Créez-en dans <a href="{{ route('admin.currencies.index') }}">Devises</a>.</p>
            @else
                <form method="POST" action="{{ route('admin.price-columns.store') }}">
                    @csrf
                    <input type="hidden" name="feed_file_id" value="{{ $selectedFeedId }}">
                    <label>Nom affiché</label>
                    <input name="name" required placeholder="Prix Pologne">
                    <label>Colonne CSV</label>
                    <x-csv-column-select :headers="$availableHeaders" name="csv_column" />
                    <label>Devise</label>
                    <select name="currency_id" required>
                        <option value="">— choisir —</option>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->id }}" @selected(old('currency_id') == $currency->id || $currency->code === 'EUR')>
                                {{ $currency->label() }}
                            </option>
                        @endforeach
                    </select>
                    <label>Alias</label>
                    <input name="alias" placeholder="PL">
                    <label style="margin-top:.35rem;"><input type="checkbox" name="is_public_price" value="1" style="width:auto;"> Prix publique (référence remise)</label>
                    <label style="margin-top:.35rem;"><input type="checkbox" name="is_chart_default" value="1" style="width:auto;"> Colonne par défaut du graphe d’historique</label>
                    <button class="btn" type="submit">Ajouter</button>
                </form>
            @endif
        </div>

        <div class="card">
            <table>
                <thead><tr><th>Nom</th><th>Colonne CSV</th><th>Devise</th><th>Alias</th><th>Actif</th><th>Publique</th><th>Graphe</th><th></th></tr></thead>
                <tbody>
                @forelse ($columns as $column)
                    <tr data-price-column-row="{{ $column->id }}">
                        <td>
                            <strong class="pc-view">{{ $column->name }}</strong>
                            <input class="pc-edit" name="name" form="pc-form-{{ $column->id }}" value="{{ $column->name }}" style="display:none;margin:0;" required>
                        </td>
                        <td>
                            <code class="pc-view">{{ $column->csv_column }}</code>
                            <div class="pc-edit" style="display:none;min-width:200px;">
                                <x-csv-column-select
                                    :headers="$availableHeaders"
                                    name="csv_column"
                                    :value="$column->csv_column"
                                    :form="'pc-form-'.$column->id"
                                    style="margin-bottom:0;"
                                />
                            </div>
                        </td>
                        <td>
                            <span class="pc-view">{{ $column->currency?->code ?? '—' }}</span>
                            <select class="pc-edit" name="currency_id" form="pc-form-{{ $column->id }}" required style="display:none;margin:0;max-width:140px;">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->id }}" @selected((int) $column->currency_id === $currency->id)>
                                        {{ $currency->code }}
                                    </option>
                                @endforeach
                                @if($column->currency_id && ! $currencies->contains('id', $column->currency_id) && $column->currency)
                                    <option value="{{ $column->currency->id }}" selected>{{ $column->currency->code }} (inactive)</option>
                                @endif
                            </select>
                        </td>
                        <td>
                            <span class="pc-view">{{ $column->alias ?: '—' }}</span>
                            <input class="pc-edit" name="alias" form="pc-form-{{ $column->id }}" value="{{ $column->alias }}" style="display:none;margin:0;max-width:80px;">
                        </td>
                        <td>
                            <span class="pc-view">{{ $column->is_active ? 'oui' : 'non' }}</span>
                            <label class="pc-edit" style="display:none;margin:0;">
                                <input type="checkbox" name="is_active" value="1" form="pc-form-{{ $column->id }}" style="width:auto;" @checked($column->is_active)> actif
                            </label>
                        </td>
                        <td>
                            <span class="pc-view">{{ $column->is_public_price ? 'oui' : 'non' }}</span>
                            <label class="pc-edit" style="display:none;margin:0;">
                                <input type="checkbox" name="is_public_price" value="1" form="pc-form-{{ $column->id }}" style="width:auto;" @checked($column->is_public_price)> publique
                            </label>
                        </td>
                        <td>
                            <span class="pc-view">{{ $column->is_chart_default ? 'oui' : 'non' }}</span>
                            <label class="pc-edit" style="display:none;margin:0;">
                                <input type="checkbox" name="is_chart_default" value="1" form="pc-form-{{ $column->id }}" style="width:auto;" @checked($column->is_chart_default)> défaut
                            </label>
                        </td>
                        <td>
                            <form id="pc-form-{{ $column->id }}" method="POST" action="{{ route('admin.price-columns.update', $column) }}">
                                @csrf
                                @method('PUT')
                            </form>
                            <div class="actions pc-view">
                                <button class="btn secondary" type="button" data-pc-edit="{{ $column->id }}">Éditer</button>
                                <form method="POST" action="{{ route('admin.price-columns.destroy', $column) }}" onsubmit="return confirm('Supprimer cette colonne prix ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn secondary" type="submit">Supprimer</button>
                                </form>
                            </div>
                            <div class="actions pc-edit" style="display:none;">
                                <button class="btn" type="submit" form="pc-form-{{ $column->id }}">Enregistrer</button>
                                <button class="btn secondary" type="button" data-pc-cancel="{{ $column->id }}">Annuler</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Aucun mapping pour ce flux.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $columns->links() }}
        </div>
    @endif
@endsection

@push('scripts')
<script>
(() => {
    function setRowMode(row, editing) {
        row.querySelectorAll('.pc-view').forEach(el => {
            el.style.display = editing ? 'none' : '';
        });
        row.querySelectorAll('.pc-edit').forEach(el => {
            if (el.tagName === 'LABEL' || el.classList.contains('actions') || el.tagName === 'DIV') {
                el.style.display = editing ? (el.classList.contains('actions') ? 'flex' : '') : 'none';
            } else {
                el.style.display = editing ? '' : 'none';
            }
        });
    }

    document.querySelectorAll('[data-pc-edit]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-pc-edit');
            const row = document.querySelector(`[data-price-column-row="${id}"]`);
            if (row) setRowMode(row, true);
        });
    });

    document.querySelectorAll('[data-pc-cancel]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-pc-cancel');
            const row = document.querySelector(`[data-price-column-row="${id}"]`);
            if (row) {
                setRowMode(row, false);
                // Reload to discard unsaved edits in inputs.
                window.location.reload();
            }
        });
    });
})();
</script>
@endpush
