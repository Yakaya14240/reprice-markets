@php($c = $currency ?? null)

<label>Code ISO (3 lettres)</label>
<input name="code" value="{{ old('code', $c?->code) }}" maxlength="3" required placeholder="PLN">

<label>Nom</label>
<input name="name" value="{{ old('name', $c?->name) }}" required placeholder="Złoty polonais">

<label>Symbole</label>
<input name="symbol" value="{{ old('symbol', $c?->symbol) }}" placeholder="zł">

<label>Décimales</label>
<input name="decimals" type="number" min="0" max="6" value="{{ old('decimals', $c?->decimals ?? 2) }}" required>

<label>Ordre d’affichage</label>
<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $c?->sort_order ?? 100) }}">

<label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $c?->is_active ?? true))> Active</label>
