<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\ResetPasswordRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('auth/views/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $user = User::query()
            ->with(['role', 'patient'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user?->canResetPassword()) {
            throw ValidationException::withMessages([
                'email' => 'El enlace no es válido o la cuenta ya no puede acceder.',
            ]);
        }

        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return redirect()->route('login')->with(
            'status',
            'La contraseña fue actualizada. Ya podés iniciar sesión.',
        );
    }
}
