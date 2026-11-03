@php($m = $marketplace ?? null)

<label>Nom</label>
<input name="name" value="{{ old('name', $m?->name) }}" required>

<label>Driver</label>
<select name="driver" required>
    @foreach ($drivers as $driver)
        <option value="{{ $driver->value }}" @selected(old('driver', $m?->driver?->value) === $driver->value)>{{ $driver->value }}</option>
    @endforeach
</select>

<label>API URL</label>
<input name="api_url" value="{{ old('api_url', $m?->api_url) }}" required>

<label>API key {{ $m ? '(laisser vide pour conserver)' : '' }}</label>
<input name="api_key" type="password" autocomplete="new-password">

<label>Client key (Kaufland)</label>
<input name="client_key" type="password" autocomplete="new-password">

<label>Client secret (Kaufland)</label>
<input name="client_secret" type="password" autocomplete="new-password">

<label>Shop IDs (séparés par des virgules)</label>
<input name="shop_ids" value="{{ old('shop_ids', $m ? implode(',', $m->shop_ids ?? []) : '') }}">

<label><input type="checkbox" name="supports_channels" value="1" style="width:auto;" @checked(old('supports_channels', $m?->supports_channels))> Multi-channel</label>
<label><input type="checkbox" name="supports_multi_shop" value="1" style="width:auto;" @checked(old('supports_multi_shop', $m?->supports_multi_shop))> Multi-shop</label>
<label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $m?->is_active ?? true))> Active</label>
