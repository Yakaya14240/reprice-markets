@php($u = $user ?? null)

<label>Nom</label>
<input name="name" value="{{ old('name', $u?->name) }}" required>

<label>Email</label>
<input name="email" type="email" value="{{ old('email', $u?->email) }}" required>

<label>Mot de passe {{ $u ? '(laisser vide pour conserver)' : '' }}</label>
<input name="password" type="password" autocomplete="new-password" @required(! $u)>

<label>Rôle</label>
<select name="role" required>
    @foreach ($roles as $role)
        <option value="{{ $role->value }}" @selected(old('role', $u?->role?->value) === $role->value)>{{ $role->label() }}</option>
    @endforeach
</select>

<label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked(old('is_active', $u?->is_active ?? true))> Actif</label>
