<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_has_link_back_to_login(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Voltar para o login')
            ->assertSee(route('login'), false);
    }

    public function test_reset_password_page_has_link_back_to_login(): void
    {
        $this->get('/reset-password/token-qualquer')
            ->assertOk()
            ->assertSee('Voltar para o login');
    }

    public function test_profile_shows_visible_confirmation_after_password_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Nova-Senha-2026!',
                'password_confirmation' => 'Nova-Senha-2026!',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertSee('Senha alterada com sucesso');
    }
}