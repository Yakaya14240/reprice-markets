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
        $response = Http::timeout(120)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("Unable to download feed URL (HTTP {$response->status()}).");
        }

        return $response->body();
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
