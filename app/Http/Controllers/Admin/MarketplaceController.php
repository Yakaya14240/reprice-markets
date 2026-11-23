<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketplaceDriver;
use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use App\Models\MarketplacePriceMapping;
use App\Models\PriceColumn;
use App\Services\Marketplace\MarketplaceConnectionTester;
use App\Services\Marketplace\Mirakl\MiraklChannelDirectory;
use App\Services\Marketplace\Mirakl\MiraklShopDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function index(): View
    {
        return view('admin.marketplaces.index', [
            'marketplaces' => Marketplace::query()->orderBy('name')->paginate(20),
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
        $marketplace->load(['priceMappings.priceColumn.feedFile']);

        return view('admin.marketplaces.edit', [
            'marketplace' => $marketplace,
            'drivers' => MarketplaceDriver::cases(),
            'priceColumns' => PriceColumn::query()
                ->with('feedFile')
                ->where('is_active', true)
                ->orderBy('feed_file_id')
                ->orderBy('name')
                ->get(),
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

        return redirect()->route('admin.marketplaces.edit', $marketplace)
            ->with('status', 'Marketplace mise à jour.');
    }

    public function destroy(Marketplace $marketplace): RedirectResponse
    {
        $name = $marketplace->name;
        $marketplace->delete();

        return redirect()->route('admin.marketplaces.index')
            ->with('status', "Marketplace « {$name} » supprimée.");
    }

    public function test(Marketplace $marketplace, MarketplaceConnectionTester $tester): JsonResponse
    {
        $result = $tester->test($marketplace);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function remoteShops(Marketplace $marketplace, MiraklShopDirectory $shops): JsonResponse
    {
        try {
            $list = $shops->listShops($marketplace);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'shops' => [],
            ], 422);
        }

        $fromAccount = count($list) === 1 && str_contains((string) ($list[0]['name'] ?? ''), '(compte)');

        return response()->json([
            'ok' => true,
            'message' => $fromAccount
                ? 'Liste /shops interdite (403) — shop du compte récupéré via /account : '.$list[0]['id']
                : count($list).' shop(s) récupéré(s) depuis Mirakl.',
            'shops' => $list,
            'source' => $fromAccount ? 'account' : 'shops',
        ]);
    }

    public function remoteChannels(Request $request, Marketplace $marketplace, MiraklChannelDirectory $channels): JsonResponse
    {
        $data = $request->validate([
            'shop_id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $list = $channels->listChannels($marketplace, $data['shop_id']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'channels' => [],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => count($list).' channel(s) récupéré(s) pour le shop '.$data['shop_id'].'.',
            'channels' => $list,
            'shop_id' => $data['shop_id'],
        ]);
    }

    public function storePriceMapping(Request $request, Marketplace $marketplace): RedirectResponse
    {
        $data = $request->validate([
            'shop_id' => ['required', 'string', 'max:64'],
            'channel_code' => ['nullable', 'string', 'max:64'],
            'channel_name' => ['nullable', 'string', 'max:255'],
            'price_column_id' => ['required', 'exists:price_columns,id'],
        ]);

        $channelCode = trim((string) ($data['channel_code'] ?? ''));

        MarketplacePriceMapping::query()->updateOrCreate(
            [
                'marketplace_id' => $marketplace->id,
                'shop_id' => $data['shop_id'],
                'channel_code' => $channelCode,
            ],
            [
                'channel_name' => $channelCode !== ''
                    ? ($data['channel_name'] ?: $channelCode)
                    : null,
                'price_column_id' => $data['price_column_id'],
            ],
        );

        return redirect()->route('admin.marketplaces.edit', $marketplace)
            ->with('status', 'Mapping prix enregistré.');
    }

    public function destroyPriceMapping(Marketplace $marketplace, MarketplacePriceMapping $priceMapping): RedirectResponse
    {
        abort_unless($priceMapping->marketplace_id === $marketplace->id, 404);
        $priceMapping->delete();

        return redirect()->route('admin.marketplaces.edit', $marketplace)
            ->with('status', 'Mapping prix supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', Rule::enum(MarketplaceDriver::class)],
            'api_url' => ['required', 'url'],
            'api_key' => ['nullable', 'string'],
            'client_key' => ['nullable', 'string'],
            'client_secret' => ['nullable', 'string'],
            'shop_ids' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $driver = MarketplaceDriver::from($data['driver']);
        if ($driver !== MarketplaceDriver::Mirakl) {
            $data['shop_ids'] = null;
        }

        // Unchecked checkbox is absent from the request — must default to false.
        $data['is_active'] = $request->boolean('is_active');

        return $data;
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
