@props([
    'suffix' => null,
])

<span {{ $attributes->class(['brand-name']) }}>Agri<span class="brand-gator">gator</span>@if ($suffix) <span class="brand-suffix">{{ $suffix }}</span>@endif</span>
