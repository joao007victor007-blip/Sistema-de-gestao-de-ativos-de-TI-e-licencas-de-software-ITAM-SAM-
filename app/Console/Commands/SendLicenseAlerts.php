<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Mail\LicenseAlertMail;
use App\Models\License;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLicenseAlerts extends Command
{
    protected $signature = 'intranet:alertas-licencas
                            {--forcar : Envia todas as licenças em alerta, ignorando o calendário de avisos}';

    protected $description = 'Avança as renovações automáticas e envia por e-mail os avisos de licenças';

    /** Dias antes do vencimento com lembrete, para licenças de renovação MANUAL */
    private const REMINDER_DAYS = [15, 7, 3, 1, 0];

    public function handle(): int
    {
        $today = today();
        $renewed = $this->rollAutomaticRenewals($today);

        if ($renewed > 0) {
            $this->info("Renovações automáticas registradas: {$renewed}.");
        }

        $force = (bool) $this->option('forcar');

        $due = License::query()
            ->notRetired()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $today->copy()->addYear()->toDateString())
            ->with('software')
            ->orderBy('expires_at')
            ->get()
            ->filter(fn (License $license) => $force
                ? in_array($license->expirationState(), ['vencendo', 'vencida', 'renova_em_breve'], true)
                : $this->isReminderDay($license, $today));

        if ($due->isEmpty()) {
            $this->info('Nenhum aviso de licença para enviar hoje.');

            return self::SUCCESS;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Tecnico->value])
            ->pluck('email');

        if ($recipients->isEmpty()) {
            $this->warn('Há avisos, mas nenhum administrador ou técnico ativo para receber.');

            return self::FAILURE;
        }

        $renewing = $due->filter(fn (License $license) => $license->isAutoRenewing())->values();
        $manual = $due->reject(fn (License $license) => $license->isAutoRenewing());
        $expired = $manual->filter(fn (License $license) => $license->expires_at->lt($today))->values();
        $expiring = $manual->reject(fn (License $license) => $license->expires_at->lt($today))->values();

        // Um e-mail por destinatário: ninguém vê o endereço dos demais
        foreach ($recipients as $email) {
            Mail::to($email)->send(new LicenseAlertMail($expiring, $expired, $renewing));
        }

        $message = "Aviso enviado para {$recipients->count()} pessoa(s): {$renewing->count()} renovação(ões), "
            . "{$expiring->count()} vencendo, {$expired->count()} vencida(s).";

        Log::info($message);
        $this->info($message);

        return self::SUCCESS;
    }

    /** Avança para o próximo ciclo as renovações automáticas cuja data já passou */
    private function rollAutomaticRenewals(Carbon $today): int
    {
        $count = 0;

        License::query()
            ->notRetired()
            ->autoRenewing()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $today->toDateString())
            ->get()
            ->each(function (License $license) use ($today, &$count) {
                $previous = $license->expires_at->toDateString();
                $next = $license->nextRenewalDate($today);

                $license->expires_at = $next;
                $license->saveQuietly();

                Audit::record(AuditAction::Renewed, $license, "Renovação automática de {$license->name}", [
                    'expires_at' => ['de' => $previous, 'para' => $next->toDateString()],
                ]);

                $count++;
            });

        return $count;
    }

    /** Hoje é dia de aviso para esta licença? */
    private function isReminderDay(License $license, Carbon $today): bool
    {
        $daysLeft = (int) $today->diffInDays($license->expires_at, false);

        // Renovação automática: um único aviso por ciclo
        if ($license->isAutoRenewing()) {
            return $daysLeft === $license->alert_days;
        }

        // Renovação manual já vencida: lembrete semanal
        if ($daysLeft < 0) {
            return abs($daysLeft) % 7 === 0;
        }

        // Renovação manual: entrada no prazo de aviso e datas-chave
        if ($daysLeft === $license->alert_days) {
            return true;
        }

        return $daysLeft <= $license->alert_days && in_array($daysLeft, self::REMINDER_DAYS, true);
    }
}