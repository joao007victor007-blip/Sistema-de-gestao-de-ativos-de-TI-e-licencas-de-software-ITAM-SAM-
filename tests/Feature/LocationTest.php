<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    private array $data = [
        'name' => 'Obra Residencial Jardins',
        'type' => 'obra',
        'city' => 'São Paulo',
        'state' => 'pe',
        'notes' => null,
    ];

    public function test_any_logged_user_can_see_locations(): void
    {
        $location = Location::factory()->create();

        $this->actingAs(User::factory()->leitura()->create())
            ->get(route('locations.index'))
            ->assertOk()
            ->assertSee($location->name);
    }

    public function test_read_only_user_cannot_create_locations(): void
    {
        $user = User::factory()->leitura()->create();

        $this->actingAs($user)->get(route('locations.create'))->assertForbidden();
        $this->actingAs($user)->post(route('locations.store'), $this->data)->assertForbidden();

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_tecnico_can_create_location(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('locations.store'), $this->data)
            ->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('locations', [
            'name' => 'Obra Residencial Jardins',
            'type' => 'obra',
            'state' => 'PE',
        ]);
    }

    public function test_location_name_must_be_unique(): void
    {
        Location::factory()->create(['name' => 'Obra Residencial Jardins']);

        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('locations.store'), $this->data)
            ->assertSessionHasErrors('name');
    }

    public function test_tecnico_can_edit_and_deactivate_location(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $location = Location::factory()->create();

        $this->actingAs($tecnico)
            ->put(route('locations.update', $location), [...$this->data, 'name' => 'Escritório Central'])
            ->assertRedirect(route('locations.index'));

        $this->assertSame('Escritório Central', $location->fresh()->name);

        $this->actingAs($tecnico)->patch(route('locations.toggle-active', $location));

        $this->assertFalse($location->fresh()->is_active);
    }
}