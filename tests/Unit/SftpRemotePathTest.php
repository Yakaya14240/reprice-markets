<?php

namespace Tests\Unit;

use App\Support\SftpRemotePath;
use PHPUnit\Framework\TestCase;

class SftpRemotePathTest extends TestCase
{
    public function test_it_strips_host_and_keeps_home_relative_path(): void
    {
        $this->assertSame(
            'test_clement/Flux_Sun-chauffage.csv',
            SftpRemotePath::normalize(
                'sftp.main.multithermique.aws.keenobi.com/test_clement/Flux_Sun-chauffage.csv',
                'sftp.main.multithermique.aws.keenobi.com'
            )
        );

        $this->assertSame(
            'test_clement/Flux_Sun-chauffage.csv',
            SftpRemotePath::normalize('test_clement/Flux_Sun-chauffage.csv')
        );

        $this->assertSame(
            'test_clement/Flux_Sun-chauffage.csv',
            SftpRemotePath::normalize('sftp://sftp.example.com/test_clement/Flux_Sun-chauffage.csv', 'sftp.example.com')
        );

        $this->assertSame(
            '/var/www/cdn/current/test_clement/Flux.csv',
            SftpRemotePath::normalize('/var/www/cdn/current/test_clement/Flux.csv')
        );
    }
}
