<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\AcceptInvitationRequest;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/views/Register', [
            'invitation' => null,
            'acceptUrl' => null,
        ]);
    }

    public function accept(Request $request, User $user, UserService $service): Response
    {
        $token = $request->string('token')->toString();
        $service->ensureInvitationIsValid($user, $token);
        $user->loadMissing('role');

        return Inertia::render('auth/views/Register', [
            'invitation' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role === null ? null : [
                    'code' => $user->role->code->value,
                    'name' => $user->role->name,
                ],
                'expires_at' => $user->invitation_expires_at?->toIso8601String(),
            ],
            'acceptUrl' => $request->fullUrl(),
        ]);
    }

    public function store(
        AcceptInvitationRequest $request,
        User $user,
        UserService $service,
    ): RedirectResponse {
        $user = $service->acceptInvitation(
            user: $user,
            token: $request->string('token')->toString(),
            data: $request->validated(),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
