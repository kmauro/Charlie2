<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Auth\Requests\MfaCodeRequest;
use App\Domains\Auth\Requests\MfaRecoveryCodeRequest;
use App\Domains\Auth\Services\MfaService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MfaChallengeController extends Controller
{
    public function __construct(private MfaService $mfa, private AuditService $audit) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $challenge = $this->mfa->challengeUser();
        if ($this->mfa->hasTrustedDevice($challenge['user'], $request)) {
            return $this->completeLogin($request, $challenge['user'], $challenge['remember']);
        }
        if (! $challenge['user']->mfaFactor?->isConfirmed()) {
            return redirect()->route('mfa.enrollment.create');
        }

        return Inertia::render('auth/views/MfaChallenge', ['email' => $challenge['user']->email]);
    }

    public function store(MfaCodeRequest $request): RedirectResponse
    {
        $challenge = $this->mfa->challengeUser();
        $this->mfa->verifyFactor($challenge['user'], $request->validated('code'), AuditContext::fromRequest($request));

        return $this->completeLogin($request, $challenge['user'], $challenge['remember'], $request->boolean('trusted_device'));
    }

    public function useRecoveryCode(MfaRecoveryCodeRequest $request): RedirectResponse
    {
        $challenge = $this->mfa->challengeUser();
        $this->mfa->consumeRecoveryCode($challenge['user'], $request->validated('recovery_code'), AuditContext::fromRequest($request));

        return $this->completeLogin($request, $challenge['user'], false);
    }

    private function completeLogin(Request $request, User $user, bool $remember, bool $trustDevice = false): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('mfa.verified_at', now()->getTimestamp());
        $this->mfa->clearChallenge();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->record('auth.login_succeeded', $user, actor: $user, context: AuditContext::fromRequest($request));

        $response = redirect()->intended(route('dashboard', absolute: false));

        return $trustDevice ? $response->withCookie($this->mfa->trustDevice($user)) : $response;
    }
}
