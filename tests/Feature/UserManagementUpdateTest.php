<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->leitura()->create();

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Nome Atualizado',
            'email' => 'NOVO@exemplo.com.br',
            'role' => 'tecnico',
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Nome Atualizado', $user->name);
        $this->assertSame('novo@exemplo.com.br', $user->email);
        $this->assertSame(UserRole::Tecnico, $user->role);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'leitura',
        ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_email_must_remain_unique_when_editing(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'ocupado@exemplo.com.br']);
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => 'ocupado@exemplo.com.br',
            'role' => 'leitura',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_can_deactivate_and_reactivate_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('users.toggle-active', $user));
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->patch(route('users.toggle-active', $user));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('users.toggle-active', $admin))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_password_reset_changes_password_and_ends_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'sessao-de-teste',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'teste',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)->put(route('users.reset-password', $user), [
            'password' => 'Nova-Senha-2026!',
            'password_confirmation' => 'Nova-Senha-2026!',
        ])->assertRedirect(route('users.edit', $user));

        $this->assertTrue(Hash::check('Nova-Senha-2026!', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-de-teste']);
    }

    public function test_non_admin_cannot_edit_users(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $user = User::factory()->create();

        $this->actingAs($tecnico)->get(route('users.edit', $user))->assertForbidden();
        $this->actingAs($tecnico)->patch(route('users.toggle-active', $user))->assertForbidden();
        $this->actingAs($tecnico)->put(route('users.reset-password', $user), [
            'password' => 'Nova-Senha-2026!',
            'password_confirmation' => 'Nova-Senha-2026!',
        ])->assertForbidden();
    }
}