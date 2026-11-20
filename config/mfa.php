<?php

use App\Domains\Users\Enums\RoleCode;

return [
    'issuer' => env('MFA_ISSUER', env('APP_NAME', 'Charlie')),
    // MFA is opt-in. Populate this list only when an organization decides
    // to make it mandatory for specific roles.
    'required_roles' => [],
    'challenge_ttl_minutes' => (int) env('MFA_CHALLENGE_TTL_MINUTES', 10),
    'max_attempts' => (int) env('MFA_MAX_ATTEMPTS', 5),
    'recovery_codes' => (int) env('MFA_RECOVERY_CODES', 10),
    'trusted_device_days' => (int) env('MFA_TRUSTED_DEVICE_DAYS', 30),
    'trusted_device_cookie' => env('MFA_TRUSTED_DEVICE_COOKIE', 'charlie_mfa_device'),
];
