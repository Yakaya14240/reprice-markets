@extends('layouts.admin')

@section('title', 'Zones fiche')

@section('content')
    <div class="actions" style="justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">Zones fiche</h1>
        <a class="btn" href="{{ route('admin.product-sheet-groups.create') }}">Ajouter</a>
    </div>

    <p class="muted">Ces zones regroupent les champs sur l’écran FO Fiches (ex. Titres, Stock, Descriptions).</p>

    <div class="card">
        <table>
            <thead>
            <tr><th>Nom</th><th>Ordre</th><th>Actif</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($groups as $group)
                <tr>
                    <td><strong>{{ $group->name }}</strong></td>
                    <td>{{ $group->sort_order }}</td>
                    <td>{{ $group->is_active ? 'oui' : 'non' }}</td>
                    <td class="actions" style="gap:.5rem;">
                        <a href="{{ route('admin.product-sheet-groups.edit', $group) }}">Éditer</a>
                        <form method="POST" action="{{ route('admin.product-sheet-groups.destroy', $group) }}" onsubmit="return confirm('Supprimer la zone {{ $group->name }} ?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button class="btn secondary" type="submit">Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Aucune zone. Lancez le seeder ou créez-en une.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $groups->links() }}
    </div>
@endsection
