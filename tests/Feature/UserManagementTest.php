<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private array $validData = [
        'name' => 'Maria Técnica',
        'email' => 'Maria.Tecnica@exemplo.com.br',
        'role' => 'tecnico',
        'password' => 'Senha-Forte-2026!',
        'password_confirmation' => 'Senha-Forte-2026!',
    ];

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_see_user_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee($admin->email);
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        foreach ([User::factory()->tecnico()->create(), User::factory()->leitura()->create()] as $user) {
            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
            $this->actingAs($user)->post(route('users.store'), $this->validData)->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'maria.tecnica@exemplo.com.br']);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), $this->validData)
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'maria.tecnica@exemplo.com.br')->firstOrFail();

        $this->assertSame(UserRole::Tecnico, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Senha-Forte-2026!', $user->password));
    }

    public function test_weak_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            ...$this->validData,
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 1);
    }
}