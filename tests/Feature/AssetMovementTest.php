<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetMovementTest extends TestCase
{
    use RefreshDatabase;

    private function checkOut(User $user, Asset $asset, Employee $employee, array $overrides = [])
    {
        return $this->actingAs($user)->post(route('assets.checkout.store', $asset), [
            'employee_id' => $employee->id,
            'checked_out_at' => today()->toDateString(),
            ...$overrides,
        ]);
    }

    public function test_tecnico_can_check_out_available_asset(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $location = Location::factory()->create();
        $employee = Employee::factory()->create(['location_id' => $location->id]);
        $asset = Asset::factory()->create();

        $this->checkOut($tecnico, $asset, $employee)->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $assignment = $asset->currentAssignment;

        $this->assertSame(AssetStatus::EmUso, $asset->status);
        $this->assertSame($location->id, $asset->location_id);
        $this->assertSame($employee->id, $assignment->employee_id);
        $this->assertSame($tecnico->id, $assignment->checked_out_by);
        $this->assertFalse($assignment->term_signed);
    }

    public function test_unavailable_asset_cannot_be_checked_out(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $employee = Employee::factory()->create();

        foreach ([AssetStatus::EmUso, AssetStatus::Manutencao, AssetStatus::Descartado] as $status) {
            $asset = Asset::factory()->create(['status' => $status]);

            $this->checkOut($tecnico, $asset, $employee)->assertSessionHas('error');

            $this->assertSame(0, $asset->assignments()->count());
        }
    }

    public function test_cannot_check_out_to_terminated_employee(): void
    {
        $asset = Asset::factory()->create();
        $employee = Employee::factory()->create(['is_active' => false]);

        $this->checkOut(User::factory()->tecnico()->create(), $asset, $employee)->assertSessionHas('error');

        $this->assertSame(AssetStatus::Disponivel, $asset->fresh()->status);
    }

    public function test_read_only_user_cannot_move_assets(): void
    {
        $user = User::factory()->leitura()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($user)->get(route('assets.checkout', $asset))->assertForbidden();
        $this->checkOut($user, $asset, Employee::factory()->create())->assertForbidden();
    }

    public function test_check_in_closes_assignment_and_sets_status(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $asset = Asset::factory()->create();
        $this->checkOut($tecnico, $asset, Employee::factory()->create());

        $this->actingAs($tecnico)
            ->post(route('assets.checkin.store', $asset), [
                'checked_in_at' => today()->toDateString(),
                'status' => 'manutencao',
                'checkin_condition' => 'Tela trincada',
            ])
            ->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $assignment = $asset->assignments()->first();

        $this->assertSame(AssetStatus::Manutencao, $asset->status);
        $this->assertNull($asset->currentAssignment);
        $this->assertTrue($assignment->checked_in_at->isToday());
        $this->assertSame($tecnico->id, $assignment->checked_in_by);
        $this->assertSame('Tela trincada', $assignment->checkin_condition);
    }

    public function test_check_in_date_cannot_be_before_check_out(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $asset = Asset::factory()->create();
        $this->checkOut($tecnico, $asset, Employee::factory()->create(), [
            'checked_out_at' => today()->subDays(5)->toDateString(),
        ]);

        $this->actingAs($tecnico)
            ->post(route('assets.checkin.store', $asset), [
                'checked_in_at' => today()->subDays(10)->toDateString(),
                'status' => 'disponivel',
            ])
            ->assertSessionHas('error');

        $this->assertSame(AssetStatus::EmUso, $asset->fresh()->status);
    }

    public function test_term_can_be_signed_later_and_history_is_shown(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $asset = Asset::factory()->create();
        $employee = Employee::factory()->create(['name' => 'Joana Responsavel']);
        $this->checkOut($tecnico, $asset, $employee);

        $assignment = $asset->assignments()->first();

        $this->actingAs($tecnico)
            ->patch(route('assignments.sign-term', $assignment), ['term_signed_at' => today()->toDateString()])
            ->assertRedirect(route('assets.show', $asset));

        $this->assertTrue($assignment->fresh()->term_signed);

        $this->actingAs(User::factory()->leitura()->create())
            ->get(route('assets.show', $asset))
            ->assertSee('Joana Responsavel')
            ->assertSee('Assinado em');
    }
}