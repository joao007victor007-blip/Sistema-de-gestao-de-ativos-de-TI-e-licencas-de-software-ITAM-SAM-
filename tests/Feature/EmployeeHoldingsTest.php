<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\LicenseAssignmentType;
use App\Enums\LicenseType;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHoldingsTest extends TestCase
{
    use RefreshDatabase;

    private User $tecnico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tecnico = User::factory()->tecnico()->create();
    }

    private function giveAsset(Employee $employee, ?Asset $asset = null): Asset
    {
        $asset ??= Asset::factory()->create();

        $this->actingAs($this->tecnico)->post(route('assets.checkout.store', $asset), [
            'employee_id' => $employee->id,
            'checked_out_at' => today()->toDateString(),
        ]);

        return $asset;
    }

    private function giveLicense(Employee $employee, ?License $license = null): License
    {
        $license ??= License::factory()->create(['seats' => 5]);

        $this->actingAs($this->tecnico)->post(route('licenses.assign.store', $license), [
            'employee_id' => $employee->id,
            'assigned_at' => today()->toDateString(),
        ]);

        return $license;
    }

    public function test_employee_page_shows_current_equipment_and_licenses(): void
    {
        $employee = Employee::factory()->create();
        $asset = $this->giveAsset($employee);
        $this->giveLicense($employee, License::factory()->create(['name' => 'Licenca Do Colaborador', 'seats' => 5]));

        $this->actingAs(User::factory()->leitura()->create())
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee($asset->fresh()->displayIdentifier())
            ->assertSee('Licenca Do Colaborador');
    }

    public function test_termination_with_pending_equipment_requires_acknowledgement(): void
    {
        $employee = Employee::factory()->create();
        $asset = $this->giveAsset($employee);

        $this->actingAs($this->tecnico)
            ->patch(route('employees.toggle-active', $employee))
            ->assertSessionHasErrors('acknowledge_pending');

        $this->assertTrue($employee->fresh()->is_active);

        $this->actingAs($this->tecnico)
            ->patch(route('employees.toggle-active', $employee), ['acknowledge_pending' => '1'])
            ->assertRedirect(route('employees.show', $employee));

        $this->assertFalse($employee->fresh()->is_active);

        // O equipamento continua em uso: a devolução física precisa ser registrada
        $this->assertSame(AssetStatus::EmUso, $asset->fresh()->status);
    }

        public function test_termination_always_releases_licenses(): void
    {
        $license = License::factory()->create(['seats' => 5]);
        $employee = Employee::factory()->create();
        $this->giveLicense($employee, $license);

        $this->actingAs($this->tecnico)->patch(route('employees.toggle-active', $employee));

        $this->assertSame(0, $employee->activeLicenseAssignments()->count());
        $this->assertSame(0, $license->activeAssignments()->count());
        $this->assertSame($this->tecnico->id, $employee->licenseAssignments()->first()->revoked_by);
    }

    public function test_pending_filter_lists_terminated_employees_with_equipment(): void
    {
        $pending = Employee::factory()->create(['name' => 'Desligado Com Notebook']);
        $this->giveAsset($pending);
        $this->actingAs($this->tecnico)->patch(route('employees.toggle-active', $pending), ['acknowledge_pending' => '1']);

        Employee::factory()->create(['name' => 'Desligado Sem Nada', 'is_active' => false]);

        $this->actingAs(User::factory()->leitura()->create())
            ->get(route('employees.index', ['status' => 'pendencias']))
            ->assertSee('Desligado Com Notebook')
            ->assertDontSee('Desligado Sem Nada');
    }

    public function test_asset_page_lists_installed_licenses(): void
    {
        $license = License::factory()->create([
            'name' => 'Windows Do Notebook',
            'license_type' => LicenseType::Oem,
            'assignment_type' => LicenseAssignmentType::PerDevice,
            'expires_at' => null,
        ]);
        $asset = Asset::factory()->create();

        $this->actingAs($this->tecnico)->post(route('licenses.assign.store', $license), [
            'asset_id' => $asset->id,
            'assigned_at' => today()->toDateString(),
        ]);

        $this->actingAs(User::factory()->leitura()->create())
            ->get(route('assets.show', $asset))
            ->assertSee('Windows Do Notebook');
    }
}