@extends('layouts.admin')

@section('title', 'Mapping prix')

@section('content')
    <h1>Mapping des champs prix</h1>
    <p class="muted">Sélectionnez les colonnes CSV et la devise associée (ex. Pologne = PLN).</p>

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
                            <option value="{{ $currency->id }}" @selected(old('currency_id') == $currency->id || $currency->code === 'EUR')">
                                {{ $currency->label() }}
                            </option>
                        @endforeach
                    </select>
                    <label>Alias</label>
                    <input name="alias" placeholder="PL">
                    <button class="btn" type="submit">Ajouter</button>
                </form>
            @endif
        </div>

        <div class="card">
            <table>
                <thead><tr><th>Nom</th><th>Colonne CSV</th><th>Devise</th><th>Alias</th><th>Actif</th><th></th></tr></thead>
                <tbody>
                @forelse ($columns as $column)
                    <tr>
                        <td colspan="6">
                            <form method="POST" action="{{ route('admin.price-columns.update', $column) }}" class="actions" style="align-items:end;">
                                @csrf
                                @method('PUT')
                                <input name="name" value="{{ $column->name }}" style="max-width:160px;" required>
                                <div style="min-width:200px;flex:1;">
                                    <x-csv-column-select
                                        :headers="$availableHeaders"
                                        name="csv_column"
                                        :value="$column->csv_column"
                                        style="margin-bottom:0;"
                                    />
                                </div>
                                <select name="currency_id" required style="max-width:220px;margin-bottom:0;">
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency->id }}" @selected((int) old('currency_id', $column->currency_id) === $currency->id)>
                                            {{ $currency->code }}
                                        </option>
                                    @endforeach
                                    @if($column->currency_id && ! $currencies->contains('id', $column->currency_id) && $column->currency)
                                        <option value="{{ $column->currency->id }}" selected>{{ $column->currency->code }} (inactive)</option>
                                    @endif
                                </select>
                                <input name="alias" value="{{ $column->alias }}" style="max-width:80px;margin-bottom:0;">
                                <label style="margin:0;"><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked($column->is_active)> actif</label>
                                <button class="btn" type="submit">OK</button>
                            </form>
                            <form method="POST" action="{{ route('admin.price-columns.destroy', $column) }}" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button class="btn secondary" type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Aucun mapping pour ce flux.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
