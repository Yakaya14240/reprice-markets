@php($g = $group ?? null)

<label>Nom</label>
<input name="name" value="{{ old('name', $g?->name) }}" required maxlength="100" placeholder="Titres">

<label>Ordre d’affichage</label>
<input name="sort_order" type="number" min="0" value="{{ old('sort_order', $g?->sort_order ?? 100) }}">

<label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $g?->is_active ?? true))> Actif</label>
