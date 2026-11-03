<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketplaceDriver;
use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function index(): View
    {
        return view('admin.marketplaces.index', [
            'marketplaces' => Marketplace::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.marketplaces.create', [
            'drivers' => MarketplaceDriver::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        $data['shop_ids'] = $this->parseShopIds($data['shop_ids'] ?? null);

        Marketplace::query()->create($data);

        return redirect()->route('admin.marketplaces.index')
            ->with('status', 'Marketplace créée.');
    }

    public function edit(Marketplace $marketplace): View
    {
        return view('admin.marketplaces.edit', [
            'marketplace' => $marketplace,
            'drivers' => MarketplaceDriver::cases(),
        ]);
    }

    public function update(Request $request, Marketplace $marketplace): RedirectResponse
    {
        $data = $this->validated($request, updating: true);
        $data['shop_ids'] = $this->parseShopIds($data['shop_ids'] ?? null);

        foreach (['api_key', 'client_key', 'client_secret'] as $secret) {
            if (($data[$secret] ?? null) === null || $data[$secret] === '') {
                unset($data[$secret]);
            }
        }

        $marketplace->update($data);

        return redirect()->route('admin.marketplaces.index')
            ->with('status', 'Marketplace mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:mirakl,kaufland'],
            'api_url' => ['required', 'url'],
            'api_key' => [$updating ? 'nullable' : 'nullable', 'string'],
            'client_key' => ['nullable', 'string'],
            'client_secret' => ['nullable', 'string'],
            'shop_ids' => ['nullable', 'string'],
            'supports_channels' => ['sometimes', 'boolean'],
            'supports_multi_shop' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'supports_channels' => $request->boolean('supports_channels'),
            'supports_multi_shop' => $request->boolean('supports_multi_shop'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /**
     * @return list<string>
     */
    private function parseShopIds(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->values()
            ->all();
    }
}
