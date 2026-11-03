<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshFeedJob;
use App\Models\FeedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Identifiants invalides.']);
        }

        $request->session()->regenerate();

        $user = $request->user();
        if (! $user?->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Compte désactivé.']);
        }

        FeedFile::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $id) => RefreshFeedJob::dispatch($id));

        return redirect()
            ->intended(route('prices.index'))
            ->with('boot_refresh', true);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
