<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');

        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_every_route_requires_login_except_the_public_ones(): void
    {
        // Únicos endereços que podem ser abertos sem login
        $public = [
            '/',
            'up',
            'login',
            'forgot-password',
            'reset-password',
            'reset-password/{token}',
            'storage/{path}',
        ];

        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route) => in_array($route->uri(), $public, true))
            ->reject(fn ($route) => in_array('auth', $route->gatherMiddleware(), true))
            ->map(fn ($route) => implode('|', $route->methods()) . ' ' . $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected, 'Rotas sem exigência de login: ' . implode(', ', $unprotected));
    }

    public function test_revealing_keys_is_rate_limited(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = License::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($tecnico)->post(route('licenses.reveal-key', $license))->assertOk();
        }

        $this->actingAs($tecnico)->post(route('licenses.reveal-key', $license))->assertStatus(429);
    }

    public function test_forgot_password_does_not_reveal_registered_emails(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'ninguem@exemplo.com.br'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    public function test_error_pages_are_in_portuguese(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pagina-que-nao-existe')
            ->assertNotFound()
            ->assertSee('Página não encontrada');
    }
}