<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return [
            'software_id' => Software::firstOrCreate(['name' => 'Microsoft 365'])->id,
            'name' => 'Microsoft 365 Business Standard',
            'license_type' => 'assinatura',
            'assignment_type' => 'per_user',
            'seats' => 10,
            'expires_at' => now()->addYear()->toDateString(),
            'alert_days' => 30,
            'license_key' => 'AAAAA-BBBBB-CCCCC-DDDDD',
            ...$overrides,
        ];
    }

    private function licenseWithKey(string $key, array $attributes = []): License
    {
        $license = License::factory()->create($attributes);
        $license->license_key = $key;
        $license->save();

        return $license;
    }

    public function test_read_only_user_can_view_but_not_create_or_reveal(): void
    {
        $license = $this->licenseWithKey('CHAVE-SECRETA-123');
        $user = User::factory()->leitura()->create();

        $this->actingAs($user)->get(route('licenses.index'))->assertOk()->assertSee($license->name);
        $this->actingAs($user)->get(route('licenses.show', $license))->assertOk()->assertDontSee('CHAVE-SECRETA-123');
        $this->actingAs($user)->get(route('licenses.create'))->assertForbidden();
        $this->actingAs($user)->post(route('licenses.reveal-key', $license))->assertForbidden();
    }

    public function test_key_is_encrypted_in_the_database(): void
    {
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('licenses.store'), $this->validData())
            ->assertSessionHasNoErrors();

        $rawValue = DB::table('licenses')->value('license_key');

        $this->assertNotEmpty($rawValue);
        $this->assertStringNotContainsString('AAAAA-BBBBB', $rawValue);
        $this->assertSame('AAAAA-BBBBB-CCCCC-DDDDD', License::first()->license_key);
    }

    public function test_tecnico_can_reveal_key_and_page_is_not_cached(): void
    {
        $license = $this->licenseWithKey('CHAVE-VISIVEL-999');

        $response = $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('licenses.reveal-key', $license));

        $response->assertOk()->assertSee('CHAVE-VISIVEL-999');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_key_is_never_shown_on_edit_and_blank_keeps_current(): void
    {
        $license = $this->licenseWithKey('CHAVE-ORIGINAL-1');
        $tecnico = User::factory()->tecnico()->create();

        $this->actingAs($tecnico)->get(route('licenses.edit', $license))
            ->assertOk()
            ->assertDontSee('CHAVE-ORIGINAL-1');

        $this->actingAs($tecnico)
            ->put(route('licenses.update', $license), $this->validData(['name' => 'Nome Novo', 'license_key' => '']))
            ->assertSessionHasNoErrors();

        $license->refresh();
        $this->assertSame('Nome Novo', $license->name);
        $this->assertSame('CHAVE-ORIGINAL-1', $license->license_key);
    }

    public function test_subscription_requires_expiration_but_perpetual_does_not(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        $this->actingAs($tecnico)
            ->post(route('licenses.store'), $this->validData(['expires_at' => '']))
            ->assertSessionHasErrors('expires_at');

        $this->actingAs($tecnico)
            ->post(route('licenses.store'), $this->validData(['license_type' => 'perpetua', 'expires_at' => '']))
            ->assertSessionHasNoErrors();
    }

        public function test_corporate_license_has_no_seats_but_others_require_them(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        // Corporativa: quantidade é descartada, mesmo se enviada
        $this->actingAs($tecnico)
            ->post(route('licenses.store'), $this->validData([
                'name' => 'Nextcloud Corporativo',
                'assignment_type' => 'corporate',
                'seats' => 50,
            ]))
            ->assertSessionHasNoErrors();

        $corporate = License::where('name', 'Nextcloud Corporativo')->firstOrFail();
        $this->assertNull($corporate->seats);
        $this->assertSame('Uso corporativo', $corporate->seatsLabel());

        // Por usuário: quantidade é obrigatória
        $this->actingAs($tecnico)
            ->post(route('licenses.store'), $this->validData(['assignment_type' => 'per_user', 'seats' => '']))
            ->assertSessionHasErrors('seats');
    }

    public function test_expiration_states(): void
    {
        $this->assertSame('vencendo', License::factory()->make(['expires_at' => today()->addDays(10), 'alert_days' => 30])->expirationState());
        $this->assertSame('vigente', License::factory()->make(['expires_at' => today()->addDays(60), 'alert_days' => 30])->expirationState());
        $this->assertSame('vencida', License::factory()->make(['expires_at' => today()->subDay()])->expirationState());
        $this->assertSame('sem_vencimento', License::factory()->make(['expires_at' => null])->expirationState());
    }

    public function test_expiring_soon_filter(): void
    {
        License::factory()->create(['name' => 'Licenca Vence Logo', 'expires_at' => today()->addDays(10), 'alert_days' => 30]);
        License::factory()->create(['name' => 'Licenca Tranquila', 'expires_at' => today()->addDays(200), 'alert_days' => 30]);

        $this->actingAs(User::factory()->leitura()->create())
            ->get(route('licenses.index', ['situacao' => 'vencendo']))
            ->assertSee('Licenca Vence Logo')
            ->assertDontSee('Licenca Tranquila');
    }

        public function test_only_admin_can_retire_license(): void
    {
        $license = License::factory()->create(['name' => 'Licenca Para Baixa']);

        $this->actingAs(User::factory()->tecnico()->create())
            ->patch(route('licenses.retire', $license), ['retirement_reason' => 'Cancelada'])
            ->assertForbidden();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('licenses.retire', $license), ['retirement_reason' => 'Contrato não renovado'])
            ->assertRedirect(route('licenses.show', $license));

        $this->assertTrue($license->fresh()->retired_at->isToday());

        // Descarta a mensagem de sucesso da sessão, para verificar apenas a listagem
        $this->flushSession();

        $this->actingAs($admin)
            ->get(route('licenses.index'))
            ->assertDontSee('Licenca Para Baixa');

        $this->actingAs($admin)
            ->get(route('licenses.index', ['situacao' => 'baixadas']))
            ->assertSee('Licenca Para Baixa');
    }
}