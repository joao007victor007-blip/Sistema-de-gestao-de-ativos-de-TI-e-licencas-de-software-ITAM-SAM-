<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Mail\LicenseAlertMail;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LicenseRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function autoRenewing(array $attributes = []): License
    {
        return License::factory()->create([
            'auto_renew' => true,
            'billing_cycle' => BillingCycle::Mensal,
            'alert_days' => 10,
            ...$attributes,
        ]);
    }

    public function test_auto_renewing_license_never_becomes_expired(): void
    {
        $license = $this->autoRenewing(['expires_at' => today()->subDay()]);

        $this->assertNotSame('vencida', $license->expirationState());
        $this->assertSame(0, License::expired()->count());
        $this->assertNull($license->assignmentBlockReason());
    }

    public function test_command_rolls_dates_forward_and_records_audit(): void
    {
        $monthly = $this->autoRenewing(['expires_at' => today()->subDay()]);
        $yearly = $this->autoRenewing(['expires_at' => today()->subDays(3), 'billing_cycle' => BillingCycle::Anual]);
        $manual = License::factory()->create(['expires_at' => today()->subDay()]);

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        $this->assertTrue($monthly->fresh()->expires_at->equalTo(today()->subDay()->addMonthNoOverflow()));
        $this->assertTrue($yearly->fresh()->expires_at->equalTo(today()->subDays(3)->addYearNoOverflow()));
        $this->assertTrue($manual->fresh()->expires_at->equalTo(today()->subDay()));
        $this->assertSame(2, AuditLog::where('action', 'renovacao')->count());
    }

    public function test_auto_renewal_warns_once_per_cycle(): void
    {
        User::factory()->admin()->create();
        $this->autoRenewing(['name' => 'Renova Em Dez Dias', 'expires_at' => today()->addDays(10)]);

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        Mail::assertSent(LicenseAlertMail::class, fn ($mail) => $mail->renewing->pluck('name')->all() === ['Renova Em Dez Dias']
            && $mail->expiring->isEmpty());
    }

    public function test_auto_renewal_does_not_repeat_warnings_in_the_same_cycle(): void
    {
        User::factory()->admin()->create();
        $this->autoRenewing(['expires_at' => today()->addDays(7)]);

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_auto_renew_requires_monthly_or_yearly_cycle(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $data = [
            'software_id' => Software::firstOrCreate(['name' => 'Microsoft 365'])->id,
            'name' => 'Assinatura Teste',
            'license_type' => 'assinatura',
            'assignment_type' => 'per_user',
            'seats' => 5,
            'expires_at' => today()->addMonth()->toDateString(),
            'alert_days' => 10,
            'auto_renew' => '1',
        ];

        $this->actingAs($tecnico)->post(route('licenses.store'), [...$data, 'billing_cycle' => ''])
            ->assertSessionHasErrors('billing_cycle');

        $this->actingAs($tecnico)->post(route('licenses.store'), [...$data, 'billing_cycle' => 'unica'])
            ->assertSessionHasErrors('billing_cycle');

        // Perpétua com a caixa marcada: a renovação automática é ignorada
        $this->actingAs($tecnico)->post(route('licenses.store'), [
            ...$data, 'name' => 'Perpetua Teste', 'license_type' => 'perpetua', 'expires_at' => '', 'billing_cycle' => '',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(License::where('name', 'Perpetua Teste')->first()->auto_renew);
    }

    public function test_renewals_and_expirations_are_listed_separately(): void
    {
        $user = User::factory()->leitura()->create();
        $this->autoRenewing(['name' => 'Automatica Proxima', 'expires_at' => today()->addDays(5)]);
        License::factory()->create(['name' => 'Manual Proxima', 'expires_at' => today()->addDays(5), 'alert_days' => 10]);

        $this->actingAs($user)->get(route('licenses.index', ['situacao' => 'renovando']))
            ->assertSee('Automatica Proxima')
            ->assertDontSee('Manual Proxima');

        $this->actingAs($user)->get(route('licenses.index', ['situacao' => 'vencendo']))
            ->assertSee('Manual Proxima')
            ->assertDontSee('Automatica Proxima');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Renovações automáticas nos próximos 30 dias')
            ->assertSee('Automatica Proxima');
    }
}