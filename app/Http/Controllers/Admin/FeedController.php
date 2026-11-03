<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedSourceType;
use App\Http\Controllers\Controller;
use App\Models\FeedFile;
use App\Services\Feed\FeedIO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(): View
    {
        return view('admin.feeds.index', [
            'feeds' => FeedFile::query()->withCount('priceColumns')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.feeds.create', [
            'sourceTypes' => FeedSourceType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        FeedFile::query()->create($data);

        return redirect()->route('admin.feeds.index')->with('status', 'Flux créé.');
    }

    public function edit(FeedFile $feed): View
    {
        return view('admin.feeds.edit', [
            'feed' => $feed,
            'sourceTypes' => FeedSourceType::cases(),
        ]);
    }

    public function update(Request $request, FeedFile $feed): RedirectResponse
    {
        $data = $this->validated($request, $feed);
        $feed->update($data);

        return redirect()->route('admin.feeds.index')->with('status', 'Flux mis à jour.');
    }

    public function test(FeedFile $feed, FeedIO $feedIO): JsonResponse
    {
        $result = $feedIO->probe($feed);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?FeedFile $feed = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::enum(FeedSourceType::class)],
            'location' => ['required', 'string', 'max:2048'],
            'write_source_type' => ['nullable', Rule::enum(FeedSourceType::class)],
            'write_location' => ['nullable', 'string', 'max:2048'],
            'delimiter' => ['required', 'string', 'max:8'],
            'sku_column' => ['required', 'string', 'max:255'],
            'name_column' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sftp_host' => ['nullable', 'string', 'max:255'],
            'sftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'sftp_username' => ['nullable', 'string', 'max:255'],
            'sftp_password' => ['nullable', 'string', 'max:255'],
        ]);

        $config = $feed?->connection_config ?? [];
        if ($request->filled('sftp_host')) {
            $config['host'] = $data['sftp_host'];
        }
        if ($request->filled('sftp_port')) {
            $config['port'] = (int) $data['sftp_port'];
        }
        if ($request->filled('sftp_username')) {
            $config['username'] = $data['sftp_username'];
        }
        if ($request->filled('sftp_password')) {
            $config['password'] = $data['sftp_password'];
        }

        return [
            'name' => $data['name'],
            'source_type' => $data['source_type'],
            'location' => $data['location'],
            'write_source_type' => $data['write_source_type'] ?? null,
            'write_location' => $data['write_location'] ?? null,
            'delimiter' => $data['delimiter'] === '\t' ? "\t" : $data['delimiter'],
            'sku_column' => $data['sku_column'],
            'name_column' => $data['name_column'],
            'is_active' => $request->boolean('is_active', true),
            'connection_config' => $config ?: null,
        ];
    }
}
