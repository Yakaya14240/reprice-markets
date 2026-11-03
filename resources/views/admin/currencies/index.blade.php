@extends('layouts.admin')

@section('title', 'Devises')

@section('content')
    <div class="actions" style="justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">Devises</h1>
        <a class="btn" href="{{ route('admin.currencies.create') }}">Ajouter</a>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr><th>Code</th><th>Nom</th><th>Symbole</th><th>Décimales</th><th>Ordre</th><th>Actif</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($currencies as $currency)
                <tr>
                    <td><strong>{{ $currency->code }}</strong></td>
                    <td>{{ $currency->name }}</td>
                    <td>{{ $currency->symbol }}</td>
                    <td>{{ $currency->decimals }}</td>
                    <td>{{ $currency->sort_order }}</td>
                    <td>{{ $currency->is_active ? 'oui' : 'non' }}</td>
                    <td><a href="{{ route('admin.currencies.edit', $currency) }}">Éditer</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aucune devise. Lancez le seeder.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
