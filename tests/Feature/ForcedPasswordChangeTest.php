<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_pending_change_is_sent_to_profile(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_changing_password_releases_the_system(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Nova-Senha-2026!',
                'password_confirmation' => 'Nova-Senha-2026!',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_weak_password_is_rejected_on_profile(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_user_created_by_admin_must_change_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Novo Colaborador',
            'email' => 'novo@exemplo.com.br',
            'role' => 'leitura',
            'password' => 'Senha-Inicial-2026!',
            'password_confirmation' => 'Senha-Inicial-2026!',
        ]);

        $this->assertTrue(User::where('email', 'novo@exemplo.com.br')->first()->must_change_password);
    }

    public function test_password_reset_by_admin_requires_new_change(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('users.reset-password', $user), [
            'password' => 'Senha-Temporaria-2026!',
            'password_confirmation' => 'Senha-Temporaria-2026!',
        ]);

        $this->assertTrue($user->fresh()->must_change_password);
    }
}