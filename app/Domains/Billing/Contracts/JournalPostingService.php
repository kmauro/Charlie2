<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Data\JournalLine;
use App\Domains\Billing\Models\JournalEntry;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

interface JournalPostingService
{
    /** @param list<JournalLine> $lines */
    public function post(Model $source, int $legalEntityId, User $actor, string $description, array $lines): JournalEntry;

    public function reverse(JournalEntry $original, Model $source, User $actor, string $reason): JournalEntry;
}
