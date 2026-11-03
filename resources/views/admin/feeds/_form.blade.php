@php($f = $feed ?? null)

<label>Nom</label>
<input name="name" value="{{ old('name', $f?->name) }}" required>

<label>Type de source (lecture)</label>
<select name="source_type" required>
    @foreach ($sourceTypes as $type)
        <option value="{{ $type->value }}" @selected(old('source_type', $f?->source_type?->value) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</select>

<label>Emplacement lecture (chemin local, chemin SFTP ou URL)</label>
<input name="location" value="{{ old('location', $f?->location) }}" required>

<label>Type d’écriture (optionnel — requis si source URL)</label>
<select name="write_source_type">
    <option value="">= même que lecture</option>
    @foreach ($sourceTypes as $type)
        @if($type->canWrite())
            <option value="{{ $type->value }}" @selected(old('write_source_type', $f?->write_source_type?->value) === $type->value)>{{ $type->label() }}</option>
        @endif
    @endforeach
</select>

<label>Emplacement écriture (optionnel)</label>
<input name="write_location" value="{{ old('write_location', $f?->write_location) }}">

<label>Délimiteur (`\t` pour tabulation)</label>
<input name="delimiter" value="{{ old('delimiter', $f?->delimiter === "\t" ? '\t' : ($f?->delimiter ?? '\t')) }}" required>

@php($headers = $availableHeaders ?? ($f?->availableHeaders() ?? []))
<label>Colonne SKU</label>
<x-csv-column-select
    name="sku_column"
    :headers="$headers"
    :value="old('sku_column', $f?->sku_column ?? 'Identifiant_unique')"
/>

<label>Colonne nom produit</label>
<x-csv-column-select
    name="name_column"
    :headers="$headers"
    :value="old('name_column', $f?->name_column ?? 'Titre')"
/>

<h3>SFTP (surcharge optionnelle — sinon .env)</h3>
@php($cfg = $f?->connection_config ?? [])
<label>Host</label>
<input name="sftp_host" value="{{ old('sftp_host', $cfg['host'] ?? '') }}">
<label>Port</label>
<input name="sftp_port" type="number" value="{{ old('sftp_port', $cfg['port'] ?? '') }}">
<label>Username</label>
<input name="sftp_username" value="{{ old('sftp_username', $cfg['username'] ?? '') }}">
<label>Password {{ $f ? '(laisser vide pour conserver)' : '' }}</label>
<input name="sftp_password" type="password" autocomplete="new-password">

<label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $f?->is_active ?? true))> Actif</label>
