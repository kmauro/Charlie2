<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Auth\Requests\UpdateAccountRequest;
use App\Domains\Auth\Services\MfaService;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    public function __construct(private MfaService $mfa, private AuditService $audit) {}

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('mfaFactor');

        return Inertia::render('auth/views/AccountSettings', [
            'account' => ['name' => $user->name, 'email' => $user->email],
            'mfaEnabled' => $user->mfaFactor?->isConfirmed() ?? false,
            'showMfaPrompt' => $request->session()->pull('mfa.setup_prompt', false),
        ]);
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $before = $user->only('name', 'email');
        $user->update($request->validated());
        $this->audit->record('auth.account_updated', $user, $before, $user->only('name', 'email'), $user, AuditContext::fromRequest($request));

        return back()->with('success', 'Los datos de tu cuenta fueron actualizados.');
    }

    public function skipMfaPrompt(Request $request): RedirectResponse
    {
        $request->session()->forget('mfa.setup_prompt');

        return redirect()->route('dashboard');
    }
}
