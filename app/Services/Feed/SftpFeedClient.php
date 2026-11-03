<?php

namespace App\Services\Feed;

use phpseclib4\Net\SFTP;
use RuntimeException;

final class SftpFeedClient
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            host: (string) config('feeds.sftp.host'),
            port: (int) config('feeds.sftp.port'),
            username: (string) config('feeds.sftp.username'),
            password: (string) config('feeds.sftp.password'),
        );
    }

    /**
     * @return list<list<string|null>>
     */
    public function readCsv(string $remotePath, string $delimiter = "\t"): array
    {
        $sftp = $this->connect();
        $content = $sftp->get($remotePath);

        if ($content === false) {
            throw new RuntimeException("Unable to download remote feed [{$remotePath}].");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'feed_');
        file_put_contents($tmp, $content);

        $rows = [];
        $handle = fopen($tmp, 'r');
        if ($handle === false) {
            unlink($tmp);
            throw new RuntimeException('Unable to open temporary feed file.');
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
    public function writeCsv(string $remotePath, array $rows, string $delimiter = "\t"): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'feedw_');
        $handle = fopen($tmp, 'w');
        if ($handle === false) {
            throw new RuntimeException('Unable to create temporary feed file.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, $delimiter);
        }
        fclose($handle);

        $sftp = $this->connect();
        if (! $sftp->put($remotePath, $tmp, SFTP::SOURCE_LOCAL_FILE)) {
            unlink($tmp);
            throw new RuntimeException("Unable to upload feed to [{$remotePath}].");
        }

        unlink($tmp);
    }

    private function connect(): SFTP
    {
        $sftp = new SFTP($this->host, $this->port);
        if (! $sftp->login($this->username, $this->password)) {
            throw new RuntimeException('SFTP authentication failed.');
        }

        return $sftp;
    }
}
