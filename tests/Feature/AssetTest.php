<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return [
            'category_id' => Category::firstOrCreate(['name' => 'Notebook'])->id,
            'model' => 'Latitude 5440',
            'serial_number' => ' abc123xyz ',
            'status' => 'disponivel',
            ...$overrides,
        ];
    }

    public function test_read_only_user_can_list_and_view_but_not_create(): void
    {
        $asset = Asset::factory()->create();
        $user = User::factory()->leitura()->create();

        $this->actingAs($user)->get(route('assets.index'))->assertOk()->assertSee($asset->code);
        $this->actingAs($user)->get(route('assets.show', $asset))->assertOk()->assertSee($asset->serial_number);
        $this->actingAs($user)->get(route('assets.create'))->assertForbidden();
        $this->actingAs($user)->post(route('assets.store'), $this->validData())->assertForbidden();
    }

    public function test_create_page_is_not_confused_with_details_page(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->get('/ativos/novo')
            ->assertOk()
            ->assertSee('Novo ativo');
    }

    public function test_tecnico_creates_asset_with_generated_code(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('assets.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $asset = Asset::firstOrFail();

        $this->assertMatchesRegularExpression('/^ATV-\d{6}$/', $asset->code);
        $this->assertSame('ABC123XYZ', $asset->serial_number);
        $this->assertSame(AssetStatus::Disponivel, $asset->status);
    }

    public function test_model_and_description_are_optional_and_description_is_searchable(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        $this->actingAs($tecnico)
            ->post(route('assets.store'), $this->validData([
                'model' => '',
                'serial_number' => 'SEMMODELO1',
                'description' => 'Monitor 24 polegadas Full HD',
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($tecnico)
            ->post(route('assets.store'), $this->validData(['serial_number' => 'SEMDESCR1', 'description' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Asset::count());
        $this->assertNull(Asset::where('serial_number', 'SEMMODELO1')->first()->model);

        $this->actingAs($tecnico)->get(route('assets.index', ['q' => '24 polegadas']))
            ->assertSee('SEMMODELO1')
            ->assertDontSee('SEMDESCR1');
    }

    public function test_asset_tag_is_optional_but_unique_and_shown_first(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        $this->actingAs($tecnico)->post(route('assets.store'), $this->validData(['serial_number' => 'S1']));
        $this->actingAs($tecnico)->post(route('assets.store'), $this->validData(['serial_number' => 'S2']));
        $this->assertSame(2, Asset::count());

        $this->actingAs($tecnico)->post(route('assets.store'), $this->validData(['serial_number' => 'S3', 'asset_tag' => 'pat-001']));
        $this->assertSame('Pat. PAT-001', Asset::where('serial_number', 'S3')->first()->displayIdentifier());

        $this->actingAs($tecnico)
            ->post(route('assets.store'), $this->validData(['serial_number' => 'S4', 'asset_tag' => 'PAT-001']))
            ->assertSessionHasErrors('asset_tag');
    }

    public function test_form_cannot_set_in_use_or_disposed_status(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        foreach (['em_uso', 'descartado'] as $status) {
            $this->actingAs($tecnico)
                ->post(route('assets.store'), $this->validData(['status' => $status]))
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(0, Asset::count());
    }

    public function test_only_admin_can_dispose_and_reason_is_required(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->tecnico()->create())
            ->patch(route('assets.dispose', $asset), ['disposal_reason' => 'Quebrado'])
            ->assertForbidden();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('assets.dispose', $asset), ['disposal_reason' => ''])
            ->assertSessionHasErrors('disposal_reason');

        $this->actingAs($admin)
            ->patch(route('assets.dispose', $asset), ['disposal_reason' => 'Placa-mãe queimada'])
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame(AssetStatus::Descartado, $asset->status);
        $this->assertTrue($asset->disposed_at->isToday());
        $this->assertSame('Placa-mãe queimada', $asset->disposal_reason);
    }

    public function test_asset_in_use_cannot_be_disposed(): void
    {
        $asset = Asset::factory()->create(['status' => AssetStatus::EmUso]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('assets.dispose', $asset), ['disposal_reason' => 'Teste'])
            ->assertSessionHas('error');

        $this->assertSame(AssetStatus::EmUso, $asset->fresh()->status);
    }

    public function test_disposed_assets_are_hidden_by_default(): void
    {
        Asset::factory()->create(['model' => 'Modelo Visivel']);
        Asset::factory()->create(['model' => 'Modelo Descartado', 'status' => AssetStatus::Descartado]);
        $user = User::factory()->leitura()->create();

        $this->actingAs($user)->get(route('assets.index'))
            ->assertSee('Modelo Visivel')
            ->assertDontSee('Modelo Descartado');

        $this->actingAs($user)->get(route('assets.index', ['status' => 'descartado']))
            ->assertSee('Modelo Descartado');
    }
}