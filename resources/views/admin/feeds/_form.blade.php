@php($f = $feed ?? null)

@if ($errors->any())
    <div class="flash" style="background:#7f1d1d;border-color:#f87171;color:#fee2e2;">
        <strong>Enregistrement impossible :</strong>
        <ul style="margin:.4rem 0 0 1.1rem;">
            @foreach ($errors->getMessages() as $field => $messages)
                @foreach ($messages as $error)
                    <li><code>{{ $field }}</code> — {{ $error }}</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif

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

<label>Type d’écriture @if(($f?->source_type?->value ?? old('source_type')) === 'url')(requis pour source URL)@else(optionnel — sinon = lecture)@endif</label>
<select name="write_source_type">
    <option value="">= même que lecture</option>
    @foreach ($sourceTypes as $type)
        @if($type->canWrite())
            <option value="{{ $type->value }}" @selected(old('write_source_type', $f?->write_source_type?->value) === $type->value)>{{ $type->label() }}</option>
        @endif
    @endforeach
</select>

<label>Emplacement écriture</label>
<input name="write_location" value="{{ old('write_location', $f?->write_location) }}" placeholder="test_clement/Flux_Sun-chauffage.csv">
<div class="muted" style="margin-top:-.45rem;margin-bottom:.9rem;line-height:1.45;">
    <strong>Si type d’écriture = SFTP</strong> : chemin <em>par rapport au dossier d’arrivée</em> du compte (souvent <code>current</code>), sans le host.<br>
    ✅ Compte ouvert dans <code>current</code> : <code>test_clement/Flux_Sun-chauffage.csv</code><br>
    ✅ Ou chemin absolu serveur : <code>/var/www/cdn/current/test_clement/Flux_Sun-chauffage.csv</code><br>
    ❌ <code>sftp.….com/test_clement/…</code> (host → champ Host plus bas)<br>
    ❌ URL CDN / chemin Windows <code>C:\…</code><br>
    <strong>Si type = Fichier local</strong> : chemin Windows complet vers un <em>fichier</em>, pas un dossier.
</div>

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

<h3>Identifiants SFTP</h3>
@php($cfg = is_array($f?->connection_config) ? $f->connection_config : [])
@php($hasSftp = filled($cfg['host'] ?? null) || filled($cfg['username'] ?? null))
<p class="muted" style="margin-top:-.35rem;">
    Requis si lecture ou écriture = SFTP.
    @if($hasSftp)
        <span style="color:#86efac;">Enregistrés : {{ $cfg['host'] ?? '?' }}{{ isset($cfg['port']) ? ':'.$cfg['port'] : '' }} / {{ $cfg['username'] ?? '?' }}{{ filled($cfg['password'] ?? null) ? ' / mot de passe ✓' : '' }}</span>
    @else
        Sinon fallback <code>.env</code> (<code>FEED_SFTP_*</code>).
    @endif
</p>
<label>Host</label>
<input name="sftp_host" value="{{ old('sftp_host', $cfg['host'] ?? '') }}" autocomplete="off">
<label>Port</label>
<input name="sftp_port" type="number" value="{{ old('sftp_port', $cfg['port'] ?? '') }}" placeholder="2200" autocomplete="off">
<label>Username</label>
<input name="sftp_username" value="{{ old('sftp_username', $cfg['username'] ?? '') }}" autocomplete="off">
<x-secret-field
    name="sftp_password"
    label="Password"
    :keep-on-blank="$f && filled($cfg['password'] ?? null)"
/>

<label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $f?->is_active ?? true))> Actif</label>
