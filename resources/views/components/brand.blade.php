@props([
    'suffix' => null,
    'compact' => false,
    'size' => 'md',
    'tone' => 'light',
])

@php
    $logoClass = match ($size) {
        'lg' => 'brand-logo brand-logo-lg',
        'sm' => 'brand-logo brand-logo-sm',
        default => 'brand-logo',
    };
    $isDark = $tone === 'dark';
@endphp

<span {{ $attributes->class([
    'brand',
    $compact ? 'brand-compact' : 'brand-full',
    $isDark ? 'brand-on-dark' : null,
]) }}>
    @if ($isDark)
        <span class="brand-plate" aria-hidden="true">
            <img
                class="{{ $logoClass }}"
                src="{{ asset('images/agrigator-logo.png') }}"
                alt=""
                decoding="async"
            >
        </span>
        <span class="brand-lockup">
            <span class="visually-hidden">Agrigator</span>
            @if ($suffix)
                <span class="brand-badge">{{ $suffix }}</span>
            @endif
        </span>
    @elseif ($compact)
        <img
            class="brand-mark"
            src="{{ asset('images/agrigator-mark.png') }}"
            alt=""
            width="72"
            height="40"
            decoding="async"
        >
        <span class="brand-name">Agri<span class="brand-gator">gator</span>@if ($suffix) <span class="brand-suffix">{{ $suffix }}</span>@endif</span>
    @else
        <img
            class="{{ $logoClass }}"
            src="{{ asset('images/agrigator-logo.png') }}"
            alt="Agrigator"
            decoding="async"
        >
        @if ($suffix)
            <span class="brand-suffix">{{ $suffix }}</span>
        @endif
    @endif
</span>
