@extends('layouts.admin')

@section('title', 'Admin dashboard')

@section('content')
    <h1>Administration</h1>
    <p class="muted">Configuration marketplaces, flux, mappings et utilisateurs.</p>
    <div class="grid">
        <div class="card"><div class="muted">Utilisateurs</div><div class="stat">{{ $usersCount }}</div></div>
        <div class="card"><div class="muted">Flux</div><div class="stat">{{ $feedsCount }}</div></div>
        <div class="card"><div class="muted">Marketplaces</div><div class="stat">{{ $marketplacesCount }}</div></div>
    </div>
@endsection
