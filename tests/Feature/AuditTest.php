<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Employee;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_asset_is_recorded_with_the_user(): void
    {
        $tecnico = User::factory()->tecnico()->create();

        $this->actingAs($tecnico)->post(route('assets.store'), [
            'category_id' => Category::firstOrCreate(['name' => 'Notebook'])->id,
            'model' => 'Latitude 5440',
            'status' => 'disponivel',
        ]);

        $asset = Asset::firstOrFail();
        $log = AuditLog::where('subject_type', Asset::class)->where('subject_id', $asset->id)->firstOrFail();

        $this->assertSame(AuditAction::Created, $log->action);
        $this->assertSame($tecnico->id, $log->user_id);
        $this->assertStringContainsString($asset->code, $log->description);
    }

    public function test_updates_record_old_and_new_values_only_for_changed_fields(): void
    {
        $asset = Asset::factory()->create(['model' => 'Modelo Antigo']);

        $asset->update(['model' => 'Modelo Novo']);

        $log = AuditLog::where('action', AuditAction::Updated->value)->firstOrFail();

        $this->assertSame(['model' => ['de' => 'Modelo Antigo', 'para' => 'Modelo Novo']], $log->changes);
    }

    public function test_saving_without_changes_creates_no_record(): void
    {
        $asset = Asset::factory()->create();
        $before = AuditLog::count();

        $asset->update(['model' => $asset->model]);

        $this->assertSame($before, AuditLog::count());
    }

    public function test_license_key_is_never_stored_in_the_audit(): void
    {
        $license = License::factory()->create();
        $license->license_key = 'CHAVE-SUPER-SECRETA';
        $license->save();

        $this->assertSame(0, AuditLog::where('changes', 'like', '%CHAVE-SUPER-SECRETA%')->count());

        $log = AuditLog::where('action', AuditAction::Updated->value)->firstOrFail();
        $this->assertSame(['de' => '[oculta]', 'para' => '[alterada]'], $log->changes['license_key']);
    }

    public function test_checkout_creates_one_readable_record_and_no_duplicate(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $asset = Asset::factory()->create();
        $employee = Employee::factory()->create(['name' => 'Maria Auditada']);
        $updatesBefore = AuditLog::where('action', AuditAction::Updated->value)->count();

        $this->actingAs($tecnico)->post(route('assets.checkout.store', $asset), [
            'employee_id' => $employee->id,
            'checked_out_at' => today()->toDateString(),
        ]);

        $log = AuditLog::where('action', AuditAction::Checkout->value)->firstOrFail();

        $this->assertStringContainsString('Maria Auditada', $log->description);
        $this->assertSame($updatesBefore, AuditLog::where('action', AuditAction::Updated->value)->count());
    }

    public function test_revealing_a_key_is_recorded(): void
    {
        $tecnico = User::factory()->tecnico()->create();
        $license = License::factory()->create();

        $this->actingAs($tecnico)->post(route('licenses.reveal-key', $license));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::KeyRevealed->value,
            'user_id' => $tecnico->id,
            'subject_id' => $license->id,
        ]);
    }

    public function test_successful_and_failed_logins_are_recorded(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'senha-errada']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::LoginFailed->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::Login->value, 'user_id' => $user->id]);
        $this->assertSame(0, AuditLog::where('description', 'like', '%senha-errada%')->count());
    }

    public function test_passwords_never_appear_in_the_audit(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Novo Usuario',
            'email' => 'novo@exemplo.com.br',
            'role' => 'leitura',
            'password' => 'Senha-Muito-Secreta-1!',
            'password_confirmation' => 'Senha-Muito-Secreta-1!',
        ]);

        // Nenhum registro pode ter o campo "password" (o indicador must_change_password é permitido)
        $withPasswordField = AuditLog::all()->filter(
            fn (AuditLog $log) => array_key_exists('password', $log->changes ?? [])
        );
        $this->assertCount(0, $withPasswordField);

        // Nem a senha digitada, nem o hash dela (que no Laravel começa com $2y$)
        $this->assertSame(0, AuditLog::where('changes', 'like', '%Senha-Muito-Secreta%')->count());
        $this->assertSame(0, AuditLog::where('changes', 'like', '%$2y$%')->count());

        // O cadastro foi registrado normalmente, com os demais campos
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'criou',
            'description' => 'Cadastrou usuário Novo Usuario',
        ]);
    }

    public function test_audit_records_cannot_be_changed(): void
    {
        User::factory()->create();
        $log = AuditLog::firstOrFail();

        $this->expectException(LogicException::class);

        $log->forceFill(['description' => 'Adulterado'])->save();
    }

    public function test_audit_records_cannot_be_deleted(): void
    {
        User::factory()->create();
        $log = AuditLog::firstOrFail();

        $this->expectException(LogicException::class);

        $log->delete();
    }
}