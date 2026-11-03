@extends('layouts.admin')

@section('title', 'Mapping prix')

@section('content')
    <h1>Mapping des champs prix</h1>

    <div class="card">
        <form method="GET">
            <label>Flux</label>
            <select name="feed_file_id" onchange="this.form.submit()">
                @foreach ($feeds as $feed)
                    <option value="{{ $feed->id }}" @selected($selectedFeedId === $feed->id)>{{ $feed->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($selectedFeedId)
        <div class="card">
            <h2>Ajouter une colonne</h2>
            <form method="POST" action="{{ route('admin.price-columns.store') }}">
                @csrf
                <input type="hidden" name="feed_file_id" value="{{ $selectedFeedId }}">
                <label>Nom</label>
                <input name="name" required>
                <label>Colonne CSV</label>
                <input name="csv_column" required placeholder="Prix_TTC">
                <label>Alias</label>
                <input name="alias" placeholder="FR">
                <button class="btn" type="submit">Ajouter</button>
            </form>
        </div>

        <div class="card">
            <table>
                <thead><tr><th>Nom</th><th>CSV</th><th>Alias</th><th>Actif</th><th></th></tr></thead>
                <tbody>
                @foreach ($columns as $column)
                    <tr>
                        <td colspan="5">
                            <form method="POST" action="{{ route('admin.price-columns.update', $column) }}" class="actions">
                                @csrf
                                @method('PUT')
                                <input name="name" value="{{ $column->name }}" style="max-width:180px;">
                                <input name="csv_column" value="{{ $column->csv_column }}" style="max-width:180px;">
                                <input name="alias" value="{{ $column->alias }}" style="max-width:100px;">
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
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
