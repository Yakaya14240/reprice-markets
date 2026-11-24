@php($l = $language ?? null)

<label>Code (ex. FR, DE, CZ)</label>
<input name="code" value="{{ old('code', $l?->code) }}" maxlength="5" required placeholder="FR">

<label>Nom</label>
<input name="name" value="{{ old('name', $l?->name) }}" required placeholder="Français">

<label>Ordre d’affichage</label>
<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $l?->sort_order ?? 100) }}">

<label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $l?->is_active ?? true))> Actif</label>
