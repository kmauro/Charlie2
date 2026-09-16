<?php

namespace App\Domains\Users\Data;

use App\Domains\Users\Models\User;

final readonly class InvitationResult
{
    public function __construct(
        public User $user,
        public string $invitationUrl,
    ) {}
}
