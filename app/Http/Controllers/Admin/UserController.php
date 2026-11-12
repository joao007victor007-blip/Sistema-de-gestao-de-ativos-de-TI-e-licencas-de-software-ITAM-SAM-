<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Enums\AuditAction;
use App\Support\Audit;


class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => UserRole::cases()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->role = UserRole::from($data['role']);
        $user->is_active = true;
        $user->must_change_password = true;
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('users.index')
            ->with('status', "Usuário {$user->name} criado com sucesso.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $newRole = UserRole::from($data['role']);

        // Trava: o administrador não pode remover o próprio papel de administrador
        if ($user->is($request->user()) && $newRole !== UserRole::Admin) {
            return back()
                ->withErrors(['role' => 'Você não pode remover o seu próprio papel de administrador.'])
                ->withInput();
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $newRole;
        $user->save();

        return redirect()->route('users.index')
            ->with('status', "Usuário {$user->name} atualizado.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        // Trava: o administrador não pode desativar a própria conta
        if ($user->is($request->user())) {
            return back()->with('error', 'Você não pode desativar a sua própria conta.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $situacao = $user->is_active ? 'reativado' : 'desativado';

        return back()->with('status', "Usuário {$user->name} {$situacao}.");
    }

        public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        // A própria senha é trocada na página Perfil, que exige a senha atual
        if ($user->is($request->user())) {
            return back()->withErrors(['password' => 'Para trocar a sua própria senha, use a página Perfil.']);
        }

        $user->password = $request->validated('password');
        $user->setRememberToken(Str::random(60));
        $user->must_change_password = true;
        $user->saveQuietly();

        // Encerra todas as sessões abertas desse usuário
        DB::table('sessions')->where('user_id', $user->id)->delete();

        Audit::record(AuditAction::PasswordReset, $user, "Redefiniu a senha de {$user->name}");

        return redirect()->route('users.edit', $user)
            ->with('status', "Senha de {$user->name} redefinida. Informe a nova senha ao usuário por um canal seguro.");
    }
}