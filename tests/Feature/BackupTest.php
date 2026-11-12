<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Services\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function readBackup(string $relativePath): string
    {
        $content = Storage::disk('local')->get($relativePath);

        return str_ends_with($relativePath, '.gz') ? gzdecode($content) : $content;
    }

    public function test_command_creates_a_complete_backup(): void
    {
        Asset::factory()->create(['model' => 'Modelo No Backup']);

        $this->artisan('intranet:backup')->assertSuccessful();

        $files = Storage::disk('local')->files(BackupManager::DIRECTORY);
        $this->assertCount(1, $files);

        $sql = $this->readBackup($files[0]);

        $this->assertStringContainsString('CREATE TABLE `assets`', $sql);
        $this->assertStringContainsString('Modelo No Backup', $sql);
        $this->assertStringContainsString('CREATE TABLE `migrations`', $sql);
    }

    public function test_temporary_tables_keep_structure_but_not_data(): void
    {
        DB::table('sessions')->insert([
            'id' => 'sessao-que-nao-vai-para-o-backup',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'teste',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->artisan('intranet:backup')->assertSuccessful();

        $sql = $this->readBackup(Storage::disk('local')->files(BackupManager::DIRECTORY)[0]);

        $this->assertStringContainsString('CREATE TABLE `sessions`', $sql);
        $this->assertStringNotContainsString('sessao-que-nao-vai-para-o-backup', $sql);
    }

    public function test_old_backups_are_removed(): void
    {
        $disk = Storage::disk('local');
        $old = BackupManager::DIRECTORY . '/backup-2020-01-01-000000.sql.gz';
        $disk->put($old, 'antigo');
        touch($disk->path($old), now()->subDays(BackupManager::KEEP_DAYS + 1)->getTimestamp());

        $this->artisan('intranet:backup')->assertSuccessful();

        $this->assertFalse($disk->exists($old));
        $this->assertCount(1, $disk->files(BackupManager::DIRECTORY));
    }

    public function test_only_admin_can_access_backups(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())->get(route('backups.index'))->assertForbidden();
        $this->actingAs(User::factory()->leitura()->create())->get(route('backups.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('backups.index'))->assertOk();
    }

    public function test_manual_backup_and_download_are_audited(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('backups.store'))->assertSessionHas('status');

        $name = basename(Storage::disk('local')->files(BackupManager::DIRECTORY)[0]);

        $this->actingAs($admin)->get(route('backups.download', $name))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'backup', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_download', 'user_id' => $admin->id]);
    }

    public function test_download_rejects_names_outside_the_pattern(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/backups/..%2F..%2F.env')->assertNotFound();
        $this->actingAs($admin)->get('/backups/backup-2099-01-01-000000.sql.gz')->assertNotFound();
    }

    public function test_dashboard_warns_admin_when_backup_is_late(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSee('O último backup do banco tem mais de 48 horas');

        $this->actingAs(User::factory()->tecnico()->create())
            ->get(route('dashboard'))
            ->assertDontSee('O último backup do banco tem mais de 48 horas');
    }
}