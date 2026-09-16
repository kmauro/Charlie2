<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function create(): Response
    {
        return Inertia::render('auth/views/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            $context = AuditContext::fromRequest($request);
            $attemptedUser = User::query()
                ->where('email', $request->string('email')->lower()->toString())
                ->first();

            $this->audit->recordResource(
                event: 'auth.login_failed',
                auditableType: User::class,
                auditableId: $attemptedUser?->getKey(),
                context: new AuditContext(
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                    outcome: 'denied',
                    reason: 'Las credenciales o el estado de la cuenta no permitieron el acceso.',
                    correlationId: $context->correlationId,
                ),
            );

            throw $exception;
        }
        $request->session()->regenerate();

        $user = $request->user();

        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            $this->audit->record(
                event: 'auth.login_succeeded',
                auditable: $user,
                actor: $user,
                context: AuditContext::fromRequest($request),
            );
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->audit->record(
                event: 'auth.logout',
                auditable: $user,
                actor: $user,
                context: AuditContext::fromRequest($request),
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return redirect()->route('login');
    }
}
