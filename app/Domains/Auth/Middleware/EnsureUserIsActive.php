<?php

namespace App\Domains\Auth\Middleware;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->loadMissing(['role', 'patient']);

            if ($user->canAccessApplication()) {
                return $next($request);
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return redirect()
            ->route('login')
            ->withErrors([
                'email' => 'La cuenta está pendiente, suspendida o no posee un rol válido.',
            ]);
    }
}
