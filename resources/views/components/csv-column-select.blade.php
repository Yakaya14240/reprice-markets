@props([
    'name' => 'csv_column',
    'headers' => [],
    'value' => null,
    'required' => true,
    'style' => null,
])

@php
    $selected = old($name, $value);
    $headers = collect($headers)->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
@endphp

@if($headers->isEmpty())
    <input
        name="{{ $name }}"
        value="{{ $selected }}"
        @if($required) required @endif
        placeholder="Chargez d’abord le flux pour lister les colonnes"
        @if($style) style="{{ $style }}" @endif
    >
    <p class="muted" style="margin-top:-.5rem;margin-bottom:.9rem;">
        Aucune colonne connue pour ce flux. Utilisez « Recharger les colonnes CSV ».
    </p>
@else
    <select name="{{ $name }}" @if($required) required @endif @if($style) style="{{ $style }}" @endif>
        <option value="">— choisir une colonne —</option>
        @foreach ($headers as $header)
            <option value="{{ $header }}" @selected($selected === $header)>{{ $header }}</option>
        @endforeach
        @if($selected && ! $headers->contains($selected))
            <option value="{{ $selected }}" selected>{{ $selected }} (absent du dernier chargement)</option>
        @endif
    </select>
@endif
