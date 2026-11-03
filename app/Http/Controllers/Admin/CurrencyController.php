<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(): View
    {
        return view('admin.currencies.index', [
            'currencies' => Currency::query()->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.currencies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Currency::query()->create($this->validated($request));

        return redirect()->route('admin.currencies.index')->with('status', 'Devise créée.');
    }

    public function edit(Currency $currency): View
    {
        return view('admin.currencies.edit', compact('currency'));
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $currency->update($this->validated($request, $currency));

        return redirect()->route('admin.currencies.index')->with('status', 'Devise mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Currency $currency = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies', 'code')->ignore($currency?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'decimals' => ['required', 'integer', 'min:0', 'max:6'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? null,
            'decimals' => (int) $data['decimals'],
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
