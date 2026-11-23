<?php

namespace Tests\Feature;

use App\Enums\FeedSourceType;
use App\Enums\UserRole;
use App\Models\FeedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeedSftpConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_persist_sftp_connection_config_and_write_destination(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'URL feed',
            'source_type' => FeedSourceType::Url,
            'location' => 'https://cdn.example.test/flux.csv',
            'write_source_type' => null,
            'write_location' => null,
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
            'connection_config' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.feeds.update', $feed), [
                'name' => 'URL feed',
                'source_type' => FeedSourceType::Url->value,
                'location' => 'https://cdn.example.test/flux.csv',
                'write_source_type' => FeedSourceType::Sftp->value,
                'write_location' => '/remote/Flux.csv',
                'delimiter' => '\t',
                'sku_column' => 'Identifiant_unique',
                'name_column' => 'Titre',
                'is_active' => '1',
                'sftp_host' => 'sftp.example.test',
                'sftp_port' => '2200',
                'sftp_username' => 'lengow',
                'sftp_password' => 'secret-pass',
            ])
            ->assertRedirect();

        $feed->refresh();

        $this->assertSame(FeedSourceType::Sftp, $feed->write_source_type);
        $this->assertSame('/remote/Flux.csv', $feed->write_location);
        $this->assertSame([
            'host' => 'sftp.example.test',
            'port' => 2200,
            'username' => 'lengow',
            'password' => 'secret-pass',
        ], $feed->connection_config);
    }

    public function test_admin_can_save_sftp_fields_when_port_left_blank(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'SFTP feed',
            'source_type' => FeedSourceType::Sftp,
            'location' => '/remote/in.csv',
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.feeds.edit', $feed))
            ->put(route('admin.feeds.update', $feed), [
                'name' => 'SFTP feed',
                'source_type' => FeedSourceType::Sftp->value,
                'location' => '/remote/in.csv',
                'write_source_type' => '',
                'write_location' => '',
                'delimiter' => '\t',
                'sku_column' => 'Identifiant_unique',
                'name_column' => 'Titre',
                'is_active' => '1',
                'sftp_host' => 'sftp.example.test',
                'sftp_port' => '',
                'sftp_username' => 'lengow',
                'sftp_password' => 'secret-pass',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $feed->refresh();
        $this->assertSame('sftp.example.test', $feed->connection_config['host'] ?? null);
        $this->assertSame('lengow', $feed->connection_config['username'] ?? null);
        $this->assertSame('secret-pass', $feed->connection_config['password'] ?? null);
    }

    public function test_admin_keeps_sftp_password_when_left_blank_on_update(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'SFTP feed',
            'source_type' => FeedSourceType::Sftp,
            'location' => '/remote/in.csv',
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
            'connection_config' => [
                'host' => 'sftp.example.test',
                'port' => 2200,
                'username' => 'lengow',
                'password' => 'keep-me',
            ],
        ]);

        $this->actingAs($admin)
            ->put(route('admin.feeds.update', $feed), [
                'name' => 'SFTP feed renamed',
                'source_type' => FeedSourceType::Sftp->value,
                'location' => '/remote/in.csv',
                'write_source_type' => '',
                'write_location' => '',
                'delimiter' => '\t',
                'sku_column' => 'Identifiant_unique',
                'name_column' => 'Titre',
                'is_active' => '1',
                'sftp_host' => 'sftp.example.test',
                'sftp_port' => '2200',
                'sftp_username' => 'lengow',
                'sftp_password' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $feed->refresh();
        $this->assertSame('SFTP feed renamed', $feed->name);
        $this->assertSame('keep-me', $feed->connection_config['password'] ?? null);
    }

    public function test_admin_normalizes_host_prefixed_sftp_write_path(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'URL feed',
            'source_type' => FeedSourceType::Url,
            'location' => 'https://cdn.example.test/flux.csv',
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.feeds.update', $feed), [
                'name' => 'URL feed',
                'source_type' => FeedSourceType::Url->value,
                'location' => 'https://cdn.example.test/flux.csv',
                'write_source_type' => FeedSourceType::Sftp->value,
                'write_location' => 'sftp.main.example.com/test_clement/Flux.csv',
                'delimiter' => '\t',
                'sku_column' => 'Identifiant_unique',
                'name_column' => 'Titre',
                'is_active' => '1',
                'sftp_host' => 'sftp.main.example.com',
                'sftp_port' => '2200',
                'sftp_username' => 'lengow',
                'sftp_password' => 'secret',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $feed->refresh();
        $this->assertSame('test_clement/Flux.csv', $feed->write_location);
        $this->assertSame('sftp.main.example.com', $feed->connection_config['host'] ?? null);
    }

    public function test_write_probe_rejects_windows_path_for_sftp(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'URL feed',
            'source_type' => FeedSourceType::Url,
            'location' => 'https://cdn.example.test/flux.csv',
            'write_source_type' => FeedSourceType::Sftp,
            'write_location' => 'C:\\Users\\damie\\project\\public\\dist',
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
            'connection_config' => [
                'host' => 'sftp.example.test',
                'port' => 2200,
                'username' => 'lengow',
                'password' => 'secret',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.feeds.test-write', $feed))
            ->assertStatus(422)
            ->assertJsonFragment(['ok' => false]);

        $this->assertStringContainsString('Chemin Windows local', (string) $response->json('message'));
    }

    public function test_url_feed_requires_write_location_when_write_type_is_sftp(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $feed = FeedFile::query()->create([
            'name' => 'URL feed',
            'source_type' => FeedSourceType::Url,
            'location' => 'https://cdn.example.test/flux.csv',
            'delimiter' => "\t",
            'sku_column' => 'Identifiant_unique',
            'name_column' => 'Titre',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.feeds.edit', $feed))
            ->put(route('admin.feeds.update', $feed), [
                'name' => 'URL feed',
                'source_type' => FeedSourceType::Url->value,
                'location' => 'https://cdn.example.test/flux.csv',
                'write_source_type' => FeedSourceType::Sftp->value,
                'write_location' => '',
                'delimiter' => '\t',
                'sku_column' => 'Identifiant_unique',
                'name_column' => 'Titre',
                'is_active' => '1',
                'sftp_host' => 'sftp.example.test',
                'sftp_username' => 'lengow',
                'sftp_password' => 'secret',
            ])
            ->assertRedirect(route('admin.feeds.edit', $feed))
            ->assertSessionHasErrors('write_location');
    }
}
