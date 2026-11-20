<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Auth\Requests\MfaCodeRequest;
use App\Domains\Auth\Services\MfaService;
use App\Http\Controllers\Controller;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MfaEnrollmentController extends Controller
{
    public function __construct(private MfaService $mfa, private AuditService $audit) {}

    public function create(Request $request): Response
    {
        $user = $this->enrollingUser($request);
        $secret = $this->mfa->enrollmentSecret($user);
        $issuer = (string) config('mfa.issuer');
        $label = rawurlencode($issuer.':'.$user->email);
        $otpauthUrl = "otpauth://totp/{$label}?secret={$secret}&issuer=".rawurlencode($issuer);
        $qrCodeDataUri = (new SvgWriter)->write(new QrCode(data: $otpauthUrl, size: 260))->getDataUri();

        return Inertia::render('auth/views/MfaEnrollment', [
            'secret' => $secret,
            'otpauthUrl' => $otpauthUrl,
            'qrCodeDataUri' => $qrCodeDataUri,
            'submitUrl' => $request->user() === null ? '/configurar-mfa' : '/mi-cuenta/2fa/configurar',
        ]);
    }

    public function store(MfaCodeRequest $request): RedirectResponse
    {
        $user = $this->enrollingUser($request);
        $codes = $this->mfa->confirmEnrollment(
            $user,
            $request->validated('code'),
            AuditContext::fromRequest($request),
        );
        $request->session()->put('mfa.recovery_codes', $codes);

        return redirect()->route('mfa.recovery-codes.show');
    }

    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = session('mfa.recovery_codes');
        if (! is_array($codes)) {
            return $request->user() === null ? redirect()->route('login') : redirect()->route('account.settings');
        }

        return Inertia::render('auth/views/MfaRecoveryCodes', [
            'codes' => $codes,
            'finishUrl' => $request->user() === null ? '/configurar-mfa/codigos' : '/mi-cuenta/2fa/codigos',
        ]);
    }

    public function finish(Request $request): RedirectResponse
    {
        $user = $this->enrollingUser($request);
        $request->session()->forget('mfa.recovery_codes');
        if ($request->user() !== null) {
            return redirect()->route('account.settings')->with('success', 'La verificación en dos pasos fue configurada.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('mfa.verified_at', now()->getTimestamp());
        $this->mfa->clearChallenge();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->record('auth.login_succeeded', $user, actor: $user, context: AuditContext::fromRequest($request));

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function enrollingUser(Request $request): \App\Domains\Users\Models\User
    {
        $user = $request->user();
        if ($user instanceof \App\Domains\Users\Models\User) {
            return $user;
        }

        return $this->mfa->challengeUser()['user'];
    }
}
