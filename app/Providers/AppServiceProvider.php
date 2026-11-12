<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Enums\AuditAction;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Trava de segurança: testes só podem rodar em banco terminado em _testing
        if ($this->app->runningUnitTests()) {
            $database = (string) config('database.connections.' . config('database.default') . '.database');

            if (! str_ends_with($database, '_testing')) {
                throw new \RuntimeException(
                    "Testes bloqueados: o banco '{$database}' não é um banco de testes."
                );
            }
        }

        // Regra de senha padrão para todo o sistema (Perfil, recuperação de senha)
Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        // Matriz de permissões (RBAC)
        $isOperator = fn (User $user): bool => in_array($user->role, [UserRole::Admin, UserRole::Tecnico], true);

        Gate::define('manage-inventory', $isOperator);
        Gate::define('move-assets', $isOperator);
        Gate::define('reveal-license-keys', $isOperator);
        Gate::define('dispose-assets', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-audit', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('import-data', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-backups', fn (User $user): bool => $user->isAdmin());

                // Auditoria de acesso ao sistema
        Event::listen(Login::class, function (Login $event) {
            Audit::record(AuditAction::Login, $event->user, 'Entrou no sistema', null, $event->user->getAuthIdentifier());
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                Audit::record(AuditAction::Logout, $event->user, 'Saiu do sistema', null, $event->user->getAuthIdentifier());
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            $email = Str::limit((string) ($event->credentials['email'] ?? ''), 100);
            Audit::record(AuditAction::LoginFailed, null, "Tentativa de login falha com o e-mail {$email}");
        });

                // Limite para ações sensíveis: ver chaves de licença e importar planilhas
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

    }

    

    

    
}