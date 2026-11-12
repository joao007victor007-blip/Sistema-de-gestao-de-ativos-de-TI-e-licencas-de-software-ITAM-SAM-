<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use App\Models\Software;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

        public static function catalogs(): array
    {
        return [
            'setores' => ['departments', Department::class],
            'categorias' => ['categories', Category::class],
            'fabricantes' => ['manufacturers', Manufacturer::class],
            'softwares' => ['software', Software::class],
        ];
    }

    #[DataProvider('catalogs')]
    public function test_read_only_user_can_list_but_not_change(string $route, string $model): void
    {
        $item = $model::create(['name' => 'Item Existente']);
        $user = User::factory()->leitura()->create();

        $this->actingAs($user)->get(route("{$route}.index"))->assertOk()->assertSee('Item Existente');
        $this->actingAs($user)->get(route("{$route}.create"))->assertForbidden();
        $this->actingAs($user)->post(route("{$route}.store"), ['name' => 'Novo'])->assertForbidden();
        $this->actingAs($user)->patch(route("{$route}.toggle-active", $item))->assertForbidden();
    }

    #[DataProvider('catalogs')]
    public function test_tecnico_can_create_and_names_are_unique(string $route, string $model): void
    {
        $tecnico = User::factory()->tecnico()->create();

        $this->actingAs($tecnico)
            ->post(route("{$route}.store"), ['name' => '  Nome Teste  ', 'notes' => 'Observação'])
            ->assertRedirect(route("{$route}.index"));

        $this->assertSame(1, $model::where('name', 'Nome Teste')->count());

        $this->actingAs($tecnico)
            ->post(route("{$route}.store"), ['name' => 'Nome Teste'])
            ->assertSessionHasErrors('name');
    }

    #[DataProvider('catalogs')]
    public function test_tecnico_can_edit_and_deactivate(string $route, string $model): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $item = $model::create(['name' => 'Nome Antigo']);

        $this->actingAs($tecnico)
            ->put(route("{$route}.update", $item), ['name' => 'Nome Novo'])
            ->assertRedirect(route("{$route}.index"));

        $this->assertSame('Nome Novo', $item->fresh()->name);

        $this->actingAs($tecnico)->patch(route("{$route}.toggle-active", $item));

        $this->assertFalse($item->fresh()->is_active);
    }
}