<?php

namespace App\Services\Feed;

use App\Enums\FeedSourceType;
use App\Models\FeedFile;
use App\Support\SftpRemotePath;
use Illuminate\Support\Facades\Http;
use phpseclib4\Net\SFTP;
use RuntimeException;

final class FeedIO
{
    public function __construct(private readonly LengowCsvParser $parser) {}

    /**
     * @return array{headers: list<string>, products: list<array<string, string|null>>}
     */
    public function read(FeedFile $feed): array
    {
        $raw = match ($feed->source_type) {
            FeedSourceType::Local => $this->readLocal((string) $feed->location),
            FeedSourceType::Url => $this->readUrl((string) $feed->location),
            FeedSourceType::Sftp => $this->readSftp($feed, (string) $feed->location),
        };

        $rows = $this->parseDelimited($raw, $feed->delimiter ?: "\t");

        return $this->parser->parse($rows);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string|null>>  $products
     */
    public function write(FeedFile $feed, array $headers, array $products): void
    {
        if (! $feed->hasWritableDestination()) {
            throw new RuntimeException(
                'Destination d’écriture non configurée. Renseignez un chemin local ou SFTP (write_location) en admin.'
            );
        }

        $sourceType = $feed->effectiveWriteSourceType();
        $location = $feed->effectiveWriteLocation();

        $rows = $this->parser->toRows($headers, $products);
        $content = $this->rowsToContent($rows, $feed->delimiter ?: "\t");

        try {
            match ($sourceType) {
                FeedSourceType::Local => $this->writeLocal($location, $content),
                FeedSourceType::Sftp => $this->writeSftp($feed, $location, $content),
                FeedSourceType::Url => throw new RuntimeException('URL is not a writable destination.'),
            };
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Échec écriture flux [{$sourceType->label()} → {$location}] : ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Lightweight connectivity check used by admin "Tester".
     *
     * @return array{ok: bool, status: int|null, bytes: int|null, message: string}
     */
    public function probe(FeedFile $feed): array
    {
        try {
            return match ($feed->source_type) {
                FeedSourceType::Url => $this->probeUrl((string) $feed->location),
                FeedSourceType::Local => $this->probeLocal((string) $feed->location),
                FeedSourceType::Sftp => $this->probeSftp($feed, (string) $feed->location),
            };
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'bytes' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check that the configured write destination is usable.
     *
     * @return array{ok: bool, status: int|null, bytes: int|null, message: string}
     */
    public function probeWrite(FeedFile $feed): array
    {
        try {
            if (! $feed->hasWritableDestination()) {
                return [
                    'ok' => false,
                    'status' => null,
                    'bytes' => null,
                    'message' => 'Écriture non configurée (type + chemin d’écriture requis pour une source URL).',
                ];
            }

            $type = $feed->effectiveWriteSourceType();
            $location = $feed->effectiveWriteLocation();

            if ($type === FeedSourceType::Sftp && $this->looksLikeWindowsLocalPath($location)) {
                return [
                    'ok' => false,
                    'status' => null,
                    'bytes' => null,
                    'message' => "Chemin Windows local détecté ({$location}) avec type SFTP. "
                        .'Soit passez le type d’écriture en « Fichier local », '
                        .'soit mettez un chemin distant Unix (ex. /test_clement/Flux.csv).',
                ];
            }

            return match ($type) {
                FeedSourceType::Local => $this->probeLocalWritable($location),
                FeedSourceType::Sftp => $this->probeSftpWritable($feed, $location),
                FeedSourceType::Url => [
                    'ok' => false,
                    'status' => null,
                    'bytes' => null,
                    'message' => 'URL ne peut pas servir de destination d’écriture.',
                ],
            };
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'bytes' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function readLocal(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("Local feed file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Unable to read local feed: {$path}");
        }

        return $content;
    }

    private function writeLocal(string $path, string $content): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create directory: {$dir}");
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Unable to write local feed file: {$path}");
        }
    }

    private function readUrl(string $url): string
    {
        $url = trim($url);
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Invalid feed URL: {$url}");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'feedurl_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temporary file for URL download.');
        }

        try {
            $response = Http::timeout(180)
                ->withHeaders($this->browserHeaders())
                ->withOptions([
                    'allow_redirects' => true,
                    'sink' => $tmp,
                    'http_errors' => false,
                ])
                ->get($url);

            $status = $response->status();
            $bytes = is_file($tmp) ? filesize($tmp) : 0;

            if ($status < 200 || $status >= 300) {
                $snippet = is_file($tmp) ? substr((string) file_get_contents($tmp), 0, 200) : '';
                throw new RuntimeException(
                    "Remote feed URL returned HTTP {$status} for [{$url}]"
                    .($snippet !== '' ? " — body: {$snippet}" : '')
                );
            }

            if ($bytes === 0 || $bytes === false) {
                throw new RuntimeException("Remote feed URL returned an empty body for [{$url}]");
            }

            $content = file_get_contents($tmp);
            if ($content === false) {
                throw new RuntimeException("Unable to read downloaded feed for [{$url}]");
            }

            return $content;
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /**
     * @return array{ok: bool, status: int|null, bytes: int|null, message: string}
     */
    private function probeUrl(string $url): array
    {
        $url = trim($url);
        $response = Http::timeout(60)
            ->withHeaders($this->browserHeaders())
            ->withOptions(['allow_redirects' => true, 'http_errors' => false])
            ->head($url);

        // Some CDNs disallow HEAD — fallback to ranged GET.
        if ($response->status() === 405 || $response->status() === 403 || $response->status() === 404) {
            $response = Http::timeout(60)
                ->withHeaders($this->browserHeaders() + ['Range' => 'bytes=0-1023'])
                ->withOptions(['allow_redirects' => true, 'http_errors' => false])
                ->get($url);
        }

        $status = $response->status();
        $ok = $status >= 200 && $status < 300;
        $bytes = (int) ($response->header('Content-Length') ?: strlen($response->body()));

        return [
            'ok' => $ok,
            'status' => $status,
            'bytes' => $bytes,
            'message' => $ok
                ? "OK — HTTP {$status}, {$bytes} octets"
                : "Échec — HTTP {$status} pour [{$url}]",
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, bytes: int|null, message: string}
     */
    private function probeLocal(string $path): array
    {
        if (! is_file($path)) {
            return ['ok' => false, 'status' => null, 'bytes' => null, 'message' => "Fichier introuvable: {$path}"];
        }

        return [
            'ok' => true,
            'status' => 200,
            'bytes' => filesize($path) ?: 0,
            'message' => 'OK — fichier local accessible',
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, bytes: int|null, message: string}
     */
    private function probeLocalWritable(string $path): array
    {
        if (is_dir($path)) {
            return [
                'ok' => false,
                'status' => null,
                'bytes' => null,
                'message' => "Chemin local = répertoire ({$path}). Indiquez un fichier complet, ex. {$path}".DIRECTORY_SEPARATOR.'Flux.csv',
            ];
        }

        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return ['ok' => false, 'status' => null, 'bytes' => null, 'message' => "Répertoire non créable: {$dir}"];
        }

        if (is_file($path) && ! is_writable($path)) {
            return ['ok' => false, 'status' => null, 'bytes' => null, 'message' => "Fichier non inscriptible: {$path}"];
        }

        if (! is_file($path) && ! is_writable($dir)) {
            return ['ok' => false, 'status' => null, 'bytes' => null, 'message' => "Répertoire non inscriptible: {$dir}"];
        }

        return [
            'ok' => true,
            'status' => 200,
            'bytes' => is_file($path) ? (filesize($path) ?: 0) : null,
            'message' => "OK — écriture locale possible → {$path}",
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, bytes: int|null, message: string}
     */
    private function probeSftp(FeedFile $feed, string $remotePath): array
    {
        $sftp = $this->connectSftp($feed);
        $stat = $sftp->stat($remotePath);
        if ($stat === false) {
            return ['ok' => false, 'status' => null, 'bytes' => null, 'message' => "Chemin SFTP introuvable: {$remotePath}"];
        }

        return [
            'ok' => true,
            'status' => 200,
            'bytes' => (int) ($stat['size'] ?? 0),
            'message' => 'OK — fichier SFTP accessible',
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, bytes: int|null, message: string}
     */
    private function probeSftpWritable(FeedFile $feed, string $remotePath): array
    {
        $host = (string) (($feed->connection_config['host'] ?? null) ?: config('feeds.sftp.host'));
        $remotePath = SftpRemotePath::normalize($remotePath, $host) ?? '';
        if ($remotePath === '') {
            return [
                'ok' => false,
                'status' => null,
                'bytes' => null,
                'message' => 'Chemin SFTP d’écriture invalide. Exemple (compte dans current) : test_clement/Flux_Sun-chauffage.csv',
            ];
        }

        $sftp = $this->connectSftp($feed);
        $pwd = method_exists($sftp, 'pwd') ? (string) $sftp->pwd() : '?';

        // phpseclib4::stat() throws SSH_FX_NO_SUCH_FILE when the target file does not exist yet.
        // Use file_exists/is_dir — writing creates the file; it must not be required beforehand.
        $isDir = str_ends_with($remotePath, '/') || $sftp->is_dir($remotePath);

        if ($isDir) {
            $dir = rtrim($remotePath, '/') ?: '.';
            $probePath = $dir.'/.agrigator-write-probe';
        } else {
            $dir = dirname(str_replace('\\', '/', $remotePath));
            if ($dir === '.' || $dir === '\\' || $dir === '') {
                $dir = '.';
            } else {
                $dir = SftpRemotePath::normalize($dir, $host) ?? $dir;
            }
            $probePath = $remotePath.'.agrigator-write-probe';
        }

        if ($dir !== '.' && $dir !== '/' && ! $sftp->file_exists($dir)) {
            try {
                $sftp->mkdir($dir, -1, true);
            } catch (\Throwable $e) {
                $listing = $this->safeSftpListing($sftp, '.');

                return [
                    'ok' => false,
                    'status' => null,
                    'bytes' => null,
                    'message' => "Répertoire SFTP introuvable/non créable sur {$host}: {$dir}. "
                        ."Home SFTP={$pwd}. Contenu du home : {$listing}. "
                        .'Si le compte ouvre current, utilisez test_clement/Flux_Sun-chauffage.csv (chemin relatif). '
                        .'Détail: '.$e->getMessage(),
                ];
            }
        }

        try {
            $sftp->put($probePath, "agrigator write probe\n");
            try {
                $sftp->delete($probePath);
            } catch (\Throwable) {
                // ignore cleanup failure
            }
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'bytes' => null,
                'message' => "Écriture SFTP impossible sur {$host} → {$probePath} (home={$pwd}) — ".$e->getMessage(),
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'bytes' => null,
            'message' => "OK — écriture SFTP possible → {$host}:{$remotePath}",
        ];
    }

    private function safeSftpListing(SFTP $sftp, string $dir): string
    {
        try {
            $list = $sftp->nlist($dir);
            $list = array_values(array_filter(
                $list,
                static fn (string $name): bool => ! in_array($name, ['.', '..'], true)
            ));
            $list = array_slice($list, 0, 12);

            return $list === [] ? '(vide)' : implode(', ', $list);
        } catch (\Throwable) {
            return '(listing impossible)';
        }
    }

    private function looksLikeWindowsLocalPath(string $path): bool
    {
        $path = trim($path);

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || str_starts_with($path, '\\\\');
    }

    private function readSftp(FeedFile $feed, string $remotePath): string
    {
        $sftp = $this->connectSftp($feed);
        $content = $sftp->get($remotePath);
        if ($content === false) {
            throw new RuntimeException("Unable to download SFTP feed: {$remotePath}");
        }

        return $content;
    }

    private function writeSftp(FeedFile $feed, string $remotePath, string $content): void
    {
        $host = (string) (($feed->connection_config['host'] ?? null) ?: config('feeds.sftp.host'));
        $remotePath = SftpRemotePath::normalize($remotePath, $host) ?? $remotePath;
        $sftp = $this->connectSftp($feed);

        $dir = dirname(str_replace('\\', '/', $remotePath));
        if ($dir !== '/' && $dir !== '.' && $dir !== '' && ! $sftp->file_exists($dir)) {
            $sftp->mkdir($dir, -1, true);
        }

        try {
            $sftp->put($remotePath, $content);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Unable to upload SFTP feed to {$host}:{$remotePath} — ".$e->getMessage(),
                previous: $e
            );
        }
    }

    private function connectSftp(FeedFile $feed): SFTP
    {
        $config = $feed->connection_config ?? [];
        $host = (string) ($config['host'] ?? config('feeds.sftp.host'));
        $port = (int) ($config['port'] ?? config('feeds.sftp.port', 2200));
        $username = (string) ($config['username'] ?? config('feeds.sftp.username'));
        $password = (string) ($config['password'] ?? config('feeds.sftp.password'));

        if ($host === '' || $username === '') {
            throw new RuntimeException('SFTP credentials are missing for this feed (host/username).');
        }

        $sftp = new SFTP($host, $port);
        try {
            $sftp->login($username, $password);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "SFTP authentication failed for {$username}@{$host}:{$port} — ".$e->getMessage(),
                previous: $e
            );
        }

        return $sftp;
    }

    /**
     * @return array<string, string>
     */
    private function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (compatible; Agrigator/1.0; +internal)',
            'Accept' => 'text/csv,text/plain,*/*',
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseDelimited(string $content, string $delimiter): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'feedr_');
        file_put_contents($tmp, $content);
        $rows = [];
        $handle = fopen($tmp, 'r');
        if ($handle === false) {
            unlink($tmp);
            throw new RuntimeException('Unable to parse feed content.');
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        unlink($tmp);

        return $rows;
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function rowsToContent(array $rows, string $delimiter): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'feedw_');
        $handle = fopen($tmp, 'w');
        if ($handle === false) {
            throw new RuntimeException('Unable to serialize feed content.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, $delimiter);
        }
        fclose($handle);
        $content = file_get_contents($tmp) ?: '';
        unlink($tmp);

        return $content;
    }
}
