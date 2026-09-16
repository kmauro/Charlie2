<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\ForgotPasswordRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/views/ForgotPassword');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');
        $user = User::query()
            ->with(['role', 'patient'])
            ->where('email', $email)
            ->first();

        if ($user?->canResetPassword()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with(
            'status',
            'Si la cuenta está habilitada, recibirás un correo para restablecer la contraseña.',
        );
    }
}
