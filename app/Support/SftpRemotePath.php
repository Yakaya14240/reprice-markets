<?php

namespace App\Support;

/**
 * Normalize operator-entered SFTP paths.
 * Host belongs in connection_config; write_location is only the remote file path.
 *
 * Paths may be absolute (/var/www/...) or relative to the SFTP account home
 * (e.g. test_clement/file.csv when the account opens in .../current).
 */
final class SftpRemotePath
{
    public static function normalize(?string $path, ?string $host = null): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }

        $path = (string) preg_replace('#^sftp://#i', '', $path);

        $host = $host !== null ? trim($host) : '';
        if ($host !== '') {
            $host = (string) preg_replace('#^sftp://#i', '', $host);
            $host = rtrim($host, '/');
            if (str_starts_with(strtolower($path), strtolower($host).'/')) {
                $path = substr($path, strlen($host) + 1);
            } elseif (strcasecmp($path, $host) === 0) {
                return null;
            }
        }

        // host.example.com/remote/file.csv → remote/file.csv (relative to SFTP home)
        if (! str_starts_with($path, '/') && preg_match('#^([A-Za-z0-9._-]+\.[A-Za-z0-9._-]+)/(.+)$#', $path, $matches) === 1) {
            $path = $matches[2];
        }

        $absolute = str_starts_with($path, '/');
        $path = (string) preg_replace('#/+#', '/', $path);
        if ($absolute) {
            $path = '/'.ltrim($path, '/');
        } else {
            $path = ltrim($path, '/');
        }

        return $path === '' || $path === '/' ? null : $path;
    }

    public static function looksLikeHostPrefixedPath(string $path): bool
    {
        $path = trim(str_replace('\\', '/', $path));

        return (bool) preg_match('#^([A-Za-z0-9._-]+\.[A-Za-z0-9._-]+)/.+#', $path);
    }
}
