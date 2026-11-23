<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.users.index')
            ->with('status', 'Utilisateur créé.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        // Unchecked checkbox is absent from the request — must default to false.
        $user->is_active = $request->boolean('is_active');

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')
            ->with('status', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->is($user)) {
            return redirect()->route('admin.users.edit', $user)
                ->with('status', 'Vous ne pouvez pas archiver votre propre compte.');
        }

        $name = $user->name;

        // Free the unique email constraint while keeping a recoverable trace.
        $user->forceFill([
            'is_active' => false,
            'email' => 'archived.'.$user->id.'.'.$user->email,
        ])->save();

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', "Compte « {$name} » archivé.");
    }
}
