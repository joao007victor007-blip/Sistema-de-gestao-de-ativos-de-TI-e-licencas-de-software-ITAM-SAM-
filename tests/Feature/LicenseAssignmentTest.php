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

class LicenseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function assign(User $user, License $license, array $data)
    {
        return $this->actingAs($user)->post(route('licenses.assign.store', $license), [
            'assigned_at' => today()->toDateString(),
            ...$data,
        ]);
    }

    private function perDeviceLicense(array $attributes = []): License
    {
        return License::factory()->create([
            'license_type' => LicenseType::Oem,
            'assignment_type' => LicenseAssignmentType::PerDevice,
            'expires_at' => null,
            ...$attributes,
        ]);
    }

    public function test_per_user_license_is_assigned_to_employee(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = License::factory()->create(['seats' => 2]);

        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id])
            ->assertRedirect(route('licenses.show', $license));

        $this->assertSame(1, $license->activeAssignments()->count());
        $this->assertSame($tecnico->id, $license->assignments()->first()->assigned_by);
    }

    public function test_seat_limit_is_enforced(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = License::factory()->create(['seats' => 1]);

        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id]);
        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id])
            ->assertSessionHas('error');

        $this->assertSame(1, $license->activeAssignments()->count());
    }

    public function test_same_employee_cannot_take_two_seats(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = License::factory()->create(['seats' => 5]);
        $employee = Employee::factory()->create();

        $this->assign($tecnico, $license, ['employee_id' => $employee->id]);
        $this->assign($tecnico, $license, ['employee_id' => $employee->id])->assertSessionHas('error');

        $this->assertSame(1, $license->activeAssignments()->count());
    }

    public function test_corporate_expired_and_retired_licenses_do_not_accept_assignments(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $employee = Employee::factory()->create();

        $corporate = License::factory()->create(['assignment_type' => LicenseAssignmentType::Corporate, 'seats' => null]);
        $expired = License::factory()->create(['expires_at' => today()->subDay()]);
        $retired = License::factory()->create();
        $retired->retired_at = today();
        $retired->save();

        foreach ([$corporate, $expired, $retired] as $license) {
            $this->assign($tecnico, $license, ['employee_id' => $employee->id])->assertSessionHas('error');
            $this->assertSame(0, $license->assignments()->count());
        }
    }

    public function test_per_device_license_requires_an_asset(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = $this->perDeviceLicense();
        $asset = Asset::factory()->create();

        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id])
            ->assertSessionHas('error');

        $this->assign($tecnico, $license, ['asset_id' => $asset->id])->assertSessionHasNoErrors();

        $this->assertSame($asset->id, $license->activeAssignments()->first()->asset_id);
    }

    public function test_cannot_assign_to_terminated_employee(): void
    {
        $license = License::factory()->create();
        $employee = Employee::factory()->create(['is_active' => false]);

        $this->assign(User::factory()->tecnico()->create(), $license, ['employee_id' => $employee->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $license->assignments()->count());
    }

    public function test_revoking_frees_a_seat(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = License::factory()->create(['seats' => 1]);

        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id]);
        $assignment = $license->assignments()->first();

        $this->actingAs($tecnico)
            ->patch(route('license-assignments.revoke', $assignment), ['revoked_at' => today()->toDateString()])
            ->assertRedirect(route('licenses.show', $license));

        $this->assertSame($tecnico->id, $assignment->fresh()->revoked_by);

        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $license->activeAssignments()->count());
        $this->assertSame(2, $license->assignments()->count());
    }

    public function test_read_only_user_cannot_assign_or_revoke(): void
    {
        $user = User::factory()->leitura()->create();
        $license = License::factory()->create();

        $this->actingAs($user)->get(route('licenses.assign', $license))->assertForbidden();
        $this->assign($user, $license, ['employee_id' => Employee::factory()->create()->id])->assertForbidden();
    }

    public function test_seats_cannot_be_reduced_below_usage_nor_type_changed(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = License::factory()->create(['seats' => 3]);

        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id]);
        $this->assign($tecnico, $license, ['employee_id' => Employee::factory()->create()->id]);

        $data = [
            'software_id' => $license->software_id,
            'name' => $license->name,
            'license_type' => 'assinatura',
            'assignment_type' => 'per_user',
            'seats' => 3,
            'expires_at' => now()->addYear()->toDateString(),
            'alert_days' => 30,
        ];

        $this->actingAs($tecnico)
            ->put(route('licenses.update', $license), [...$data, 'seats' => 1])
            ->assertSessionHasErrors('seats');

        $this->actingAs($tecnico)
            ->put(route('licenses.update', $license), [...$data, 'assignment_type' => 'per_device'])
            ->assertSessionHasErrors('assignment_type');

        $this->assertSame(3, $license->fresh()->seats);
    }

    public function test_asset_with_active_license_cannot_be_disposed(): void
    {
        $license = $this->perDeviceLicense();
        $asset = Asset::factory()->create();

        $this->assign(User::factory()->tecnico()->create(), $license, ['asset_id' => $asset->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('assets.dispose', $asset), ['disposal_reason' => 'Sem conserto'])
            ->assertSessionHas('error');

        $this->assertSame(AssetStatus::Disponivel, $asset->fresh()->status);
    }
}