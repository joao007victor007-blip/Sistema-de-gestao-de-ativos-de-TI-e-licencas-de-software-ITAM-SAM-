<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\License;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceOnCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function assetData(array $overrides = []): array
    {
        return [
            'category_id' => Category::firstOrCreate(['name' => 'Notebook'])->id,
            'model' => 'Latitude 5440',
            'status' => 'disponivel',
            ...$overrides,
        ];
    }

    public function test_asset_can_be_created_with_its_invoice(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('assets.store'), $this->assetData([
                'invoice_file' => UploadedFile::fake()->create('nf-notebook.pdf', 200, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        $asset = Asset::firstOrFail();
        $attachment = $asset->attachments()->firstOrFail();

        $this->assertSame('nota_fiscal', $attachment->category);
        $this->assertSame('nf-notebook.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_asset_without_invoice_still_works(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('assets.store'), $this->assetData())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Asset::count());
        $this->assertSame(0, Attachment::count());
    }

    public function test_invalid_invoice_blocks_the_whole_registration(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('assets.store'), $this->assetData([
                'invoice_file' => UploadedFile::fake()->create('nota.pdf', 3000, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('invoice_file');

        $this->assertSame(0, Asset::count());
        $this->assertSame(0, Attachment::count());
    }

    public function test_license_can_be_created_with_its_invoice(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('licenses.store'), [
                'software_id' => Software::firstOrCreate(['name' => 'Microsoft 365'])->id,
                'name' => 'Licenca Com Nota',
                'license_type' => 'perpetua',
                'assignment_type' => 'per_user',
                'seats' => 5,
                'alert_days' => 10,
                'invoice_file' => UploadedFile::fake()->create('nf-licenca.pdf', 150, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, License::where('name', 'Licenca Com Nota')->first()->attachments()->count());
    }

    public function test_edit_form_does_not_show_the_invoice_field(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->tecnico()->create())
            ->get(route('assets.edit', $asset))
            ->assertDontSee('Arquivo da nota fiscal');
    }
}