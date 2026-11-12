<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function checkOut(User $user, Asset $asset, Employee $employee, array $extra = []): void
    {
        $this->actingAs($user)->post(route('assets.checkout.store', $asset), [
            'employee_id' => $employee->id,
            'checked_out_at' => today()->toDateString(),
            ...$extra,
        ]);
    }

    public function test_every_role_can_open_the_dashboard(): void
    {
        foreach (['admin', 'tecnico', 'leitura'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Licenças que vencem em breve');
        }
    }

    public function test_dashboard_lists_what_needs_attention(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        License::factory()->create(['name' => 'Licenca Vence Logo', 'expires_at' => today()->addDays(5), 'alert_days' => 30]);

        $retired = License::factory()->create(['name' => 'Licenca Baixada Vencendo', 'expires_at' => today()->addDays(5)]);
        $retired->retired_at = today();
        $retired->saveQuietly();

        $this->checkOut($tecnico, Asset::factory()->create(), Employee::factory()->create(['name' => 'Fulano Sem Termo']));

        $full = License::factory()->create(['name' => 'Licenca Quase Cheia', 'seats' => 1]);
        $this->actingAs($tecnico)->post(route('licenses.assign.store', $full), [
            'employee_id' => Employee::factory()->create()->id,
            'assigned_at' => today()->toDateString(),
        ]);

        $this->actingAs(User::factory()->leitura()->create())
            ->get(route('dashboard'))
            ->assertSee('Licenca Vence Logo')
            ->assertDontSee('Licenca Baixada Vencendo')
            ->assertSee('Fulano Sem Termo')
            ->assertSee('Licenca Quase Cheia');
    }

    public function test_terminated_employee_with_equipment_appears(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $employee = Employee::factory()->create(['name' => 'Desligado Com Pendencia']);
        $this->checkOut($tecnico, Asset::factory()->create(), $employee);

        $this->actingAs($tecnico)->patch(route('employees.toggle-active', $employee), ['acknowledge_pending' => '1']);

        $this->actingAs($tecnico)
            ->get(route('dashboard'))
            ->assertSee('Desligado Com Pendencia');
    }

    public function test_pending_term_filter_on_assets_list(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        $this->checkOut($tecnico, Asset::factory()->create(['asset_tag' => 'PAT-SEM-TERMO']), Employee::factory()->create());
        $this->checkOut($tecnico, Asset::factory()->create(['asset_tag' => 'PAT-COM-TERMO']), Employee::factory()->create(), ['term_signed' => '1']);

        $this->actingAs($tecnico)
            ->get(route('assets.index', ['termo' => 'pendente']))
            ->assertSee('PAT-SEM-TERMO')
            ->assertDontSee('PAT-COM-TERMO');
    }
}