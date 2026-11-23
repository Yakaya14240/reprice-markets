@props([
    'name',
    'label',
    'keepOnBlank' => false,
    'required' => false,
    'autocomplete' => 'new-password',
])

<label for="{{ $name }}">
    @if($keepOnBlank)
        <span style="margin-right:.35rem;" title="Laisser vide pour conserver la valeur actuelle" aria-hidden="true">🔒</span>
    @endif
    {{ $label }}
    @if($keepOnBlank)
        <span class="muted" style="font-weight:500;">(laisser vide pour conserver)</span>
    @endif
</label>
<input
    id="{{ $name }}"
    name="{{ $name }}"
    type="password"
    autocomplete="{{ $autocomplete }}"
    @if($required) required @endif
    @if($keepOnBlank) placeholder="••••••••  — inchangé si vide" @endif
    {{ $attributes }}
>
