@extends('layouts.admin')

@section('title', 'Utilisateurs')

@section('content')
    <div class="actions" style="justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h1 style="margin:0;">Utilisateurs</h1>
        <a class="btn" href="{{ route('admin.users.create') }}">Ajouter</a>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Actif</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role->label() }}</td>
                    <td>{{ $user->is_active ? 'oui' : 'non' }}</td>
                    <td class="actions" style="gap:.5rem;">
                        <a href="{{ route('admin.users.edit', $user) }}">Éditer</a>
                        @unless(auth()->id() === $user->id)
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Archiver le compte de {{ $user->name }} ? Il ne pourra plus se connecter.');" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button class="btn secondary" type="submit">Archiver</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $users->links() }}
    </div>
@endsection
