<?php

namespace App\Domains\Auth\Middleware;

use App\Domains\Auth\Services\MfaService;
use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaIsVerified
{
    public function __construct(private MfaService $mfa) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $this->mfa->isRequiredFor($user)) {
            return $next($request);
        }

        if ($request->session()->has('mfa.verified_at')) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Por seguridad, completá la verificación en dos pasos para continuar.',
        ]);
    }
}
