<?php

namespace Tests\Feature;

use App\Mail\LicenseAlertMail;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LicenseAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_alert_goes_to_active_admins_and_technicians_only(): void
    {
        $admin = User::factory()->admin()->create();
        $tecnico = User::factory()->tecnico()->create();
        $leitura = User::factory()->leitura()->create();
        $inactive = User::factory()->admin()->create(['is_active' => false]);

        License::factory()->create(['name' => 'Entra No Alerta', 'expires_at' => today()->addDays(30), 'alert_days' => 30]);

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        Mail::assertSent(LicenseAlertMail::class, 2);
        Mail::assertSent(LicenseAlertMail::class, fn ($mail) => $mail->hasTo($admin->email)
            && $mail->expiring->pluck('name')->contains('Entra No Alerta'));
        Mail::assertSent(LicenseAlertMail::class, fn ($mail) => $mail->hasTo($tecnico->email));
        Mail::assertNotSent(LicenseAlertMail::class, fn ($mail) => $mail->hasTo($leitura->email));
        Mail::assertNotSent(LicenseAlertMail::class, fn ($mail) => $mail->hasTo($inactive->email));
    }

    public function test_no_email_on_days_without_reminder(): void
    {
        User::factory()->admin()->create();
        License::factory()->create(['expires_at' => today()->addDays(20), 'alert_days' => 30]);

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_key_dates_trigger_reminders(): void
    {
        User::factory()->admin()->create();

        foreach ([15, 7, 1, 0] as $days) {
            License::factory()->create(['name' => "Faltam {$days}", 'expires_at' => today()->addDays($days), 'alert_days' => 30]);
        }

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        Mail::assertSent(LicenseAlertMail::class, fn ($mail) => $mail->expiring->count() === 4);
    }

    public function test_expired_licenses_are_reminded_weekly(): void
    {
        User::factory()->admin()->create();
        License::factory()->create(['name' => 'Venceu Ha Uma Semana', 'expires_at' => today()->subDays(7)]);
        License::factory()->create(['name' => 'Venceu Ha Tres Dias', 'expires_at' => today()->subDays(3)]);

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        Mail::assertSent(LicenseAlertMail::class, fn ($mail) => $mail->expired->pluck('name')->all() === ['Venceu Ha Uma Semana']);
    }

    public function test_retired_licenses_never_alert(): void
    {
        User::factory()->admin()->create();
        $license = License::factory()->create(['expires_at' => today()->addDays(7), 'alert_days' => 30]);
        $license->retired_at = today();
        $license->saveQuietly();

        $this->artisan('intranet:alertas-licencas')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_force_option_sends_everything_in_alert(): void
    {
        User::factory()->admin()->create();
        License::factory()->create(['name' => 'Fora Da Data Chave', 'expires_at' => today()->addDays(20), 'alert_days' => 30]);
        License::factory()->create(['name' => 'Longe Do Vencimento', 'expires_at' => today()->addDays(200), 'alert_days' => 30]);

        $this->artisan('intranet:alertas-licencas', ['--forcar' => true])->assertSuccessful();

        Mail::assertSent(LicenseAlertMail::class, fn ($mail) => $mail->expiring->pluck('name')->all() === ['Fora Da Data Chave']);
    }
}