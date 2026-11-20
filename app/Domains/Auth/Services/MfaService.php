<?php

namespace App\Domains\Auth\Services;

use App\Domains\Audits\Data\AuditContext;
use App\Domains\Audits\Services\AuditService;
use App\Domains\Auth\Models\MfaFactor;
use App\Domains\Auth\Models\MfaRecoveryCode;
use App\Domains\Auth\Models\MfaTrustedDevice;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Validation\ValidationException;

class MfaService
{
    private const CHALLENGE_KEY = 'mfa.challenge';

    public function __construct(private AuditService $audit) {}

    public function isRequiredFor(User $user): bool
    {
        return in_array($user->role?->code?->value, config('mfa.required_roles', []), true);
    }

    public function beginChallenge(User $user, bool $remember): void
    {
        Session::put(self::CHALLENGE_KEY, [
            'user_id' => $user->getKey(),
            'remember' => $remember && ! $this->isRequiredFor($user),
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('mfa.challenge_ttl_minutes', 10))->getTimestamp(),
        ]);
    }

    /** @return array{user: User, remember: bool} */
    public function challengeUser(): array
    {
        $challenge = Session::get(self::CHALLENGE_KEY);

        if (! is_array($challenge) || now()->getTimestamp() > (int) ($challenge['expires_at'] ?? 0)) {
            $this->clearChallenge();
            throw ValidationException::withMessages(['code' => 'El desafío de seguridad venció. Ingresá nuevamente.']);
        }

        $user = User::query()->with(['role', 'patient', 'mfaFactor'])->find($challenge['user_id'] ?? null);
        if (! $user || ! $user->canAccessApplication() || (! $this->isRequiredFor($user) && ! $user->mfaFactor?->isConfirmed())) {
            $this->clearChallenge();
            throw ValidationException::withMessages(['code' => 'El desafío ya no es válido para esta cuenta.']);
        }

        return ['user' => $user, 'remember' => (bool) ($challenge['remember'] ?? false)];
    }

    public function enrollmentSecret(User $user): string
    {
        $key = 'mfa.enrollment.'.$user->getKey();
        $secret = Session::get($key);
        if (! is_string($secret)) {
            $secret = $this->generateSecret();
            Session::put($key, $secret);
        }

        return $secret;
    }

    /** @return array<int, string> */
    public function confirmEnrollment(User $user, string $code, ?AuditContext $context = null): array
    {
        $secret = $this->enrollmentSecret($user);
        $step = $this->verifiedStep($secret, $code);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'El código de autenticación no es válido.']);
        }

        $codes = DB::transaction(function () use ($user, $secret, $step): array {
            $factor = MfaFactor::query()->whereBelongsTo($user)->lockForUpdate()->first();
            if ($factor === null) {
                $factor = new MfaFactor(['user_id' => $user->getKey(), 'secret' => $secret]);
            } else {
                $factor->secret = $secret;
            }
            $factor->confirmed_at = now();
            $factor->revoked_at = null;
            $factor->last_accepted_step = $step;
            $factor->save();

            $user->mfaRecoveryCodes()->delete();
            $codes = collect(range(1, (int) config('mfa.recovery_codes', 10)))
                ->map(fn (): string => Str::upper(Str::random(4).'-'.Str::random(4)))
                ->all();
            foreach ($codes as $recoveryCode) {
                $user->mfaRecoveryCodes()->create(['code_hash' => Hash::make($recoveryCode)]);
            }

            return $codes;
        }, attempts: 3);

        Session::forget('mfa.enrollment.'.$user->getKey());
        $this->audit->record('auth.mfa_enrolled', $user, actor: $user, context: $context);

        return $codes;
    }

    public function verifyFactor(User $user, string $code, ?AuditContext $context = null): void
    {
        $this->assertAttemptsAvailable();

        $accepted = DB::transaction(function () use ($user, $code): bool {
            $factor = MfaFactor::query()->whereBelongsTo($user)->lockForUpdate()->first();
            if (! $factor?->isConfirmed()) {
                return false;
            }
            $step = $this->verifiedStep($factor->secret, $code);
            if ($step === null || $step <= (int) $factor->last_accepted_step) {
                return false;
            }
            $factor->update(['last_accepted_step' => $step]);

            return true;
        }, attempts: 3);

        if (! $accepted) {
            $this->registerFailedAttempt();
            $this->audit->record(
                'auth.mfa_challenge_failed',
                $user,
                actor: $user,
                context: $this->deniedContext($context, 'Código MFA inválido o reutilizado.'),
            );
            throw ValidationException::withMessages(['code' => 'El código de autenticación no es válido o ya fue utilizado.']);
        }

        $this->audit->record('auth.mfa_challenge_succeeded', $user, actor: $user, context: $context);
    }

    public function consumeRecoveryCode(User $user, string $code, ?AuditContext $context = null): void
    {
        $this->assertAttemptsAvailable();
        $normalizedCode = Str::upper(Str::replace(' ', '', $code));
        $accepted = DB::transaction(function () use ($user, $normalizedCode): bool {
            $recoveryCodes = MfaRecoveryCode::query()->whereBelongsTo($user)->whereNull('used_at')->lockForUpdate()->get();
            $match = $recoveryCodes->first(fn (MfaRecoveryCode $recoveryCode): bool => Hash::check($normalizedCode, $recoveryCode->code_hash));
            if (! $match) {
                return false;
            }
            $match->update(['used_at' => now()]);

            return true;
        }, attempts: 3);

        if (! $accepted) {
            $this->registerFailedAttempt();
            $this->audit->record(
                'auth.mfa_recovery_code_failed',
                $user,
                actor: $user,
                context: $this->deniedContext($context, 'Código de recuperación inválido o reutilizado.'),
            );
            throw ValidationException::withMessages(['recovery_code' => 'El código de recuperación no es válido o ya fue utilizado.']);
        }

        $this->audit->record('auth.mfa_recovery_code_used', $user, actor: $user, context: $context);
    }

    public function revoke(User $user, ?User $actor = null, ?AuditContext $context = null): void
    {
        DB::transaction(function () use ($user): void {
            MfaFactor::query()->whereBelongsTo($user)->update(['revoked_at' => now()]);
            $user->mfaRecoveryCodes()->delete();
            $user->mfaTrustedDevices()->delete();
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }, attempts: 3);
        $this->audit->record('auth.mfa_revoked', $user, actor: $actor, context: $context);
    }

    public function hasTrustedDevice(User $user, Request $request): bool
    {
        $token = $request->cookie((string) config('mfa.trusted_device_cookie'));
        if (! is_string($token) || $token === '') {
            return false;
        }

        $device = MfaTrustedDevice::query()
            ->whereBelongsTo($user)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
        if ($device === null) {
            return false;
        }

        $device->update(['last_used_at' => now()]);

        return true;
    }

    public function trustDevice(User $user): Cookie
    {
        $token = Str::random(80);
        $expiresAt = now()->addDays((int) config('mfa.trusted_device_days', 30));
        $user->mfaTrustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        return cookie(
            name: (string) config('mfa.trusted_device_cookie'),
            value: $token,
            minutes: $expiresAt->diffInMinutes(),
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public function clearChallenge(): void
    {
        Session::forget([self::CHALLENGE_KEY, 'mfa.enrollment']);
    }

    private function assertAttemptsAvailable(): void
    {
        $challenge = Session::get(self::CHALLENGE_KEY, []);
        if ((int) ($challenge['attempts'] ?? 0) >= (int) config('mfa.max_attempts', 5)) {
            $this->clearChallenge();
            throw ValidationException::withMessages(['code' => 'Se agotaron los intentos permitidos. Ingresá nuevamente.']);
        }
    }

    private function registerFailedAttempt(): void
    {
        $challenge = Session::get(self::CHALLENGE_KEY, []);
        $challenge['attempts'] = (int) ($challenge['attempts'] ?? 0) + 1;
        Session::put(self::CHALLENGE_KEY, $challenge);
    }

    private function verifiedStep(string $secret, string $code): ?int
    {
        $normalizedCode = preg_replace('/\s+/', '', $code) ?? '';
        $currentStep = intdiv(now()->getTimestamp(), 30);
        foreach ([-1, 0, 1] as $offset) {
            $step = $currentStep + $offset;
            if (hash_equals($this->totp($secret, $step), $normalizedCode)) {
                return $step;
            }
        }

        return null;
    }

    private function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bytes = random_bytes(20);
        $secret = '';
        foreach (str_split($bytes) as $byte) {
            $secret .= $alphabet[ord($byte) % 32];
        }

        return $secret;
    }

    private function totp(string $secret, int $step): string
    {
        $binarySecret = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N2', 0, $step), $binarySecret, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(Str::upper($value)) as $character) {
            $position = strpos($alphabet, $character);
            if ($position === false) {
                throw new \InvalidArgumentException('La clave MFA no tiene un formato válido.');
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }

    private function deniedContext(?AuditContext $context, string $reason): AuditContext
    {
        return new AuditContext(
            ipAddress: $context?->ipAddress,
            userAgent: $context?->userAgent,
            outcome: 'denied',
            reason: $reason,
            correlationId: $context?->correlationId,
        );
    }
}
