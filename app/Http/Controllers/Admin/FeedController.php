<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedSourceType;
use App\Enums\PriceDecimalSeparator;
use App\Http\Controllers\Controller;
use App\Models\FeedFile;
use App\Services\Feed\FeedIO;
use App\Support\SftpRemotePath;
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
            'feeds' => FeedFile::query()->withCount('priceColumns')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.feeds.create', [
            'sourceTypes' => FeedSourceType::cases(),
            'priceDecimalSeparators' => PriceDecimalSeparator::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $feed = FeedFile::query()->create($data);

        if (! $feed->hasWritableDestination()) {
            return redirect()
                ->route('admin.feeds.edit', $feed)
                ->with('status', 'Flux créé. Attention : configurez une destination d’écriture (local/SFTP) pour pouvoir publier.');
        }

        return redirect()->route('admin.feeds.index')->with('status', 'Flux créé.');
    }

    public function edit(FeedFile $feed): View
    {
        $feed->load('snapshot');

        return view('admin.feeds.edit', [
            'feed' => $feed,
            'sourceTypes' => FeedSourceType::cases(),
            'priceDecimalSeparators' => PriceDecimalSeparator::cases(),
            'availableHeaders' => $feed->availableHeaders(),
        ]);
    }

    public function update(Request $request, FeedFile $feed): RedirectResponse
    {
        $data = $this->validated($request, $feed);
        $feed->update($data);

        $feed->refresh();
        if (! $feed->hasWritableDestination()) {
            return redirect()
                ->route('admin.feeds.edit', $feed)
                ->with('status', 'Flux enregistré, mais destination d’écriture manquante (requis pour publier).');
        }

        return redirect()->route('admin.feeds.index')->with('status', 'Flux mis à jour.');
    }

    public function test(FeedFile $feed, FeedIO $feedIO): JsonResponse
    {
        $result = $feedIO->probe($feed);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function testWrite(FeedFile $feed, FeedIO $feedIO): JsonResponse
    {
        $result = $feedIO->probeWrite($feed);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?FeedFile $feed = null): array
    {
        // Empty number inputs arrive as "" before ConvertEmptyStringsToNull in some paths.
        if ($request->input('sftp_port') === '' || $request->input('sftp_port') === null) {
            $request->merge(['sftp_port' => null]);
        }

        $needsExplicitWrite = in_array($request->input('source_type'), [
            FeedSourceType::Url->value,
        ], true) || filled($request->input('write_source_type'));

        $usesSftp = in_array($request->input('source_type'), [FeedSourceType::Sftp->value], true)
            || $request->input('write_source_type') === FeedSourceType::Sftp->value;

        $existingConfig = is_array($feed?->connection_config) ? $feed->connection_config : [];
        $hasStoredSftp = filled($existingConfig['host'] ?? null) && filled($existingConfig['username'] ?? null);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::enum(FeedSourceType::class)],
            'location' => ['required', 'string', 'max:2048'],
            'write_source_type' => [
                Rule::requiredIf(fn () => $request->input('source_type') === FeedSourceType::Url->value),
                'nullable',
                Rule::enum(FeedSourceType::class),
                Rule::notIn([FeedSourceType::Url->value]),
            ],
            'write_location' => [
                Rule::requiredIf(fn () => $needsExplicitWrite),
                'nullable',
                'string',
                'max:2048',
                Rule::when(
                    $usesSftp,
                    ['not_regex:/^[A-Za-z]:[\\\\\\/]/', 'not_regex:/^\\\\\\\\/'],
                ),
            ],
            'delimiter' => ['required', 'string', 'max:8'],
            'price_decimal_separator' => ['nullable', Rule::enum(PriceDecimalSeparator::class)],
            'sku_column' => ['required', 'string', 'max:255'],
            'name_column' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sftp_host' => [
                Rule::requiredIf(fn () => $usesSftp && ! $hasStoredSftp && ! filled(config('feeds.sftp.host'))),
                'nullable',
                'string',
                'max:255',
            ],
            'sftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'sftp_username' => [
                Rule::requiredIf(fn () => $usesSftp && ! $hasStoredSftp && ! filled(config('feeds.sftp.username'))),
                'nullable',
                'string',
                'max:255',
            ],
            'sftp_password' => [
                Rule::requiredIf(fn () => $usesSftp
                    && ! filled($existingConfig['password'] ?? null)
                    && ! filled(config('feeds.sftp.password'))),
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'write_location.required' => 'L\'emplacement d\'écriture (chemin local ou SFTP) est obligatoire.',
            'write_source_type.required' => 'Le type d\'écriture est obligatoire pour une source URL.',
            'sftp_host.required' => 'Le host SFTP est obligatoire.',
            'sftp_username.required' => 'Le username SFTP est obligatoire.',
            'sftp_password.required' => 'Le mot de passe SFTP est obligatoire.',
            'write_location.not_regex' => 'Pour une écriture SFTP, utilisez un chemin distant Unix (ex. /dossier/fichier.csv), pas un chemin Windows C:\\...',
        ], [
            'name' => 'nom',
            'source_type' => 'type de source',
            'location' => 'emplacement lecture',
            'write_source_type' => 'type d\'écriture',
            'write_location' => 'emplacement écriture',
            'delimiter' => 'délimiteur',
            'price_decimal_separator' => 'séparateur décimal des prix',
            'sku_column' => 'colonne SKU',
            'name_column' => 'colonne nom produit',
            'sftp_host' => 'host SFTP',
            'sftp_port' => 'port SFTP',
            'sftp_username' => 'username SFTP',
            'sftp_password' => 'mot de passe SFTP',
        ]);

        $config = $existingConfig;
        $configChanged = false;

        if ($request->filled('sftp_host')) {
            $config['host'] = trim((string) $data['sftp_host']);
            $configChanged = true;
        }
        if ($request->filled('sftp_port')) {
            $config['port'] = (int) $data['sftp_port'];
            $configChanged = true;
        }
        if ($request->filled('sftp_username')) {
            $config['username'] = trim((string) $data['sftp_username']);
            $configChanged = true;
        }
        if ($request->filled('sftp_password')) {
            $config['password'] = (string) $data['sftp_password'];
            $configChanged = true;
        }

        $writeLocation = $data['write_location'] ?? null;
        $readLocation = $data['location'];
        $sftpHost = $config['host'] ?? ($existingConfig['host'] ?? config('feeds.sftp.host'));

        if ($usesSftp) {
            // If operator pasted host/path into write_location, peel host into config when missing.
            $rawWrite = trim((string) ($writeLocation ?? ''));
            if (
                $rawWrite !== ''
                && SftpRemotePath::looksLikeHostPrefixedPath($rawWrite)
                && ! filled($sftpHost)
            ) {
                if (preg_match('#^([A-Za-z0-9._-]+\.[A-Za-z0-9._-]+)/#', str_replace('\\', '/', $rawWrite), $m) === 1) {
                    $config['host'] = $m[1];
                    $sftpHost = $m[1];
                    $configChanged = true;
                }
            }

            $writeLocation = SftpRemotePath::normalize($writeLocation, is_string($sftpHost) ? $sftpHost : null);

            if ($data['source_type'] === FeedSourceType::Sftp->value) {
                $readLocation = SftpRemotePath::normalize($readLocation, is_string($sftpHost) ? $sftpHost : null) ?? $readLocation;
            }
        }

        $payload = [
            'name' => $data['name'],
            'source_type' => $data['source_type'],
            'location' => $readLocation,
            'write_source_type' => $data['write_source_type'] ?? null,
            'write_location' => $writeLocation,
            'delimiter' => $data['delimiter'] === '\t' ? "\t" : $data['delimiter'],
            'price_decimal_separator' => $data['price_decimal_separator'] ?? PriceDecimalSeparator::Dot->value,
            'sku_column' => $data['sku_column'],
            'name_column' => $data['name_column'],
            'is_active' => $request->boolean('is_active', true),
        ];

        // Only write connection_config when SFTP fields were submitted, so we never wipe stored secrets.
        if ($configChanged) {
            $payload['connection_config'] = $config;
        }

        return $payload;
    }
}
