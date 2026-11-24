@extends('layouts.admin')

@section('title', 'Langues')

@section('content')
    <div class="actions" style="justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">Langues</h1>
        <a class="btn" href="{{ route('admin.languages.create') }}">Ajouter</a>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr><th>Code</th><th>Nom</th><th>Ordre</th><th>Actif</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($languages as $language)
                <tr>
                    <td><strong>{{ $language->code }}</strong></td>
                    <td>{{ $language->name }}</td>
                    <td>{{ $language->sort_order }}</td>
                    <td>{{ $language->is_active ? 'oui' : 'non' }}</td>
                    <td class="actions" style="gap:.5rem;">
                        <a href="{{ route('admin.languages.edit', $language) }}">Éditer</a>
                        <form method="POST" action="{{ route('admin.languages.destroy', $language) }}" onsubmit="return confirm('Supprimer la langue {{ $language->code }} ?');" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button class="btn secondary" type="submit">Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Aucune langue. Lancez le seeder.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $languages->links() }}
    </div>
@endsection
