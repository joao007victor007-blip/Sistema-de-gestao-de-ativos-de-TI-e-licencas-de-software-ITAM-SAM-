<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Envia o link se o e-mail existir, mas responde sempre a mesma coisa:
        // assim, ninguém descobre quais e-mails estão cadastrados
        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            'Se este e-mail estiver cadastrado, você receberá em instantes um link para redefinir a senha.'
        );
    }
}