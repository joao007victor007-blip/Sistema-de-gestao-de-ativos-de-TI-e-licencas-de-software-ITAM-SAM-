<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_user_can_list_but_not_create(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->leitura()->create();

        $this->actingAs($user)->get(route('employees.index'))->assertOk()->assertSee($employee->name);
        $this->actingAs($user)->get(route('employees.create'))->assertForbidden();
        $this->actingAs($user)->post(route('employees.store'), ['name' => 'Fulano'])->assertForbidden();
    }

    public function test_tecnico_can_create_employee_with_department_and_location(): void
    {
        $department = Department::create(['name' => 'Engenharia']);
        $location = Location::factory()->create();

        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('employees.store'), [
                'name' => 'Maria da Silva',
                'registration' => ' 1234 ',
                'email' => 'maria.silva@exemplo.com.br',
                'job_title' => 'Engenheira Civil',
                'department_id' => $department->id,
                'location_id' => $location->id,
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'name' => 'Maria da Silva',
            'registration' => '1234',
            'email' => 'maria.silva@exemplo.com.br',
            'department_id' => $department->id,
            'location_id' => $location->id,
        ]);
    }

    public function test_registration_must_be_unique(): void
    {
        Employee::factory()->create(['registration' => '5555']);

        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('employees.store'), ['name' => 'Outro', 'registration' => '5555'])
            ->assertSessionHasErrors('registration');
    }

    public function test_nonexistent_department_is_rejected(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('employees.store'), ['name' => 'Fulano', 'department_id' => 999])
            ->assertSessionHasErrors('department_id');
    }

    public function test_search_and_status_filters(): void
    {
        Employee::factory()->create(['name' => 'Carlos Pereira']);
        Employee::factory()->create(['name' => 'Ana Souza']);
        $desligado = Employee::factory()->create(['name' => 'Bruno Lima', 'is_active' => false]);
        $user = User::factory()->leitura()->create();

        $this->actingAs($user)->get(route('employees.index', ['q' => 'Carlos']))
            ->assertSee('Carlos Pereira')
            ->assertDontSee('Ana Souza');

        $this->actingAs($user)->get(route('employees.index'))
            ->assertDontSee($desligado->name);

        $this->actingAs($user)->get(route('employees.index', ['status' => 'desligados']))
            ->assertSee($desligado->name)
            ->assertDontSee('Carlos Pereira');
    }

    public function test_termination_records_date_and_reactivation_clears_it(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($tecnico)->patch(route('employees.toggle-active', $employee));
        $employee->refresh();

        $this->assertFalse($employee->is_active);
        $this->assertTrue($employee->terminated_at->isToday());

        $this->actingAs($tecnico)->patch(route('employees.toggle-active', $employee));
        $employee->refresh();

        $this->assertTrue($employee->is_active);
        $this->assertNull($employee->terminated_at);
    }
}