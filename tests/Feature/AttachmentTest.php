<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $name = 'nota-fiscal.pdf', int $kilobytes = 200): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_tecnico_attaches_invoice_to_asset(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($tecnico)
            ->post(route('assets.attachments.store', $asset), ['arquivo' => $this->pdf(), 'categoria' => 'nota_fiscal'])
            ->assertRedirect(route('assets.show', $asset));

        $attachment = Attachment::firstOrFail();

        $this->assertSame('nota-fiscal.pdf', $attachment->original_name);
        $this->assertSame('nota_fiscal', $attachment->category);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringNotContainsString('nota-fiscal', $attachment->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'anexo', 'subject_id' => $asset->id]);
    }

    public function test_license_accepts_attachments_too(): void
    {
        $license = License::factory()->create();

        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('licenses.attachments.store', $license), ['arquivo' => $this->pdf('contrato.pdf'), 'categoria' => 'contrato'])
            ->assertRedirect(route('licenses.show', $license));

        $this->assertSame(1, $license->attachments()->count());
    }

    public function test_files_over_two_megabytes_are_rejected(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('assets.attachments.store', $asset), ['arquivo' => $this->pdf('grande.pdf', 3000), 'categoria' => 'nota_fiscal'])
            ->assertSessionHasErrors('arquivo');

        $this->assertSame(0, Attachment::count());
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $asset = Asset::factory()->create();
        $tecnico = User::factory()->tecnico()->create();

        foreach ([['pagina.html', 'text/html'], ['programa.exe', 'application/x-msdownload']] as [$name, $mime]) {
            $this->actingAs($tecnico)
                ->post(route('assets.attachments.store', $asset), [
                    'arquivo' => UploadedFile::fake()->create($name, 10, $mime),
                    'categoria' => 'outro',
                ])
                ->assertSessionHasErrors('arquivo');
        }

        $this->assertSame(0, Attachment::count());
    }

    public function test_limit_of_attachments_per_item(): void
    {
        $asset = Asset::factory()->create();
        $tecnico = User::factory()->tecnico()->create();

        for ($i = 1; $i <= Attachment::MAX_PER_ITEM; $i++) {
            $this->actingAs($tecnico)->post(route('assets.attachments.store', $asset), [
                'arquivo' => $this->pdf("doc{$i}.pdf", 50), 'categoria' => 'outro',
            ]);
        }

        $this->actingAs($tecnico)
            ->post(route('assets.attachments.store', $asset), ['arquivo' => $this->pdf('extra.pdf', 50), 'categoria' => 'outro'])
            ->assertSessionHasErrors('arquivo');

        $this->assertSame(Attachment::MAX_PER_ITEM, $asset->attachments()->count());
    }

    public function test_read_only_user_can_view_but_not_upload_or_remove(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('assets.attachments.store', $asset), ['arquivo' => $this->pdf(), 'categoria' => 'nota_fiscal']);
        $attachment = Attachment::firstOrFail();
        $leitura = User::factory()->leitura()->create();

        $this->actingAs($leitura)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($leitura)
            ->post(route('assets.attachments.store', $asset), ['arquivo' => $this->pdf('outro.pdf'), 'categoria' => 'outro'])
            ->assertForbidden();
        $this->actingAs($leitura)->delete(route('attachments.destroy', $attachment))->assertForbidden();
    }

    public function test_removal_deletes_file_and_is_audited(): void
    {
        $asset = Asset::factory()->create();
        $tecnico = User::factory()->tecnico()->create();
        $this->actingAs($tecnico)
            ->post(route('assets.attachments.store', $asset), ['arquivo' => $this->pdf(), 'categoria' => 'nota_fiscal']);
        $attachment = Attachment::firstOrFail();

        $this->actingAs($tecnico)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect(route('assets.show', $asset));

        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertSame(0, Attachment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'anexo_removido', 'subject_id' => $asset->id]);
    }

    public function test_admin_downloads_all_attachments_as_zip(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('assets.attachments.store', $asset), ['arquivo' => $this->pdf(), 'categoria' => 'nota_fiscal']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('backups.attachments'))
            ->assertOk()
            ->assertDownload();
    }
}