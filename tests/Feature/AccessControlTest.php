<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_users(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('manage-users'));
        $this->assertFalse(Gate::forUser(User::factory()->tecnico()->create())->allows('manage-users'));
        $this->assertFalse(Gate::forUser(User::factory()->leitura()->create())->allows('manage-users'));
    }

    public function test_tecnico_operates_but_cannot_dispose_or_audit(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        $this->assertTrue(Gate::forUser($tecnico)->allows('manage-inventory'));
        $this->assertTrue(Gate::forUser($tecnico)->allows('move-assets'));
        $this->assertTrue(Gate::forUser($tecnico)->allows('reveal-license-keys'));
        $this->assertFalse(Gate::forUser($tecnico)->allows('dispose-assets'));
        $this->assertFalse(Gate::forUser($tecnico)->allows('view-audit'));
    }

    public function test_read_only_user_cannot_change_anything(): void
    {
        $leitura = User::factory()->leitura()->create();

        $this->assertFalse(Gate::forUser($leitura)->allows('manage-inventory'));
        $this->assertFalse(Gate::forUser($leitura)->allows('move-assets'));
        $this->assertFalse(Gate::forUser($leitura)->allows('reveal-license-keys'));
        $this->assertFalse(Gate::forUser($leitura)->allows('dispose-assets'));
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}