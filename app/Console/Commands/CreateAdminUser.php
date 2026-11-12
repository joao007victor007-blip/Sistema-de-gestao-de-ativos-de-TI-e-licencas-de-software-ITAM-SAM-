<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    protected $signature = 'intranet:criar-admin';

    protected $description = 'Cria um usuário administrador da intranet';

    public function handle(): int
    {
        $name = text(label: 'Nome completo', required: true);

        $email = text(
            label: 'E-mail',
            required: true,
            validate: fn (string $value) => $this->validateField('email', $value, ['email', 'unique:users,email']),
        );

        $password = password(
            label: 'Senha (mín. 12 caracteres, maiúsculas, minúsculas, números e símbolos)',
            required: true,
            validate: fn (string $value) => $this->validateField(
                'senha',
                $value,
                [Password::min(12)->mixedCase()->numbers()->symbols()]
            ),
        );

        password(
            label: 'Confirme a senha',
            required: true,
            validate: fn (string $value) => $value === $password ? null : 'As senhas não coincidem.',
        );

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->role = UserRole::Admin;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Administrador {$user->email} criado com sucesso.");

        return self::SUCCESS;
    }

    private function validateField(string $attribute, string $value, array $rules): ?string
    {
        $validator = Validator::make([$attribute => $value], [$attribute => $rules]);

        return $validator->fails() ? $validator->errors()->first($attribute) : null;
    }
}