<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_the_audit_screen(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('audit.index'))->assertOk();
        $this->actingAs(User::factory()->tecnico()->create())->get(route('audit.index'))->assertForbidden();
        $this->actingAs(User::factory()->leitura()->create())->get(route('audit.index'))->assertForbidden();
    }

    public function test_filter_by_action(): void
    {
        $admin = User::factory()->admin()->create();
        Asset::factory()->create(['model' => 'Modelo Cadastrado']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'senha-errada']);

        $this->actingAs($admin)
            ->get(route('audit.index', ['action' => AuditAction::LoginFailed->value]))
            ->assertSee('Tentativa de login falha')
            ->assertDontSee('Cadastrou ativo');
    }

    public function test_filter_by_item_shows_only_that_item(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Asset::factory()->create(['asset_tag' => 'PAT-PRIMEIRO']);
        Asset::factory()->create(['asset_tag' => 'PAT-SEGUNDO']);

        $this->actingAs($admin)
            ->get(route('audit.index', ['tipo' => Asset::class, 'item' => $first->id]))
            ->assertSee('PAT-PRIMEIRO')
            ->assertDontSee('PAT-SEGUNDO');
    }

    public function test_unknown_item_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('audit.index', ['tipo' => 'App\\Models\\Inexistente', 'item' => 1]))
            ->assertSessionHasErrors('tipo');
    }

    public function test_changes_are_shown_with_portuguese_labels(): void
    {
        $asset = Asset::factory()->create(['model' => 'Modelo Antigo']);
        $asset->update(['model' => 'Modelo Novo', 'status' => 'manutencao']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('audit.index'))
            ->assertSee('Modelo:')
            ->assertSee('Modelo Novo')
            ->assertSee('Situação:')
            ->assertSee('Em manutenção');
    }

    public function test_item_history_is_visible_only_to_admin(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('assets.show', $asset))
            ->assertSee('Histórico de alterações');

        $this->actingAs(User::factory()->tecnico()->create())
            ->get(route('assets.show', $asset))
            ->assertDontSee('Histórico de alterações');
    }
}