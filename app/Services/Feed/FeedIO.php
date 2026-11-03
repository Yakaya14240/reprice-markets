<?php

namespace App\Services\Feed;

use App\Enums\FeedSourceType;
use App\Models\FeedFile;
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
        $sourceType = $feed->effectiveWriteSourceType();
        $location = $feed->effectiveWriteLocation();

        if ($sourceType === FeedSourceType::Url) {
            throw new RuntimeException('URL sources are read-only. Configure a write location (SFTP or local).');
        }

        $rows = $this->parser->toRows($headers, $products);
        $content = $this->rowsToContent($rows, $feed->delimiter ?: "\t");

        match ($sourceType) {
            FeedSourceType::Local => $this->writeLocal($location, $content),
            FeedSourceType::Sftp => $this->writeSftp($feed, $location, $content),
            FeedSourceType::Url => throw new RuntimeException('Unsupported write source.'),
        };
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
            throw new RuntimeException("Unable to write local feed: {$path}");
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
        $sftp = $this->connectSftp($feed);
        if (! $sftp->put($remotePath, $content)) {
            throw new RuntimeException("Unable to upload SFTP feed: {$remotePath}");
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
            throw new RuntimeException('SFTP credentials are missing for this feed.');
        }

        $sftp = new SFTP($host, $port);
        if (! $sftp->login($username, $password)) {
            throw new RuntimeException('SFTP authentication failed.');
        }

        return $sftp;
    }

    /**
     * @return array<string, string>
     */
    private function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (compatible; reprice-markets/1.0; +internal)',
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
